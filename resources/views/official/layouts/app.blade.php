<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Official Tim') | Futsal League</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.88); backdrop-filter: blur(18px); }
    </style>
    @stack('styles')
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <div class="min-h-screen pb-28">
        <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-950/95 backdrop-blur-sm">
            <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-3xl bg-slate-900 border border-slate-800 text-violet-300 text-2xl">
                        ⚽
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Official Team</p>
                        <h1 class="text-lg font-semibold text-white">{{ $team->name ?? 'Official' }}</h1>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-3">
                    @if(isset($team) && $team->logo)
                        <img src="{{ Storage::url($team->logo) }}" alt="Logo {{ $team->name }}" class="h-12 w-12 rounded-3xl object-cover border border-slate-800" />
                    @endif
                    <form action="{{ route('official.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="rounded-2xl border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:border-violet-400 hover:bg-slate-800">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-4 py-6">
            @if(session('error'))
                <div class="mb-4 rounded-2xl border border-rose-500/30 bg-rose-900/20 p-4 text-sm text-rose-200">
                    {{ session('error') }}
                </div>
            @endif
            @if(session('success'))
                <div class="mb-4 rounded-2xl border border-emerald-500/30 bg-emerald-900/20 p-4 text-sm text-emerald-200">
                    {{ session('success') }}
                </div>
            @endif
            @if(isset($team) && ($team->verification_status ?? 'pending') === 'approved' && (request()->routeIs('official.players.*') || request()->routeIs('official.officials.*')))
                <div class="mb-4 rounded-2xl border border-amber-500/30 bg-amber-900/20 p-4 text-sm text-amber-200">
                    🔒 Data tim Anda sudah <strong>diverifikasi</strong> oleh panitia dan dikunci. Penambahan/perubahan pemain & ofisial dinonaktifkan.
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-800 bg-slate-950/95 backdrop-blur-sm">
        <div class="max-w-6xl mx-auto px-4 py-3">
            <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                <a href="{{ route('official.dashboard') }}" class="rounded-3xl px-3 py-3 text-center text-xs font-semibold {{ request()->routeIs('official.dashboard') ? 'bg-violet-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800' }}">
                    Beranda
                </a>
                <a href="{{ route('official.players.index') }}" class="rounded-3xl px-3 py-3 text-center text-xs font-semibold {{ request()->routeIs('official.players.*') ? 'bg-violet-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800' }}">
                    Pemain
                </a>
                <a href="{{ route('official.officials.index') }}" class="rounded-3xl px-3 py-3 text-center text-xs font-semibold {{ request()->routeIs('official.officials.*') ? 'bg-violet-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800' }}">
                    Official
                </a>
                <a href="{{ route('official.schedule') }}" class="rounded-3xl px-3 py-3 text-center text-xs font-semibold {{ request()->routeIs('official.schedule') ? 'bg-violet-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800' }}">
                    Jadwal
                </a>
                <a href="{{ route('official.standings') }}" class="rounded-3xl px-3 py-3 text-center text-xs font-semibold {{ request()->routeIs('official.standings') ? 'bg-violet-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800' }}">
                    Klasemen
                </a>
                <a href="{{ route('official.bracket') }}" class="rounded-3xl px-3 py-3 text-center text-xs font-semibold {{ request()->routeIs('official.bracket') ? 'bg-violet-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800' }}">
                    Bracket
                </a>
                <a href="{{ route('official.statistics') }}" class="rounded-3xl px-3 py-3 text-center text-xs font-semibold {{ request()->routeIs('official.statistics') ? 'bg-violet-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800' }}">
                    Statistik
                </a>
                <span class="rounded-3xl bg-slate-900 px-3 py-3 text-center text-xs font-semibold text-slate-500 opacity-60">Profil</span>
            </div>
        </div>
    </nav>
    @stack('scripts')
</body>
</html>
