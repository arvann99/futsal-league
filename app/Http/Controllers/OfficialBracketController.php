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
        $setting = $this->bracketView->bracketSetting($tournament);
        $settingValue = $setting?->value ?? [];
        $competitionType = $settingValue['competition_type'] ?? 'tournament';
        $playoffOptions = $settingValue['playoff_options'] ?? [];

        // Liga murni tidak punya bracket.
        if ($competitionType === 'league') {
            return null;
        }

        // league_playoff hanya punya bracket bila salah satu opsi playoff aktif.
        $hasPromotion = in_array('promotion', $playoffOptions, true);
        $hasRelegation = in_array('relegation', $playoffOptions, true);
        if ($competitionType === 'league_playoff' && ! $hasPromotion && ! $hasRelegation) {
            return null;
        }

        // Tentukan mode (untuk league_playoff dengan kedua opsi → default promosi).
        $playoffMode = null;
        if ($competitionType === 'league_playoff') {
            $playoffMode = $hasPromotion ? 'promotion' : 'relegation';
        }

        $built = $this->bracketView->columns($settingValue, $playoffMode);

        if (empty($built['columns'])) {
            return null;
        }

        return [
            'tournament' => $tournament,
            'competition_type' => $competitionType,
            'columns' => $built['columns'],
            'third_place_round' => $built['thirdPlaceRound'],
            'card_tops' => $built['cardTops'],
            'canvas_height' => $built['canvasHeight'],
            'row_unit' => $built['rowUnit'],
            'header_height' => $built['headerHeight'],
            'assigned_matches' => $this->bracketView->assignedMatches($tournament, $playoffMode),
            'scores' => $this->bracketView->scoreSummaries($tournament, $playoffMode),
            'team_id' => $team->id,
        ];
    }
}
