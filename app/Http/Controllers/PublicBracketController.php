<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\BracketViewService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tab Bagan/bracket di dalam Portal Publik per-turnamen (view-only, tanpa
 * login). Hanya menyediakan halaman bagan satu turnamen; entry point & gate
 * akses turnamen ada di PublicTournamentController. Bracket hanya tampil bila
 * turnamen punya match (N13) dan benar-benar memakai babak gugur (bukan liga).
 *
 * Susunan data bracket memakai BracketViewService yang sama dengan portal
 * Official/Manager agar konsisten (skor/agregat/penalti & posisi kartu).
 */
class PublicBracketController extends Controller
{
    public function __construct(private BracketViewService $bracketView)
    {
    }

    /**
     * Bagan satu turnamen (view-only). 404 bila turnamen belum punya match
     * atau tidak memakai babak gugur.
     */
    public function show(Tournament $tournament)
    {
        if (! $tournament->matches()->exists()) {
            throw new NotFoundHttpException('Bagan turnamen ini tidak tersedia untuk publik.');
        }

        $bracket = $this->bracketView->buildBracket($tournament);

        if ($bracket === null) {
            throw new NotFoundHttpException('Turnamen ini tidak memiliki babak gugur.');
        }

        return view('public.bracket.show', [
            'tournament' => $tournament,
            'bracket' => $bracket,
        ]);
    }
}
