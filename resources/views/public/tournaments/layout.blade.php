<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $tournament->name) | Futsal League</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
    @stack('styles')
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <div class="min-h-screen pb-28">
        <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-950/95 backdrop-blur-sm">
            <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-3xl bg-slate-900 border border-slate-800 text-emerald-300 text-2xl shrink-0">
                        ⚽
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.35em] text-emerald-400">Publik · Turnamen</p>
                        <h1 class="text-lg font-semibold text-white truncate">{{ $tournament->name }}</h1>
                    </div>
                </div>
                <a href="{{ route('public.tournaments.index') }}" class="shrink-0 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:border-emerald-400 hover:bg-slate-800">
                    Daftar Turnamen
                </a>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-4 py-6">
            <div class="mb-4 rounded-2xl border border-emerald-500/20 bg-emerald-900/10 px-4 py-3 text-xs text-emerald-200/90">
                👁️ Tampilan publik hanya-baca. Pengelolaan tim, pemain, dan skor dilakukan oleh panitia/manajer.
            </div>
            @yield('content')
        </main>
    </div>

    {{-- Navigasi tab portal turnamen (mirror struktur Manager, tanpa aksi kelola) --}}
    <nav class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-800 bg-slate-950/95 backdrop-blur-sm">
        <div class="max-w-6xl mx-auto px-4 py-3">
            @php
                $tabBase = 'rounded-3xl px-3 py-3 text-center text-xs font-semibold transition';
                $tabOn = 'bg-emerald-500 text-white';
                $tabOff = 'bg-slate-900 text-slate-300 hover:bg-slate-800';
                // Tab Bracket hanya muncul bila turnamen memakai babak gugur, agar
                // tidak menautkan ke halaman 404 untuk liga murni. Memakai gate
                // yang SAMA dengan render bagan (buildBracket) supaya konsisten.
                $showBracketTab = app(\App\Services\BracketViewService::class)->hasRenderableBracket($tournament);
                $tabGridCols = $showBracketTab ? 'sm:grid-cols-6' : 'sm:grid-cols-5';
            @endphp
            <div class="grid grid-cols-3 gap-2 {{ $tabGridCols }}">
                <a href="{{ route('public.tournaments.overview', $tournament) }}"
                   class="{{ $tabBase }} {{ request()->routeIs('public.tournaments.overview') ? $tabOn : $tabOff }}">Beranda</a>
                <a href="{{ route('public.tournaments.schedule', $tournament) }}"
                   class="{{ $tabBase }} {{ request()->routeIs('public.tournaments.schedule') ? $tabOn : $tabOff }}">Jadwal</a>
                <a href="{{ route('public.tournaments.standings', $tournament) }}"
                   class="{{ $tabBase }} {{ request()->routeIs('public.tournaments.standings') ? $tabOn : $tabOff }}">Klasemen</a>
                @if($showBracketTab)
                    <a href="{{ route('public.bracket.show', $tournament) }}"
                       class="{{ $tabBase }} {{ request()->routeIs('public.bracket.show') ? $tabOn : $tabOff }}">Bracket</a>
                @endif
                <a href="{{ route('public.tournaments.statistics', $tournament) }}"
                   class="{{ $tabBase }} {{ request()->routeIs('public.tournaments.statistics') ? $tabOn : $tabOff }}">Statistik</a>
                <a href="{{ route('public.tournaments.roster', $tournament) }}"
                   class="{{ $tabBase }} {{ request()->routeIs('public.tournaments.roster') ? $tabOn : $tabOff }}">Roster</a>
            </div>
        </div>
    </nav>
    @stack('scripts')
</body>
</html>
