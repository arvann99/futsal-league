<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\Tournament;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * R21 — Pastikan admin yang login hanya bisa mengakses turnamen/tim miliknya.
 * Memeriksa route-model-bound {tournament} dan {team} bila ada.
 */
class EnsureResourceOwnership
{
    public function handle(Request $request, Closure $next)
    {
        $userId = Auth::id();

        $tournament = $request->route('tournament');
        if ($tournament instanceof Tournament && $tournament->created_by !== $userId) {
            abort(403, 'Anda tidak memiliki akses ke turnamen ini.');
        }

        // Tim tanpa created_by dianggap bukan milik siapa pun (pasca-R21 semua
        // tim punya owner via backfill) → akses ditolak, kecuali pemiliknya.
        $team = $request->route('team');
        if ($team instanceof Team && $team->created_by !== $userId) {
            abort(403, 'Anda tidak memiliki akses ke tim ini.');
        }

        return $next($request);
    }
}
