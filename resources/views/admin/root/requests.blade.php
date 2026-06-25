@extends('admin.layouts.app')

@section('title', 'ACC Pembayaran | Root')

@section('body')
    @php
        $statusBadge = [
            'pending'  => 'bg-amber-700/40 text-amber-200',
            'approved' => 'bg-emerald-700/40 text-emerald-200',
            'rejected' => 'bg-rose-700/40 text-rose-200',
        ];
        $rp = fn ($n) => $n ? ('Rp ' . number_format($n, 0, ',', '.')) : '-';
    @endphp

    <header class="border-b border-slate-800 bg-slate-900 bg-opacity-50 backdrop-blur sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <p class="text-xs sm:text-sm text-amber-400 font-semibold mb-1">ADMIN ROOT</p>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">ACC Pembayaran Upgrade</h1>
                <p class="text-slate-400 text-sm mt-1">{{ $pendingCount }} permintaan menunggu peninjauan</p>
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

        {{-- filter --}}
        <div class="mb-6 flex flex-wrap gap-2">
            @foreach(['' => 'Semua', 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $val => $label)
                <a href="{{ route('root.requests', array_filter(['status' => $val])) }}"
                   class="px-4 py-2 rounded-lg text-sm font-semibold transition {{ (string)($status ?? '') === (string)$val ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if($requests->isEmpty())
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-10 text-center text-slate-400">
                Belum ada permintaan upgrade.
            </div>
        @else
            <div class="space-y-3">
                @foreach($requests as $req)
                    <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex-1 min-w-[200px]">
                                <p class="text-white font-semibold">{{ $req->user->name ?? 'N/A' }}</p>
                                <p class="text-xs text-slate-400">{{ $req->user->email ?? '-' }}</p>
                                <p class="text-sm text-slate-300 mt-1">
                                    Minta: <strong class="text-indigo-300">{{ ucfirst($req->requested_plan) }}</strong>
                                    · {{ $rp($req->amount) }}
                                    · {{ $req->created_at->format('d M Y H:i') }}
                                </p>
                                @if($req->status !== 'pending')
                                    <p class="text-xs text-slate-500 mt-1">
                                        Ditinjau {{ optional($req->reviewed_at)->format('d M Y H:i') }} oleh {{ $req->reviewer->name ?? '-' }}
                                        @if($req->note) — catatan: "{{ $req->note }}" @endif
                                    </p>
                                @endif
                            </div>

                            <a href="{{ route('root.requests.proof', $req) }}" target="_blank"
                               class="shrink-0 px-3 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-white text-sm font-medium">Lihat Bukti TF</a>

                            <span class="shrink-0 inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] {{ $statusBadge[$req->status] ?? 'bg-slate-700 text-slate-200' }}">
                                {{ $req->status }}
                            </span>

                            @if($req->status === 'pending')
                                <div class="flex items-center gap-2 shrink-0">
                                    <form action="{{ route('root.requests.approve', $req) }}" method="POST" onsubmit="return confirm('Setujui pembayaran ini & naikkan paket?');">
                                        @csrf
                                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 rounded-lg text-white text-sm font-semibold transition">Setujui</button>
                                    </form>
                                    <form action="{{ route('root.requests.reject', $req) }}" method="POST" class="flex items-center gap-2" onsubmit="return confirm('Tolak pembayaran ini?');">
                                        @csrf
                                        <input type="text" name="note" placeholder="Alasan (opsional)" class="rounded-lg bg-slate-800 border border-slate-700 text-slate-200 text-sm px-2 py-2 w-40">
                                        <button type="submit" class="px-4 py-2 bg-rose-600/80 hover:bg-rose-600 rounded-lg text-white text-sm font-semibold transition">Tolak</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </main>
@endsection
