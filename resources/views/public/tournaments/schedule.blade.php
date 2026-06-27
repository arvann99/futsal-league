@extends('public.tournaments.layout')

@section('title', 'Jadwal - ' . $tournament->name)

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-emerald-300">Jadwal</p>
                <h1 class="mt-3 text-3xl font-semibold text-white">Jadwal {{ $tournament->name }}</h1>
                <p class="mt-2 text-sm text-slate-400">Seluruh pertandingan turnamen ({{ $totalMatches }} laga).</p>
            </div>
        </div>

        {{-- Filter status (read-only links) --}}
        <form method="GET" action="{{ route('public.tournaments.schedule', $tournament) }}" class="flex flex-wrap gap-2">
            @php $filters = ['all' => 'Semua', 'upcoming' => 'Akan Datang', 'finished' => 'Selesai', 'tbd' => 'Belum Dijadwalkan']; @endphp
            @foreach($filters as $key => $label)
                <button type="submit" name="filter" value="{{ $key }}"
                        class="rounded-full px-5 py-2.5 text-sm font-semibold transition {{ $filter === $key ? 'bg-emerald-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </form>

        @if(count($matches) === 0)
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center text-slate-400">
                <p class="text-lg font-semibold text-white">Tidak ada pertandingan.</p>
                <p class="mt-2 text-sm">Tidak ada laga yang cocok dengan filter ini.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($matches as $match)
                    @php
                        $homeName = $match->homeTeam?->team?->name ?? $match->home_team_key ?? $match->source_home ?? 'TBD';
                        $awayName = $match->awayTeam?->team?->name ?? $match->away_team_key ?? $match->source_away ?? 'TBD';
                        $score = is_null($match->home_score) || is_null($match->away_score)
                            ? '-' : "{$match->home_score} - {$match->away_score}";
                        $statusLabel = $match->status === 'scheduled' ? 'Akan Datang' : ($match->status === 'live_match' ? 'Live' : ucfirst(str_replace('_', ' ', $match->status)));
                        $statusClass = $match->status === 'live_match' ? 'bg-emerald-500 text-emerald-100' : ($match->status === 'scheduled' ? 'bg-slate-800 text-slate-200' : 'bg-slate-700 text-slate-200');

                        // Label babak: utamakan nama ronde (Quarterfinal/Semifinal/Final
                        // untuk gugur, Matchday N untuk grup). Sertakan label grup bila
                        // ada; fallback ke stage_type bila round_name kosong.
                        $roundParts = [];
                        if ($match->group_label) {
                            $roundParts[] = 'Grup ' . $match->group_label;
                        }
                        if ($match->round_name) {
                            $roundParts[] = $match->round_name;
                        } elseif ($match->stage_type) {
                            $roundParts[] = ucfirst(str_replace('_', ' ', $match->stage_type));
                        }
                        $roundLabel = implode(' · ', $roundParts);
                        if ($match->leg) {
                            $roundLabel .= ' · Leg ' . $match->leg;
                        }
                    @endphp
                    <article class="rounded-[2rem] border border-slate-800 bg-slate-950 p-5 shadow-2xl shadow-slate-950/40">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                @if($roundLabel)
                                    <p class="text-xs uppercase tracking-[0.35em] text-slate-500">{{ $roundLabel }}</p>
                                @endif
                                <h3 class="mt-2 text-xl font-semibold text-white">{{ $homeName }} <span class="text-slate-600">vs</span> {{ $awayName }}</h3>
                            </div>
                            <span class="inline-flex shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                        </div>
                        <div class="mt-5 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-3xl bg-slate-900 p-4">
                                <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Tanggal</p>
                                @if($match->match_date)
                                    <p class="mt-2 text-lg font-semibold text-white">{{ $match->match_date->format('d M Y') }}</p>
                                    <p class="text-sm text-slate-400">{{ $match->match_date->format('H:i') }}</p>
                                @else
                                    <p class="mt-2 text-lg font-semibold text-amber-300">Belum dijadwalkan</p>
                                    <p class="text-sm text-slate-400">Menunggu jadwal dari panitia</p>
                                @endif
                            </div>
                            <div class="rounded-3xl bg-slate-900 p-4">
                                <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Skor</p>
                                <p class="mt-2 text-lg font-semibold text-white tabular-nums">{{ $score }}</p>
                            </div>
                            <div class="rounded-3xl bg-slate-900 p-4">
                                <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Venue</p>
                                <p class="mt-2 text-base font-semibold text-white">{{ $match->venue ?? 'Belum ditetapkan' }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
