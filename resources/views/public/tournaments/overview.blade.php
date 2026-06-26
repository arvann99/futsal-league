@extends('public.tournaments.layout')

@section('title', 'Beranda - ' . $tournament->name)

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-emerald-300">Beranda Turnamen</p>
            <h1 class="mt-3 text-3xl font-semibold text-white">{{ $tournament->name }}</h1>
            <p class="mt-2 text-sm text-slate-400">
                {{ $tournament->division ?? 'Turnamen Futsal' }}@if($tournament->venue) · {{ $tournament->venue }}@endif
            </p>
        </div>

        {{-- Ringkasan angka --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-5 shadow-2xl shadow-slate-950/40">
                <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Tim Peserta</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $teams->count() }}</p>
            </div>
            <div class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-5 shadow-2xl shadow-slate-950/40">
                <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Total Laga</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $matchCounts['total'] }}</p>
            </div>
            <div class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-5 shadow-2xl shadow-slate-950/40">
                <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Laga Selesai</p>
                <p class="mt-3 text-3xl font-semibold text-white">{{ $matchCounts['finished'] }}</p>
            </div>
            <div class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-5 shadow-2xl shadow-slate-950/40">
                <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Sedang Berlangsung</p>
                <p class="mt-3 text-3xl font-semibold {{ $matchCounts['live'] > 0 ? 'text-emerald-300' : 'text-white' }}">{{ $matchCounts['live'] }}</p>
            </div>
        </div>

        {{-- Pintasan ke halaman lain --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $links = [
                    ['route' => route('public.tournaments.schedule', $tournament), 'label' => 'Jadwal', 'desc' => 'Seluruh laga turnamen'],
                    ['route' => route('public.tournaments.standings', $tournament), 'label' => 'Klasemen', 'desc' => 'Posisi tim per grup'],
                ];
                if ($hasBracket) {
                    $links[] = ['route' => route('public.bracket.show', $tournament), 'label' => 'Bracket', 'desc' => 'Bagan babak gugur'];
                }
                $links[] = ['route' => route('public.tournaments.statistics', $tournament), 'label' => 'Statistik', 'desc' => 'Top skor & kartu'];
                $links[] = ['route' => route('public.tournaments.roster', $tournament), 'label' => 'Roster', 'desc' => 'Pemain tiap tim'];
            @endphp
            @foreach($links as $link)
                <a href="{{ $link['route'] }}" class="group rounded-[2rem] border border-slate-800 bg-slate-900/95 p-5 transition hover:border-emerald-400/40 hover:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <p class="text-lg font-semibold text-white">{{ $link['label'] }}</p>
                        <svg class="w-5 h-5 text-slate-600 group-hover:text-emerald-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </div>
                    <p class="mt-1 text-sm text-slate-400">{{ $link['desc'] }}</p>
                </a>
            @endforeach
        </div>

        {{-- Daftar tim --}}
        <section class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-6 shadow-2xl shadow-slate-950/40">
            <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Tim Peserta</p>
            @if($teams->isEmpty())
                <p class="mt-4 text-sm text-slate-400">Belum ada tim terdaftar.</p>
            @else
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($teams->sortBy(fn($t) => $t->team?->name) as $tournamentTeam)
                        <div class="flex items-center gap-3 rounded-3xl bg-slate-950 px-4 py-3 border border-slate-800">
                            <div class="h-10 w-10 overflow-hidden rounded-2xl bg-slate-800 border border-slate-700 shrink-0">
                                @if($tournamentTeam->team?->logo)
                                    <img src="{{ Storage::url($tournamentTeam->team->logo) }}" alt="Logo {{ $tournamentTeam->team->name }}" class="h-full w-full object-cover" />
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-slate-500 text-sm">⚽</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-white truncate">{{ $tournamentTeam->team?->name ?? 'Tim ' . $tournamentTeam->id }}</p>
                                @if($tournamentTeam->group_label)
                                    <p class="text-xs text-slate-500">Grup {{ $tournamentTeam->group_label }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
