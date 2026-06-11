<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | Futsal League</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-lg rounded-[2rem] border border-white/10 bg-slate-900/95 p-10 shadow-2xl shadow-slate-950/50 backdrop-blur-md">
        <div class="text-center">
            <p class="text-xs uppercase tracking-[0.35em] text-cyan-300/90">Akses Sementara</p>
            <h1 class="mt-4 text-4xl font-semibold text-white">{{ $title }}</h1>
            <p class="mt-4 text-sm leading-7 text-slate-400">{{ $description }}</p>
        </div>

        <div class="mt-10 text-center">
            <p class="text-sm text-slate-400">Halaman login ini belum diimplementasikan. Silakan kembali ke portal untuk memilih akses lain.</p>
            <a href="/" class="mt-6 inline-flex rounded-full bg-cyan-500 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400">Kembali ke Portal</a>
        </div>
    </div>
</body>
</html>