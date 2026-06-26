<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use App\Services\BracketViewService;
use Illuminate\Http\Request;

/**
 * N4 — Portal Official/Manager dapat melihat bagan/bracket (read-only) untuk
 * setiap turnamen yang diikuti timnya. Tidak ada aksi simpan/assign.
 */
class OfficialBracketController extends Controller
{
    public function __construct(private BracketViewService $bracketView)
    {
    }

    public function index(Request $request)
    {
        $team = Team::find($request->session()->get('official_team_id'));

        if (! $team) {
            return redirect()->route('official.login');
        }

        $tournamentIds = $team->tournamentTeams()->pluck('tournament_id')->unique();

        $brackets = $tournamentIds
            ->map(fn ($id) => Tournament::with('groupSetting')->find($id))
            ->filter()
            ->map(fn (Tournament $tournament) => $this->buildBracketFor($tournament, $team))
            ->filter()
            ->values();

        return view('official.bracket', [
            'team' => $team,
            'brackets' => $brackets,
        ]);
    }

    /**
     * Susun data bracket satu turnamen, atau null bila turnamen tidak memakai
     * babak gugur (mis. Liga murni / belum ada struktur bracket).
     */
    private function buildBracketFor(Tournament $tournament, Team $team): ?array
    {
        $bracket = $this->bracketView->buildBracket($tournament);

        if ($bracket === null) {
            return null;
        }

        // Konteks tim untuk badge "Tim Anda" (khusus portal Official).
        $bracket['team_id'] = $team->id;

        return $bracket;
    }
}
