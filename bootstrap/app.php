<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // R21 — alias middleware kepemilikan turnamen/tim per admin.
        // R22 — alias middleware admin root (ACC pembayaran).
        $middleware->alias([
            'owns' => \App\Http\Middleware\EnsureResourceOwnership::class,
            'root' => \App\Http\Middleware\EnsureRoot::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
