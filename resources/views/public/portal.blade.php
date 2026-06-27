<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Futsal League</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(56,189,248,0.24),_transparent_42%),radial-gradient(circle_at_bottom_left,_rgba(168,85,247,0.18),_transparent_36%)]"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-3xl rounded-[2rem] border border-white/10 bg-slate-900/90 p-8 shadow-2xl shadow-slate-950/50 backdrop-blur-md">
            <div class="text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.35em] text-cyan-300/90">Portal Akses</p>
                <h1 class="mt-5 text-4xl font-semibold text-white sm:text-5xl">Selamat Datang di Futsal League</h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">
                    Pilih akses yang paling sesuai. Masuk sebagai admin, official tim, atau publik untuk melihat informasi turnamen.
                </p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-3">
                <a href="{{ route('login') }}" class="flex flex-col items-start justify-between rounded-3xl border border-slate-700/80 bg-slate-950/90 p-6 transition hover:-translate-y-1 hover:border-cyan-400/30 hover:bg-slate-900">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-cyan-300">Admin</p>
                        <h2 class="mt-3 text-xl font-semibold text-white">Masuk Admin</h2>
                        <p class="mt-2 text-sm text-slate-400">Kelola turnamen, tim, dan jadwal.</p>
                    </div>
                    <span class="mt-6 inline-flex rounded-full bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200">Login</span>
                </a>

                <a href="{{ route('official.login') }}" class="flex flex-col items-start justify-between rounded-3xl border border-slate-700/80 bg-slate-950/90 p-6 transition hover:-translate-y-1 hover:border-violet-400/30 hover:bg-slate-900">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-violet-300">Official Tim</p>
                        <h2 class="mt-3 text-xl font-semibold text-white">Masuk Official</h2>
                        <p class="mt-2 text-sm text-slate-400">Akses khusus untuk official tim turnamen.</p>
                    </div>
                    <span class="mt-6 inline-flex rounded-full bg-violet-500/10 px-4 py-2 text-sm font-semibold text-violet-200">Masuk</span>
                </a>

                <a href="{{ route('public.tournaments.index') }}" class="flex flex-col items-start justify-between rounded-3xl border border-slate-700/80 bg-slate-950/90 p-6 transition hover:-translate-y-1 hover:border-emerald-400/30 hover:bg-slate-900">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-emerald-300">Publik</p>
                        <h2 class="mt-3 text-xl font-semibold text-white">Portal Turnamen</h2>
                        <p class="mt-2 text-sm text-slate-400">Jadwal, klasemen, bagan, statistik & roster tanpa login.</p>
                    </div>
                    <span class="mt-6 inline-flex rounded-full bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200">Lihat</span>
                </a>
            </div>

            <div class="mt-10 border-t border-slate-700/50 pt-6 text-center text-sm text-slate-400">
                <p>Gunakan portal ini untuk memilih jenis akses. Jika belum memiliki akun, silakan hubungi administrator.</p>
            </div>
        </div>
    </div>
</body>
</html>