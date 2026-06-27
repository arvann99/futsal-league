@extends('official.layouts.app')

@section('title', 'Statistik')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-violet-300">Statistik</p>
                <h1 class="mt-3 text-3xl font-semibold text-white">Statistik Pemain & Tim</h1>
                <p class="mt-2 text-sm text-slate-400">Top skor, assist, kartu, dan statistik tim untuk turnamen yang Anda ikuti.</p>
            </div>
            <a href="{{ route('official.dashboard') }}" class="rounded-2xl border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-200 hover:border-violet-400 hover:text-white transition">
                Kembali ke Beranda
            </a>
        </div>

        @if($reports->isEmpty())
            <div class="rounded-[2rem] border border-slate-800 bg-slate-950/95 p-10 text-center text-slate-400">
                <p class="text-xl font-semibold text-white">Belum ada data statistik.</p>
                <p class="mt-2 text-sm">Statistik akan muncul setelah pertandingan turnamen Anda dimulai.</p>
            </div>
        @else
            @foreach($reports as $report)
                <section class="rounded-[2rem] border border-slate-800 bg-slate-900/60 p-5 sm:p-6 shadow-2xl shadow-slate-950/40">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Turnamen</p>
                            <h2 class="mt-2 text-2xl font-semibold text-white">{{ $report['tournament']->name }}</h2>
                        </div>
                    </div>

                    @include('partials.statistics.body', $report['stats'])
                </section>
            @endforeach
        @endif
    </div>
@endsection
