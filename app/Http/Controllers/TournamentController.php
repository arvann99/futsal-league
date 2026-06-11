<?php

namespace App\Http\Controllers;

use App\Models\MatchEvent;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use App\Models\AppSetting;
use App\Models\TournamentGroupSetting;
use App\Services\MatchGenerator;
use App\Debug\MatchTimelineTracer;
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
        $tournaments = Tournament::with('creator')->latest()->get();
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

        $validated['created_by'] = Auth::id();

        Tournament::create($validated);

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
        
        // Hitung statistik turnamen
        $statistics = [
            'total_pendaftar' => 0,
            'terverifikasi' => 0,
            'butuh_verifikasi' => 0,
            'ditolak_draft' => 0,
        ];

        // Dapatkan competition type dari bracket setting
        $bracketSetting = $this->resolveBracketSetting($tournament);
        $competitionType = $bracketSetting->value['competition_type'] ?? 'tournament';
        $playoffOptions = $bracketSetting->value['playoff_options'] ?? [];
        
        // Tentukan apakah bracket admin tersedia
        $isTournament = $competitionType === 'tournament';
        $hasPromotion = in_array('promotion', $playoffOptions);
        $hasRelegation = in_array('relegation', $playoffOptions);
        $isLeaguePlayoff = $competitionType === 'league_playoff' && ($hasPromotion || $hasRelegation);
        $bracketAdminAvailable = $isTournament || $isLeaguePlayoff;

        return view('admin.tournaments.manage', compact('tournament', 'statistics', 'competitionType', 'bracketAdminAvailable'));
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

        $groupCounts = array_fill_keys($groupLabels, 0);

        foreach ($teams as $index => $team) {
            $labelIndex = intdiv($index, $teamsPerGroup);
            if ($labelIndex >= count($groupLabels)) {
                $labelIndex = count($groupLabels) - 1;
            }

            $groupLabel = $groupLabels[$labelIndex];
            $team->group_label = $groupLabel;
            $team->save();
            $groupCounts[$groupLabel]++;
        }

        Log::info('Assigned TournamentTeam group labels', [
            'tournament_id' => $tournament->id,
            'group_count' => $groupCount,
            'teams_per_group' => $teamsPerGroup,
            'teams_total' => $teams->count(),
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
            ->orderBy('round_name')
            ->orderBy('bracket_match_id')
            ->get();

        $scheduleMatches = $matches->map(function ($match) {
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
                'datetime' => $match->match_date?->toDateTimeString(),
                'status' => $match->status ?? 'scheduled',
            ];
        })->toArray();

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

    private function buildScheduleLabel(TournamentMatch $match): string
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

    private function generateLiveMatchRoster(string $side): array
    {
        if ($side === 'home') {
            return [
                ['label' => 'Ahmad #10', 'player_name' => 'Ahmad #10'],
                ['label' => 'Rizky #7', 'player_name' => 'Rizky #7'],
                ['label' => 'Budi #9', 'player_name' => 'Budi #9'],
            ];
        }

        return [
            ['label' => 'Andre #11', 'player_name' => 'Andre #11'],
            ['label' => 'Yoga #8', 'player_name' => 'Yoga #8'],
            ['label' => 'Dedi #5', 'player_name' => 'Dedi #5'],
        ];
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

    public function pointsSettings(Tournament $tournament)
    {
        $key = $this->pointSettingsKey($tournament);
        $default = [
            'win' => 3,
            'draw' => 1,
            'loss' => 0,
            'tiebreakers' => [
                'points',
                'head_to_head',
                'goal_difference',
                'goals_scored',
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

        if (! isset($value['group_count']) || ! is_int($value['group_count']) || $value['group_count'] < 2) {
            $value['group_count'] = $default['group_count'];
            $updated = true;
        }

        $competitionType = $value['competition_type'] ?? 'tournament';
        $playoffOptions = $value['playoff_options'] ?? [];
        
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

    public function bracketAdmin(Tournament $tournament)
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
        $hasPromotion = in_array('promotion', $playoffOptions);
        $hasRelegation = in_array('relegation', $playoffOptions);
        $isLeaguePlayoff = $competitionType === 'league_playoff' && ($hasPromotion || $hasRelegation);
        $bracketAllowed = $isTournament || $isLeaguePlayoff;

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
        $playoffMode = 'promotion'; // default untuk tournament
        if ($competitionType === 'league_playoff') {
            if ($hasPromotion && !$hasRelegation) {
                $playoffMode = 'promotion';
            } elseif ($hasRelegation && !$hasPromotion) {
                $playoffMode = 'relegation';
            } else {
                // Jika ada kedua opsi, gunakan mode yang ditentukan (untuk saat ini, defaultnya promotion)
                $playoffMode = 'promotion';
            }
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

        // Load actual TournamentTeam records for Manual mode selection
        $tournamentTeams = $tournament->tournamentTeams()->with('team')->get();

        // Load assigned matches (by bracket_match_id) so we can display assigned team names/ids
        $assignedMatches = \App\Models\TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('bracket_match_id')
            ->get()
            ->keyBy('bracket_match_id');

        // Mode turnamen (gugur murni) tidak memiliki fase grup yang harus ditunggu
        $groupStageComplete = $isTournament ? true : $this->isGroupStageComplete($tournament);

        $qualifiedTeams = $isTournament ? [] : $this->getQualifiedTeams($tournament);
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

        return view('admin.tournaments.bracket.manage', compact('tournament', 'setting', 'teamsToUse', 'competitionType', 'isLeaguePlayoffWithPromotion', 'isLeaguePlayoffWithRelegation', 'playoffMode', 'tournamentTeams', 'assignedMatches', 'groupStageComplete', 'qualifiedTeamOptions'));
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
        if ($competitionType === 'league_playoff') {
            $hasPromotion = in_array('promotion', $playoffOptions);
            $hasRelegation = in_array('relegation', $playoffOptions);
            
            if ($hasPromotion && !$hasRelegation) {
                $playoffMode = 'promotion';
            } elseif ($hasRelegation && !$hasPromotion) {
                $playoffMode = 'relegation';
            } else {
                // Jika ada kedua opsi, gunakan mode yang ditentukan (untuk saat ini, defaultnya promotion)
                $playoffMode = 'promotion';
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

        $qualifiedTeamIds = [];
        if ($playoffMode === 'promotion') {
            $qualifiedTeamIds = array_values($this->getQualifiedTeams($tournament));
        }

        $qualifiedTeams = TournamentTeam::with('team')
            ->where('tournament_id', $tournament->id)
            ->when(! empty($qualifiedTeamIds), fn($query) => $query->whereIn('id', $qualifiedTeamIds))
            ->get()
            ->keyBy('id');

        $teamNameById = $qualifiedTeams->mapWithKeys(fn($tt) => [$tt->id => $tt->team?->name ?? 'Tim ' . $tt->id])->all();

        $currentValue = $setting->value ?? [];
        $currentMatches = $currentValue['matches'] ?? [];

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

        $currentValue['matches'] = $currentMatches;
        $setting->update(['value' => $currentValue]);

        $dbMatches = \App\Models\TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('bracket_match_id')
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

        return back()->with('success', 'Tim bracket berhasil disimpan.');
    }

    private function resolveBracketSetting(Tournament $tournament): AppSetting
    {
        $key = $this->bracketSettingsKey($tournament);
        $default = [
            'match_type' => 'single',
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

        if (! isset($value['group_count']) || ! is_int($value['group_count']) || $value['group_count'] < 2) {
            $value['group_count'] = $default['group_count'];
            $updated = true;
        }

        if (! isset($value['competition_type']) || ! in_array($value['competition_type'], ['tournament', 'league', 'league_playoff'], true)) {
            $value['competition_type'] = $default['competition_type'];
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
        if ($competitionType === 'tournament') {
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
            'third_place' => 'sometimes|accepted',
            'group_count' => 'required|integer|min:1|max:16',
            'matches' => 'sometimes|array|min:1',
            'matches.*.left' => 'required_with:matches|string|max:80',
            'matches.*.right' => 'required_with:matches|string|max:80',
        ], [
            'match_type.required' => 'Pilih jenis pertandingan knock out',
            'match_type.in' => 'Jenis pertandingan tidak valid',
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
                isset($validated['third_place'])
            );
            $promotionMatches = $matches;
            $relegationMatches = $this->generateDefaultBracketMatches(
                $validated['group_count'],
                $relegatedRanks,
                isset($validated['third_place'])
            );
        } elseif ($competitionType === 'tournament') {
            // Gugur murni: regenerate slot bracket dari tim terverifikasi
            $matches = $this->generateKnockoutBracketFromTeams(
                $tournament,
                isset($validated['third_place'])
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
                isset($validated['third_place'])
            );
        }

        $valueToSave = [
            'match_type' => $validated['match_type'],
            'third_place' => isset($validated['third_place']),
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

        return back()->with('success', 'Pengaturan Bagan Bracket berhasil disimpan!');
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
            'competition_type' => 'required|in:tournament,league,league_playoff',
            'teams_per_group' => 'exclude_if:competition_type,tournament|required|integer|min:2|max:20',
            'group_count' => 'exclude_if:competition_type,tournament|required|integer|min:1|max:32',
            'playoff_type' => 'required_if:competition_type,league_playoff|in:promotion,relegation,both',
            'qualified_teams' => 'array',
            'qualified_teams.*' => 'integer|min:1',
            'relegated_teams' => 'array',
            'relegated_teams.*' => 'integer|min:1',
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

        // Simpan atau update pengaturan grup
        TournamentGroupSetting::updateOrCreate(
            ['tournament_id' => $tournament->id],
            [
                'teams_per_group' => $validated['teams_per_group'],
                'group_count' => $validated['group_count'],
                'qualified_teams' => $qualified,
                'relegated_teams' => $relegated,
                'locked' => true,
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
        $matchesQuery = TournamentMatch::with(['events', 'homeTeam.team', 'awayTeam.team'])->where('tournament_id', $tournament->id)
            ->orderBy('match_date')
            ->orderBy('stage_type')
            ->orderBy('group_label')
            ->orderBy('round_name');

        $allMatches = $matchesQuery->get();

        // Build filter options from data
        $filterOptions = [
            'group_label' => $allMatches->pluck('group_label')->filter()->unique()->values()->all(),
            'round_name' => $allMatches->pluck('round_name')->filter()->unique()->values()->all(),
            'stage_type' => $allMatches->pluck('stage_type')->filter()->unique()->values()->all(),
            'playoff_type' => $allMatches->pluck('playoff_type')->filter()->unique()->values()->all(),
        ];

        // Map matches to view-friendly structure
        $scheduleMatches = $allMatches->map(function ($match) {
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
                'datetime' => $match->match_date?->toDateTimeString(),
                'status' => $match->status ?? 'scheduled',
                'venue' => $match->venue,
                'home_roster' => $this->generateLiveMatchRoster('home'),
                'away_roster' => $this->generateLiveMatchRoster('away'),
                'events' => $match->events->sortBy('created_at')->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'event_type' => $event->event_type,
                        'team_side' => $event->team_side,
                        'player_name' => $event->player_name,
                        'created_at' => $event->created_at->toDateTimeString(),
                    ];
                })->values()->all(),
            ];
        })->toArray();

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

        $status = $validated['match_status'];
        if ($match->stage_type === 'group'
            && array_key_exists('home_score', $validated)
            && array_key_exists('away_score', $validated)
            && $validated['home_score'] !== null
            && $validated['away_score'] !== null
        ) {
            $status = 'full_time';
        }

        // TRACE: Before update
        MatchTimelineTracer::log($match->id, 'updateMatch:before', [
            'incoming_status' => $validated['match_status'],
            'computed_status' => $status,
            'incoming_home_score' => $validated['home_score'] ?? 'not_provided',
            'incoming_away_score' => $validated['away_score'] ?? 'not_provided',
        ]);

        $match->update([
            'match_date' => Carbon::createFromFormat('Y-m-d H:i', sprintf('%s %s', $validated['match_date'], $validated['match_time'])),
            'status' => $status,
            'home_score' => array_key_exists('home_score', $validated) ? $validated['home_score'] : $match->home_score,
            'away_score' => array_key_exists('away_score', $validated) ? $validated['away_score'] : $match->away_score,
        ]);

        // TRACE: After update
        MatchTimelineTracer::log($match->id, 'updateMatch:after', [
            'status_changed_to' => $status,
        ]);

        if ($status === 'full_time') {
            $match->refresh();
            $this->finalizeMatchResult($match);
        }

        return back()->with('success', 'Perubahan jadwal dan status pertandingan berhasil disimpan.');
    }

    public function openLiveMatchLogger(Tournament $tournament, TournamentMatch $match)
    {
        if ($match->tournament_id !== $tournament->id) {
            abort(404);
        }

        if ($match->stage_type !== 'group' && (is_null($match->home_team_id) || is_null($match->away_team_id))) {
            return back()->withErrors(['live_match' => 'Pertandingan belum siap dimainkan karena peserta belum lengkap.']);
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

            if ($match->stage_type !== 'group' && (is_null($match->home_team_id) || is_null($match->away_team_id))) {
                return back()->withErrors(['event_logger' => 'Pertandingan belum siap dimainkan.']);
            }

            if ($match->status === 'full_time') {
                return back()->withErrors(['event_logger' => 'Pertandingan sudah selesai. Event logger hanya dapat dibuka dalam mode read-only.']);
            }

            $validated = $request->validate([
                'event_type' => 'required|in:goal,own_goal,yellow_card,red_card,foul,timeout,halftime,full_time',
                'team_side' => 'nullable|in:home,away',
                'player_name' => 'nullable|string|max:120',
                'description' => 'nullable|string|max:255',
                'minute' => 'nullable|integer|min:0|max:120',
            ]);

            if (in_array($validated['event_type'], ['goal', 'own_goal', 'yellow_card', 'red_card', 'foul'], true) && empty($validated['team_side'])) {
                return back()->withErrors(['team_side' => 'Pilih tim untuk event ini.']);
            }

            if ($validated['event_type'] === 'full_time' && ($match->home_score === null || $match->away_score === null)) {
                Log::info('Attempt to finalize via event but scores missing', [
                    'match_id' => $match->id,
                    'home_score' => $match->home_score,
                    'away_score' => $match->away_score,
                    'status' => $match->status,
                    'event_payload' => $validated,
                ]);

                return back()->withErrors(['event_type' => 'Pertandingan tidak bisa diselesaikan tanpa kedua skor terisi.']);
            }

            // TRACE: Before processing event
            MatchTimelineTracer::logWithEvent($match->id, 'storeMatchEvent:before', [
                'event_type' => $validated['event_type'],
                'team_side' => $validated['team_side'] ?? null,
                'player_name' => $validated['player_name'] ?? null,
                'minute' => $validated['minute'] ?? 0,
            ]);

            DB::transaction(function () use ($match, $validated) {
                if (in_array($validated['event_type'], ['goal', 'own_goal'], true)) {
                    if ($match->home_score === null) {
                        $match->home_score = 0;
                    }

                    if ($match->away_score === null) {
                        $match->away_score = 0;
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
                    $match->status = 'full_time';
                }

                $match->save();

                MatchEvent::create([
                    'match_id' => $match->id,
                    'event_type' => $validated['event_type'],
                    'team_side' => $validated['team_side'],
                    'player_name' => $validated['player_name'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'minute' => $validated['minute'] ?? 0,
                ]);

                if ($validated['event_type'] === 'full_time') {
                    $match->refresh();
                    $this->finalizeMatchResult($match);
                }
            });

            // TRACE: After event stored
            MatchTimelineTracer::logWithEvent($match->id, 'storeMatchEvent:after', [
                'event_type' => $validated['event_type'],
            ]);

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

            $homeScore = $match->home_score ?? 0;
            $awayScore = $match->away_score ?? 0;

            // TRACE: Before ending match
            MatchTimelineTracer::log($match->id, 'endMatch:before', [
                'current_status' => $match->status,
                'home_score' => $homeScore,
                'away_score' => $awayScore,
            ]);

            DB::transaction(function () use ($match, $homeScore, $awayScore) {
                // Ensure scores are set in database (NULL treated as 0)
                if ($match->home_score === null || $match->away_score === null) {
                    $match->update([
                        'home_score' => $homeScore,
                        'away_score' => $awayScore,
                        'status' => 'full_time',
                    ]);
                } else {
                    // Scores already set, just update status
                    $match->update(['status' => 'full_time']);
                }

                // TRACE: After setting status
                MatchTimelineTracer::log($match->id, 'endMatch:after_status_set', [
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                ]);

                $this->finalizeMatchResult($match);
            });

            return back()->with([
                'success' => 'Pertandingan berhasil diselesaikan dan di-finalisasi.',
                'open_live_match' => $match->id,
            ]);
        });
    }

    private function finalizeMatchResult(TournamentMatch $match)
    {
        $this->updateStandingsForTournament($match->tournament);
        $this->updateBracketForTournament($match);
        $this->updatePlayoffIfNeeded($match->tournament);
    }

    private function updateStandingsForTournament(Tournament $tournament): void
    {
        if ($this->isGroupStageComplete($tournament)) {
            $groups = $this->buildStandingsGroups($tournament);

            app(\App\Services\MatchGenerator::class)->generateBracketStructureForTournament($tournament);
            $this->fillBracketFromFinalStandings($tournament, $groups);
        }
    }

    private function updateBracketForTournament(TournamentMatch $match): void
    {
        if (! $match->next_match_id) {
            return;
        }

        $nextMatch = TournamentMatch::find($match->next_match_id);
        if (! $nextMatch) {
            return;
        }

        $winnerTeamId = null;
        $winnerName = null;

        if ($match->home_score > $match->away_score) {
            $winnerTeamId = $match->home_team_id;
            $winnerName = $match->homeTeam?->team?->name ?? $match->home_team_key ?? $match->source_home;
        } elseif ($match->away_score > $match->home_score) {
            $winnerTeamId = $match->away_team_id;
            $winnerName = $match->awayTeam?->team?->name ?? $match->away_team_key ?? $match->source_away;
        }

        if ($winnerTeamId === null || $winnerName === null) {
            return;
        }

        if (is_null($nextMatch->home_team_id)) {
            $nextMatch->home_team_id = $winnerTeamId;
            $nextMatch->home_team_key = $winnerName;
            $nextMatch->source_home = $winnerName;
            $nextMatch->save();
            return;
        }

        if (is_null($nextMatch->away_team_id)) {
            $nextMatch->away_team_id = $winnerTeamId;
            $nextMatch->away_team_key = $winnerName;
            $nextMatch->source_away = $winnerName;
            $nextMatch->save();
        }
    }

    private function isGroupStageComplete(Tournament $tournament): bool
    {
        $groupMatchCount = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('stage_type', 'group')
            ->count();

        if ($groupMatchCount === 0) {
            return false;
        }

        $completedCount = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('stage_type', 'group')
            ->where('status', 'full_time')
            ->count();

        return $completedCount >= $groupMatchCount;
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

        foreach ($groups as $groupLabel => $rows) {
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
        $participants = TournamentTeam::with(['team', 'players'])
            ->where('tournament_id', $tournament->id)
            ->get();

        return view('admin.tournaments.verification', compact('tournament', 'participants'));
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

        return view('admin.tournaments.standings', compact('tournament', 'groups', 'setting', 'competitionType', 'playoffType', 'playoffPromotionTeams', 'hasPlayoffPromotion', 'playoffRelegationTeams', 'hasPlayoffRelegation'));
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

            // TRACE: Before scoring this match
            MatchTimelineTracer::log($match->id, 'buildStandingsGroups:before_scoring', [
                'tournament_id' => $tournament->id,
            ]);

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
            } elseif ($homeScore < $awayScore) {
                $away['wins']++;
                $home['losses']++;
                $away['points'] += $pointSettings['win'];
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