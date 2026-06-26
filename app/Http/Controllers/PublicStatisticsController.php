<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\TournamentStatisticsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * N13 — Statistik view-only untuk Tamu/Visitor (publik, tanpa login).
 *
 * Kebijakan akses: hanya turnamen yang sudah memiliki minimal satu
 * pertandingan (jadwal/match terbuat) yang boleh dilihat publik. Turnamen
 * yang belum punya match sama sekali (belum dipublikasikan/disetel admin)
 * tetap privat. Catatan: tabel `tournaments` tidak punya kolom status, jadi
 * keberadaan match dipakai sebagai penanda turnamen sudah berjalan.
 */
class PublicStatisticsController extends Controller
{
    /**
     * Daftar turnamen yang statistiknya boleh dilihat publik.
     */
    public function index()
    {
        $tournaments = Tournament::whereHas('matches')
            ->withCount(['matches as finished_matches_count' => function ($query) {
                $query->where('status', 'full_time');
            }])
            ->latest()
            ->get(['id', 'name', 'division', 'venue', 'match_date']);

        return view('public.statistics.index', [
            'tournaments' => $tournaments,
        ]);
    }

    /**
     * Statistik satu turnamen (view-only). 404 bila turnamen belum punya match.
     */
    public function show(Tournament $tournament, TournamentStatisticsService $statistics)
    {
        if (! $tournament->matches()->exists()) {
            throw new NotFoundHttpException('Statistik turnamen ini tidak tersedia untuk publik.');
        }

        return view('public.statistics.show', array_merge(
            ['tournament' => $tournament],
            $statistics->forTournament($tournament),
        ));
    }
}
