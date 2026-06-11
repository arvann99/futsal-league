<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Official Tim | Futsal League</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-lg rounded-[2rem] border border-slate-800 bg-slate-900/95 p-8 shadow-2xl shadow-slate-950/40 backdrop-blur-md">
        <div class="text-center mb-8">
            <p class="text-xs uppercase tracking-[0.35em] text-violet-300/90">Official Tim</p>
            <h1 class="mt-4 text-3xl font-semibold text-white">Masuk Official Tim</h1>
            <p class="mt-3 text-sm text-slate-400">Gunakan manager token tim untuk mengakses dashboard official.</p>
        </div>

        <form action="{{ route('official.login.submit') }}" method="POST" class="space-y-5">
            @csrf

            @if($errors->has('manager_token'))
                <div class="rounded-2xl bg-red-500/10 border border-red-500/20 p-3 text-sm text-red-200">
                    {{ $errors->first('manager_token') }}
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Manager Token</label>
                <input type="text" name="manager_token" value="{{ old('manager_token') }}" placeholder="ARVAN-1280" required
                    class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-500/20 transition"/>
            </div>

            <button type="submit" class="w-full rounded-2xl bg-violet-500 px-5 py-3 text-sm font-semibold text-white transition hover:bg-violet-400">
                Masuk dengan Token
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-slate-500">
            <a href="{{ route('portal') }}" class="text-slate-300 hover:text-white">Kembali ke Portal</a>
        </div>
    </div>
</body>
</html>
