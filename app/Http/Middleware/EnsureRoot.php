<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * R22 — Hanya admin root aplikasi yang boleh mengakses area ini
 * (mis. peninjauan & ACC pembayaran upgrade paket).
 */
class EnsureRoot
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check() || ! Auth::user()->is_root) {
            abort(403, 'Halaman ini hanya untuk admin root aplikasi.');
        }

        return $next($request);
    }
}
