<?php

namespace App\Http\Controllers;

use App\Models\MatchEvent;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use App\Models\AppSetting;
use App\Models\TournamentGroupSetting;
use App\Services\MatchGenerator;
use App\Services\TieResolver;
use App\Services\TournamentStatisticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentController extends Controller
{
    // List semua turnamen
    public function index()
    {
        // R21 — scoping: admin hanya melihat turnamen miliknya sendiri.
        $tournaments = Tournament::with('creator')
            ->where('created_by', Auth::id())
            ->latest()
            ->get();
        return view('admin.tournaments.index', compact('tournaments'));
    }

    // Tampilkan form buat turnamen baru
    public function create()
    {
        return view('admin.tournaments.create');
    }

    // Simpan turnamen baru ke database
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'match_date' => 'required|date',
            'division' => 'required|string|max:255',
            'venue' => 'required|string|max:255',
        ]);

        // R22 — enforcement limit paket (jumlah turnamen per admin). Dibungkus
        // transaksi + lock baris user agar dua request paralel tidak melewati
        // limit (TOCTOU).
        $userId = Auth::id();
        $limit = Auth::user()->tournamentLimit();

        try {
            DB::transaction(function () use ($validated, $userId, $limit) {
                if ($limit !== null) {
                    \App\Models\User::where('id', $userId)->lockForUpdate()->first();
                    $count = Tournament::where('created_by', $userId)->count();
                    if ($count >= $limit) {
                        throw new \RuntimeException('LIMIT_REACHED');
                    }
                }

                $validated['created_by'] = $userId;
                Tournament::create($validated);
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('subscription.plans')
                ->with('error', "Batas paket Anda tercapai (maks {$limit} turnamen). Upgrade paket untuk membuat lebih banyak turnamen.");
        }

        return redirect()->route('tournaments.index')
                       ->with('success', 'Turnamen berhasil dibuat!');
    }

    // Tampilkan detail turnamen
    public function show(Tournament $tournament)
    {
        $tournament->load('creator');
        return view('admin.tournaments.show', compact('tournament'));
    }

    // Tampilkan dashboard management turnamen
    public function manage(Tournament $tournament)
    {
        $tournament->load('creator');

        // Dapatkan competition type dari bracket setting
        $bracketSetting = $this->resolveBracketSetting($tournament);
        $competitionType = $bracketSetting->value['competition_type'] ?? 'tournament';
        $playoffOptions = $bracketSetting->value['playoff_options'] ?? [];

        // Tentukan apakah bracket admin tersedia
        $isTournament = $competitionType === 'tournament';
        $isGroupKnockout = $competitionType === 'group_knockout';
        $hasPromotion = in_array('promotion', $playoffOptions);
        $hasRelegation = in_array('relegation', $playoffOptions);
        $isLeaguePlayoff = $competitionType === 'league_playoff' && ($hasPromotion || $hasRelegation);
        $bracketAdminAvailable = $isTournament || $isLeaguePlayoff || $isGroupKnockout;

        $statistics = $this->buildDashboardStatistics($tournament);
        $matchProgress = $this->buildMatchProgress($tournament);
        $nextMatch = $this->buildNextMatch($tournament);
        $recentResults = $this->buildRecentResults($tournament);
        $champion = $this->resolveChampion($tournament, $competitionType);

        return view('admin.tournaments.manage', compact(
            'tournament',
            'statistics',
            'competitionType',
            'bracketAdminAvailable',
            'matchProgress',
            'nextMatch',
            'recentResults',
            'champion'
        ));
    }

    /**
     * Statistik pendaftaran tim berdasarkan status verifikasi (teams.verification_status).
     */
    private function buildDashboardStatistics(Tournament $tournament): array
    {
        $counts = TournamentTeam::where('tournament_teams.tournament_id', $tournament->id)
            ->leftJoin('teams', 'teams.id', '=', 'tournament_teams.team_id')
            ->selectRaw("COALESCE(teams.verification_status, 'pending') as status, COUNT(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $approved = (int) ($counts['approved'] ?? 0);
        $pending = (int) ($counts['pending'] ?? 0);
        $rejected = (int) ($counts['rejected'] ?? 0);
        $total = (int) $counts->sum();

        return [
            'total_pendaftar' => $total,
            'terverifikasi' => $approved,
            'butuh_verifikasi' => $pending,
            'ditolak_draft' => $rejected,
            'readiness_percent' => $total > 0 ? (int) round($approved / $total * 100) : 0,
        ];
    }

    /**
     * Ringkasan kemajuan pertandingan (selesai / berlangsung / terjadwal).
     */
    private function buildMatchProgress(Tournament $tournament): array
    {
        $rows = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('is_bye', false)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $finished = (int) ($rows['full_time'] ?? 0);
        $live = (int) ($rows['live_match'] ?? 0) + (int) ($rows['penalty_shootout'] ?? 0);
        $total = (int) $rows->sum();
        $upcoming = max($total - $finished - $live, 0);

        return [
            'total' => $total,
            'finished' => $finished,
            'live' => $live,
            'upcoming' => $upcoming,
            'percent' => $total > 0 ? (int) round($finished / $total * 100) : 0,
        ];
    }

    /**
     * Pertandingan terdekat yang belum dimainkan (untuk panel "Laga Berikutnya").
     */
    private function buildNextMatch(Tournament $tournament): ?array
    {
        $match = TournamentMatch::with(['homeTeam.team', 'awayTeam.team'])
            ->where('tournament_id', $tournament->id)
            ->where('is_bye', false)
            ->whereIn('status', ['scheduled', 'live_match', 'penalty_shootout'])
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->orderByRaw('match_date IS NULL')
            ->orderBy('match_date')
            ->orderBy('id')
            ->first();

        if (! $match) {
            return null;
        }

        return [
            'home' => $match->homeTeam?->team?->name ?? $match->home_team_key ?? 'TBD',
            'away' => $match->awayTeam?->team?->name ?? $match->away_team_key ?? 'TBD',
            'home_logo' => $match->homeTeam?->team?->logo,
            'away_logo' => $match->awayTeam?->team?->logo,
            'stage' => $this->buildScheduleBaseLabel($match),
            'date' => $match->match_date,
            'venue' => $match->venue,
            'is_live' => in_array($match->status, ['live_match', 'penalty_shootout'], true),
        ];
    }

    /**
     * Hasil pertandingan terakhir yang sudah selesai.
     */
    private function buildRecentResults(Tournament $tournament, int $limit = 5): array
    {
        return TournamentMatch::with(['homeTeam.team', 'awayTeam.team'])
            ->where('tournament_id', $tournament->id)
            ->where('status', 'full_time')
            ->where('is_bye', false)
            ->orderByRaw('match_date IS NULL')
            ->orderByDesc('match_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($match) => [
                'home' => $match->homeTeam?->team?->name ?? $match->home_team_key ?? 'TBD',
                'away' => $match->awayTeam?->team?->name ?? $match->away_team_key ?? 'TBD',
                'home_score' => (int) $match->home_score,
                'away_score' => (int) $match->away_score,
                'home_pen' => $match->home_penalty_score,
                'away_pen' => $match->away_penalty_score,
                'stage' => $this->buildScheduleBaseLabel($match),
            ])
            ->all();
    }

    /**
     * Juara turnamen: pemenang laga Final yang sudah selesai. Untuk format liga
     * murni (tanpa bracket), juara adalah pemuncak klasemen tunggal bila semua
     * laga liga telah dimainkan.
     */
    private function resolveChampion(Tournament $tournament, string $competitionType): ?array
    {
        // Format dengan bracket: juara = pemenang laga Final yang sudah final.
        $final = TournamentMatch::with(['homeTeam.team', 'awayTeam.team'])
            ->where('tournament_id', $tournament->id)
            ->where('round_name', 'Final')
            ->where('is_third_place', false)
            ->where('status', 'full_time')
            ->orderByDesc('leg')
            ->orderByDesc('id')
            ->first();

        if ($final) {
            $resolver = app(TieResolver::class);
            $outcome = $resolver->tieOutcome($final, $resolver->calculationMode($tournament));

            if ($outcome['both_played']) {
                $winner = $resolver->winnerDescriptor($final, $outcome);
                if ($winner) {
                    $logo = $winner['team_id'] === $final->home_team_id
                        ? $final->homeTeam?->team?->logo
                        : $final->awayTeam?->team?->logo;

                    return [
                        'name' => $winner['name'],
                        'logo' => $logo,
                        'context' => 'Juara — Final',
                    ];
                }
            }
        }

        // Liga murni (tanpa playoff bracket): juara = peringkat 1 klasemen bila
        // seluruh laga liga sudah dimainkan.
        if ($competitionType === 'league') {
            $remaining = TournamentMatch::where('tournament_id', $tournament->id)
                ->whereIn('stage_type', ['group', 'league'])
                ->where('is_bye', false)
                ->where('status', '!=', 'full_time')
                ->exists();

            if (! $remaining) {
                $groups = $this->buildStandingsGroups($tournament);
                $top = collect($groups)->flatten(1)->sortByDesc('points')->first();

                if (is_array($top) && ! empty($top['name'])) {
                    return [
                        'name' => $top['name'],
                        'logo' => $top['logo'] ?? null,
                        'context' => 'Juara Liga — Puncak Klasemen',
                    ];
                }
            }
        }

        return null;
    }

    // Tampilkan form edit turnamen
    public function edit(Tournament $tournament)
    {
        return view('admin.tournaments.edit', compact('tournament'));
    }

    // Update turnamen
    public function update(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'match_date' => 'required|date',
            'division' => 'required|string|max:255',
            'venue' => 'required|string|max:255',
        ]);

        $tournament->update($validated);

        return redirect()->route('tournaments.show', $tournament)
                       ->with('success', 'Turnamen berhasil diupdate!');
    }

    // Hapus turnamen
    public function destroy(Tournament $tournament)
    {
        $tournament->delete();

        return redirect()->route('tournaments.index')
                       ->with('success', 'Turnamen berhasil dihapus!');
    }

    // Fungsi untuk mengambil semua data (pengganti localStorage.getItem)
    public function getData()
    {
        $tournaments = AppSetting::where('key', 'tournament_list')->first();
        $teams = AppSetting::where('key', 'tournament_teams')->first();

        return response()->json([
            'tournaments' => $tournaments ? $tournaments->value : [],
            'teams' => $teams ? $teams->value : []
        ]);
    }

    // Fungsi untuk menyimpan data (pengganti saveToLocalStorage)
    public function saveAll(Request $request)
    {
        // Menyimpan daftar turnamen
        AppSetting::updateOrCreate(
            ['key' => 'tournament_list'],
            ['value' => $request->tournaments]
        );

        // Menyimpan daftar tim
        AppSetting::updateOrCreate(
            ['key' => 'tournament_teams'],
            ['value' => $request->teams]
        );

        return response()->json(['message' => 'Data berhasil disimpan ke database!']);
    }

    // Tampilkan form pengaturan turnamen (Pengaturan Kelolosan Grup)
    public function settings(Tournament $tournament)
    {
        $tournament->load('groupSetting');
        $bracketSetting = AppSetting::where('key', $this->bracketSettingsKey($tournament))->first();
        $competitionType = $bracketSetting?->value['competition_type'] ?? 'tournament';

        return view('admin.tournaments.settings.index', compact('tournament', 'competitionType'));
    }

    private function assignGroupLabelsToTournamentTeams(Tournament $tournament, int $groupCount, int $teamsPerGroup): void
    {
        $groupLabels = $this->buildGroupLabels($groupCount);

        $teams = TournamentTeam::where('tournament_id', $tournament->id)
            ->orderByRaw('COALESCE(seed, 999999), id')
            ->get();

        // R15/R16 — hormati tim yang grup-nya sudah ditetapkan manual / lewat
        // undian. Hanya tim auto yang didistribusikan ulang ke slot tersisa.
        $groupCounts = array_fill_keys($groupLabels, 0);

        // Tim manual yang label-nya kini invalid (mis. jumlah grup dikurangi)
        // di-reset flag-nya agar tidak "orphaned" dan ikut diredistribusi.
        foreach ($teams as $team) {
            if ($team->group_assigned_manually
                && (! $team->group_label || ! in_array($team->group_label, $groupLabels, true))) {
                $team->group_assigned_manually = false;
                $team->group_label = null;
                $team->save();
            }
        }

        foreach ($teams as $team) {
            if ($team->group_assigned_manually
                && $team->group_label
                && in_array($team->group_label, $groupLabels, true)) {
                $groupCounts[$team->group_label]++;
            }
        }

        $autoTeams = $teams->filter(fn ($t) => ! $t->group_assigned_manually)->values();

        foreach ($autoTeams as $team) {
            // Cari grup pertama yang masih punya slot kosong.
            $placed = false;
            foreach ($groupLabels as $label) {
                if ($groupCounts[$label] < $teamsPerGroup) {
                    $team->group_label = $label;
                    $team->save();
                    $groupCounts[$label]++;
                    $placed = true;
                    break;
                }
            }

            // Overflow: semua grup penuh — tumpuk ke grup terakhir (perilaku lama).
            if (! $placed) {
                $lastLabel = $groupLabels[count($groupLabels) - 1];
                $team->group_label = $lastLabel;
                $team->save();
                $groupCounts[$lastLabel]++;
            }
        }

        Log::info('Assigned TournamentTeam group labels', [
            'tournament_id' => $tournament->id,
            'group_count' => $groupCount,
            'teams_per_group' => $teamsPerGroup,
            'teams_total' => $teams->count(),
            'auto_assigned' => $autoTeams->count(),
            'groups_assigned' => array_filter($groupCounts),
        ]);
    }

    public function schedule(Tournament $tournament)
    {
        $tournament->load('groupSetting');

        $bracketSetting = $this->resolveBracketSetting($tournament);
        $competitionType = $bracketSetting->value['competition_type'] ?? 'tournament';
        $playoffOptions = $bracketSetting->value['playoff_options'] ?? [];

        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->orderByRaw("FIELD(stage_type, 'group', 'league', 'knockout', 'promotion_playoff', 'relegation_playoff')")
            ->orderBy('group_label')
            ->orderBy('bracket_match_id')
            ->orderBy('leg')
            ->orderByRaw("CAST(REGEXP_REPLACE(round_name, '[^0-9]', '') AS UNSIGNED)")
            ->orderBy('round_name')
            ->orderBy('id')
            ->get();

        $tieResolver = app(TieResolver::class);

        $scheduleMatches = $matches->map(function ($match) use ($tieResolver, $matches) {
            $leg1 = $match->leg === 2 ? $tieResolver->siblingLeg($match, $matches) : null;

            return [
                'id' => $match->id,
                'stage_type' => $match->stage_type,
                'playoff_type' => $match->playoff_type,
                'group_label' => $match->group_label,
                'round' => $this->buildScheduleLabel($match),
                'left' => $match->home_team_key ?? $match->source_home,
                'right' => $match->away_team_key ?? $match->source_away,
                'score_left' => $match->home_score,
                'score_right' => $match->away_score,
                'home_penalty_score' => $match->home_penalty_score,
                'away_penalty_score' => $match->away_penalty_score,
                'leg' => $match->leg,
                'leg1_completed' => $match->leg === 2 ? $leg1?->status === 'full_time' : true,
                'datetime' => $match->match_date?->toDateTimeString(),
                'status' => $match->status ?? 'scheduled',
            ];
        });

        $scheduleMatches = $this->mergeTieRows($matches, $scheduleMatches);

        $tabs = $this->buildScheduleTabs($competitionType, $matches);
        $selectedTab = request()->query('tab', 'all');
        $selectedTab = in_array($selectedTab, array_column($tabs, 'key'), true) ? $selectedTab : 'all';

        $matches = $this->filterScheduleMatches($scheduleMatches, $selectedTab);

        $view = match ($competitionType) {
            'league' => 'admin.tournaments.schedule.league',
            'league_playoff' => 'admin.tournaments.schedule.league-playoff',
            default => 'admin.tournaments.schedule.bracket',
        };

        return view($view, compact('tournament', 'matches', 'tabs', 'selectedTab', 'competitionType'));
    }

    private function buildScheduleTabs(string $competitionType, $matches): array
    {
        $tabs = [
            ['key' => 'all', 'label' => 'Semua Laga'],
        ];

        $hasGroup = $matches->contains(fn($match) => $match->stage_type === 'group');
        $hasLeague = $matches->contains(fn($match) => $match->stage_type === 'league');
        $hasKnockout = $matches->contains(fn($match) => $match->stage_type === 'knockout');
        $hasPromotion = $matches->contains(fn($match) => $match->stage_type === 'promotion_playoff');
        $hasRelegation = $matches->contains(fn($match) => $match->stage_type === 'relegation_playoff');
        $hasMultipleRounds = $matches->where('stage_type', 'league')->pluck('round_name')->unique()->count() > 1;

        if ($hasGroup) {
            $tabs[] = ['key' => 'group', 'label' => 'Fase Grup'];
        }

        if ($hasLeague) {
            $tabs[] = ['key' => 'league', 'label' => 'Liga Reguler'];
        }

        if ($competitionType === 'league' && $hasMultipleRounds) {
            $tabs[] = ['key' => 'matchday', 'label' => 'Pekan Pertandingan'];
        }

        if ($hasKnockout) {
            $tabs[] = ['key' => 'bracket', 'label' => 'Fase Gugur'];
        }

        if ($hasPromotion) {
            $tabs[] = ['key' => 'promotion', 'label' => 'Playoff Promosi'];
        }

        if ($hasRelegation) {
            $tabs[] = ['key' => 'relegation', 'label' => 'Playoff Degradasi'];
        }

        return $tabs;
    }

    private function filterScheduleMatches(array $matches, string $selectedTab): array
    {
        if ($selectedTab === 'all') {
            return $matches;
        }

        return array_values(array_filter($matches, function ($match) use ($selectedTab) {
            return match ($selectedTab) {
                'group' => $match['stage_type'] === 'group',
                'league', 'matchday' => $match['stage_type'] === 'league',
                'bracket' => $match['stage_type'] === 'knockout',
                'promotion' => $match['stage_type'] === 'promotion_playoff',
                'relegation' => $match['stage_type'] === 'relegation_playoff',
                default => true,
            };
        }));
    }

    /**
     * Payload satu match untuk tabel jadwal & Live Match Event Logger.
     * Dipakai oleh manageSchedule (semua match) dan respons JSON
     * storeMatchEvent (refresh logger tanpa reload halaman).
     */
    private function buildLoggerMatchPayload(TournamentMatch $match, TieResolver $tieResolver, string $calculationMode, $allMatches = null): array
    {
        $leg1 = null;
        $tieOutcome = null;

        if ($match->leg === 2) {
            [$leg1] = $tieResolver->getLegs($match, $allMatches);
            $tieOutcome = $tieResolver->tieOutcome($match, $calculationMode, $allMatches);
        }

        return [
            'id' => $match->id,
            'stage_type' => $match->stage_type,
            'playoff_type' => $match->playoff_type,
            'group_label' => $match->group_label,
            'round_name' => $match->round_name,
            'round' => $this->buildScheduleLabel($match),
            'left' => $match->homeTeam?->team?->name ?? $match->source_home ?? $match->home_team_key,
            'right' => $match->awayTeam?->team?->name ?? $match->source_away ?? $match->away_team_key,
            'home_team_id' => $match->home_team_id,
            'away_team_id' => $match->away_team_id,
            'home_team_key' => $match->home_team_key,
            'away_team_key' => $match->away_team_key,
            'source_home' => $match->source_home,
            'source_away' => $match->source_away,
            'is_match_ready' => ! is_null($match->home_team_id) && ! is_null($match->away_team_id),
            'score_left' => $match->home_score,
            'score_right' => $match->away_score,
            'home_penalty_score' => $match->home_penalty_score,
            'away_penalty_score' => $match->away_penalty_score,
            'leg' => $match->leg,
            'is_deciding' => $tieResolver->isDecidingMatch($match),
            'calculation_mode' => $calculationMode,
            'leg1_completed' => $match->leg === 2 ? $leg1?->status === 'full_time' : true,
            'leg1' => $leg1 ? [
                'home' => $leg1->homeTeam?->team?->name ?? $leg1->source_home ?? $leg1->home_team_key,
                'away' => $leg1->awayTeam?->team?->name ?? $leg1->source_away ?? $leg1->away_team_key,
                'home_score' => $leg1->home_score,
                'away_score' => $leg1->away_score,
                'status' => $leg1->status,
            ] : null,
            'agg_home' => $tieOutcome['agg_home'] ?? null,
            'agg_away' => $tieOutcome['agg_away'] ?? null,
            'wins_home' => $tieOutcome['wins_home'] ?? null,
            'wins_away' => $tieOutcome['wins_away'] ?? null,
            'datetime' => $match->match_date?->toDateTimeString(),
            'status' => $match->status ?? 'scheduled',
            'venue' => $match->venue,
            'home_roster' => $this->buildMatchRoster($match->homeTeam),
            'away_roster' => $this->buildMatchRoster($match->awayTeam),
            'events' => $match->events->sortBy('created_at')->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'team_side' => $event->team_side,
                    'player_name' => $event->player_name,
                    'player_id' => $event->player_id,
                    'created_at' => $event->created_at->toDateTimeString(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Gabungkan dua row leg pada tie yang sama menjadi satu row jadwal.
     * Row tie memakai orientasi Leg 1; payload lengkap tiap leg tersimpan di
     * 'legs' (dipakai kolom jadwal ganda dan tab Leg 1/Leg 2 di Live Match
     * Event Logger).
     */
    private function mergeTieRows($allMatches, $payloads): array
    {
        $tieResolver = app(TieResolver::class);
        $payloadById = collect($payloads)->keyBy('id');

        $rows = [];
        $processed = [];

        foreach ($allMatches as $match) {
            if (isset($processed[$match->id])) {
                continue;
            }

            $payload = $payloadById[$match->id];

            if ($match->leg === null) {
                $rows[] = $payload;
                continue;
            }

            $sibling = $tieResolver->siblingLeg($match, $allMatches);

            if (! $sibling || ! isset($payloadById[$sibling->id])) {
                $rows[] = $payload;
                continue;
            }

            $legOneMatch = $match->leg === 1 ? $match : $sibling;
            $legTwoMatch = $match->leg === 1 ? $sibling : $match;
            $processed[$legOneMatch->id] = true;
            $processed[$legTwoMatch->id] = true;

            $legOne = $payloadById[$legOneMatch->id];
            $legTwo = $payloadById[$legTwoMatch->id];

            $legStatuses = [$legOne['status'] ?? 'scheduled', $legTwo['status'] ?? 'scheduled'];

            $tieStatus = 'scheduled';
            if (($legTwo['status'] ?? '') === 'full_time') {
                $tieStatus = 'full_time';
            } elseif (in_array('penalty_shootout', $legStatuses, true)) {
                $tieStatus = 'penalty_shootout';
            } elseif (in_array('live_match', $legStatuses, true) || ($legOne['status'] ?? '') === 'full_time') {
                $tieStatus = 'live_match';
            }

            // Agregat dalam orientasi row (kiri = home Leg 1 = away Leg 2)
            $aggLeft = null;
            $aggRight = null;
            if (($legOne['score_left'] ?? null) !== null && ($legOne['score_right'] ?? null) !== null
                && ($legTwo['score_left'] ?? null) !== null && ($legTwo['score_right'] ?? null) !== null) {
                $aggLeft = (int) $legOne['score_left'] + (int) $legTwo['score_right'];
                $aggRight = (int) $legOne['score_right'] + (int) $legTwo['score_left'];
            }

            $rows[] = array_merge($legOne, [
                'is_tie' => true,
                'round' => $this->buildScheduleBaseLabel($legOneMatch),
                'status' => $tieStatus,
                'legs' => [$legOne, $legTwo],
                'logger_match_id' => ($legOne['status'] ?? '') === 'full_time' ? $legTwo['id'] : $legOne['id'],
                'tie_agg_left' => $aggLeft,
                'tie_agg_right' => $aggRight,
                // Netralkan field per-leg agar badge/lock leg tunggal di tabel
                // tidak aktif untuk row gabungan.
                'leg' => null,
                'leg1_completed' => true,
                'home_penalty_score' => null,
                'away_penalty_score' => null,
            ]);
        }

        return $rows;
    }

    private function buildScheduleLabel(TournamentMatch $match): string
    {
        $label = $this->buildScheduleBaseLabel($match);

        if ($match->leg !== null) {
            $label .= " (Leg {$match->leg})";
        }

        return $label;
    }

    private function buildScheduleBaseLabel(TournamentMatch $match): string
    {
        if ($match->stage_type === 'group' && $match->group_label) {
            return trim("Grup {$match->group_label} • {$match->round_name}");
        }

        if ($match->stage_type === 'league') {
            return $match->round_name ?: 'Liga Reguler';
        }

        if ($match->stage_type === 'knockout') {
            return $match->round_name ?: 'Fase Gugur';
        }

        if ($match->stage_type === 'promotion_playoff') {
            return trim("Playoff Promosi" . ($match->round_name ? " • {$match->round_name}" : ''));
        }

        if ($match->stage_type === 'relegation_playoff') {
            return trim("Playoff Degradasi" . ($match->round_name ? " • {$match->round_name}" : ''));
        }

        return $match->round_name ?: ucfirst(str_replace('_', ' ', $match->stage_type));
    }

    /**
     * Roster pemain untuk Live Match Event Logger dari pemain terdaftar tim.
     * Karena roster diambil dari tim match itu sendiri, pada Leg 2 (home/away
     * dibalik) lineup otomatis ikut bertukar sisi.
     */
    private function buildMatchRoster(?TournamentTeam $tournamentTeam): array
    {
        if (! $tournamentTeam) {
            return [];
        }

        $players = $tournamentTeam->players
            ->where('status', 'active')
            ->sortBy(fn ($player) => $player->shirt_number ?? 9999)
            ->values();

        if ($players->isEmpty()) {
            // Tim belum mendaftarkan pemain: sediakan satu kartu tim agar
            // event tetap bisa dicatat tanpa nama pemain.
            $teamName = $tournamentTeam->team?->name ?? 'Tim';

            return [[
                'label' => $teamName . ' (roster belum terdaftar)',
                'player_name' => null,
                'player_id' => null,
            ]];
        }

        return $players->map(function ($player) {
            $label = $player->player_name
                . ($player->shirt_number !== null ? ' #' . $player->shirt_number : '');

            return [
                'label' => $label . ($player->is_captain ? ' (C)' : ''),
                'player_name' => $label,
                // R19 — id pemain asli agar event terhubung ke kartu pemain.
                'player_id' => $player->id,
            ];
        })->values()->all();
    }

    public function groupSettings(Tournament $tournament)
    {
        // CRITICAL: Reload tournament with fresh groupSetting data from database
        $tournament->load('groupSetting');
        $tournament->refresh(); // Force reload to ensure latest data

        if (!$tournament->groupSetting) {
            TournamentGroupSetting::create([
                'tournament_id' => $tournament->id,
                'teams_per_group' => 4,
                'group_count' => 4,
                'qualified_teams' => [1, 2], // Default: ranking 1 dan 2 lolos
                'relegated_teams' => [],
                'locked' => false,
            ]);
            $tournament->load('groupSetting');
        }

        if (! isset($tournament->groupSetting->group_count)) {
            $tournament->groupSetting->update(['group_count' => 4]);
        }

        if (! isset($tournament->groupSetting->relegated_teams)) {
            $tournament->groupSetting->update(['relegated_teams' => []]);
        }

        if (! isset($tournament->groupSetting->locked)) {
            $tournament->groupSetting->update(['locked' => true]);
        }

        $bracketSetting = $this->resolveBracketSetting($tournament);
        $competitionType = $bracketSetting->value['competition_type'] ?? 'tournament';

        return view('admin.tournaments.settings.group', compact('tournament', 'bracketSetting', 'competitionType'));
    }

    /**
     * R16 — Halaman undian (spin) penempatan tim ke grup.
     */
    public function groupDraw(Tournament $tournament)
    {
        $tournament->load(['groupSetting', 'tournamentTeams.team']);

        $bracketSetting = AppSetting::where('key', $this->bracketSettingsKey($tournament))->first();
        $competitionType = $bracketSetting?->value['competition_type'] ?? 'tournament';

        if ($competitionType === 'tournament' || ! $tournament->groupSetting || ! $tournament->groupSetting->group_count) {
            return redirect()->route('tournaments.groupSettings', $tournament)
                ->with('warning', 'Undian grup hanya tersedia untuk Sistem Liga / Liga + Play Off yang memakai grup.');
        }

        $groupLabels = $this->buildGroupLabels((int) $tournament->groupSetting->group_count);

        $teams = $tournament->tournamentTeams
            ->map(fn ($tt) => [
                'id' => $tt->id,
                'name' => $tt->team?->name ?? ('Tim ' . $tt->id),
                'group_label' => $tt->group_label,
            ])
            ->values();

        return view('admin.tournaments.settings.group-draw', compact('tournament', 'groupLabels', 'teams'));
    }

    /**
     * R16 — Eksekusi undian: acak urutan tim lalu distribusikan ke grup secara
     * round-robin. Hasil ditandai manual agar tidak ditimpa auto-assign.
     */
    public function performGroupDraw(Request $request, Tournament $tournament)
    {
        $tournament->load('groupSetting');

        $bracketSetting = AppSetting::where('key', $this->bracketSettingsKey($tournament))->first();
        $competitionType = $bracketSetting?->value['competition_type'] ?? 'tournament';

        if ($competitionType === 'tournament' || ! $tournament->groupSetting || ! $tournament->groupSetting->group_count) {
            return response()->json([
                'success' => false,
                'message' => 'Undian grup tidak tersedia untuk tipe kompetisi ini.',
            ], 422);
        }

        $groupCount = (int) $tournament->groupSetting->group_count;
        $teamsPerGroup = (int) $tournament->groupSetting->teams_per_group;
        $groupLabels = $this->buildGroupLabels($groupCount);

        $teams = TournamentTeam::with('team')
            ->where('tournament_id', $tournament->id)
            ->get()
            ->shuffle()
            ->values();

        // R16 — hormati kapasitas: isi tiap grup sampai penuh (teams_per_group)
        // sebelum lanjut ke grup berikutnya, bukan round-robin buta yang bisa
        // membuat grup melebihi kapasitas saat jumlah tim > kapasitas total.
        $capacity = $groupCount * $teamsPerGroup;
        if ($capacity > 0 && $teams->count() > $capacity) {
            return response()->json([
                'success' => false,
                'message' => "Jumlah tim ({$teams->count()}) melebihi kapasitas grup ({$capacity} = {$groupCount} grup × {$teamsPerGroup}). Sesuaikan pengaturan grup atau kurangi peserta sebelum undian.",
            ], 422);
        }

        $assignments = [];
        $groupCounts = array_fill_keys($groupLabels, 0);

        foreach ($teams as $index => $team) {
            // Cari grup pertama yang masih punya slot; fallback ke distribusi
            // round-robin bila (entah bagaimana) semua penuh.
            $label = null;
            foreach ($groupLabels as $g) {
                if ($groupCounts[$g] < $teamsPerGroup) {
                    $label = $g;
                    break;
                }
            }
            if ($label === null) {
                $label = $groupLabels[$index % $groupCount];
            }

            $team->update([
                'group_label' => $label,
                'group_assigned_manually' => true,
            ]);

            $groupCounts[$label]++;
            $assignments[$label][] = $team->team?->name ?? ('Tim ' . $team->id);
        }

        // Regenerasi jadwal grup sesuai hasil undian.
        app(MatchGenerator::class)->generateForTournament($tournament);

        return response()->json([
            'success' => true,
            'message' => 'Undian selesai. Hasil tersimpan & jadwal diperbarui.',
            'assignments' => $assignments,
            'teams_per_group' => $teamsPerGroup,
        ]);
    }

    /**
     * Simpan penempatan grup hasil edit manual (drag & drop) dari halaman
     * Plotting/Undian. Menerima map { tournamentTeamId: groupLabel|null } lalu
     * mem-persist ke tournament_teams (ditandai manual bila punya grup) dan
     * meregenerasi jadwal. Dipakai agar admin bisa menukar tim antar grup
     * tanpa mengacak ulang, dan hasilnya tetap tampil saat halaman dibuka lagi.
     */
    public function saveGroupPlotting(Request $request, Tournament $tournament)
    {
        $tournament->load('groupSetting');

        $bracketSetting = AppSetting::where('key', $this->bracketSettingsKey($tournament))->first();
        $competitionType = $bracketSetting?->value['competition_type'] ?? 'tournament';

        if ($competitionType === 'tournament' || ! $tournament->groupSetting || ! $tournament->groupSetting->group_count) {
            return response()->json([
                'success' => false,
                'message' => 'Plotting grup tidak tersedia untuk tipe kompetisi ini.',
            ], 422);
        }

        $groupCount = (int) $tournament->groupSetting->group_count;
        $teamsPerGroup = (int) $tournament->groupSetting->teams_per_group;
        $groupLabels = $this->buildGroupLabels($groupCount);

        $validated = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*' => ['nullable', 'string', 'in:' . implode(',', $groupLabels)],
        ]);

        // Hanya tim milik turnamen ini yang boleh diubah.
        $teams = TournamentTeam::where('tournament_id', $tournament->id)
            ->get()
            ->keyBy('id');

        // Validasi kapasitas per grup sebelum menyimpan apa pun.
        $counts = array_fill_keys($groupLabels, 0);
        foreach ($validated['assignments'] as $teamId => $label) {
            if ($label !== null && $label !== '') {
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }
        foreach ($counts as $label => $count) {
            if ($teamsPerGroup > 0 && $count > $teamsPerGroup) {
                return response()->json([
                    'success' => false,
                    'message' => "Grup {$label} melebihi kapasitas ({$count}/{$teamsPerGroup}). Kurangi tim di grup tersebut sebelum menyimpan.",
                ], 422);
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $teams) {
            foreach ($validated['assignments'] as $teamId => $label) {
                $team = $teams->get((int) $teamId);
                if (! $team) {
                    continue;
                }

                $label = ($label === '') ? null : $label;

                $team->update([
                    'group_label' => $label,
                    // Penempatan manual dikunci agar tidak ditimpa auto-assign;
                    // tim tanpa grup dilepas kuncinya.
                    'group_assigned_manually' => $label !== null,
                ]);
            }
        });

        // Regenerasi jadwal grup + bracket sesuai penempatan baru.
        app(MatchGenerator::class)->generateForTournament($tournament);

        return response()->json([
            'success' => true,
            'message' => 'Penempatan grup tersimpan & jadwal diperbarui.',
        ]);
    }

    public function pointsSettings(Tournament $tournament)
    {
        $key = $this->pointSettingsKey($tournament);
        $default = [
            'win' => 3,
            'draw' => 1,
            'loss' => 0,
            'tiebreakers' => [
                'points',
                'goal_difference',
                'goals_scored',
                'head_to_head',
            ],
        ];

        $setting = AppSetting::firstOrCreate(
            ['key' => $key],
            ['value' => $default]
        );

        $value = $setting->value ?? [];
        $updated = false;

        foreach (['win', 'draw', 'loss'] as $field) {
            if (! isset($value[$field])) {
                $value[$field] = $default[$field];
                $updated = true;
            }
        }

        if (! isset($value['tiebreakers']) || ! is_array($value['tiebreakers'])) {
            $value['tiebreakers'] = $default['tiebreakers'];
            $updated = true;
        }

        if ($updated) {
            $setting->update(['value' => $value]);
        }

        return view('admin.tournaments.settings.points', compact('tournament', 'setting'));
    }

    public function updatePointSettings(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'win_points' => 'required|integer|min:0|max:99',
            'draw_points' => 'required|integer|min:0|max:99',
            'loss_points' => 'required|integer|min:0|max:99',
            'tiebreakers' => 'required|array|min:1',
            'tiebreakers.*' => 'required|string|in:points,head_to_head,goal_difference,goals_scored',
        ], [
            'win_points.required' => 'Poin menang tidak boleh kosong',
            'draw_points.required' => 'Poin imbang tidak boleh kosong',
            'loss_points.required' => 'Poin kalah tidak boleh kosong',
            'tiebreakers.required' => 'Pengaturan tie-breaker tidak boleh kosong',
            'tiebreakers.array' => 'Format tie-breaker tidak valid',
            'tiebreakers.min' => 'Pilih minimal satu kriteria tie-breaker',
            'tiebreakers.*.in' => 'Kriteria tie-breaker tidak valid',
        ]);

        $tiebreakers = array_values(array_unique($validated['tiebreakers']));

        AppSetting::updateOrCreate(
            ['key' => $this->pointSettingsKey($tournament)],
            ['value' => [
                'win' => $validated['win_points'],
                'draw' => $validated['draw_points'],
                'loss' => $validated['loss_points'],
                'tiebreakers' => $tiebreakers,
            ]]
        );

        return back()->with('success', 'Standar Liga Poin berhasil disimpan!');
    }

    public function bracketSettings(Tournament $tournament)
    {
        // CRITICAL: Reload tournament with fresh groupSetting data from database
        $tournament->load('groupSetting');
        $tournament->refresh(); // Force reload to ensure latest data

        $key = $this->bracketSettingsKey($tournament);
        $default = [
            'match_type' => 'single',
            'home_away_calculation' => 'aggregate',
            'third_place' => false,
            'group_count' => 4,
            'matches' => [],
        ];

        $setting = AppSetting::firstOrCreate([
            'key' => $key,
        ], [
            'value' => $default,
        ]);

        $value = $setting->value ?? [];
        $updated = false;

        if (! isset($value['match_type'])) {
            $value['match_type'] = $default['match_type'];
            $updated = true;
        }

        if (! isset($value['third_place'])) {
            $value['third_place'] = $default['third_place'];
            $updated = true;
        }

        if (! isset($value['home_away_calculation']) || ! in_array($value['home_away_calculation'], ['aggregate', 'wins'], true)) {
            $value['home_away_calculation'] = $default['home_away_calculation'];
            $updated = true;
        }

        if (! isset($value['group_count']) || ! is_int($value['group_count']) || $value['group_count'] < 2) {
            $value['group_count'] = $default['group_count'];
            $updated = true;
        }

        $competitionType = $value['competition_type'] ?? 'tournament';
        $playoffOptions = $value['playoff_options'] ?? [];

        // N10 — paksa third_place nonaktif pada gugur murni agar semua generator
        // bracket di bawah tidak pernah memunculkan babak Third Place.
        $normalizedThirdPlace = $this->effectiveThirdPlace($competitionType, (bool) ($value['third_place'] ?? false));
        if (($value['third_place'] ?? false) !== $normalizedThirdPlace) {
            $value['third_place'] = $normalizedThirdPlace;
            $updated = true;
        }

        // Determine playoff mode
        $playoffMode = 'promotion'; // default untuk tournament
        $hasBothOptions = false;
        if ($competitionType === 'league_playoff') {
            $hasPromotion = in_array('promotion', $playoffOptions);
            $hasRelegation = in_array('relegation', $playoffOptions);
            
            if ($hasPromotion && $hasRelegation) {
                $hasBothOptions = true;
                $playoffMode = 'both'; // Menandakan ada kedua opsi
            } elseif ($hasPromotion && !$hasRelegation) {
                $playoffMode = 'promotion';
            } elseif ($hasRelegation && !$hasPromotion) {
                $playoffMode = 'relegation';
            } else {
                $playoffMode = 'promotion';
            }
        }

        $bracketGroupCount = optional($tournament->groupSetting)->group_count ?? $value['group_count'];
        
        // Generate teams based on mode and current group settings
        // CRITICAL: Always read from tournament.groupSetting for current configuration
        if ($competitionType === 'tournament') {
            // Gugur murni: slot bracket adalah tim terverifikasi itu sendiri
            $teamNames = $this->approvedTeamNames($tournament);
            $teamsToUse = array_combine($teamNames, $teamNames) ?: [];
            $teamsToUsePromotion = null;
            $teamsToUseRelegation = null;
        } elseif ($hasBothOptions) {
            // Untuk "Promosi & Degradasi", generate kedua teams
            $promotionRanks = $tournament->groupSetting->qualified_teams ?? [1, 2];
            $relegationRanks = $tournament->groupSetting->relegated_teams ?? [];
            $teamsToUsePromotion = $this->generateQualifiedTeams($tournament, $bracketGroupCount, $promotionRanks);
            $teamsToUseRelegation = $this->generateRelegatedTeams($bracketGroupCount, $relegationRanks);
            $teamsToUse = $teamsToUsePromotion; // Default untuk backward compatibility
        } elseif ($playoffMode === 'promotion') {
            $qualifiedRanks = $tournament->groupSetting->qualified_teams ?? [1, 2];
            $teamsToUse = $this->generateQualifiedTeams($tournament, $bracketGroupCount, $qualifiedRanks);
            $teamsToUsePromotion = null;
            $teamsToUseRelegation = null;
        } else {
            $relegatedRanks = $tournament->groupSetting->relegated_teams ?? [];
            $teamsToUse = $this->generateRelegatedTeams($bracketGroupCount, $relegatedRanks);
            $teamsToUsePromotion = null;
            $teamsToUseRelegation = null;
        }
        
        // Handle matches based on mode
        if ($hasBothOptions) {
            // Generate separate matches for promotion and relegation
            $promotionMatches = $value['matches_promotion'] ?? [];
            $relegationMatches = $value['matches_relegation'] ?? [];
            
            // Generate promotion matches if needed
            if (! is_array($promotionMatches) || count($promotionMatches) === 0 || ! $this->isBracketStructureValid($promotionMatches)) {
                $promotionRanks = $tournament->groupSetting->qualified_teams ?? [1, 2];
                $value['matches_promotion'] = $this->generateDefaultBracketMatches(
                    $value['group_count'],
                    $promotionRanks,
                    (bool) ($value['third_place'] ?? false)
                );
                $updated = true;
            }
            
            // Generate relegation matches if needed
            if (! is_array($relegationMatches) || count($relegationMatches) === 0 || ! $this->isBracketStructureValid($relegationMatches)) {
                $relegationRanks = $tournament->groupSetting->relegated_teams ?? [];
                $value['matches_relegation'] = $this->generateDefaultBracketMatches(
                    $value['group_count'],
                    $relegationRanks,
                    (bool) ($value['third_place'] ?? false)
                );
                $updated = true;
            }
            
            $matches = $value['matches_promotion'] ?? [];
        } else {
            // Single mode - use standard matches key
            $matches = $value['matches'] ?? [];
            if (! is_array($matches) || count($matches) === 0 || ! $this->isBracketStructureValid($matches)) {
                if ($competitionType === 'tournament') {
                    // Gugur murni: slot bracket berasal dari tim terverifikasi
                    $value['matches'] = $this->generateKnockoutBracketFromTeams(
                        $tournament,
                        (bool) ($value['third_place'] ?? false)
                    );
                } else {
                    // Determine ranks to use for generating default bracket matches
                    if ($playoffMode === 'promotion') {
                        $rankForMatches = $tournament->groupSetting->qualified_teams ?? [1, 2];
                    } else {
                        $rankForMatches = $tournament->groupSetting->relegated_teams ?? [];
                    }

                    $value['matches'] = $this->generateDefaultBracketMatches(
                        $value['group_count'],
                        $rankForMatches,
                        (bool) ($value['third_place'] ?? false)
                    );
                }

                if ($value['matches'] !== $matches) {
                    $updated = true;
                }
            }
        }

        if ($updated) {
            $setting->update(['value' => $value]);
        }

        return view('admin.tournaments.settings.bracket', compact('tournament', 'setting', 'competitionType', 'playoffOptions', 'playoffMode', 'teamsToUse', 'hasBothOptions', 'teamsToUsePromotion', 'teamsToUseRelegation'));
    }

    public function bracketAdmin(Tournament $tournament, Request $request)
    {
        // CRITICAL: Reload tournament with fresh groupSetting data from database
        $tournament->load('groupSetting');
        $tournament->refresh(); // Force reload to ensure latest data

        if (! $tournament->groupSetting) {
            return redirect()->route('tournaments.settings', $tournament)
                           ->with('warning', 'Silakan atur pengaturan grup terlebih dahulu sebelum mengelola bracket.');
        }

        $setting = $this->resolveBracketSetting($tournament);
        $competitionType = $setting->value['competition_type'] ?? 'tournament';
        $playoffOptions = $setting->value['playoff_options'] ?? [];
        
        // Check if bracket admin is allowed for this competition type
        $isTournament = $competitionType === 'tournament';
        $isGroupKnockout = $competitionType === 'group_knockout';
        $hasPromotion = in_array('promotion', $playoffOptions);
        $hasRelegation = in_array('relegation', $playoffOptions);
        $isLeaguePlayoff = $competitionType === 'league_playoff' && ($hasPromotion || $hasRelegation);
        $bracketAllowed = $isTournament || $isLeaguePlayoff || $isGroupKnockout;

        if ($competitionType === 'league') {
            // Sistem liga murni tidak memiliki babak gugur
            return redirect()->route('tournaments.standings', $tournament)
                           ->with('warning', 'Sistem Liga tidak menggunakan babak gugur. Hasil akhir ditentukan dari tabel klasemen.');
        }

        if (! $bracketAllowed) {
            return redirect()->route('tournaments.standings', $tournament)
                           ->with('warning', 'Bracket gugur tidak tersedia untuk mode kompetisi ini. Aktifkan "Play Off" di pengaturan grup.');
        }

        // ENSURE TEAMS ARE READ FROM CURRENT GROUP SETTINGS
        $groupCount = $tournament->groupSetting->group_count ?? 4;
        $qualifiedRanks = $tournament->groupSetting->qualified_teams ?? [1, 2];
        $relegatedRanks = $tournament->groupSetting->relegated_teams ?? [];

        // Determine which teams to use based on playoff mode
        $hasBothOptions = $hasPromotion && $hasRelegation;
        $playoffMode = 'promotion'; // default untuk tournament
        if ($competitionType === 'league_playoff') {
            if ($hasPromotion && !$hasRelegation) {
                $playoffMode = 'promotion';
            } elseif ($hasRelegation && !$hasPromotion) {
                $playoffMode = 'relegation';
            } else {
                // R5 — kedua opsi aktif: admin bisa beralih lewat ?mode=
                // (promotion/relegation). Default promotion.
                $requested = $request->query('mode');
                $playoffMode = in_array($requested, ['promotion', 'relegation'], true)
                    ? $requested
                    : 'promotion';
            }
        }

        // R5 — saat both, sajikan struktur bracket sesuai mode terpilih ke view
        // dari key terpisah matches_promotion / matches_relegation.
        if ($hasBothOptions) {
            $value = $setting->value ?? [];
            $modeKey = $playoffMode === 'relegation' ? 'matches_relegation' : 'matches_promotion';
            $value['matches'] = $value[$modeKey] ?? [];
            // Hanya untuk tampilan — tidak di-save() ke DB.
            $setting->value = $value;
        }

        // Generate teams berdasarkan mode - ALWAYS FRESH FROM GROUP SETTINGS
        if ($isTournament) {
            // Gugur murni: slot bracket adalah tim terverifikasi itu sendiri
            $teamNames = $this->approvedTeamNames($tournament);
            $teamsToUse = array_combine($teamNames, $teamNames) ?: [];
        } elseif ($playoffMode === 'promotion') {
            $teamsToUse = $this->generateQualifiedTeams($tournament, $groupCount, $qualifiedRanks);
        } else {
            $teamsToUse = $this->generateRelegatedTeams($groupCount, $relegatedRanks);
        }

        $isLeaguePlayoffWithPromotion = $competitionType === 'league_playoff' && $playoffMode === 'promotion';
        $isLeaguePlayoffWithRelegation = $competitionType === 'league_playoff' && $playoffMode === 'relegation';
        $isGroupKnockout = $competitionType === 'group_knockout';

        // Load actual TournamentTeam records for Manual mode selection
        $tournamentTeams = $tournament->tournamentTeams()->with('team')->get();

        // Load assigned matches (by bracket_match_id) so we can display assigned team names/ids.
        // Mode Home & Away punya 2 row per bracket_match_id — pakai row leg 1
        // agar orientasi home/away kartu bracket tidak terbalik.
        $assignedMatches = \App\Models\TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('bracket_match_id')
            ->where(function ($query) {
                $query->whereNull('leg')->orWhere('leg', 1);
            })
            // R5 — saat both, promosi & degradasi punya bracket_match_id yang
            // bisa bertabrakan; saring sesuai stage_type mode terpilih.
            ->when($hasBothOptions, function ($query) use ($playoffMode) {
                $query->where('stage_type', $playoffMode === 'relegation' ? 'relegation_playoff' : 'promotion_playoff');
            })
            ->get()
            ->keyBy('bracket_match_id');

        // Ringkasan skor per kartu bracket (single / 2-leg / adu penalti) untuk
        // ditampilkan di bagan. Dibangun dari TieResolver agar agregat, leg, dan
        // penalti konsisten dengan logika penentuan pemenang.
        $bracketScores = $this->buildBracketScoreSummaries($tournament, $hasBothOptions ? $playoffMode : null);

        // Mode turnamen (gugur murni) tidak memiliki fase grup yang harus ditunggu
        $groupStageComplete = $isTournament ? true : $this->isGroupStageComplete($tournament);

        // R5 — di mode degradasi, opsi tim harus dari ranking degradasi
        // (getRelegatedTeams), bukan tim promosi (getQualifiedTeams).
        if ($isTournament) {
            $qualifiedTeams = [];
        } elseif ($playoffMode === 'relegation') {
            $qualifiedTeams = $this->getRelegatedTeams($tournament);
        } else {
            $qualifiedTeams = $this->getQualifiedTeams($tournament);
        }
        $qualifiedTeamOptions = [];

        if ($isTournament) {
            $tournament->load('tournamentTeams.team');
            foreach ($tournament->tournamentTeams as $tournamentTeam) {
                if (($tournamentTeam->team?->verification_status ?? 'pending') !== 'approved') {
                    continue;
                }

                $teamName = $tournamentTeam->team?->name ?? "Tim {$tournamentTeam->id}";
                $qualifiedTeamOptions[$tournamentTeam->id] = [
                    'position' => $teamName,
                    'name' => $teamName,
                ];
            }
        } elseif (! empty($qualifiedTeams)) {
            $tournament->load('tournamentTeams.team');
            $teamMap = $tournament->tournamentTeams->keyBy('id');

            foreach ($qualifiedTeams as $position => $teamId) {
                $team = $teamMap[$teamId] ?? null;
                $qualifiedTeamOptions[$teamId] = [
                    'position' => $position,
                    'name' => $team?->team?->name ?? "Tim {$position}",
                ];
            }
        }

        return view('admin.tournaments.bracket.manage', compact('tournament', 'setting', 'teamsToUse', 'competitionType', 'isLeaguePlayoffWithPromotion', 'isLeaguePlayoffWithRelegation', 'isGroupKnockout', 'playoffMode', 'hasBothOptions', 'tournamentTeams', 'assignedMatches', 'groupStageComplete', 'qualifiedTeamOptions', 'bracketScores'));
    }

    public function saveBracketAssignments(Request $request, Tournament $tournament)
    {
        $tournament->load('groupSetting');

        if (! $tournament->groupSetting) {
            return redirect()->route('tournaments.settings', $tournament)
                           ->with('warning', 'Silakan atur pengaturan grup terlebih dahulu sebelum menyimpan bracket.');
        }

        $setting = $this->resolveBracketSetting($tournament);
        $competitionType = $setting->value['competition_type'] ?? 'tournament';
        $playoffOptions = $setting->value['playoff_options'] ?? [];
        
        // Determine playoff mode
        $playoffMode = 'promotion'; // default untuk tournament
        $hasBothOptions = false;
        if ($competitionType === 'league_playoff') {
            $hasPromotion = in_array('promotion', $playoffOptions);
            $hasRelegation = in_array('relegation', $playoffOptions);
            $hasBothOptions = $hasPromotion && $hasRelegation;

            if ($hasPromotion && !$hasRelegation) {
                $playoffMode = 'promotion';
            } elseif ($hasRelegation && !$hasPromotion) {
                $playoffMode = 'relegation';
            } else {
                // R5 — kedua opsi aktif: simpan ke bracket sesuai mode yang
                // sedang diedit (dikirim lewat ?mode=). Default promotion.
                $requested = $request->query('mode');
                $playoffMode = in_array($requested, ['promotion', 'relegation'], true)
                    ? $requested
                    : 'promotion';
            }
        }
        
        // Generate teams berdasarkan mode
        if ($playoffMode === 'promotion') {
            $teamsToUse = $this->generateQualifiedTeams(
                $tournament,
                $tournament->groupSetting->group_count ?? 4,
                $tournament->groupSetting->qualified_teams ?? [1, 2]
            );
        } else {
            $teamsToUse = $this->generateRelegatedTeams(
                $tournament->groupSetting->group_count ?? 4,
                $tournament->groupSetting->relegated_teams ?? []
            );
        }

        // R5 — batasi daftar tim yang bisa di-assign sesuai mode: promosi pakai
        // getQualifiedTeams, degradasi pakai getRelegatedTeams. Sebelumnya mode
        // degradasi mengosongkan filter → memuat SEMUA tim (bisa salah assign).
        $qualifiedTeamIds = $playoffMode === 'relegation'
            ? array_values($this->getRelegatedTeams($tournament))
            : array_values($this->getQualifiedTeams($tournament));

        $qualifiedTeams = TournamentTeam::with('team')
            ->where('tournament_id', $tournament->id)
            ->when(! empty($qualifiedTeamIds), fn($query) => $query->whereIn('id', $qualifiedTeamIds))
            ->get()
            ->keyBy('id');

        $teamNameById = $qualifiedTeams->mapWithKeys(fn($tt) => [$tt->id => $tt->team?->name ?? 'Tim ' . $tt->id])->all();

        $currentValue = $setting->value ?? [];
        // R5 — saat both, baca/tulis struktur sesuai mode (matches_promotion /
        // matches_relegation). Selain itu pakai 'matches' seperti biasa.
        $matchesKey = $hasBothOptions
            ? ($playoffMode === 'relegation' ? 'matches_relegation' : 'matches_promotion')
            : 'matches';
        $currentMatches = $currentValue[$matchesKey] ?? [];

        $validated = $request->validate([
            'matches' => 'required|array|min:1',
            'matches.*.left' => 'required|string|max:80',
            'matches.*.right' => 'required|string|max:80',
            'matches.*.left_id' => 'nullable|integer',
            'matches.*.right_id' => 'nullable|integer',
        ], [
            'matches.required' => 'Daftar pertandingan tidak boleh kosong.',
            'matches.array' => 'Format bracket tidak valid.',
            'matches.*.left.required' => 'Slot kiri tidak boleh kosong.',
            'matches.*.right.required' => 'Slot kanan tidak boleh kosong.',
            'matches.*.left_id.integer' => 'Pilihan tim tidak valid.',
            'matches.*.right_id.integer' => 'Pilihan tim tidak valid.',
        ]);

        // Ensure left/right keys are updated in the stored bracket structure
        foreach ($currentMatches as $index => &$match) {
            if (isset($validated['matches'][$index])) {
                $match['left'] = trim($validated['matches'][$index]['left']);
                $match['right'] = trim($validated['matches'][$index]['right']);
            }
        }
        unset($match);

        $currentValue[$matchesKey] = $currentMatches;
        $setting->update(['value' => $currentValue]);

        // Mode Home & Away punya 2 row per bracket_match_id — penetapan manual
        // dilakukan pada row entry point (single match atau leg 1), lalu leg 2
        // disinkronkan (dibalik) setelahnya.
        $dbMatches = \App\Models\TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('bracket_match_id')
            ->where(function ($query) {
                $query->whereNull('leg')->orWhere('leg', 1);
            })
            // R5 — saat both, batasi ke stage_type mode terpilih agar tidak
            // menukar tim antar bracket promosi & degradasi.
            ->when($hasBothOptions, function ($query) use ($playoffMode) {
                $query->where('stage_type', $playoffMode === 'relegation' ? 'relegation_playoff' : 'promotion_playoff');
            })
            ->get()
            ->keyBy('bracket_match_id');

        // Build team location map: teamId => ['bracket_match_id' => id, 'side' => 'home'|'away']
        $teamLocation = [];
        foreach ($dbMatches as $bm) {
            if ($bm->home_team_id) {
                $teamLocation[$bm->home_team_id] = ['bracket_match_id' => $bm->bracket_match_id, 'side' => 'home'];
            }
            if ($bm->away_team_id) {
                $teamLocation[$bm->away_team_id] = ['bracket_match_id' => $bm->bracket_match_id, 'side' => 'away'];
            }
        }

        // Perform assignments inside a transaction to ensure atomic swaps
        DB::transaction(function () use ($validated, $currentMatches, $dbMatches, &$teamLocation, $teamNameById) {
            foreach ($validated['matches'] as $index => $submitted) {
                $leftId = isset($submitted['left_id']) && $submitted['left_id'] !== '' ? (int) $submitted['left_id'] : null;
                $rightId = isset($submitted['right_id']) && $submitted['right_id'] !== '' ? (int) $submitted['right_id'] : null;

                $bracketMatchId = $currentMatches[$index]['id'] ?? null;
                if ($bracketMatchId === null) {
                    continue;
                }

                $dbMatch = $dbMatches[$bracketMatchId] ?? null;
                if (! $dbMatch) {
                    continue;
                }

                // Update placeholder keys only if no team has been assigned yet.
                if ($leftId !== null) {
                    $dbMatch->home_team_key = $teamNameById[$leftId] ?? $dbMatch->home_team_key;
                    $dbMatch->source_home = $teamNameById[$leftId] ?? $dbMatch->source_home;
                } elseif (! $dbMatch->home_team_id && isset($currentMatches[$index]['left'])) {
                    $dbMatch->home_team_key = $currentMatches[$index]['left'];
                }

                if ($rightId !== null) {
                    $dbMatch->away_team_key = $teamNameById[$rightId] ?? $dbMatch->away_team_key;
                    $dbMatch->source_away = $teamNameById[$rightId] ?? $dbMatch->source_away;
                } elseif (! $dbMatch->away_team_id && isset($currentMatches[$index]['right'])) {
                    $dbMatch->away_team_key = $currentMatches[$index]['right'];
                }

                // Track matches that need saving (including swapped other matches)
                $matchesToSave = [];

                // Helper to perform assignment with swap
                $assignWithSwap = function($desiredId, $targetMatch, $targetSide) use (&$dbMatches, &$teamLocation, &$matchesToSave, $teamNameById) {
                    if ($desiredId === null) {
                        // do not overwrite if admin left blank
                        return;
                    }

                    $currentVal = $targetSide === 'home' ? $targetMatch->home_team_id : $targetMatch->away_team_id;
                    if ($currentVal === $desiredId) {
                        return;
                    }

                    if (isset($teamLocation[$desiredId])) {
                        $other = $teamLocation[$desiredId];
                        // if it's the same target, nothing to do
                        if ($other['bracket_match_id'] === $targetMatch->bracket_match_id && $other['side'] === $targetSide) {
                            return;
                        }

                        $otherMatch = $dbMatches[$other['bracket_match_id']] ?? null;
                        if (! $otherMatch) return;

                        // previous value in target (may be null)
                        $prevTargetVal = $currentVal;

                        // place prevTargetVal into the other slot (swap)
                        if ($other['side'] === 'home') {
                            $otherMatch->home_team_id = $prevTargetVal;
                            if ($prevTargetVal !== null) {
                                $otherMatch->home_team_key = $teamNameById[$prevTargetVal] ?? $otherMatch->home_team_key;
                                $otherMatch->source_home = $teamNameById[$prevTargetVal] ?? $otherMatch->source_home;
                                $teamLocation[$prevTargetVal] = ['bracket_match_id' => $otherMatch->bracket_match_id, 'side' => 'home'];
                            }
                        } else {
                            $otherMatch->away_team_id = $prevTargetVal;
                            if ($prevTargetVal !== null) {
                                $otherMatch->away_team_key = $teamNameById[$prevTargetVal] ?? $otherMatch->away_team_key;
                                $otherMatch->source_away = $teamNameById[$prevTargetVal] ?? $otherMatch->source_away;
                                $teamLocation[$prevTargetVal] = ['bracket_match_id' => $otherMatch->bracket_match_id, 'side' => 'away'];
                            }
                        }

                        // assign desired to target
                        if ($targetSide === 'home') {
                            $targetMatch->home_team_id = $desiredId;
                            $targetMatch->home_team_key = $teamNameById[$desiredId] ?? $targetMatch->home_team_key;
                            $targetMatch->source_home = $teamNameById[$desiredId] ?? $targetMatch->source_home;
                        } else {
                            $targetMatch->away_team_id = $desiredId;
                            $targetMatch->away_team_key = $teamNameById[$desiredId] ?? $targetMatch->away_team_key;
                            $targetMatch->source_away = $teamNameById[$desiredId] ?? $targetMatch->source_away;
                        }

                        // update mapping
                        $teamLocation[$desiredId] = ['bracket_match_id' => $targetMatch->bracket_match_id, 'side' => $targetSide];

                        // mark otherMatch to be saved
                        $matchesToSave[$otherMatch->bracket_match_id] = $otherMatch;
                    } else {
                        // desired id not assigned elsewhere, just assign
                        if ($targetSide === 'home') {
                            $targetMatch->home_team_id = $desiredId;
                            $targetMatch->home_team_key = $teamNameById[$desiredId] ?? $targetMatch->home_team_key;
                            $targetMatch->source_home = $teamNameById[$desiredId] ?? $targetMatch->source_home;
                        } else {
                            $targetMatch->away_team_id = $desiredId;
                            $targetMatch->away_team_key = $teamNameById[$desiredId] ?? $targetMatch->away_team_key;
                            $targetMatch->source_away = $teamNameById[$desiredId] ?? $targetMatch->source_away;
                        }

                        // update mapping
                        $teamLocation[$desiredId] = ['bracket_match_id' => $targetMatch->bracket_match_id, 'side' => $targetSide];
                    }
                };

                // Assign left then right
                $assignWithSwap($leftId, $dbMatch, 'home');
                $assignWithSwap($rightId, $dbMatch, 'away');

                // ensure current match is saved as well
                $matchesToSave[$dbMatch->bracket_match_id] = $dbMatch;

                // Persist all modified matches for this iteration
                foreach ($matchesToSave as $m) {
                    $m->save();
                }
            }
        });

        $this->syncSecondLegSlots($tournament);

        // R5 — redirect eksplisit menjaga ?mode= (jangan andalkan Referer via back()).
        $redirectParams = $hasBothOptions ? [$tournament, 'mode' => $playoffMode] : [$tournament];

        return redirect()->route('tournaments.bracketAdmin', $redirectParams)
            ->with('success', 'Tim bracket berhasil disimpan.');
    }

    /**
     * Sinkronkan slot tim row leg 2 dari row leg 1 pada tie yang sama, dengan
     * posisi home/away dibalik. source_home/source_away leg 2 sengaja tidak
     * disentuh karena masih menyimpan label asal slot.
     */
    private function syncSecondLegSlots(Tournament $tournament): void
    {
        $legOnes = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('leg', 1)
            ->get();

        if ($legOnes->isEmpty()) {
            return;
        }

        $legTwos = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('leg', 2)
            ->get()
            ->keyBy(fn ($match) => $match->stage_type . '#' . $match->bracket_match_id);

        foreach ($legOnes as $legOne) {
            $legTwo = $legTwos[$legOne->stage_type . '#' . $legOne->bracket_match_id] ?? null;

            if (! $legTwo) {
                continue;
            }

            $dirty = false;

            if ($legTwo->home_team_id !== $legOne->away_team_id) {
                $legTwo->home_team_id = $legOne->away_team_id;
                $dirty = true;
            }

            if ($legTwo->away_team_id !== $legOne->home_team_id) {
                $legTwo->away_team_id = $legOne->home_team_id;
                $dirty = true;
            }

            if ($legTwo->home_team_key !== $legOne->away_team_key) {
                $legTwo->home_team_key = $legOne->away_team_key;
                $dirty = true;
            }

            if ($legTwo->away_team_key !== $legOne->home_team_key) {
                $legTwo->away_team_key = $legOne->home_team_key;
                $dirty = true;
            }

            if ($dirty) {
                $legTwo->save();
            }
        }
    }

    private function resolveBracketSetting(Tournament $tournament): AppSetting
    {
        $key = $this->bracketSettingsKey($tournament);
        $default = [
            'match_type' => 'single',
            'home_away_calculation' => 'aggregate',
            'third_place' => false,
            'competition_type' => 'tournament',
            'group_count' => 4,
            'matches' => [],
        ];

        $setting = AppSetting::firstOrCreate([
            'key' => $key,
        ], [
            'value' => $default,
        ]);

        $value = $setting->value ?? [];
        $updated = false;

        if (! isset($value['match_type'])) {
            $value['match_type'] = $default['match_type'];
            $updated = true;
        }

        if (! isset($value['third_place'])) {
            $value['third_place'] = $default['third_place'];
            $updated = true;
        }

        if (! isset($value['home_away_calculation']) || ! in_array($value['home_away_calculation'], ['aggregate', 'wins'], true)) {
            $value['home_away_calculation'] = $default['home_away_calculation'];
            $updated = true;
        }

        if (! isset($value['group_count']) || ! is_int($value['group_count']) || $value['group_count'] < 2) {
            $value['group_count'] = $default['group_count'];
            $updated = true;
        }

        if (! isset($value['competition_type']) || ! in_array($value['competition_type'], ['tournament', 'league', 'league_playoff', 'group_knockout'], true)) {
            $value['competition_type'] = $default['competition_type'];
            $updated = true;
        }

        // N10 — gugur murni tidak boleh punya babak Third Place.
        $normalizedThirdPlace = $this->effectiveThirdPlace($value['competition_type'], (bool) ($value['third_place'] ?? false));
        if (($value['third_place'] ?? false) !== $normalizedThirdPlace) {
            $value['third_place'] = $normalizedThirdPlace;
            $updated = true;
        }

        $matches = $value['matches'] ?? [];
        if (! is_array($matches) || count($matches) === 0 || ! $this->isBracketStructureValid($matches)) {
            if ($value['competition_type'] === 'tournament') {
                // Gugur murni: slot bracket berasal dari tim terverifikasi
                $value['matches'] = $this->generateKnockoutBracketFromTeams(
                    $tournament,
                    (bool) ($value['third_place'] ?? false)
                );
            } elseif ($value['competition_type'] === 'group_knockout') {
                // Grup → Gugur: placeholder posisi grup dgn seeding silang.
                $value['matches'] = app(MatchGenerator::class)->buildGroupKnockoutStructure(
                    (int) ($value['group_count'] ?? $tournament->groupSetting->group_count ?? 0),
                    $tournament->groupSetting->qualified_teams ?? [1, 2],
                    (bool) ($value['third_place'] ?? false)
                );
            } else {
                $qualifiedRanks = $tournament->groupSetting->qualified_teams ?? [1, 2];
                $value['matches'] = $this->generateDefaultBracketMatches(
                    $value['group_count'],
                    $qualifiedRanks,
                    (bool) ($value['third_place'] ?? false)
                );
            }

            if ($value['matches'] !== $matches) {
                $updated = true;
            }
        }

        if ($updated) {
            $setting->update(['value' => $value]);
        }

        return $setting;
    }

    private function generateQualifiedTeams(Tournament $tournament, int $groupCount, array $qualifiedRanks): array
    {
        $qualified = $this->getQualifiedTeams($tournament);
        $teams = [];

        if (! empty($qualified)) {
            $tournament->load('tournamentTeams.team');
            $teamMap = $tournament->tournamentTeams->keyBy('id');

            foreach ($qualified as $position => $teamId) {
                $team = $teamMap[$teamId] ?? null;
                $teams[$position] = $team?->team?->name ?? "Tim {$position}";
            }

            return $teams;
        }

        $groupLabels = array_slice(['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P'], 0, $groupCount);
        foreach ($groupLabels as $group) {
            foreach ($qualifiedRanks as $rank) {
                $position = strtoupper($group) . $rank;
                $teams[$position] = "Tim {$position}";
            }
        }

        return $teams;
    }

    private function getQualifiedTeams(Tournament $tournament): array
    {
        if (! $this->isGroupStageComplete($tournament) || ! $tournament->groupSetting) {
            return [];
        }

        $groups = $this->buildStandingsGroups($tournament);
        $qualifiedRanks = $tournament->groupSetting->qualified_teams ?? [1, 2];
        $qualified = [];

        foreach ($groups as $groupLabel => $rows) {
            foreach ($qualifiedRanks as $rank) {
                $teamRow = collect($rows)->first(fn ($row) => $row['ranking'] == $rank);
                if ($teamRow) {
                    $qualified[strtoupper($groupLabel) . $rank] = $teamRow['team_id'];
                }
            }
        }

        return $qualified;
    }

    private function generateRelegatedTeams(int $groupCount, array $relegatedRanks): array
    {
        $groupLabels = array_slice(['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P'], 0, $groupCount);
        $teams = [];

        foreach ($groupLabels as $group) {
            foreach ($relegatedRanks as $rank) {
                $position = strtoupper($group) . $rank;
                $teams[$position] = "Tim {$position}";
            }
        }

        return $teams;
    }

    /**
     * R5 — versi getQualifiedTeams() untuk tim degradasi: mengembalikan id tim
     * asli pada ranking degradasi dari klasemen (position => team_id). Dipakai
     * agar bracket degradasi (mode both) menampilkan & memvalidasi tim yang benar.
     */
    private function getRelegatedTeams(Tournament $tournament): array
    {
        if (! $this->isGroupStageComplete($tournament) || ! $tournament->groupSetting) {
            return [];
        }

        $groups = $this->buildStandingsGroups($tournament);
        $relegatedRanks = $tournament->groupSetting->relegated_teams ?? [];
        $relegated = [];

        foreach ($groups as $groupLabel => $rows) {
            foreach ($relegatedRanks as $rank) {
                $teamRow = collect($rows)->first(fn ($row) => $row['ranking'] == $rank);
                if ($teamRow) {
                    $relegated[strtoupper($groupLabel) . $rank] = $teamRow['team_id'];
                }
            }
        }

        return $relegated;
    }

    /**
     * Validasi dan pastikan bracket teams match dengan group settings
     * Returns array berisi status dan teams terbaru
     */
    private function validateAndEnsureBracketTeamConsistency(Tournament $tournament): array
    {
        $groupSetting = $tournament->groupSetting;
        if (!$groupSetting) {
            return ['valid' => false, 'message' => 'No group setting'];
        }

        $bracketSetting = $this->resolveBracketSetting($tournament);
        $competitionType = $bracketSetting->value['competition_type'] ?? 'tournament';
        $groupCount = $groupSetting->group_count ?? 4;
        $qualifiedRanks = $groupSetting->qualified_teams ?? [1, 2];
        $relegatedRanks = $groupSetting->relegated_teams ?? [];

        // Generate expected teams based on current settings
        $expectedTeams = [];
        if ($competitionType === 'tournament' || $competitionType === 'group_knockout') {
            $expectedTeams = $this->generateQualifiedTeams($tournament, $groupCount, $qualifiedRanks);
        } elseif ($competitionType === 'league') {
            $expectedTeams = $this->generateRelegatedTeams($groupCount, $relegatedRanks);
        } else { // league_playoff
            $playoffOptions = $bracketSetting->value['playoff_options'] ?? [];
            if (in_array('promotion', $playoffOptions) && in_array('relegation', $playoffOptions)) {
                $expectedTeams = array_merge(
                    $this->generateQualifiedTeams($tournament, $groupCount, $qualifiedRanks),
                    $this->generateRelegatedTeams($groupCount, $relegatedRanks)
                );
            } elseif (in_array('promotion', $playoffOptions)) {
                $expectedTeams = $this->generateQualifiedTeams($tournament, $groupCount, $qualifiedRanks);
            } elseif (in_array('relegation', $playoffOptions)) {
                $expectedTeams = $this->generateRelegatedTeams($groupCount, $relegatedRanks);
            }
        }

        return [
            'valid' => true,
            'expectedTeams' => $expectedTeams,
            'groupCount' => $groupCount,
            'competitionType' => $competitionType,
        ];
    }

    public function updateBracketSettings(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'match_type' => 'required|in:single,home_away',
            'home_away_calculation' => 'required_if:match_type,home_away|nullable|in:aggregate,wins',
            'third_place' => 'sometimes|accepted',
            'group_count' => 'required|integer|min:1|max:16',
            'matches' => 'sometimes|array|min:1',
            'matches.*.left' => 'required_with:matches|string|max:80',
            'matches.*.right' => 'required_with:matches|string|max:80',
        ], [
            'match_type.required' => 'Pilih jenis pertandingan knock out',
            'match_type.in' => 'Jenis pertandingan tidak valid',
            'home_away_calculation.required_if' => 'Pilih metode penentuan pemenang Home & Away',
            'home_away_calculation.in' => 'Metode penentuan pemenang tidak valid',
            'group_count.required' => 'Jumlah grup tidak boleh kosong',
            'group_count.integer' => 'Jumlah grup harus berupa angka',
            'group_count.min' => 'Jumlah grup minimal 2',
            'group_count.max' => 'Jumlah grup maksimal 16',
            'matches.array' => 'Format pertandingan tidak valid',
            'matches.min' => 'Tambahkan minimal satu pertandingan knockout',
            'matches.*.left.required_with' => 'Nama slot kiri tidak boleh kosong',
            'matches.*.right.required_with' => 'Nama slot kanan tidak boleh kosong',
        ]);

        $matches = [];
        $currentSetting = $this->resolveBracketSetting($tournament);
        $currentValue = $currentSetting->value ?? [];
        $competitionType = $currentValue['competition_type'] ?? 'tournament';
        $playoffOptions = $currentValue['playoff_options'] ?? [];
        $isBothPlayoff = $competitionType === 'league_playoff' && in_array('promotion', $playoffOptions) && in_array('relegation', $playoffOptions);
        $qualifiedRanks = $tournament->groupSetting->qualified_teams ?? [];
        $relegatedRanks = $tournament->groupSetting->relegated_teams ?? [];

        // N10 — opsi "Perebutan Peringkat 3" tidak berlaku pada Sistem Turnamen
        // (gugur murni). Abaikan input third_place untuk tipe 'tournament'.
        $includeThirdPlace = $this->effectiveThirdPlace($competitionType, isset($validated['third_place']));

        if (isset($validated['matches'])) {
            $matches = array_values(array_map(function ($match) {
                return [
                    'left' => trim($match['left'] ?? ''),
                    'right' => trim($match['right'] ?? ''),
                ];
            }, $validated['matches']));
        } elseif ($isBothPlayoff) {
            $matches = $this->generateDefaultBracketMatches(
                $validated['group_count'],
                $qualifiedRanks,
                $includeThirdPlace
            );
            $promotionMatches = $matches;
            $relegationMatches = $this->generateDefaultBracketMatches(
                $validated['group_count'],
                $relegatedRanks,
                $includeThirdPlace
            );
        } elseif ($competitionType === 'tournament') {
            // Gugur murni: regenerate slot bracket dari tim terverifikasi
            $matches = $this->generateKnockoutBracketFromTeams(
                $tournament,
                $includeThirdPlace
            );
        } elseif ($competitionType === 'group_knockout') {
            // Grup → Gugur: placeholder posisi grup dgn seeding silang
            // juara × runner-up antar grup.
            $matches = app(MatchGenerator::class)->buildGroupKnockoutStructure(
                $validated['group_count'],
                $qualifiedRanks ?: [1, 2],
                $includeThirdPlace
            );
        } else {
            if ($competitionType === 'league_playoff' && in_array('relegation', $playoffOptions) && ! in_array('promotion', $playoffOptions)) {
                $ranksToUse = $relegatedRanks;
            } else {
                $ranksToUse = $qualifiedRanks ?: [1, 2];
            }
            $matches = $this->generateDefaultBracketMatches(
                $validated['group_count'],
                $ranksToUse,
                $includeThirdPlace
            );
        }

        $valueToSave = [
            'match_type' => $validated['match_type'],
            'home_away_calculation' => $validated['home_away_calculation'] ?? ($currentValue['home_away_calculation'] ?? 'aggregate'),
            'third_place' => $includeThirdPlace,
            'competition_type' => $competitionType,
            'group_count' => $validated['group_count'],
            'matches' => $matches,
        ];

        if ($isBothPlayoff) {
            $valueToSave['playoff_options'] = $playoffOptions;
            $valueToSave['matches_promotion'] = $promotionMatches ?? ($currentValue['matches_promotion'] ?? []);
            $valueToSave['matches_relegation'] = $relegationMatches ?? ($currentValue['matches_relegation'] ?? []);
        } elseif (! empty($playoffOptions)) {
            $valueToSave['playoff_options'] = $playoffOptions;
        }

        AppSetting::updateOrCreate(
            ['key' => $this->bracketSettingsKey($tournament)],
            ['value' => $valueToSave]
        );

        $successMessage = 'Pengaturan Bagan Bracket berhasil disimpan!';

        // Jadwal knockout perlu digenerate ulang jika mode laga berubah ATAU
        // struktur row yang ada tidak sesuai mode (mis. setting sudah
        // home_away tapi row masih single leg karena tersimpan dari versi
        // lama). Hanya aman jika belum ada hasil pertandingan.
        $bracketStages = ['knockout', 'promotion_playoff', 'relegation_playoff'];

        $bracketRows = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereIn('stage_type', $bracketStages)
            ->get(['id', 'leg', 'round_name', 'is_third_place', 'is_bye']);

        // Row yang seharusnya 2 leg pada mode home_away (Final/Third Place/bye
        // tetap single match).
        $twoLegEligibleRows = $bracketRows->filter(
            fn ($row) => $row->round_name !== 'Final' && ! $row->is_third_place && ! $row->is_bye
        );

        $structureMismatch = $validated['match_type'] === 'home_away'
            ? $twoLegEligibleRows->isNotEmpty() && $twoLegEligibleRows->contains(fn ($row) => $row->leg === null)
            : $bracketRows->contains(fn ($row) => $row->leg !== null);

        $matchTypeChanged = ($currentValue['match_type'] ?? 'single') !== $validated['match_type'];

        if ($bracketRows->isNotEmpty() && ($matchTypeChanged || $structureMismatch)) {
            $hasDirtyMatches = TournamentMatch::where('tournament_id', $tournament->id)
                ->where(function ($query) {
                    $query->where('status', '!=', 'scheduled')
                        ->orWhereNotNull('home_score')
                        ->orWhereNotNull('away_score');
                })
                ->exists();

            if ($hasDirtyMatches) {
                $successMessage .= ' Catatan: jadwal knockout tidak digenerate ulang karena sudah ada hasil pertandingan. Reset jadwal terlebih dahulu untuk menerapkan mode laga baru.';
            } elseif ($competitionType === 'tournament') {
                app(MatchGenerator::class)->generateForTournament($tournament);
                $successMessage = 'Pengaturan Bagan Bracket berhasil disimpan dan jadwal knockout digenerate ulang!';
            }
        }

        return back()->with('success', $successMessage);
    }

    private function generateDefaultBracketMatches(int $groupCount, array $qualifiedRanks, bool $includeThirdPlace = false): array
    {
        $groupLabels = array_slice(['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P'], 0, $groupCount);
        $positions = [];

        foreach ($groupLabels as $group) {
            foreach ($qualifiedRanks as $rank) {
                $positions[] = strtoupper($group) . $rank;
            }
        }

        return app(MatchGenerator::class)->buildBracketStructure($positions, $includeThirdPlace);
    }

    /**
     * Struktur bracket default untuk mode turnamen (gugur murni): slot diisi
     * langsung oleh nama tim yang lolos verifikasi, tanpa placeholder grup.
     */
    private function generateKnockoutBracketFromTeams(Tournament $tournament, bool $includeThirdPlace = false): array
    {
        $positions = $this->approvedTeamNames($tournament);

        if (count($positions) < 2) {
            return [];
        }

        return app(MatchGenerator::class)->buildBracketStructure($positions, $includeThirdPlace);
    }

    /**
     * Nama tim yang lolos verifikasi, diurutkan berdasarkan seed.
     */
    private function approvedTeamNames(Tournament $tournament): array
    {
        $tournament->load('tournamentTeams.team');

        return $tournament->tournamentTeams
            ->filter(fn ($team) => ($team->team?->verification_status ?? 'pending') === 'approved')
            ->sortBy(fn ($team) => $team->seed ?? 0)
            ->map(fn ($team) => $team->team?->name ?? ('Tim ' . $team->id))
            ->values()
            ->all();
    }

    private function isBracketStructureValid(array $matches): bool
    {
        foreach ($matches as $match) {
            if (! isset($match['left'], $match['right'], $match['round'])) {
                return false;
            }
        }

        return true;
    }

    public function resetBracketSettings(Tournament $tournament)
    {
        AppSetting::where('key', $this->bracketSettingsKey($tournament))->delete();

        return back()->with('success', 'Pengaturan Bagan Bracket berhasil direset ke default!');
    }

    private function bracketSettingsKey(Tournament $tournament)
    {
        return 'tournament_' . $tournament->id . '_bracket_settings';
    }

    /**
     * N10 — Resolusi opsi "Perebutan Peringkat 3" yang efektif. Pada Sistem
     * Turnamen (gugur murni, competition_type 'tournament') opsi ini dipaksa
     * nonaktif sehingga tidak pernah membuat/menyimpan babak Third Place.
     */
    private function effectiveThirdPlace(?string $competitionType, bool $requested): bool
    {
        if (($competitionType ?? 'tournament') === 'tournament') {
            return false;
        }

        return $requested;
    }

    public function resetPointSettings(Tournament $tournament)
    {
        AppSetting::where('key', $this->pointSettingsKey($tournament))->delete();

        return back()->with('success', 'Standar Liga Poin berhasil dikembalikan ke default!');
    }

    private function pointSettingsKey(Tournament $tournament)
    {
        return 'tournament_' . $tournament->id . '_score_system';
    }

    // Update pengaturan kelolosan grup
    public function updateSettings(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'competition_type' => 'required|in:tournament,league,league_playoff,group_knockout',
            'teams_per_group' => 'exclude_if:competition_type,tournament|required|integer|min:2|max:20',
            'group_count' => 'exclude_if:competition_type,tournament|required|integer|min:1|max:32',
            'playoff_type' => 'required_if:competition_type,league_playoff|in:promotion,relegation,both',
            'qualified_teams' => 'array',
            'qualified_teams.*' => 'integer|min:1',
            'relegated_teams' => 'array',
            'relegated_teams.*' => 'integer|min:1',
            'league_round_type' => 'nullable|in:single,double',
        ], [
            'teams_per_group.required' => 'Jumlah tim per grup tidak boleh kosong',
            'teams_per_group.integer' => 'Jumlah tim harus berupa angka',
            'teams_per_group.min' => 'Jumlah tim minimal 2',
            'teams_per_group.max' => 'Jumlah tim maksimal 20',
            'qualified_teams.*.integer' => 'Ranking harus berupa angka',
            'qualified_teams.*.min' => 'Ranking minimal 1',
            'relegated_teams.*.integer' => 'Ranking degradasi harus berupa angka',
            'relegated_teams.*.min' => 'Ranking degradasi minimal 1',
        ]);

        // Validasi khusus berdasarkan jenis kompetisi
        $competitionType = $validated['competition_type'];
        $playoffType = $validated['playoff_type'] ?? null;

        // Turnamen = babak gugur murni tanpa fase grup: pengaturan grup dan
        // ranking kelolosan tidak berlaku. Bracket dibuat otomatis dari tim
        // yang lolos verifikasi.
        if ($competitionType === 'tournament') {
            $validated['teams_per_group'] = 2;
            $validated['group_count'] = 1;
            $validated['qualified_teams'] = [];
            $validated['relegated_teams'] = [];
        }

        // Untuk league, relegated_teams harus diisi
        if ($competitionType === 'league') {
            $relegated = array_filter($validated['relegated_teams'] ?? []);
            if (empty($relegated)) {
                return back()->withErrors([
                    'relegated_teams' => 'Pilih minimal 1 ranking tim yang akan degradasi'
                ])->withInput();
            }
        }
        
        // Untuk league_playoff
        if ($competitionType === 'league_playoff') {
            if ($playoffType === 'promotion' || $playoffType === 'both') {
                $qualified = array_filter($validated['qualified_teams'] ?? []);
                if (empty($qualified)) {
                    return back()->withErrors([
                        'qualified_teams' => 'Pilih minimal 1 ranking tim yang akan promosi'
                    ])->withInput();
                }
            }
            
            if ($playoffType === 'relegation' || $playoffType === 'both') {
                $relegated = array_filter($validated['relegated_teams'] ?? []);
                if (empty($relegated)) {
                    return back()->withErrors([
                        'relegated_teams' => 'Pilih minimal 1 ranking tim yang akan degradasi'
                    ])->withInput();
                }
            }
        }

        // Grup → Gugur (Euro/UCL/Piala Dunia): butuh fase grup (min 2 grup) dan
        // ranking yang lolos ke babak gugur. Tidak memakai degradasi.
        if ($competitionType === 'group_knockout') {
            $qualified = array_filter($validated['qualified_teams'] ?? []);
            if (empty($qualified)) {
                return back()->withErrors([
                    'qualified_teams' => 'Pilih minimal 1 ranking tim yang lolos ke babak gugur'
                ])->withInput();
            }

            if (($validated['group_count'] ?? 1) < 2) {
                return back()->withErrors([
                    'group_count' => 'Sistem Grup → Gugur memerlukan minimal 2 grup'
                ])->withInput();
            }

            if ($validated['teams_per_group'] < 3) {
                return back()->withErrors([
                    'teams_per_group' => 'Sistem Grup → Gugur memerlukan minimal 3 tim per grup'
                ])->withInput();
            }

            $validated['relegated_teams'] = [];
        }

        // Validasi khusus: Jika sistem liga atau liga playoff dipilih
        if ($competitionType === 'league' || $competitionType === 'league_playoff') {
            // Minimum tim per grup untuk liga adalah 3
            if ($validated['teams_per_group'] < 3) {
                return back()->withErrors([
                    'teams_per_group' => 'Sistem Liga memerlukan minimal 3 tim per grup'
                ])->withInput();
            }

            // Ranking 1 tidak boleh dipilih untuk degradasi
            $relegated = array_filter($validated['relegated_teams'] ?? []);
            $restrictedRankings = array_intersect($relegated, [1]);
            if (!empty($restrictedRankings)) {
                return back()->withErrors([
                    'relegated_teams' => 'Ranking 1 tidak bisa dipilih untuk degradasi pada sistem liga'
                ])->withInput();
            }
        }

        // Sistem liga dan liga playoff selalu menggunakan 1 grup
        if ($validated['competition_type'] === 'league' || $validated['competition_type'] === 'league_playoff') {
            $validated['group_count'] = 1;
        }

        $qualified = [];
        $relegated = [];

        if ($validated['competition_type'] !== 'tournament') {
            // For league and league_playoff
            $qualified = array_values(array_unique(array_filter($validated['qualified_teams'] ?? [])));
            sort($qualified);
            $relegated = array_values(array_unique(array_filter($validated['relegated_teams'] ?? [])));
            rsort($relegated);
        }

        // Validasi: ranking tidak boleh melebihi jumlah tim per grup
        if ($validated['competition_type'] === 'tournament') {
            $selectedRanks = [];
        } elseif ($validated['competition_type'] === 'league_playoff') {
            $playoffType = $validated['playoff_type'] ?? 'promotion';
            if ($playoffType === 'both' || $playoffType === 'promotion') {
                $selectedRanks = array_merge($qualified, $relegated);
            } elseif ($playoffType === 'relegation') {
                $selectedRanks = $relegated;
            } else {
                $selectedRanks = [];
            }
        } else {
            $selectedRanks = $relegated;
        }
        
        $maxRank = !empty($selectedRanks) ? max($selectedRanks) : 0;
        if ($maxRank > $validated['teams_per_group']) {
            $field = $validated['competition_type'] === 'tournament' ? 'qualified_teams' : 'relegated_teams';
            return back()->withErrors([
                $field => "Ranking tidak boleh melebihi {$validated['teams_per_group']} (jumlah tim per grup)"
            ])->withInput();
        }

        if ($tournament->groupSetting && $tournament->groupSetting->locked) {
            return redirect()->route('tournaments.manage', $tournament)
                             ->with('warning', 'Pengaturan grup sudah dikunci. Tekan Reset untuk mengubahnya.');
        }

        // R11 — format liga: single (setengah) atau double (penuh/kandang-tandang).
        // Knockout murni tidak relevan → paksa single.
        $leagueRoundType = $competitionType === 'tournament'
            ? 'single'
            : ($validated['league_round_type'] ?? 'single');

        // Simpan atau update pengaturan grup
        TournamentGroupSetting::updateOrCreate(
            ['tournament_id' => $tournament->id],
            [
                'teams_per_group' => $validated['teams_per_group'],
                'group_count' => $validated['group_count'],
                'qualified_teams' => $qualified,
                'relegated_teams' => $relegated,
                'locked' => true,
                'league_round_type' => $leagueRoundType,
            ]
        );

        // Regenerasi pengaturan bracket otomatis sesuai perubahan grup
        $bracketSetting = AppSetting::where('key', $this->bracketSettingsKey($tournament))->first();
        $bracketValue = $bracketSetting->value ?? [];
        $matchType = $bracketValue['match_type'] ?? 'single';
        $thirdPlace = isset($bracketValue['third_place']) ? (bool) $bracketValue['third_place'] : false;
        $competitionType = $validated['competition_type'];
        
        // Build playoff_options for league_playoff
        $playoffOptions = [];
        if ($competitionType === 'league_playoff') {
            $playoffType = $validated['playoff_type'] ?? 'promotion';
            if ($playoffType === 'promotion' || $playoffType === 'both') {
                $playoffOptions[] = 'promotion';
            }
            if ($playoffType === 'relegation' || $playoffType === 'both') {
                $playoffOptions[] = 'relegation';
            }
        }

        $bracketRanks = $qualified;
        if ($competitionType === 'league_playoff') {
            if ($playoffType === 'relegation') {
                $bracketRanks = $relegated;
            } elseif ($playoffType === 'both') {
                $bracketRanks = array_values(array_unique(array_merge($qualified, $relegated)));
                sort($bracketRanks);
            }
        }

        // Generate matches for bracket settings
        if ($competitionType === 'tournament') {
            // Gugur murni: slot bracket diisi langsung dari tim terverifikasi
            // dan akan diperbarui otomatis oleh MatchGenerator saat peserta berubah.
            $bracketValueToSave = [
                'match_type' => $matchType,
                'third_place' => $thirdPlace,
                'competition_type' => 'tournament',
                'group_count' => 1,
                'matches' => $this->generateKnockoutBracketFromTeams($tournament, $thirdPlace),
            ];
        } elseif ($competitionType === 'group_knockout') {
            // Grup → Gugur: bracket berisi placeholder posisi grup (A1, B2, ...)
            // dengan seeding silang juara × runner-up antar grup.
            $bracketValueToSave = [
                'match_type' => $matchType,
                'third_place' => $thirdPlace,
                'competition_type' => 'group_knockout',
                'group_count' => $validated['group_count'],
                'matches' => app(MatchGenerator::class)->buildGroupKnockoutStructure(
                    $validated['group_count'],
                    $qualified,
                    $thirdPlace
                ),
            ];
        } elseif ($competitionType === 'league_playoff' && $playoffType === 'both') {
            // For both promotion and relegation, generate separate matches
            $bracketValueToSave = [
                'match_type' => $matchType,
                'third_place' => $thirdPlace,
                'competition_type' => $competitionType,
                'group_count' => $validated['group_count'],
                'playoff_options' => $playoffOptions,
                'matches' => [], // Dummy for compatibility
                'matches_promotion' => $this->generateDefaultBracketMatches(
                    $validated['group_count'],
                    $qualified,
                    $thirdPlace
                ),
                'matches_relegation' => $this->generateDefaultBracketMatches(
                    $validated['group_count'],
                    $relegated,
                    $thirdPlace
                ),
            ];
        } else {
            // For single mode or promotion/relegation only
            $bracketValueToSave = [
                'match_type' => $matchType,
                'third_place' => $thirdPlace,
                'competition_type' => $competitionType,
                'group_count' => $validated['group_count'],
                'matches' => $this->generateDefaultBracketMatches(
                    $validated['group_count'],
                    $bracketRanks,
                    $thirdPlace
                ),
            ];
            
            if (!empty($playoffOptions)) {
                $bracketValueToSave['playoff_options'] = $playoffOptions;
            }
        }


        AppSetting::updateOrCreate(
            ['key' => $this->bracketSettingsKey($tournament)],
            ['value' => $bracketValueToSave]
        );

        // Mode turnamen tidak memakai grup, jadi label grup tidak perlu diatur
        if ($competitionType !== 'tournament') {
            $this->assignGroupLabelsToTournamentTeams($tournament, $validated['group_count'], $validated['teams_per_group']);
        }

        app(MatchGenerator::class)->generateForTournament($tournament);

        $rankLabels = fn (array $ranks) => implode(', ', array_map(fn ($r) => "Ranking $r", $ranks));
        $successMessage = match ($competitionType) {
            'tournament' => 'Pengaturan disimpan! Sistem Turnamen (babak gugur) tidak memakai fase grup — bracket dibuat otomatis dari tim yang lolos verifikasi.',
            'league' => 'Pengaturan disimpan! Sistem Liga tanpa babak gugur. Tim degradasi: ' . ($relegated ? $rankLabels($relegated) : '-') . '.',
            'group_knockout' => 'Pengaturan disimpan! Sistem Grup → Gugur: fase grup digelar dulu, lalu '
                . ($qualified ? $rankLabels($qualified) . ' tiap grup lolos ke babak gugur. ' : 'tim yang lolos maju ke babak gugur. ')
                . 'Bracket terisi otomatis setelah seluruh pertandingan grup selesai.',
            default => 'Pengaturan Liga + Play Off disimpan!'
                . ($qualified ? ' Play off promosi/juara: ' . $rankLabels($qualified) . '.' : '')
                . ($relegated ? ' Play off degradasi: ' . $rankLabels($relegated) . '.' : ''),
        };

        return redirect()->route('tournaments.groupSettings', $tournament)
                         ->with('success', $successMessage);
    }

    // Reset/Delete pengaturan kelolosan grup
    public function resetSettings(Tournament $tournament)
    {
        $tournament->groupSetting()->delete();
        
        return back()->with('success', 'Pengaturan kelolosan grup berhasil direset ke default!');
    }

    // Halaman Kelola Jadwal & Skor (Admin) — menampilkan semua pertandingan dengan filter dinamis
    public function manageSchedule(Tournament $tournament, Request $request)
    {
        // Urutan jadwal mengikuti progresi bracket (bracket_match_id naik per
        // ronde: QF -> SF -> Final), bukan alfabet round_name.
        $matchesQuery = TournamentMatch::with(['events', 'homeTeam.team', 'homeTeam.players', 'awayTeam.team', 'awayTeam.players'])->where('tournament_id', $tournament->id)
            ->orderByRaw("FIELD(stage_type, 'group', 'league', 'knockout', 'promotion_playoff', 'relegation_playoff')")
            ->orderBy('group_label')
            ->orderBy('bracket_match_id')
            ->orderBy('leg')
            ->orderByRaw("CAST(REGEXP_REPLACE(round_name, '[^0-9]', '') AS UNSIGNED)")
            ->orderBy('round_name')
            ->orderBy('match_date')
            ->orderBy('id');

        $allMatches = $matchesQuery->get();

        // Build filter options from data
        $filterOptions = [
            'group_label' => $allMatches->pluck('group_label')->filter()->unique()->values()->all(),
            'round_name' => $allMatches->pluck('round_name')->filter()->unique()->values()->all(),
            'stage_type' => $allMatches->pluck('stage_type')->filter()->unique()->values()->all(),
            'playoff_type' => $allMatches->pluck('playoff_type')->filter()->unique()->values()->all(),
        ];

        $tieResolver = app(TieResolver::class);
        $calculationMode = $tieResolver->calculationMode($tournament);

        // Map matches to view-friendly structure
        $scheduleMatches = $allMatches->map(
            fn ($match) => $this->buildLoggerMatchPayload($match, $tieResolver, $calculationMode, $allMatches)
        );

        // Gabungkan pasangan leg jadi satu row tie (jadwal & logger per leg
        // tetap tersimpan di field 'legs').
        $scheduleMatches = $this->mergeTieRows($allMatches, $scheduleMatches);

        // Build tabs similar to schedule view
        $tabs = $this->buildScheduleTabs($this->resolveBracketSetting($tournament)->value['competition_type'] ?? 'tournament', $allMatches);

        // Apply filters from query
        $filters = [
            'group_label' => $request->query('group_label'),
            'round_name' => $request->query('round_name'),
            'stage_type' => $request->query('stage_type'),
            'playoff_type' => $request->query('playoff_type'),
            'tab' => $request->query('tab', 'all'),
        ];

        $filtered = array_values(array_filter($scheduleMatches, function ($m) use ($filters) {
            if ($filters['group_label'] && ($m['group_label'] ?? '') !== $filters['group_label']) return false;
            if ($filters['round_name'] && ($m['round_name'] ?? '') !== $filters['round_name']) return false;
            if ($filters['stage_type'] && ($m['stage_type'] ?? '') !== $filters['stage_type']) return false;
            if ($filters['playoff_type'] && ($m['playoff_type'] ?? '') !== $filters['playoff_type']) return false;

            // tab filtering
            if ($filters['tab'] && $filters['tab'] !== 'all') {
                $tab = $filters['tab'];
                $matchesForTab = $this->filterScheduleMatches([$m], $tab);
                return count($matchesForTab) > 0;
            }

            return true;
        }));

        return view('admin.tournaments.schedule.manage', compact('tournament', 'filtered', 'tabs', 'filters', 'filterOptions'));
    }

    public function updateMatch(Request $request, Tournament $tournament, TournamentMatch $match)
    {
        if ($match->tournament_id !== $tournament->id) {
            abort(404);
        }

        $validated = $request->validate([
            'match_date' => 'required|date',
            'match_time' => 'required|date_format:H:i',
            'match_status' => 'required|in:scheduled,live_match,full_time',
            'home_score' => 'required_if:match_status,full_time|nullable|integer|min:0',
            'away_score' => 'required_if:match_status,full_time|nullable|integer|min:0',
        ], [
            'match_date.required' => 'Tanggal pertandingan wajib diisi.',
            'match_time.required' => 'Waktu pertandingan wajib diisi.',
            'match_status.required' => 'Status laga wajib dipilih.',
            'match_status.in' => 'Status laga tidak valid.',
            'home_score.required_if' => 'Skor Home wajib diisi ketika pertandingan Full Time.',
            'away_score.required_if' => 'Skor Away wajib diisi ketika pertandingan Full Time.',
            'home_score.integer' => 'Skor Home harus berupa angka.',
            'away_score.integer' => 'Skor Away harus berupa angka.',
            'home_score.min' => 'Skor Home tidak boleh negatif.',
            'away_score.min' => 'Skor Away tidak boleh negatif.',
        ]);

        $matchReady = $match->stage_type === 'group' || (! is_null($match->home_team_id) && ! is_null($match->away_team_id));

        if (! $matchReady && in_array($validated['match_status'], ['live_match', 'full_time'], true)) {
            return back()->withErrors(['match_status' => 'Pertandingan belum siap dimainkan karena peserta belum lengkap.']);
        }

        if (! $matchReady && (array_key_exists('home_score', $validated) || array_key_exists('away_score', $validated))) {
            return back()->withErrors(['home_score' => 'Skor tidak bisa diinput sebelum peserta pertandingan lengkap.', 'away_score' => 'Skor tidak bisa diinput sebelum peserta pertandingan lengkap.']);
        }

        if ($match->status === 'full_time' && $validated['match_status'] !== 'full_time') {
            return back()->withErrors(['match_status' => 'Pertandingan yang sudah Full Time tidak dapat dipindahkan kembali ke Live Match melalui menu normal.']);
        }

        if ($match->status === 'penalty_shootout') {
            return back()->withErrors(['match_status' => 'Pertandingan sedang dalam fase adu penalti. Selesaikan melalui Live Match Event Logger.']);
        }

        if ($match->leg === 2 && in_array($validated['match_status'], ['live_match', 'full_time'], true)) {
            $leg1 = app(TieResolver::class)->siblingLeg($match);

            if (! $leg1 || $leg1->status !== 'full_time') {
                return back()->withErrors(['match_status' => 'Leg 2 tidak dapat dimulai sebelum Leg 1 selesai (Full Time).']);
            }
        }

        $status = $validated['match_status'];
        if ($match->stage_type === 'group'
            && array_key_exists('home_score', $validated)
            && array_key_exists('away_score', $validated)
            && $validated['home_score'] !== null
            && $validated['away_score'] !== null
        ) {
            $status = 'full_time';
        }

        // Match penentu babak gugur tidak boleh ditutup seri lewat edit
        // manual — adu penalti hanya tersedia di Live Match Event Logger.
        if ($status === 'full_time') {
            $tieResolver = app(TieResolver::class);

            if ($tieResolver->isDecidingMatch($match)) {
                $originalHome = $match->home_score;
                $originalAway = $match->away_score;

                $match->home_score = array_key_exists('home_score', $validated) && $validated['home_score'] !== null
                    ? (int) $validated['home_score']
                    : $match->home_score;
                $match->away_score = array_key_exists('away_score', $validated) && $validated['away_score'] !== null
                    ? (int) $validated['away_score']
                    : $match->away_score;

                $outcome = $tieResolver->tieOutcome($match, $tieResolver->calculationMode($tournament));

                $match->home_score = $originalHome;
                $match->away_score = $originalAway;

                if ($outcome['is_level'] && ! $outcome['pen_decides']) {
                    return back()->withErrors(['match_status' => 'Hasil seri pada babak gugur harus diselesaikan melalui adu penalti di Live Match Event Logger.']);
                }
            }
        }

        $match->update([
            'match_date' => Carbon::createFromFormat('Y-m-d H:i', sprintf('%s %s', $validated['match_date'], $validated['match_time'])),
            'status' => $status,
            'home_score' => array_key_exists('home_score', $validated) ? $validated['home_score'] : $match->home_score,
            'away_score' => array_key_exists('away_score', $validated) ? $validated['away_score'] : $match->away_score,
        ]);

        if ($status === 'full_time') {
            $match->refresh();
            $this->finalizeMatchResult($match);
        }

        return back()->with('success', 'Perubahan jadwal dan status pertandingan berhasil disimpan.');
    }

    /**
     * N5 — Tombol "Edit" khusus untuk mengisi/mengubah SKOR saja.
     * Tidak menyentuh tanggal/waktu pertandingan (itu lewat "Jadwal" / N6).
     * Mengisi skor pada match group/knockout akan menutup laga sebagai Full Time.
     */
    public function updateScore(Request $request, Tournament $tournament, TournamentMatch $match)
    {
        if ($match->tournament_id !== $tournament->id) {
            abort(404);
        }

        $validated = $request->validate([
            'home_score' => 'required|integer|min:0',
            'away_score' => 'required|integer|min:0',
        ], [
            'home_score.required' => 'Skor Home wajib diisi.',
            'away_score.required' => 'Skor Away wajib diisi.',
            'home_score.integer' => 'Skor Home harus berupa angka.',
            'away_score.integer' => 'Skor Away harus berupa angka.',
            'home_score.min' => 'Skor Home tidak boleh negatif.',
            'away_score.min' => 'Skor Away tidak boleh negatif.',
        ]);

        $matchReady = $match->stage_type === 'group' || (! is_null($match->home_team_id) && ! is_null($match->away_team_id));
        if (! $matchReady) {
            return back()->withErrors(['home_score' => 'Skor tidak bisa diinput sebelum peserta pertandingan lengkap.']);
        }

        if ($match->status === 'penalty_shootout') {
            return back()->withErrors(['home_score' => 'Pertandingan sedang dalam fase adu penalti. Selesaikan melalui Live Match Event Logger.']);
        }

        // Leg 2 hanya bisa diisi setelah Leg 1 Full Time.
        if ($match->leg === 2) {
            $leg1 = app(TieResolver::class)->siblingLeg($match);
            if (! $leg1 || $leg1->status !== 'full_time') {
                return back()->withErrors(['home_score' => 'Leg 2 tidak dapat diisi sebelum Leg 1 selesai (Full Time).']);
            }
        }

        // Match penentu babak gugur tidak boleh ditutup seri lewat edit manual —
        // adu penalti hanya tersedia di Live Match Event Logger.
        $tieResolver = app(TieResolver::class);
        if ($tieResolver->isDecidingMatch($match)) {
            $originalHome = $match->home_score;
            $originalAway = $match->away_score;

            $match->home_score = (int) $validated['home_score'];
            $match->away_score = (int) $validated['away_score'];
            $outcome = $tieResolver->tieOutcome($match, $tieResolver->calculationMode($tournament));

            $match->home_score = $originalHome;
            $match->away_score = $originalAway;

            if ($outcome['is_level'] && ! $outcome['pen_decides']) {
                return back()->withErrors(['home_score' => 'Hasil seri pada babak gugur harus diselesaikan melalui adu penalti di Live Match Event Logger.']);
            }
        }

        $match->update([
            'home_score' => (int) $validated['home_score'],
            'away_score' => (int) $validated['away_score'],
            'status' => 'full_time',
        ]);

        $match->refresh();
        $this->finalizeMatchResult($match);

        return back()->with('success', 'Skor pertandingan berhasil disimpan.');
    }

    /**
     * N6 — Tombol "Jadwal" khusus untuk mengatur tanggal/waktu (dan status laga).
     * Tidak menyentuh skor. Mengisi jadwal mengaktifkan tombol Live Match Logger.
     */
    public function updateSchedule(Request $request, Tournament $tournament, TournamentMatch $match)
    {
        if ($match->tournament_id !== $tournament->id) {
            abort(404);
        }

        $validated = $request->validate([
            'match_date' => 'required|date',
            'match_time' => 'required|date_format:H:i',
            'match_status' => 'required|in:scheduled,live_match',
        ], [
            'match_date.required' => 'Tanggal pertandingan wajib diisi.',
            'match_time.required' => 'Waktu pertandingan wajib diisi.',
            'match_status.required' => 'Status laga wajib dipilih.',
            'match_status.in' => 'Status laga tidak valid (skor & Full Time diatur lewat Edit Skor / Live Logger).',
        ]);

        if (in_array($match->status, ['full_time', 'penalty_shootout'], true)) {
            return back()->withErrors(['match_status' => 'Pertandingan yang sudah selesai / adu penalti tidak dapat dijadwal ulang dari sini.']);
        }

        $matchReady = $match->stage_type === 'group' || (! is_null($match->home_team_id) && ! is_null($match->away_team_id));
        if (! $matchReady && $validated['match_status'] === 'live_match') {
            return back()->withErrors(['match_status' => 'Pertandingan belum siap dimainkan karena peserta belum lengkap.']);
        }

        if ($match->leg === 2 && $validated['match_status'] === 'live_match') {
            $leg1 = app(TieResolver::class)->siblingLeg($match);
            if (! $leg1 || $leg1->status !== 'full_time') {
                return back()->withErrors(['match_status' => 'Leg 2 tidak dapat dimulai sebelum Leg 1 selesai (Full Time).']);
            }
        }

        $match->update([
            'match_date' => Carbon::createFromFormat('Y-m-d H:i', sprintf('%s %s', $validated['match_date'], $validated['match_time'])),
            'status' => $validated['match_status'],
        ]);

        return back()->with('success', 'Jadwal pertandingan berhasil disimpan.');
    }

    public function openLiveMatchLogger(Tournament $tournament, TournamentMatch $match)
    {
        if ($match->tournament_id !== $tournament->id) {
            abort(404);
        }

        if ($match->stage_type !== 'group' && (is_null($match->home_team_id) || is_null($match->away_team_id))) {
            return back()->withErrors(['live_match' => 'Pertandingan belum siap dimainkan karena peserta belum lengkap.']);
        }

        // N6 — Live Match Logger tidak boleh aktif sebelum jadwal (tanggal/waktu)
        // pertandingan diisi melalui tombol "Jadwal".
        if (is_null($match->match_date)) {
            return back()->withErrors(['live_match' => 'Isi jadwal (tanggal & waktu) pertandingan terlebih dahulu melalui tombol "Jadwal" sebelum memulai Live Match.']);
        }

        if ($match->leg === 2) {
            $leg1 = app(TieResolver::class)->siblingLeg($match);

            if (! $leg1 || $leg1->status !== 'full_time') {
                return back()->withErrors(['live_match' => 'Leg 2 belum dapat dimulai. Selesaikan Leg 1 terlebih dahulu.']);
            }
        }

        if ($match->status === 'scheduled') {
            $match->update(['status' => 'live_match']);
        }

        return back()->with([
            'success' => 'Live Match Event Logger terbuka untuk pertandingan ini.',
            'open_live_match' => $match->id,
        ]);
    }

    public function storeMatchEvent(Request $request, Tournament $tournament, TournamentMatch $match)
    {
        // RACE CONDITION FIX: Lock per match_id to prevent concurrent execution
        return Cache::lock("match_{$match->id}_lock", 10)->block(5, function () use ($request, $tournament, $match) {
            if ($match->tournament_id !== $tournament->id) {
                abort(404);
            }

            // Tombol event logger mengirim via fetch (Accept: application/json)
            // agar modal tidak perlu reload halaman.
            $fail = function (string $message) use ($request) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }

                return back()->withErrors(['event_logger' => $message]);
            };

            if ($match->stage_type !== 'group' && (is_null($match->home_team_id) || is_null($match->away_team_id))) {
                return $fail('Pertandingan belum siap dimainkan.');
            }

            if ($match->status === 'full_time') {
                return $fail('Pertandingan sudah selesai. Event logger hanya dapat dibuka dalam mode read-only.');
            }

            if ($match->leg === 2) {
                $legOne = app(TieResolver::class)->siblingLeg($match);

                if (! $legOne || $legOne->status !== 'full_time') {
                    return $fail('Leg 2 belum dapat dimulai. Selesaikan Leg 1 terlebih dahulu.');
                }
            }

            $validated = $request->validate([
                'event_type' => 'required|in:goal,own_goal,assist,yellow_card,red_card,foul,timeout,halftime,full_time,penalty_goal,penalty_miss',
                'team_side' => 'nullable|in:home,away',
                'player_name' => 'nullable|string|max:120',
                // R19 — id pemain asli (opsional; event tanpa roster tetap valid).
                'player_id' => 'nullable|integer|exists:tournament_team_players,id',
                'description' => 'nullable|string|max:255',
                'minute' => 'nullable|integer|min:0|max:120',
            ]);

            $isPenaltyEvent = in_array($validated['event_type'], ['penalty_goal', 'penalty_miss'], true);

            if ($isPenaltyEvent && $match->status !== 'penalty_shootout') {
                return $fail('Event penalti hanya bisa dicatat saat fase adu penalti.');
            }

            if ($match->status === 'penalty_shootout' && ! $isPenaltyEvent) {
                return $fail('Pertandingan dalam fase adu penalti. Hanya event penalti yang dapat dicatat.');
            }

            if (in_array($validated['event_type'], ['goal', 'own_goal', 'assist', 'yellow_card', 'red_card', 'foul', 'penalty_goal', 'penalty_miss'], true) && empty($validated['team_side'])) {
                return $fail('Pilih tim untuk event ini.');
            }

            // R19 — pastikan player_id benar-benar milik salah satu tim match
            // ini (cegah event tersambung ke pemain dari match/turnamen lain).
            if (! empty($validated['player_id'])) {
                $player = \App\Models\TournamentTeamPlayer::find($validated['player_id']);
                $validTeamIds = array_filter([$match->home_team_id, $match->away_team_id]);

                if (! $player || ! in_array($player->tournament_team_id, $validTeamIds, true)) {
                    return $fail('Pemain tidak terdaftar pada salah satu tim pertandingan ini.');
                }
            }

            // Pemain yang sudah menerima kartu merah pada match ini nonaktif:
            // tidak bisa dicatat event apa pun lagi (termasuk penalti).
            if (! empty($validated['player_name']) && ! empty($validated['team_side'])) {
                $hasRedCard = MatchEvent::where('match_id', $match->id)
                    ->where('event_type', 'red_card')
                    ->where('team_side', $validated['team_side'])
                    ->where('player_name', $validated['player_name'])
                    ->exists();

                if ($hasRedCard) {
                    return $fail('Pemain ini sudah menerima kartu merah dan tidak dapat dicatat event lagi.');
                }
            }

            if ($validated['event_type'] === 'full_time' && ($match->home_score === null || $match->away_score === null)) {
                Log::info('Attempt to finalize via event but scores missing', [
                    'match_id' => $match->id,
                    'home_score' => $match->home_score,
                    'away_score' => $match->away_score,
                    'status' => $match->status,
                    'event_payload' => $validated,
                ]);

                return $fail('Pertandingan tidak bisa diselesaikan tanpa kedua skor terisi.');
            }

            $conclusion = null;

            DB::transaction(function () use ($match, $validated, &$conclusion) {
                if (in_array($validated['event_type'], ['goal', 'own_goal'], true)) {
                    if ($match->home_score === null) {
                        $match->home_score = 0;
                    }

                    if ($match->away_score === null) {
                        $match->away_score = 0;
                    }
                }

                if (in_array($validated['event_type'], ['penalty_goal', 'penalty_miss'], true)) {
                    if ($match->home_penalty_score === null) {
                        $match->home_penalty_score = 0;
                    }

                    if ($match->away_penalty_score === null) {
                        $match->away_penalty_score = 0;
                    }
                }

                if ($validated['event_type'] === 'goal') {
                    if ($validated['team_side'] === 'home') {
                        $match->home_score += 1;
                    } else {
                        $match->away_score += 1;
                    }
                }

                if ($validated['event_type'] === 'own_goal') {
                    if ($validated['team_side'] === 'home') {
                        $match->away_score += 1;
                    } else {
                        $match->home_score += 1;
                    }
                }

                if ($validated['event_type'] === 'penalty_goal') {
                    if ($validated['team_side'] === 'home') {
                        $match->home_penalty_score += 1;
                    } else {
                        $match->away_penalty_score += 1;
                    }
                }

                if ($validated['event_type'] === 'full_time') {
                    if ($match->home_score === null || $match->away_score === null) {
                        Log::warning('Runtime finalize attempted but scores missing', [
                            'match_id' => $match->id,
                            'home_score' => $match->home_score,
                            'away_score' => $match->away_score,
                            'status' => $match->status,
                            'event_payload' => $validated,
                        ]);

                        throw new \RuntimeException('Tidak dapat menyelesaikan pertandingan tanpa kedua skor terisi.');
                    }

                    // Jalur penutupan yang sama dengan tombol End Match agar
                    // match penentu babak gugur yang seri tidak bisa lolos
                    // tanpa adu penalti.
                    $conclusion = $this->concludeMatch($match);

                    if ($conclusion !== 'full_time') {
                        // Pertandingan belum benar-benar berakhir; jangan
                        // catat event full_time.
                        return;
                    }
                } else {
                    $match->save();
                }

                MatchEvent::create([
                    'match_id' => $match->id,
                    'event_type' => $validated['event_type'],
                    'team_side' => $validated['team_side'],
                    'player_name' => $validated['player_name'] ?? null,
                    'player_id' => $validated['player_id'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'minute' => $validated['minute'] ?? 0,
                ]);
            });

            if ($request->expectsJson()) {
                $match->refresh();
                $match->load(['events', 'homeTeam.team', 'homeTeam.players', 'awayTeam.team', 'awayTeam.players']);
                $tieResolver = app(TieResolver::class);

                return response()->json([
                    'message' => $conclusion === 'penalty_shootout'
                        ? 'Hasil imbang — adu penalti dimulai.'
                        : 'Event pertandingan disimpan.',
                    'match' => $this->buildLoggerMatchPayload(
                        $match,
                        $tieResolver,
                        $tieResolver->calculationMode($tournament)
                    ),
                ]);
            }

            if ($conclusion === 'penalty_shootout') {
                return back()->with([
                    'success' => 'Hasil imbang — adu penalti dimulai. Tekan tombol penalti pada pemain penendang.',
                    'open_live_match' => $match->id,
                ]);
            }

            if ($conclusion === 'full_time') {
                $legTwo = $this->openSecondLegAfter($match);

                if ($legTwo) {
                    return back()->with([
                        'success' => 'Leg 1 selesai. Papan skor beralih ke Leg 2 — posisi home/away ditukar.',
                        'open_live_match' => $legTwo->id,
                    ]);
                }
            }

            return back()->with([
                'success' => 'Event pertandingan disimpan.',
                'open_live_match' => $match->id,
            ]);
        });
    }

    public function endMatch(Tournament $tournament, TournamentMatch $match)
    {
        // RACE CONDITION FIX: Lock per match_id to prevent concurrent execution
        return Cache::lock("match_{$match->id}_lock", 10)->block(5, function () use ($tournament, $match) {
            if ($match->tournament_id !== $tournament->id) {
                abort(404);
            }

            if ($match->status === 'full_time') {
                return back()->with('success', 'Pertandingan sudah ditandai sebagai Full Time.');
            }

            if ($match->status === 'scheduled') {
                return back()->withErrors(['end_match' => 'Pertandingan belum dimulai.']);
            }

            if ($match->stage_type !== 'group' && ($match->home_team_id === null || $match->away_team_id === null)) {
                return back()->withErrors(['end_match' => 'Pertandingan belum siap dimainkan karena peserta belum lengkap.']);
            }

            // RACE CONDITION FIX: Refresh and treat NULL scores as 0 (0-0 is valid match)
            $match->refresh();

            $conclusion = null;

            DB::transaction(function () use ($match, &$conclusion) {
                $conclusion = $this->concludeMatch($match);
            });

            if ($conclusion === 'penalty_level') {
                return back()
                    ->with('open_live_match', $match->id)
                    ->withErrors(['end_match' => 'Skor adu penalti masih seri. Lanjutkan tendangan penalti.']);
            }

            if ($conclusion === 'penalty_shootout') {
                return back()->with([
                    'success' => 'Hasil imbang — adu penalti dimulai. Tekan tombol penalti pada pemain penendang.',
                    'open_live_match' => $match->id,
                ]);
            }

            $legTwo = $this->openSecondLegAfter($match);

            if ($legTwo) {
                return back()->with([
                    'success' => 'Leg 1 selesai. Papan skor beralih ke Leg 2 — posisi home/away ditukar.',
                    'open_live_match' => $legTwo->id,
                ]);
            }

            return back()->with([
                'success' => 'Pertandingan berhasil diselesaikan dan di-finalisasi.',
                'open_live_match' => $match->id,
            ]);
        });
    }

    /**
     * Setelah Leg 1 ditutup, alihkan logger ke Leg 2 pada tie yang sama:
     * status Leg 2 dinaikkan ke live_match dan modal dibuka untuknya
     * (home/away sudah dibalik sejak generasi jadwal).
     */
    private function openSecondLegAfter(TournamentMatch $legOne): ?TournamentMatch
    {
        if ($legOne->leg !== 1) {
            return null;
        }

        $legTwo = app(TieResolver::class)->siblingLeg($legOne);

        if (! $legTwo || ! in_array($legTwo->status, ['scheduled', 'live_match'], true)) {
            return null;
        }

        if ($legTwo->status === 'scheduled') {
            $legTwo->update(['status' => 'live_match']);
        }

        return $legTwo;
    }

    /**
     * Tutup pertandingan dari status live_match/penalty_shootout.
     *
     * Match penentu babak gugur (leg 2, atau single leg termasuk Final) yang
     * berakhir seri menurut mode kalkulasi tidak langsung full_time, melainkan
     * masuk fase adu penalti. Return:
     * - 'full_time'        => pertandingan selesai dan difinalisasi
     * - 'penalty_shootout' => fase adu penalti dimulai
     * - 'penalty_level'    => skor penalti masih seri, belum bisa ditutup
     */
    private function concludeMatch(TournamentMatch $match): string
    {
        $tieResolver = app(TieResolver::class);

        if ($match->status === 'penalty_shootout') {
            if (($match->home_penalty_score ?? 0) === ($match->away_penalty_score ?? 0)) {
                return 'penalty_level';
            }

            $match->status = 'full_time';
            $match->save();
            $this->finalizeMatchResult($match);

            return 'full_time';
        }

        // NULL diperlakukan sebagai 0 (0-0 adalah hasil yang valid)
        $match->home_score = $match->home_score ?? 0;
        $match->away_score = $match->away_score ?? 0;

        if ($tieResolver->needsPenaltyShootout($match, $tieResolver->calculationMode($match->tournament))) {
            $match->status = 'penalty_shootout';
            $match->save();

            return 'penalty_shootout';
        }

        $match->status = 'full_time';
        $match->save();
        $this->finalizeMatchResult($match);

        return 'full_time';
    }

    private function finalizeMatchResult(TournamentMatch $match)
    {
        $this->updateStandingsForTournament($match->tournament);
        $this->updateBracketForTournament($match);
        $this->updatePlayoffIfNeeded($match->tournament);
    }

    private function updateStandingsForTournament(Tournament $tournament): void
    {
        // Bracket diisi progresif per grup (gaya Piala Dunia): begitu seluruh
        // laga sebuah grup selesai, juara & runner-up grup itu langsung mengisi
        // slot bracket walau grup lain belum selesai. fillBracketFromFinalStandings
        // hanya memetakan grup yang sudah selesai sehingga aman dipanggil kapan pun.
        $groups = $this->buildStandingsGroups($tournament);

        app(\App\Services\MatchGenerator::class)->generateBracketStructureForTournament($tournament);
        $this->fillBracketFromFinalStandings($tournament, $groups);
    }

    private function updateBracketForTournament(TournamentMatch $match): void
    {
        if (! $match->next_bracket_match_id && ! $match->next_match_id) {
            return;
        }

        $tieResolver = app(TieResolver::class);

        // Hanya match penentu tie (leg 2 atau single leg) yang meneruskan
        // pemenang ke babak berikutnya; leg 1 tidak pernah menentukan.
        if (! $tieResolver->isDecidingMatch($match)) {
            return;
        }

        $outcome = $tieResolver->tieOutcome($match, $tieResolver->calculationMode($match->tournament));

        if (! $outcome['both_played']) {
            return;
        }

        $winner = $tieResolver->winnerDescriptor($match, $outcome);

        if ($winner === null) {
            return;
        }

        // Ambil SEMUA row tie berikutnya: 1 row (single/Final) atau 2 row
        // (tie dua leg) — pemenang harus mengisi kedua leg sekaligus, dengan
        // posisi home/away yang sudah dibalik antar leg sejak generasi.
        $nextRows = collect();

        if ($match->next_bracket_match_id !== null) {
            $nextRows = TournamentMatch::where('tournament_id', $match->tournament_id)
                ->where('stage_type', $match->stage_type)
                ->where('bracket_match_id', $match->next_bracket_match_id)
                ->get();
        }

        if ($nextRows->isEmpty() && $match->next_match_id) {
            $nextRows = TournamentMatch::where('id', $match->next_match_id)->get();
        }

        $label = 'Pemenang M' . $match->bracket_match_id;

        foreach ($nextRows as $row) {
            $assigned = false;

            // source_home/source_away menyimpan label "Pemenang M{id}" dari
            // struktur bracket dan sengaja tidak ditimpa: label ini yang
            // membuat penempatan idempoten saat hasil dikoreksi/difinalisasi
            // ulang.
            if ($row->source_home === $label) {
                $row->home_team_id = $winner['team_id'];
                $row->home_team_key = $winner['name'];
                $assigned = true;
            }

            if ($row->source_away === $label) {
                $row->away_team_id = $winner['team_id'];
                $row->away_team_key = $winner['name'];
                $assigned = true;
            }

            // Fallback bracket custom (label tidak dikenal): slot kosong
            // pertama, perilaku lama.
            if (! $assigned) {
                if (is_null($row->home_team_id)) {
                    $row->home_team_id = $winner['team_id'];
                    $row->home_team_key = $winner['name'];
                    $row->source_home = $winner['name'];
                    $assigned = true;
                } elseif (is_null($row->away_team_id)) {
                    $row->away_team_id = $winner['team_id'];
                    $row->away_team_key = $winner['name'];
                    $row->source_away = $winner['name'];
                    $assigned = true;
                }
            }

            if ($assigned) {
                $row->save();
            }
        }
    }

    private function isGroupStageComplete(Tournament $tournament): bool
    {
        // R5 — fase reguler bisa berstatus stage_type 'group' (multi-grup) atau
        // 'league' (league_playoff 1 grup). Keduanya dianggap "fase grup" yang
        // harus selesai sebelum tim playoff promosi/degradasi bisa diisi otomatis.
        $regularStages = ['group', 'league'];

        $groupMatchCount = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereIn('stage_type', $regularStages)
            ->count();

        if ($groupMatchCount === 0) {
            return false;
        }

        $completedCount = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereIn('stage_type', $regularStages)
            ->where('status', 'full_time')
            ->count();

        return $completedCount >= $groupMatchCount;
    }

    /**
     * Daftar label grup yang SELURUH pertandingannya sudah full_time. Dipakai
     * agar bracket terisi progresif per grup (gaya Piala Dunia): begitu sebuah
     * grup selesai, juara & runner-up grup itu langsung mengisi slot bracket
     * tanpa menunggu grup lain. Fase reguler bisa berstatus 'group' (multi-grup
     * dengan label A/B/...) atau 'league' (satu grup, label bisa null → 'A').
     */
    private function completedGroupLabels(Tournament $tournament): array
    {
        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereIn('stage_type', ['group', 'league'])
            ->get(['group_label', 'status']);

        if ($matches->isEmpty()) {
            return [];
        }

        // Kelompokkan per label (laga 'league' tanpa label → 'A', menyamai
        // keying buildStandingsGroups untuk kompetisi satu grup).
        $byLabel = $matches->groupBy(fn ($m) => $m->group_label ?: 'A');

        return $byLabel
            ->filter(fn ($groupMatches) => $groupMatches->every(fn ($m) => $m->status === 'full_time'))
            ->keys()
            ->map(fn ($label) => (string) $label)
            ->all();
    }

    private function fillBracketFromFinalStandings(Tournament $tournament, array $groups): void
    {
        $bracketSetting = $this->resolveBracketSetting($tournament);
        $competitionType = $bracketSetting->value['competition_type'] ?? 'tournament';

        if ($competitionType === 'league') {
            return;
        }

        $playoffOptions = $bracketSetting->value['playoff_options'] ?? [];
        $stageTypes = ['knockout'];

        if ($competitionType === 'league_playoff') {
            $stageTypes = [];
            if (in_array('promotion', $playoffOptions, true)) {
                $stageTypes[] = 'promotion_playoff';
            }
            if (in_array('relegation', $playoffOptions, true)) {
                $stageTypes[] = 'relegation_playoff';
            }
        }

        if (empty($stageTypes)) {
            return;
        }

        $qualifiedRanks = $tournament->groupSetting->qualified_teams ?? [1, 2];
        $relegatedRanks = $tournament->groupSetting->relegated_teams ?? [];
        $placeholderMap = [];

        // Hanya petakan grup yang SELURUH lacanya sudah full_time. Dengan begitu
        // bracket terisi progresif per grup (gaya Piala Dunia) tanpa menunggu
        // seluruh fase grup beres, dan tanpa salah tebak peringkat saat grup
        // masih berjalan.
        $completedGroups = $this->completedGroupLabels($tournament);

        foreach ($groups as $groupLabel => $rows) {
            if (! in_array((string) $groupLabel, $completedGroups, true)) {
                continue;
            }

            foreach ($qualifiedRanks as $rank) {
                $teamRow = collect($rows)->first(fn ($row) => $row['ranking'] == $rank);
                if ($teamRow) {
                    $placeholderMap[strtoupper($groupLabel) . $rank] = $teamRow;
                }
            }

            foreach ($relegatedRanks as $rank) {
                $teamRow = collect($rows)->first(fn ($row) => $row['ranking'] == $rank);
                if ($teamRow) {
                    $placeholderMap[strtoupper($groupLabel) . $rank] = $teamRow;
                }
            }
        }

        if (empty($placeholderMap)) {
            return;
        }

        $bracketMatches = TournamentMatch::with(['homeTeam.team', 'awayTeam.team'])
            ->where('tournament_id', $tournament->id)
            ->whereIn('stage_type', $stageTypes)
            ->get();

        foreach ($bracketMatches as $match) {
            $updated = false;

            if ($match->home_team_key && isset($placeholderMap[$match->home_team_key])) {
                $teamRow = $placeholderMap[$match->home_team_key];
                $resolvedName = $teamRow['name'];

                if ($match->home_team_id !== $teamRow['team_id'] || $match->home_team_key !== $resolvedName || $match->source_home !== $resolvedName) {
                    $match->home_team_id = $teamRow['team_id'];
                    $match->home_team_key = $resolvedName;
                    $match->source_home = $resolvedName;
                    $updated = true;
                }
            }

            if ($match->away_team_key && isset($placeholderMap[$match->away_team_key])) {
                $teamRow = $placeholderMap[$match->away_team_key];
                $resolvedName = $teamRow['name'];

                if ($match->away_team_id !== $teamRow['team_id'] || $match->away_team_key !== $resolvedName || $match->source_away !== $resolvedName) {
                    $match->away_team_id = $teamRow['team_id'];
                    $match->away_team_key = $resolvedName;
                    $match->source_away = $resolvedName;
                    $updated = true;
                }
            }

            if ($match->home_team_id && $match->homeTeam?->team?->name) {
                $resolvedName = $match->homeTeam->team->name;
                if ($match->home_team_key !== $resolvedName || $match->source_home !== $resolvedName) {
                    $match->home_team_key = $resolvedName;
                    $match->source_home = $resolvedName;
                    $updated = true;
                }
            }

            if ($match->away_team_id && $match->awayTeam?->team?->name) {
                $resolvedName = $match->awayTeam->team->name;
                if ($match->away_team_key !== $resolvedName || $match->source_away !== $resolvedName) {
                    $match->away_team_key = $resolvedName;
                    $match->source_away = $resolvedName;
                    $updated = true;
                }
            }

            if ($updated) {
                $match->save();
            }
        }
    }

    /**
     * Ringkasan skor tiap kartu bracket (key = bracket_match_id) untuk
     * ditampilkan di bagan gugur. Menangani single leg, Home & Away (2 leg),
     * dan adu penalti. Memakai TieResolver agar agregat/penalti konsisten dengan
     * penentuan pemenang.
     *
     * Tiap entri: [
     *   'played' => bool,                 // tie sudah dimainkan tuntas
     *   'two_leg' => bool,
     *   'home' => ['score' => int|null, 'pen' => int|null],
     *   'away' => ['score' => int|null, 'pen' => int|null],   // single/aggregate
     *   'legs' => [ ['home'=>int|null,'away'=>int|null], ... ],// urut leg 1..2
     *   'winner_side' => 'home'|'away'|null,
     * ]
     */
    private function buildBracketScoreSummaries(Tournament $tournament, ?string $playoffMode = null): array
    {
        // N4 — delegasikan ke BracketViewService (sumber tunggal, juga dipakai
        // portal Official read-only).
        return app(\App\Services\BracketViewService::class)->scoreSummaries($tournament, $playoffMode);
    }

    private function updatePlayoffIfNeeded(Tournament $tournament): void
    {
        // Placeholder: jika ada logika playoff khusus, jalankan update yang sesuai.
        // Di versi ini, hanya memastikan bahwa finalisasi match tidak meninggalkan transaksi terbuka.
    }

    // Get pengaturan dalam format JSON (untuk AJAX)
    public function getSettings(Tournament $tournament)
    {
        $setting = $tournament->groupSetting;
        
        if (!$setting) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengaturan belum ada'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $setting
        ]);
    }

    // Tampilkan bagan klasemen grup
    public function verification(Tournament $tournament)
    {
        $participants = TournamentTeam::with(['team.verificationDocuments', 'players'])
            ->where('tournament_id', $tournament->id)
            ->get();

        return view('admin.tournaments.verification', compact('tournament', 'participants'));
    }

    /**
     * N12 — Halaman "Manajemen Pemain" / Statistik (sisi Admin).
     * Menampilkan ranking pemain (top skor, assist, kartu) & statistik tim
     * (produktif, kebobolan, fairplay). Komputasi didelegasikan ke service
     * agar reusable untuk N13 (view-only Manager & Tamu/Visitor).
     */
    public function statistics(Tournament $tournament, TournamentStatisticsService $statistics)
    {
        $stats = $statistics->forTournament($tournament);

        return view('admin.tournaments.statistics', array_merge(
            ['tournament' => $tournament],
            $stats,
        ));
    }

    /**
     * R18 — Upload berkas verifikasi untuk sebuah tim peserta.
     */
    public function uploadVerificationDocument(Request $request, Tournament $tournament, TournamentTeam $participant)
    {
        if ($participant->tournament_id !== $tournament->id) {
            abort(404);
        }

        $validated = $request->validate([
            'document_name' => 'required|string|max:255',
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:8192',
        ], [
            'document_name.required' => 'Nama berkas tidak boleh kosong',
            'document_file.required' => 'File berkas tidak boleh kosong',
            'document_file.mimes' => 'Berkas harus PDF atau gambar (jpg, png, webp)',
            'document_file.max' => 'Ukuran berkas maksimal 8 MB',
        ]);

        $file = $request->file('document_file');
        $path = $file->store('verification-documents', 'public');

        $participant->team->verificationDocuments()->create([
            'document_name' => $validated['document_name'],
            'document_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', "Berkas '{$validated['document_name']}' berhasil diunggah untuk {$participant->team->name}.");
    }

    /**
     * R18 — Hapus berkas verifikasi.
     */
    public function deleteVerificationDocument(Tournament $tournament, TournamentTeam $participant, \App\Models\TeamVerificationDocument $document)
    {
        if ($participant->tournament_id !== $tournament->id || $document->team_id !== $participant->team_id) {
            abort(404);
        }

        \Illuminate\Support\Facades\Storage::disk('public')->delete($document->document_path);
        $document->delete();

        return back()->with('success', 'Berkas verifikasi berhasil dihapus.');
    }

    public function verifyParticipant(Request $request, Tournament $tournament, TournamentTeam $participant)
    {
        if ($participant->tournament_id !== $tournament->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $participant->team->verification_status = $validated['status'];
        $participant->team->save();

        app(MatchGenerator::class)->generateForTournament($tournament);

        $statusText = [
            'pending' => 'pending',
            'approved' => 'terverifikasi',
            'rejected' => 'ditolak',
        ][$validated['status']];

        return back()->with('success', "Status verifikasi {$participant->team->name} diubah menjadi {$statusText}.");
    }

    public function standings(Tournament $tournament)
    {
        $tournament->load('groupSetting');
        
        // Jika belum ada setting, redirect ke settings
        if (!$tournament->groupSetting) {
            return redirect()->route('tournaments.settings', $tournament)
                           ->with('warning', 'Silakan atur pengaturan kelolosan grup terlebih dahulu');
        }

        // Get competition type and playoff settings
        $bracketSetting = AppSetting::where('key', $this->bracketSettingsKey($tournament))->first();
        $bracketValue = $bracketSetting?->value ?? [];
        $competitionType = $bracketValue['competition_type'] ?? 'tournament';
        $playoffOptions = $bracketValue['playoff_options'] ?? [];

        // Sistem turnamen (gugur murni) tidak memiliki klasemen grup
        if ($competitionType === 'tournament') {
            return redirect()->route('tournaments.bracketAdmin', $tournament)
                           ->with('warning', 'Sistem Turnamen (babak gugur) tidak menggunakan klasemen grup. Hasil ditentukan lewat bracket gugur.');
        }

        $setting = $tournament->groupSetting;
        $groups = $this->buildStandingsGroups($tournament);
        $playoffType = in_array('promotion', $playoffOptions, true) && in_array('relegation', $playoffOptions, true)
            ? 'both'
            : (in_array('promotion', $playoffOptions, true) ? 'promotion' :
              (in_array('relegation', $playoffOptions, true) ? 'relegation' : 'none'));

        // Generate playoff teams for display
        $playoffPromotionTeams = [];
        $playoffRelegationTeams = [];
        $hasPlayoffPromotion = false;
        $hasPlayoffRelegation = false;

        if ($competitionType === 'league_playoff' && in_array('promotion', $playoffOptions, true)) {
            $hasPlayoffPromotion = true;
            $qualifiedRankings = $setting->qualified_teams ?? [1, 2];
            $slotIndex = 1;
            foreach ($groups as $groupName => $teams) {
                foreach ($qualifiedRankings as $ranking) {
                    $playoffPromotionTeams["Slot {$slotIndex}"] = "Ranking {$ranking} - Grup {$groupName}";
                    $slotIndex++;
                }
            }
        }

        if ($competitionType === 'league_playoff' && in_array('relegation', $playoffOptions, true)) {
            $hasPlayoffRelegation = true;
            $relegatedRankings = $setting->relegated_teams ?? [];
            $slotIndex = 1;
            foreach ($groups as $groupName => $teams) {
                foreach ($relegatedRankings as $ranking) {
                    $playoffRelegationTeams["Slot {$slotIndex}"] = "Ranking {$ranking} - Grup {$groupName}";
                    $slotIndex++;
                }
            }
        }

        // N2 — data untuk panel Undian yang di-embed di halaman Bagan Klasemen.
        // Hanya relevan saat kompetisi memakai grup (group_count > 0).
        $drawGroupLabels = [];
        $drawTeams = collect();
        if ((int) ($setting->group_count ?? 0) > 0) {
            $drawGroupLabels = $this->buildGroupLabels((int) $setting->group_count);
            $drawTeams = $tournament->tournamentTeams()
                ->with('team')
                ->get()
                ->map(fn ($tt) => [
                    'id' => $tt->id,
                    'name' => $tt->team?->name ?? ('Tim ' . $tt->id),
                    'group_label' => $tt->group_label,
                ])
                ->values();
        }

        return view('admin.tournaments.standings', compact('tournament', 'groups', 'setting', 'competitionType', 'playoffType', 'playoffPromotionTeams', 'hasPlayoffPromotion', 'playoffRelegationTeams', 'hasPlayoffRelegation', 'drawGroupLabels', 'drawTeams'));
    }

    private function buildStandingsGroups(Tournament $tournament): array
    {
        $tournament->load(['groupSetting', 'tournamentTeams.team']);
        $pointSettings = $this->resolvePointSettings($tournament);
        $tieBreakers = $pointSettings['tiebreakers'] ?? ['points', 'goal_difference', 'goals_scored', 'head_to_head'];

        $teams = TournamentTeam::with('team')
            ->where('tournament_id', $tournament->id)
            ->get();

        $teamStats = [];
        foreach ($teams as $tournamentTeam) {
            $teamStats[$tournamentTeam->id] = [
                'team_id' => $tournamentTeam->id,
                'name' => $tournamentTeam->team?->name ?? 'Tim ' . $tournamentTeam->id,
                'logo' => $tournamentTeam->team?->logo,
                'group_label' => $tournamentTeam->group_label,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'goals_scored' => 0,
                'goals_conceded' => 0,
                'goal_difference' => 0,
                'points' => 0,
            ];
        }

        $headToHead = [];
        $finishedMatches = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('status', 'full_time')
            ->whereIn('stage_type', ['group', 'league'])
            ->get();

        foreach ($finishedMatches as $match) {
            if (! isset($teamStats[$match->home_team_id]) || ! isset($teamStats[$match->away_team_id])) {
                continue;
            }

            $home = &$teamStats[$match->home_team_id];
            $away = &$teamStats[$match->away_team_id];
            $homeScore = (int) $match->home_score;
            $awayScore = (int) $match->away_score;

            $home['played']++;
            $away['played']++;
            $home['goals_scored'] += $homeScore;
            $home['goals_conceded'] += $awayScore;
            $away['goals_scored'] += $awayScore;
            $away['goals_conceded'] += $homeScore;

            if ($homeScore > $awayScore) {
                $home['wins']++;
                $away['losses']++;
                $home['points'] += $pointSettings['win'];
                $away['points'] += $pointSettings['loss'];
            } elseif ($homeScore < $awayScore) {
                $away['wins']++;
                $home['losses']++;
                $away['points'] += $pointSettings['win'];
                $home['points'] += $pointSettings['loss'];
            } else {
                $home['draws']++;
                $away['draws']++;
                $home['points'] += $pointSettings['draw'];
                $away['points'] += $pointSettings['draw'];
            }

            if (in_array('head_to_head', $tieBreakers, true)) {
                $homeH2H = &$headToHead[$match->home_team_id][$match->away_team_id];
                $awayH2H = &$headToHead[$match->away_team_id][$match->home_team_id];

                $homeH2H['points'] = ($homeH2H['points'] ?? 0) + ($homeScore > $awayScore ? $pointSettings['win'] : ($homeScore === $awayScore ? $pointSettings['draw'] : 0));
                $awayH2H['points'] = ($awayH2H['points'] ?? 0) + ($awayScore > $homeScore ? $pointSettings['win'] : ($homeScore === $awayScore ? $pointSettings['draw'] : 0));
                $homeH2H['goal_difference'] = ($homeH2H['goal_difference'] ?? 0) + ($homeScore - $awayScore);
                $awayH2H['goal_difference'] = ($awayH2H['goal_difference'] ?? 0) + ($awayScore - $homeScore);
                $homeH2H['goals_scored'] = ($homeH2H['goals_scored'] ?? 0) + $homeScore;
                $awayH2H['goals_scored'] = ($awayH2H['goals_scored'] ?? 0) + $awayScore;
            }
        }

        foreach ($teamStats as &$teamStat) {
            $teamStat['goal_difference'] = $teamStat['goals_scored'] - $teamStat['goals_conceded'];
        }
        unset($teamStat);

        $hasGroups = $teams->pluck('group_label')->filter()->isNotEmpty();
        $grouped = [];

        $groupLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $setting = $tournament->groupSetting;
        $groupCount = $setting?->group_count ?? 0;
        
        for ($i = 1; $i <= $groupCount; $i++) {
            $groupLabel = $groupLetters[$i - 1] ?? (string) $i;
            $grouped[$groupLabel] = [];
        }

        foreach ($teamStats as $teamStat) {
            if ($hasGroups && !empty($teamStat['group_label'])) {
                $groupLabel = $teamStat['group_label'];
            } else {
                $groupLabel = $groupLetters[0] ?? 'A';
            }
            if (isset($grouped[$groupLabel])) {
                $grouped[$groupLabel][] = $teamStat;
            } else {
                $grouped[$groupLetters[0] ?? 'A'][] = $teamStat;
            }
        }

        foreach ($grouped as $groupLabel => $rows) {
            usort($rows, function ($a, $b) use ($tieBreakers, $headToHead) {
                return $this->compareTeamRows($a, $b, $tieBreakers, $headToHead);
            });

            foreach ($rows as $index => &$row) {
                $row['ranking'] = $index + 1;
            }
            unset($row);

            $grouped[$groupLabel] = $rows;
        }

        uksort($grouped, fn($a, $b) => strcmp($a, $b));

        return $grouped;
    }

    private function compareTeamRows(array $a, array $b, array $tieBreakers, array $headToHead): int
    {
        foreach ($tieBreakers as $criterion) {
            switch ($criterion) {
                case 'points':
                    $result = $b['points'] <=> $a['points'];
                    break;
                case 'goal_difference':
                    $result = $b['goal_difference'] <=> $a['goal_difference'];
                    break;
                case 'goals_scored':
                    $result = $b['goals_scored'] <=> $a['goals_scored'];
                    break;
                case 'goals_conceded':
                    $result = $a['goals_conceded'] <=> $b['goals_conceded'];
                    break;
                case 'head_to_head':
                    $aH2H = $headToHead[$a['team_id']][$b['team_id']] ?? null;
                    $bH2H = $headToHead[$b['team_id']][$a['team_id']] ?? null;

                    if ($aH2H && $bH2H) {
                        $result = $bH2H['points'] <=> $aH2H['points'];
                        if ($result !== 0) {
                            break;
                        }

                        $result = $bH2H['goal_difference'] <=> $aH2H['goal_difference'];
                        if ($result !== 0) {
                            break;
                        }

                        $result = $bH2H['goals_scored'] <=> $aH2H['goals_scored'];
                        if ($result !== 0) {
                            break;
                        }
                    }
                    $result = 0;
                    break;
                default:
                    $result = 0;
                    break;
            }

            if ($result !== 0) {
                return $result;
            }
        }

        return strcmp($a['name'], $b['name']);
    }

    private function resolvePointSettings(Tournament $tournament): array
    {
        $setting = AppSetting::firstOrCreate(
            ['key' => $this->pointSettingsKey($tournament)],
            ['value' => [
                'win' => 3,
                'draw' => 1,
                'loss' => 0,
                'tiebreakers' => ['points', 'goal_difference', 'goals_scored', 'head_to_head'],
            ]]
        );

        $value = $setting->value ?? [];

        return array_merge([
            'win' => 3,
            'draw' => 1,
            'loss' => 0,
            'tiebreakers' => ['points', 'goal_difference', 'goals_scored', 'head_to_head'],
        ], $value);
    }

    private function buildGroupLabels(int $groupCount): array
    {
        $labels = [];
        $base = range('A', 'Z');

        for ($i = 0; $i < $groupCount; $i++) {
            if ($i < count($base)) {
                $labels[] = $base[$i];
                continue;
            }

            $first = $base[floor($i / count($base)) - 1] ?? 'A';
            $second = $base[$i % count($base)];
            $labels[] = $first . $second;
        }

        return $labels;
    }
}