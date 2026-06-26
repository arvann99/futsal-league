@extends('public.tournaments.layout')

@section('title', 'Statistik - ' . $tournament->name)

@section('content')
    <div class="mb-6">
        <p class="text-xs uppercase tracking-[0.35em] text-emerald-300">Statistik</p>
        <h1 class="mt-3 text-3xl font-semibold text-white">Statistik {{ $tournament->name }}</h1>
        <p class="mt-2 text-sm text-slate-400">Top skor, kartu, dan statistik tim turnamen.</p>
    </div>

    @include('partials.statistics.body')
@endsection
