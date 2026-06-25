<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TournamentTeamPlayer;
use Illuminate\Http\Request;

class OfficialPlayerController extends Controller
{
    public function index(Request $request)
    {
        $team = $this->getOfficialTeam($request);
        $tournamentTeamIds = $team->tournamentTeams()->pluck('id');

        $players = TournamentTeamPlayer::with('tournamentTeam.tournament')
            ->whereIn('tournament_team_id', $tournamentTeamIds)
            ->orderBy('player_name')
            ->get();

        $totalPlayers = $players->count();
        $totalGoalkeepers = $players->where('dominant_position', 'GK')->count();

        return view('official.players.index', [
            'team' => $team,
            'players' => $players,
            'totalPlayers' => $totalPlayers,
            'totalGoalkeepers' => $totalGoalkeepers,
        ]);
    }

    public function create(Request $request)
    {
        $team = $this->getOfficialTeam($request);
        $tournamentTeams = $team->tournamentTeams()->with('tournament')->get();

        $cities = ['Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang', 'Makassar', 'Palembang', 'Yogyakarta', 'Pontianak', 'Manado'];

        return view('official.players.create', [
            'team' => $team,
            'tournamentTeams' => $tournamentTeams,
            'cities' => $cities,
        ]);
    }

    public function store(Request $request)
    {
        $team = $this->getOfficialTeam($request);

        // R18 — kunci data setelah berkas tim disetujui admin.
        if ($lock = $this->guardLockedTeam($team)) {
            return $lock;
        }

        $tournamentTeamIds = $team->tournamentTeams()->pluck('id')->toArray();

        $data = $request->validate([
            'tournament_team_id' => 'required|integer',
            'player_name' => ['required', 'string', 'regex:/^[A-Za-z ]{3,15}$/'],
            'shirt_number' => 'required|numeric|min:1|max:99',
            'positions' => 'required|array|min:1',
            'dominant_position' => 'required|in:GK,Anchor,Flank,Pivot',
            'phone' => 'nullable|digits_between:10,15',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192',
            'is_captain' => 'sometimes|boolean',
        ]);

        $data['tournament_team_id'] = (int) $data['tournament_team_id'];

        if (! in_array($data['tournament_team_id'], $tournamentTeamIds, true)) {
            abort(403, 'Unauthorized tournament team.');
        }

        if ($data['shirt_number'] == 1 && $data['dominant_position'] !== 'GK') {
            return back()->withErrors([
                'shirt_number' => 'Nomor punggung 1 hanya boleh digunakan oleh Goalkeeper (GK).',
            ])->onlyInput('shirt_number', 'dominant_position');
        }

        $shirtExists = TournamentTeamPlayer::where('tournament_team_id', $data['tournament_team_id'])
            ->where('shirt_number', $data['shirt_number'])
            ->exists();

        if ($shirtExists) {
            return back()->withErrors([
                'shirt_number' => 'Nomor punggung ini sudah digunakan dalam tim yang sama.',
            ])->onlyInput('shirt_number');
        }

        $gkCount = TournamentTeamPlayer::where('tournament_team_id', $data['tournament_team_id'])
            ->where('dominant_position', 'GK')
            ->count();

        if ($data['dominant_position'] === 'GK' && $gkCount >= 3) {
            return back()->withErrors([
                'dominant_position' => 'Maksimal 3 pemain Goalkeeper (GK) dalam satu tim.',
            ])->onlyInput('dominant_position');
        }

        $playerCount = TournamentTeamPlayer::where('tournament_team_id', $data['tournament_team_id'])->count();
        if ($playerCount >= 15) {
            return back()->withErrors([
                'tournament_team_id' => 'Jumlah pemain maksimal 15 orang per tim.',
            ]);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('players', 'public');
        }

        if ($request->boolean('is_captain')) {
            TournamentTeamPlayer::where('tournament_team_id', $data['tournament_team_id'])
                ->update(['is_captain' => false]);
        }

        TournamentTeamPlayer::create([
            'tournament_team_id' => $data['tournament_team_id'],
            'player_name' => strtoupper($data['player_name']),
            'shirt_number' => $data['shirt_number'],
            'positions' => $data['positions'],
            'dominant_position' => $data['dominant_position'],
            'phone' => $data['phone'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'photo' => $photoPath,
            'is_captain' => $request->boolean('is_captain'),
            'status' => 'active',
            'registered_at' => now(),
        ]);

        return redirect()->route('official.players.index')->with('success', 'Pemain berhasil ditambahkan.');
    }

    public function edit(Request $request, TournamentTeamPlayer $player)
    {
        $this->authorizePlayer($request, $player);

        $team = $this->getOfficialTeam($request);
        $tournamentTeam = $team->tournamentTeams()->find($player->tournament_team_id);
        
        $cities = ['Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang', 'Makassar', 'Palembang', 'Yogyakarta', 'Pontianak', 'Manado'];

        return view('official.players.edit', [
            'team' => $team,
            'player' => $player,
            'tournamentTeam' => $tournamentTeam,
            'cities' => $cities,
        ]);
    }

    public function update(Request $request, TournamentTeamPlayer $player)
    {
        $this->authorizePlayer($request, $player);

        $team = $this->getOfficialTeam($request);

        if ($lock = $this->guardLockedTeam($team)) {
            return $lock;
        }

        $tournamentTeamIds = $team->tournamentTeams()->pluck('id')->toArray();

        $data = $request->validate([
            'player_name' => ['required', 'string', 'regex:/^[A-Za-z ]{3,15}$/'],
            'shirt_number' => 'required|numeric|min:1|max:99',
            'positions' => 'required|array|min:1',
            'dominant_position' => 'required|in:GK,Anchor,Flank,Pivot',
            'phone' => 'nullable|digits_between:10,15',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192',
            'is_captain' => 'sometimes|boolean',
        ]);

        if ($data['shirt_number'] == 1 && $data['dominant_position'] !== 'GK') {
            return back()->withErrors([
                'shirt_number' => 'Nomor punggung 1 hanya boleh digunakan oleh Goalkeeper (GK).',
            ])->onlyInput('shirt_number', 'dominant_position');
        }

        $shirtExists = TournamentTeamPlayer::where('tournament_team_id', $player->tournament_team_id)
            ->where('shirt_number', $data['shirt_number'])
            ->where('id', '!=', $player->id)
            ->exists();

        if ($shirtExists) {
            return back()->withErrors([
                'shirt_number' => 'Nomor punggung ini sudah digunakan dalam tim yang sama.',
            ])->onlyInput('shirt_number');
        }

        if ($data['dominant_position'] === 'GK' && $player->dominant_position !== 'GK') {
            $gkCount = TournamentTeamPlayer::where('tournament_team_id', $player->tournament_team_id)
                ->where('dominant_position', 'GK')
                ->count();
            
            if ($gkCount >= 3) {
                return back()->withErrors([
                    'dominant_position' => 'Maksimal 3 pemain Goalkeeper (GK) dalam satu tim.',
                ])->onlyInput('dominant_position');
            }
        }

        $photoPath = $player->photo;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('players', 'public');
        }

        if ($request->boolean('is_captain')) {
            TournamentTeamPlayer::where('tournament_team_id', $player->tournament_team_id)
                ->where('id', '!=', $player->id)
                ->update(['is_captain' => false]);
        }

        $player->update([
            'player_name' => strtoupper($data['player_name']),
            'shirt_number' => $data['shirt_number'],
            'positions' => $data['positions'],
            'dominant_position' => $data['dominant_position'],
            'phone' => $data['phone'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'photo' => $photoPath,
            'is_captain' => $request->boolean('is_captain'),
        ]);

        return redirect()->route('official.players.index')->with('success', 'Data pemain berhasil diperbarui.');
    }

    public function destroy(Request $request, TournamentTeamPlayer $player)
    {
        $this->authorizePlayer($request, $player);

        $team = $this->getOfficialTeam($request);

        if ($lock = $this->guardLockedTeam($team)) {
            return $lock;
        }

        $player->delete();

        return redirect()->route('official.players.index')->with('success', 'Pemain berhasil dihapus.');
    }

    private function getOfficialTeam(Request $request): Team
    {
        $teamId = $request->session()->get('official_team_id');

        return Team::findOrFail($teamId);
    }

    /**
     * R18 — Jika berkas tim sudah disetujui (approved), roster dikunci:
     * manager tidak bisa menambah/ubah/hapus pemain.
     */
    private function guardLockedTeam(Team $team)
    {
        if (($team->verification_status ?? 'pending') === 'approved') {
            return back()->with('error', 'Data tim sudah diverifikasi & dikunci oleh panitia. Hubungi panitia jika perlu perubahan.');
        }

        return null;
    }

    private function authorizePlayer(Request $request, TournamentTeamPlayer $player): void
    {
        $team = $this->getOfficialTeam($request);
        $authorizedIds = $team->tournamentTeams()->pluck('id')->toArray();

        if (! in_array($player->tournament_team_id, $authorizedIds, true)) {
            abort(403, 'Akses pemain ditolak.');
        }
    }
}
