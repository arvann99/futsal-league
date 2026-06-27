<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\MatchGenerator;

class TournamentParticipantController extends Controller
{
    public function index(Tournament $tournament)
    {
        // N9 — eager-load 'officials' agar data Official/Manager yang diinput
        // lewat portal manager juga tampil di panel Admin.
        $participants = $tournament->tournamentTeams()->with(['team', 'players', 'officials'])->get();

        // R19 — agregasi statistik gol & kartu per pemain (via match_events.player_id)
        // untuk semua match di turnamen ini.
        $playerStats = \App\Models\MatchEvent::query()
            ->whereNotNull('player_id')
            ->whereIn('match_id', \App\Models\TournamentMatch::where('tournament_id', $tournament->id)->select('id'))
            ->selectRaw('player_id,
                SUM(event_type = "goal") as goals,
                SUM(event_type = "yellow_card") as yellow_cards,
                SUM(event_type = "red_card") as red_cards')
            ->groupBy('player_id')
            ->get()
            ->keyBy('player_id');

        $tournament->load('groupSetting');
        $bracketSetting = \App\Models\AppSetting::where('key', 'tournament_' . $tournament->id . '_bracket_settings')->first();
        $competitionType = $bracketSetting?->value['competition_type'] ?? 'tournament';
        $usesGroups = $competitionType !== 'tournament'
            && ($tournament->groupSetting?->group_count ?? 0) > 0;

        $groupLabels = [];
        if ($usesGroups) {
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
            $groupLabels = array_slice($letters, 0, (int) $tournament->groupSetting->group_count);
        }

        // N1 — tentukan apakah slot pendaftaran sudah penuh agar tombol
        // "Tambah Peserta" dinonaktifkan di view. Dua sumber batas:
        //   (a) kapasitas grup (group_count × teams_per_group) — hanya saat bergrup;
        //   (b) limit paket langganan admin (null = unlimited).
        $current = $participants->count();
        $groupCapacity = $usesGroups
            ? (int) $tournament->groupSetting->group_count * (int) $tournament->groupSetting->teams_per_group
            : 0;
        $teamLimit = $tournament->creator?->teamLimit(); // null = unlimited

        $isFull = false;
        $fullReason = null;
        if ($groupCapacity > 0 && $current >= $groupCapacity) {
            $isFull = true;
            $fullReason = "Kapasitas grup penuh ({$current}/{$groupCapacity} slot).";
        } elseif ($teamLimit !== null && $current >= $teamLimit) {
            $isFull = true;
            $fullReason = "Batas paket tercapai (maks {$teamLimit} tim).";
        }

        return view('admin.tournaments.participants.index', compact(
            'tournament',
            'participants',
            'usesGroups',
            'groupLabels',
            'playerStats',
            'isFull',
            'fullReason'
        ));
    }

    public function create(Tournament $tournament)
    {
        return view('admin.tournaments.participants.create', compact('tournament'));
    }

    public function store(Request $request, Tournament $tournament)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
        ];

        // Add conditional validation based on country
        if ($request->input('country') === 'Indonesia') {
            $rules['province'] = 'required|string|max:255';
        } else {
            $rules['state'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        $tournament->load('groupSetting');
        $bracketSetting = \App\Models\AppSetting::where('key', 'tournament_' . $tournament->id . '_bracket_settings')->first();
        $competitionType = $bracketSetting?->value['competition_type'] ?? 'tournament';

        $teamLimit = Auth::user()->teamLimit(); // null = unlimited
        $groupCapacity = ($competitionType !== 'tournament' && $tournament->groupSetting)
            ? (int) $tournament->groupSetting->group_count * (int) $tournament->groupSetting->teams_per_group
            : 0;

        // Handle logo upload (sebelum transaksi; file yatim saat rollback langka & diabaikan).
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('team-logos', 'public');
        }

        // R22+R14 — cek limit paket & kapasitas grup secara ATOMIK: lock baris
        // peserta turnamen ini agar dua request paralel tidak melewati batas.
        // N3 — token manager di-generate otomatis saat peserta dibuat & ditampung
        // untuk ditampilkan langsung (tidak perlu klik "Reset Token" lagi).
        $managerToken = null;
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($tournament, $validated, $logoPath, $teamLimit, $groupCapacity, &$managerToken) {
                $current = TournamentTeam::where('tournament_id', $tournament->id)
                    ->lockForUpdate()
                    ->count();

                if ($teamLimit !== null && $current >= $teamLimit) {
                    throw new \RuntimeException('PLAN_LIMIT');
                }

                if ($groupCapacity > 0 && $current >= $groupCapacity) {
                    throw new \RuntimeException('GROUP_FULL:' . $current . '/' . $groupCapacity);
                }

                $managerToken = Team::generateUniqueManagerToken($validated['name']);

                $team = Team::create([
                    'name' => $validated['name'],
                    'slug' => $this->generateTeamSlug($validated['name']),
                    'logo' => $logoPath,
                    'city' => $validated['city'],
                    'country' => $validated['country'],
                    'manager_token' => $managerToken,
                    'created_by' => $tournament->created_by,
                ]);

                $groupLabel = $this->assignGroupLabel($tournament);

                TournamentTeam::create([
                    'tournament_id' => $tournament->id,
                    'team_id' => $team->id,
                    'group_label' => $groupLabel,
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            if ($e->getMessage() === 'PLAN_LIMIT') {
                return back()->withInput()
                    ->with('error', "Batas paket Anda tercapai (maks {$teamLimit} tim per turnamen). Upgrade paket untuk menambah peserta.");
            }
            if (str_starts_with($e->getMessage(), 'GROUP_FULL:')) {
                $slot = substr($e->getMessage(), strlen('GROUP_FULL:'));
                return back()->withInput()
                    ->with('error', "Kapasitas grup sudah penuh ({$slot} slot). Ubah pengaturan grup (jumlah grup × tim per grup) terlebih dahulu untuk menambah peserta.");
            }
            throw $e;
        }

        // Regenerate tournament schedule/bracket after participant added
        app(MatchGenerator::class)->generateForTournament($tournament);

        // N3 — tampilkan token manager langsung di layar (success + sorot baris baru)
        return redirect()->route('tournaments.participants.index', $tournament)
            ->with('success', "Peserta \"{$validated['name']}\" berhasil ditambahkan. Token Manager: {$managerToken} — bagikan ke manajer tim untuk login portal.")
            ->with('new_manager_token', $managerToken);
    }

    public function edit(Tournament $tournament, TournamentTeam $participant)
    {
        if ($participant->tournament_id !== $tournament->id) {
            abort(404);
        }

        $participant->load('team');

        return view('admin.tournaments.participants.edit', compact('tournament', 'participant'));
    }

    public function update(Request $request, Tournament $tournament, TournamentTeam $participant)
    {
        if ($participant->tournament_id !== $tournament->id) {
            abort(404);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
        ];

        // Add conditional validation based on country
        if ($request->input('country') === 'Indonesia') {
            $rules['province'] = 'required|string|max:255';
        } else {
            $rules['state'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        // Handle logo upload and deletion of old file
        $team = $participant->team;
        if ($request->hasFile('logo')) {
            $newPath = $request->file('logo')->store('team-logos', 'public');
            // delete old
            if ($team->logo) {
                Storage::disk('public')->delete($team->logo);
            }
            $team->logo = $newPath;
        }

        $team->name = $validated['name'];
        $team->city = $validated['city'];
        $team->country = $validated['country'];
        $team->save();

        // Regenerate schedule/bracket when team details updated (names affect seeds/keys)
        app(MatchGenerator::class)->generateForTournament($tournament);

        return redirect()->route('tournaments.participants.index', $tournament)
            ->with('success', 'Detail peserta berhasil diperbarui.');
    }

    public function destroy(Tournament $tournament, TournamentTeam $participant)
    {
        if ($participant->tournament_id !== $tournament->id) {
            abort(404);
        }

        $participant->delete();

        // Regenerate schedule/bracket after removal
        app(MatchGenerator::class)->generateForTournament($tournament);

        return redirect()->route('tournaments.participants.index', $tournament)
            ->with('success', 'Peserta berhasil dihapus dari turnamen.');
    }

    /**
     * R15 — Admin menetapkan grup sebuah tim secara manual. Penempatan manual
     * ditandai (group_assigned_manually) agar auto-assign berbasis seed tidak
     * menimpanya saat pengaturan grup disimpan ulang.
     */
    public function assignGroupManually(Request $request, Tournament $tournament, TournamentTeam $participant)
    {
        if ($participant->tournament_id !== $tournament->id) {
            abort(404);
        }

        $tournament->load('groupSetting');
        $bracketSetting = \App\Models\AppSetting::where('key', 'tournament_' . $tournament->id . '_bracket_settings')->first();
        $competitionType = $bracketSetting?->value['competition_type'] ?? 'tournament';

        if ($competitionType === 'tournament' || ! $tournament->groupSetting || ! $tournament->groupSetting->group_count) {
            return back()->with('error', 'Turnamen ini tidak memakai grup, penempatan grup tidak tersedia.');
        }

        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $validGroups = array_slice($letters, 0, (int) $tournament->groupSetting->group_count);

        $validated = $request->validate([
            'group_label' => ['nullable', 'string', 'in:' . implode(',', $validGroups)],
        ]);

        $label = $validated['group_label'] ?? null;

        $participant->update([
            'group_label' => $label,
            'group_assigned_manually' => $label !== null,
        ]);

        // Regenerasi jadwal agar match grup ikut perubahan penempatan.
        app(MatchGenerator::class)->generateForTournament($tournament);

        $teamName = $participant->team?->name ?? 'Tim';
        $msg = $label
            ? "{$teamName} dipindahkan ke Grup {$label}."
            : "{$teamName} dikeluarkan dari grup.";

        return back()->with('success', $msg);
    }

    private function generateTeamSlug(string $name): string
    {
        $slug = Str::slug($name) ?: Str::random(10);
        $originalSlug = $slug;
        $count = 1;

        while (Team::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }

    private function assignGroupLabel(Tournament $tournament): ?string
    {
        // Mode turnamen (gugur murni) tidak memakai grup
        $bracketSetting = \App\Models\AppSetting::where('key', 'tournament_' . $tournament->id . '_bracket_settings')->first();
        if (($bracketSetting?->value['competition_type'] ?? 'tournament') === 'tournament') {
            return null;
        }

        $setting = $tournament->groupSetting;
        if (! $setting || ! $setting->group_count || ! $setting->teams_per_group) {
            return null;
        }

        $groupLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $totalGroups = min($setting->group_count, count($groupLetters));

        $counts = TournamentTeam::where('tournament_id', $tournament->id)
            ->whereNotNull('group_label')
            ->selectRaw('group_label, COUNT(*) as total')
            ->groupBy('group_label')
            ->pluck('total', 'group_label')
            ->toArray();

        for ($i = 0; $i < $totalGroups; $i++) {
            $label = $groupLetters[$i];
            $currentCount = $counts[$label] ?? 0;
            if ($currentCount < $setting->teams_per_group) {
                return $label;
            }
        }

        return $groupLetters[0] ?? 'A';
    }

    
}
