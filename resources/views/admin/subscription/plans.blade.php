@extends('admin.layouts.app')

@section('title', 'Paket Langganan | Futsal League')

@section('body')
    @php
        $planMeta = [
            'free'     => ['label' => 'Free', 'color' => 'slate', 'price' => 0, 'desc' => 'Untuk mencoba'],
            'pro'      => ['label' => 'Pro', 'color' => 'indigo', 'price' => $prices['pro'] ?? 50000, 'desc' => 'Untuk penyelenggara aktif'],
            'ultimate' => ['label' => 'Ultimate', 'color' => 'amber', 'price' => $prices['ultimate'] ?? 150000, 'desc' => 'Tanpa batas'],
        ];
        $rank = ['free' => 0, 'pro' => 1, 'ultimate' => 2];
        $fmt = fn ($n) => $n === null ? 'Tanpa batas' : $n;
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    @endphp

    <header class="border-b border-slate-800 bg-slate-900 bg-opacity-50 backdrop-blur sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <p class="text-xs sm:text-sm text-indigo-400 font-semibold mb-1">LANGGANAN</p>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">Paket Langganan</h1>
                <p class="text-slate-400 text-sm mt-1">Paket Anda saat ini: <span class="font-semibold text-white">{{ $planMeta[$user->plan]['label'] ?? ucfirst($user->plan) }}</span></p>
            </div>
            <a href="{{ route('tournaments.index') }}" class="px-5 py-3 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-xl transition">Kembali</a>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 text-white">
        @if(session('success'))
            <div class="mb-6 rounded-xl bg-emerald-900/20 border border-emerald-500/30 p-4 text-emerald-200">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-6 rounded-xl bg-rose-900/20 border border-rose-500/30 p-4 text-rose-200">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 rounded-xl bg-rose-900/20 border border-rose-500/30 p-4 text-rose-200">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Kuota terpakai --}}
        <div class="mb-8 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-xs text-slate-400 uppercase tracking-wider">Turnamen Terpakai</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $usage['tournaments'] }} / {{ $fmt($usage['tournament_limit']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-xs text-slate-400 uppercase tracking-wider">Maks Tim / Turnamen</p>
                <p class="text-2xl font-bold text-white mt-1">{{ $fmt($usage['team_limit']) }}</p>
            </div>
        </div>

        @if($pendingRequest)
            <div class="mb-8 rounded-xl bg-amber-900/20 border border-amber-500/30 p-4 text-amber-200">
                ⏳ Permintaan upgrade ke <strong>{{ $planMeta[$pendingRequest->requested_plan]['label'] ?? $pendingRequest->requested_plan }}</strong> sedang menunggu persetujuan admin root (dikirim {{ $pendingRequest->created_at->diffForHumans() }}).
            </div>
        @endif

        {{-- Kartu paket --}}
        <div class="grid gap-6 md:grid-cols-3">
            @foreach($planMeta as $key => $meta)
                @php
                    $isCurrent = $user->plan === $key;
                    $limits = $plans[$key] ?? ['tournaments' => null, 'teams' => null];
                    $isUpgrade = ($rank[$key] ?? 0) > ($rank[$user->plan] ?? 0);
                @endphp
                <div class="rounded-2xl border p-6 flex flex-col {{ $isCurrent ? 'border-indigo-500 bg-indigo-950/30' : 'border-slate-800 bg-slate-900/80' }}">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-xl font-bold text-white">{{ $meta['label'] }}</h3>
                        @if($isCurrent)
                            <span class="text-xs font-semibold px-2 py-1 rounded-full bg-indigo-600 text-white">Paket Anda</span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-400 mb-3">{{ $meta['desc'] }}</p>
                    <p class="text-2xl font-bold text-white mb-4">{{ $meta['price'] ? $rp($meta['price']) : 'Gratis' }}<span class="text-sm font-normal text-slate-400">{{ $meta['price'] ? ' / bulan' : '' }}</span></p>
                    <ul class="space-y-2 text-sm text-slate-300 mb-6 flex-1">
                        <li>✅ {{ $fmt($limits['tournaments']) }} turnamen</li>
                        <li>✅ {{ $fmt($limits['teams']) }} tim per turnamen</li>
                    </ul>

                    @if($isCurrent)
                        <button disabled class="w-full py-3 rounded-xl bg-slate-700 text-slate-400 font-semibold cursor-default">Paket Aktif</button>
                    @elseif($isUpgrade && ! $pendingRequest)
                        <form action="{{ route('subscription.upgrade') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <input type="hidden" name="requested_plan" value="{{ $key }}">
                            <div class="text-xs text-slate-400 rounded-lg bg-slate-950/60 border border-slate-700 p-3">
                                Transfer ke <strong class="text-slate-200">BCA 1234567890 a.n. Futsal League</strong> sebesar <strong class="text-slate-200">{{ $rp($meta['price']) }}</strong>, lalu unggah bukti.
                            </div>
                            <input type="file" name="payment_proof" required accept=".jpg,.jpeg,.png,.webp"
                                class="w-full text-sm text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-3 file:py-2 file:text-white">
                            <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold transition">Upgrade ke {{ $meta['label'] }}</button>
                        </form>
                    @elseif($isUpgrade && $pendingRequest)
                        <button disabled class="w-full py-3 rounded-xl bg-slate-700 text-slate-400 font-semibold cursor-default">Menunggu persetujuan…</button>
                    @else
                        <button disabled class="w-full py-3 rounded-xl bg-slate-800 text-slate-500 font-semibold cursor-default">—</button>
                    @endif
                </div>
            @endforeach
        </div>
    </main>
@endsection
