<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TournamentTeamOfficial;
use Illuminate\Http\Request;

class OfficialTeamOfficialController extends Controller
{
    public function index(Request $request)
    {
        $team = $this->getOfficialTeam($request);
        $tournamentTeamIds = $team->tournamentTeams()->pluck('id');

        $officials = TournamentTeamOfficial::with('tournamentTeam.tournament')
            ->whereIn('tournament_team_id', $tournamentTeamIds)
            ->orderByRaw("CASE WHEN role = 'Manager' THEN 1 WHEN role = 'Coach' THEN 2 WHEN role = 'Assistant Coach' THEN 3 ELSE 4 END")
            ->orderBy('role')
            ->orderBy('official_name')
            ->get();

        return view('official.officials.index', [
            'team' => $team,
            'officials' => $officials,
        ]);
    }

    public function create(Request $request)
    {
        $team = $this->getOfficialTeam($request);
        $tournamentTeams = $team->tournamentTeams()->with('tournament')->get();

        return view('official.officials.create', [
            'team' => $team,
            'tournamentTeams' => $tournamentTeams,
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

       $validated = $request->validate([
    'tournament_team_id' => 'required|integer',
    'official_name'      => 'required|string|max:255',
    'role_selection'     => 'required|string|in:Manager,Coach,Assistant Coach,Lainnya',
    'custom_role'        => 'nullable|required_if:role_selection,Lainnya|string|max:255',
    'contact_phone'      => 'nullable|string|max:50',
    'contact_email'      => 'nullable|email|max:255',
]);

        $tournamentTeamId = (int) $validated['tournament_team_id'];
        if (! in_array($tournamentTeamId, $tournamentTeamIds, true)) {
            abort(403, 'Unauthorized tournament team.');
        }

        $role = $validated['role_selection'] === 'Lainnya'
            ? trim($validated['custom_role'] ?? '')
            : $validated['role_selection'];

        if ($role === '') {
            return back()->withErrors(['custom_role' => 'Nama jabatan harus diisi.'])->withInput();
        }

        $totalOfficials = TournamentTeamOfficial::where('tournament_team_id', $tournamentTeamId)->count();
        if ($totalOfficials >= 7) {
            return back()->withErrors(['tournament_team_id' => 'Jumlah official maksimal 7 orang per tim.'])->withInput();
        }

        $roleError = $this->validateRoleLimit($tournamentTeamId, $role);
        if ($roleError) {
            return back()->withErrors(['role_selection' => $roleError])->withInput();
        }

        TournamentTeamOfficial::create([
            'tournament_team_id' => $tournamentTeamId,
            'official_name' => $validated['official_name'],
            'role' => $role,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
        ]);

        return redirect()->route('official.officials.index')->with('success', 'Official berhasil ditambahkan.');
    }

    public function edit(Request $request, TournamentTeamOfficial $official)
    {
        $this->authorizeOfficial($request, $official);

        $team = $this->getOfficialTeam($request);
        $tournamentTeam = $team->tournamentTeams()->find($official->tournament_team_id);

        return view('official.officials.edit', [
            'team' => $team,
            'official' => $official,
            'tournamentTeam' => $tournamentTeam,
        ]);
    }

    public function update(Request $request, TournamentTeamOfficial $official)
    {
        $this->authorizeOfficial($request, $official);

        if ($lock = $this->guardLockedTeam($this->getOfficialTeam($request))) {
            return $lock;
        }

        $validated = $request->validate([
            'official_name' => 'required|string|max:255',
            'role_selection' => 'required|string|in:Manager,Coach,Assistant Coach,Lainnya',
            'custom_role' => 'nullable|required_if:role_selection,Lainnya|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
        ]);

        $role = $validated['role_selection'] === 'Lainnya'
            ? trim($validated['custom_role'] ?? '')
            : $validated['role_selection'];

        if ($role === '') {
            return back()->withErrors(['custom_role' => 'Nama jabatan harus diisi.'])->withInput();
        }

        $roleError = $this->validateRoleLimit($official->tournament_team_id, $role, $official->id);
        if ($roleError) {
            return back()->withErrors(['role_selection' => $roleError])->withInput();
        }

        $official->update([
            'official_name' => $validated['official_name'],
            'role' => $role,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
        ]);

        return redirect()->route('official.officials.index')->with('success', 'Data official berhasil diperbarui.');
    }

    public function destroy(Request $request, TournamentTeamOfficial $official)
    {
        $this->authorizeOfficial($request, $official);

        if ($lock = $this->guardLockedTeam($this->getOfficialTeam($request))) {
            return $lock;
        }

        $official->delete();

        return redirect()->route('official.officials.index')->with('success', 'Official berhasil dihapus.');
    }

    private function getOfficialTeam(Request $request): Team
    {
        $teamId = $request->session()->get('official_team_id');

        return Team::findOrFail($teamId);
    }

    /**
     * R18 — Jika berkas tim sudah disetujui (approved), data ofisial dikunci.
     */
    private function guardLockedTeam(Team $team)
    {
        if (($team->verification_status ?? 'pending') === 'approved') {
            return back()->with('error', 'Data tim sudah diverifikasi & dikunci oleh panitia. Hubungi panitia jika perlu perubahan.');
        }

        return null;
    }

    private function authorizeOfficial(Request $request, TournamentTeamOfficial $official): void
    {
        $team = $this->getOfficialTeam($request);
        $authorizedIds = $team->tournamentTeams()->pluck('id')->toArray();

        if (! in_array($official->tournament_team_id, $authorizedIds, true)) {
            abort(403, 'Akses official ditolak.');
        }
    }

    private function validateRoleLimit(int $tournamentTeamId, string $role, int $excludeOfficialId = null): ?string
    {
        $query = TournamentTeamOfficial::where('tournament_team_id', $tournamentTeamId);

        if ($excludeOfficialId !== null) {
            $query->where('id', '!=', $excludeOfficialId);
        }

        if ($role === 'Manager') {
            $managerCount = (clone $query)->where('role', 'Manager')->count();
            if ($managerCount >= 1) {
                return 'Manager maksimal 1 orang per tim.';
            }
        }

        if ($role === 'Coach') {
            $coachCount = (clone $query)->where('role', 'Coach')->count();
            if ($coachCount >= 1) {
                return 'Coach maksimal 1 orang per tim.';
            }
        }

        if ($role === 'Assistant Coach') {
            $assistantCount = (clone $query)->where('role', 'Assistant Coach')->count();
            if ($assistantCount >= 2) {
                return 'Assistant Coach maksimal 2 orang per tim.';
            }
        }

        return null;
    }
}
