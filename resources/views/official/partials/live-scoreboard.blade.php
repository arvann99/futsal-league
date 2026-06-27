{{--
    N7 — Scoreboard tim yang SEDANG bertanding (live).
    Membutuhkan: $liveMatches (collection TournamentMatch), $teamTournamentTeamIds (array).
--}}
<section class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-6 shadow-2xl shadow-slate-950/40">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-emerald-300">Sedang Bertanding</p>
            <h2 class="mt-3 text-2xl font-semibold text-white">Live Scoreboard</h2>
        </div>
        @if($liveMatches->isNotEmpty())
            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-3 py-2 text-xs font-semibold text-emerald-300">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                </span>
                {{ $liveMatches->count() }} laga berlangsung
            </span>
        @endif
    </div>

    @if($liveMatches->isEmpty())
        <div class="mt-6 rounded-[2rem] border border-slate-800 bg-slate-950 p-8 text-center text-slate-400">
            <p class="text-lg font-semibold text-white">Tidak ada pertandingan yang sedang berlangsung.</p>
            <p class="mt-2 text-sm">Scoreboard akan muncul saat ada laga berstatus Live di turnamen Anda.</p>
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach($liveMatches as $live)
                @php
                    $homeName = $live->homeTeam?->team?->name ?? $live->home_team_key ?? $live->source_home ?? 'TBD';
                    $awayName = $live->awayTeam?->team?->name ?? $live->away_team_key ?? $live->source_away ?? 'TBD';
                    $homeScore = $live->home_score ?? 0;
                    $awayScore = $live->away_score ?? 0;
                    $isShootout = $live->status === 'penalty_shootout';
                    $homeMine = in_array($live->home_team_id, $teamTournamentTeamIds, true);
                    $awayMine = in_array($live->away_team_id, $teamTournamentTeamIds, true);
                @endphp
                <article class="rounded-[2rem] border border-emerald-500/30 bg-slate-950 p-5 shadow-2xl shadow-emerald-950/20">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-500">{{ $live->tournament?->name ?? 'Turnamen' }}</p>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            {{ $isShootout ? 'Adu Penalti' : 'LIVE' }}
                        </span>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div class="flex-1 text-center">
                            <p class="text-sm font-semibold {{ $homeMine ? 'text-violet-300' : 'text-white' }}">{{ $homeName }}</p>
                            @if($homeMine)<span class="mt-1 inline-flex rounded-full bg-violet-500/20 px-2 py-0.5 text-[9px] font-semibold text-violet-200">Tim Anda</span>@endif
                        </div>
                        <div class="shrink-0 px-3">
                            <p class="text-3xl font-bold tabular-nums text-white">{{ $homeScore }} <span class="text-slate-600">:</span> {{ $awayScore }}</p>
                            @if($isShootout)
                                <p class="mt-1 text-center text-[11px] font-semibold text-amber-300">Pen {{ $live->home_penalty_score ?? 0 }} - {{ $live->away_penalty_score ?? 0 }}</p>
                            @endif
                        </div>
                        <div class="flex-1 text-center">
                            <p class="text-sm font-semibold {{ $awayMine ? 'text-violet-300' : 'text-white' }}">{{ $awayName }}</p>
                            @if($awayMine)<span class="mt-1 inline-flex rounded-full bg-violet-500/20 px-2 py-0.5 text-[9px] font-semibold text-violet-200">Tim Anda</span>@endif
                        </div>
                    </div>

                    <p class="mt-4 text-center text-xs text-slate-500">{{ $live->venue ?? 'Lokasi belum ditetapkan' }}</p>
                </article>
            @endforeach
        </div>
    @endif
</section>
