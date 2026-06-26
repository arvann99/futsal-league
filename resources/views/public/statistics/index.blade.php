@extends('public.layouts.app')

@section('title', 'Statistik Turnamen')

@section('content')
    <div class="mb-6">
        <p class="text-xs uppercase tracking-[0.35em] text-emerald-300">Statistik Publik</p>
        <h1 class="mt-3 text-3xl font-semibold text-white">Statistik Turnamen</h1>
        <p class="mt-2 text-sm text-slate-400">Pilih turnamen untuk melihat top skor, kartu, dan statistik tim.</p>
    </div>

    @if($tournaments->isEmpty())
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-10 text-center text-slate-400">
            <p class="text-lg font-semibold text-white">Belum ada turnamen yang dapat dilihat.</p>
            <p class="mt-2 text-sm">Statistik akan tersedia setelah turnamen memiliki jadwal pertandingan.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($tournaments as $tournament)
                <a href="{{ route('public.statistics.show', $tournament) }}"
                   class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-5 transition hover:-translate-y-0.5 hover:border-emerald-400/40 hover:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-white truncate">{{ $tournament->name }}</h2>
                            @if($tournament->division)
                                <p class="text-xs text-slate-400 mt-1">{{ $tournament->division }}</p>
                            @endif
                        </div>
                        <svg class="w-5 h-5 shrink-0 text-slate-600 group-hover:text-emerald-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs">
                        @if($tournament->finished_matches_count > 0)
                            <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-3 py-1 font-semibold text-emerald-300">
                                {{ $tournament->finished_matches_count }} laga selesai
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-800 px-3 py-1 font-semibold text-slate-400">
                                Belum ada hasil
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
