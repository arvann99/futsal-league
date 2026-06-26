@extends('public.layouts.app')

@section('title', 'Statistik - ' . $tournament->name)

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-emerald-300">Statistik Turnamen</p>
            <h1 class="mt-3 text-3xl font-semibold text-white">{{ $tournament->name }}</h1>
            @if($tournament->division)
                <p class="mt-2 text-sm text-slate-400">{{ $tournament->division }}</p>
            @endif
        </div>
        <a href="{{ route('public.statistics.index') }}" class="rounded-2xl border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-200 hover:border-emerald-400 hover:text-white transition self-start">
            Kembali ke Daftar
        </a>
    </div>

    @include('partials.statistics.body')
@endsection
