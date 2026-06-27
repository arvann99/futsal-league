@extends('official.layouts.app')

@section('title', 'Jadwal')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-violet-300">Jadwal Tim</p>
                <h1 class="mt-3 text-3xl font-semibold text-white">Jadwal Official {{ $team->name }}</h1>
                <p class="mt-2 text-sm text-slate-400">
                    {{ $scope === 'tournament'
                        ? 'Menampilkan seluruh jadwal pertandingan dari semua tim yang berpartisipasi.'
                        : 'Menampilkan jadwal bertanding tim Anda.' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('official.dashboard') }}" class="rounded-2xl border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-200 hover:border-violet-400 hover:text-white transition">
                    Kembali ke Beranda
                </a>
            </div>
        </div>

        {{-- N11 — dua tab: Jadwal Internal (tim sendiri) vs Jadwal Turnamen (semua tim) --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('official.schedule', ['scope' => 'internal', 'filter' => $filter]) }}"
               class="rounded-full px-5 py-2.5 text-sm font-semibold transition {{ $scope === 'internal' ? 'bg-violet-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-800' }}">
                Jadwal Internal
            </a>
            <a href="{{ route('official.schedule', ['scope' => 'tournament', 'filter' => $filter]) }}"
               class="rounded-full px-5 py-2.5 text-sm font-semibold transition {{ $scope === 'tournament' ? 'bg-violet-500 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800 border border-slate-800' }}">
                Jadwal Turnamen
            </a>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
            <div class="space-y-6">
                <section class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-6 shadow-2xl shadow-slate-950/40">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Pertandingan Berikutnya</p>
                            <h2 class="mt-3 text-2xl font-semibold text-white">Next Match</h2>
                        </div>
                        <span class="rounded-full bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-300">Hanya tim Anda</span>
                    </div>

                    @if($nextMatch)
                        @php
                            $isHome = in_array($nextMatch->home_team_id, $teamTournamentTeamIds, true);
                            $opponent = $isHome
                                ? ($nextMatch->awayTeam?->team?->name ?? $nextMatch->away_team_key ?? $nextMatch->source_away ?? 'TBD')
                                : ($nextMatch->homeTeam?->team?->name ?? $nextMatch->home_team_key ?? $nextMatch->source_home ?? 'TBD');
                            $matchLabel = $isHome ? 'Home' : 'Away';
                        @endphp
                        <div class="mt-6 rounded-[2rem] border border-slate-800 bg-slate-950 p-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <p class="text-sm text-slate-400">Turnamen</p>
                                    <p class="text-xl font-semibold text-white">{{ $nextMatch->tournament?->name ?? 'Turnamen tidak tersedia' }}</p>
                                </div>
                                <div class="space-y-2">
                                    <p class="text-sm text-slate-400">Status</p>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold text-slate-200 bg-slate-800">
                                        {{ ucfirst(str_replace('_', ' ', $nextMatch->status)) }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-3xl bg-slate-900 p-4">
                                    <p class="text-sm uppercase tracking-[0.35em] text-slate-500">Tanggal</p>
                                    <p class="mt-2 text-lg font-semibold text-white">{{ optional($nextMatch->match_date)->format('d M Y') }}</p>
                                </div>
                                <div class="rounded-3xl bg-slate-900 p-4">
                                    <p class="text-sm uppercase tracking-[0.35em] text-slate-500">Jam</p>
                                    <p class="mt-2 text-lg font-semibold text-white">{{ optional($nextMatch->match_date)->format('H:i') }}</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4">
                                <div class="rounded-3xl bg-slate-800/80 p-4">
                                    <p class="text-sm uppercase tracking-[0.35em] text-slate-500">Lawan</p>
                                    <p class="mt-2 text-xl font-semibold text-white">{{ $opponent ?? 'TBD' }}</p>
                                    <p class="mt-1 text-sm text-slate-400">{{ $matchLabel }}</p>
                                </div>

                                <div class="rounded-3xl bg-slate-800/80 p-4">
                                    <p class="text-sm uppercase tracking-[0.35em] text-slate-500">Venue</p>
                                    <p class="mt-2 text-base font-semibold text-white">{{ $nextMatch->venue ?? 'Lokasi belum ditetapkan' }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-6 rounded-[2rem] border border-slate-800 bg-slate-950 p-8 text-center text-slate-400">
                            <p class="text-lg font-semibold text-white">Belum ada pertandingan yang dijadwalkan.</p>
                            <p class="mt-2 text-sm">Kami belum menemukan pertandingan mendatang untuk tim Anda.</p>
                        </div>
                    @endif
                </section>

                {{-- N7 — Riwayat Pertandingan diganti menjadi scoreboard tim yang sedang bertanding --}}
                @include('official.partials.live-scoreboard')

                <section class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-6 shadow-2xl shadow-slate-950/40">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Daftar Pertandingan</p>
                            <h2 class="mt-3 text-2xl font-semibold text-white">{{ $scope === 'tournament' ? 'Semua Laga Turnamen' : 'Pertandingan Tim Anda' }}</h2>
                        </div>
                        <form method="GET" action="{{ route('official.schedule') }}" class="flex flex-wrap gap-2">
                            <input type="hidden" name="scope" value="{{ $scope }}">
                            <button type="submit" name="filter" value="all" class="rounded-3xl px-4 py-3 text-sm font-semibold {{ $filter === 'all' ? 'bg-violet-500 text-white' : 'bg-slate-950 text-slate-300 hover:bg-slate-900' }}">Semua</button>
                            <button type="submit" name="filter" value="upcoming" class="rounded-3xl px-4 py-3 text-sm font-semibold {{ $filter === 'upcoming' ? 'bg-violet-500 text-white' : 'bg-slate-950 text-slate-300 hover:bg-slate-900' }}">Akan Datang</button>
                            <button type="submit" name="filter" value="finished" class="rounded-3xl px-4 py-3 text-sm font-semibold {{ $filter === 'finished' ? 'bg-violet-500 text-white' : 'bg-slate-950 text-slate-300 hover:bg-slate-900' }}">Selesai</button>
                            <button type="submit" name="filter" value="tbd" class="rounded-3xl px-4 py-3 text-sm font-semibold {{ $filter === 'tbd' ? 'bg-violet-500 text-white' : 'bg-slate-950 text-slate-300 hover:bg-slate-900' }}">Belum Dijadwalkan</button>
                        </form>
                    </div>

                    @if(count($matches) === 0)
                        <div class="mt-6 rounded-[2rem] border border-slate-800 bg-slate-950 p-8 text-center text-slate-400">
                            <p class="text-lg font-semibold text-white">Belum ada pertandingan yang dijadwalkan.</p>
                            <p class="mt-2 text-sm">Tambahkan jadwal pada panel tournament jika sudah tersedia.</p>
                        </div>
                    @else
                        <div class="mt-6 space-y-4">
                            @foreach($matches as $match)
                                @php
                                    $homeName = $match->homeTeam?->team?->name ?? $match->home_team_key ?? $match->source_home ?? 'TBD';
                                    $awayName = $match->awayTeam?->team?->name ?? $match->away_team_key ?? $match->source_away ?? 'TBD';
                                    $homeMine = in_array($match->home_team_id, $teamTournamentTeamIds, true);
                                    $awayMine = in_array($match->away_team_id, $teamTournamentTeamIds, true);
                                    $involvesMe = $homeMine || $awayMine;
                                    $opponent = $homeMine ? $awayName : $homeName;
                                    $score = is_null($match->home_score) || is_null($match->away_score)
                                        ? '-' : "{$match->home_score} - {$match->away_score}";
                                    $statusLabel = $match->status === 'scheduled' ? 'Akan Datang' : ($match->status === 'live_match' ? 'Live' : ucfirst(str_replace('_', ' ', $match->status)));
                                    $statusClass = $match->status === 'live_match' ? 'bg-emerald-500 text-emerald-100' : ($match->status === 'scheduled' ? 'bg-slate-800 text-slate-200' : 'bg-slate-700 text-slate-200');
                                @endphp
                                <article class="rounded-[2rem] border bg-slate-950 p-5 shadow-2xl shadow-slate-950/40 {{ ($scope === 'tournament' && $involvesMe) ? 'border-violet-500/40' : 'border-slate-800' }}">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.35em] text-slate-500">{{ $match->tournament?->name ?? 'Turnamen' }}</p>
                                            @if($scope === 'tournament')
                                                <h3 class="mt-2 text-xl font-semibold text-white">{{ $homeName }} <span class="text-slate-600">vs</span> {{ $awayName }}</h3>
                                                <p class="mt-1 text-sm text-slate-400">
                                                    @if($involvesMe)
                                                        <span class="inline-flex rounded-full bg-violet-500/20 px-2 py-0.5 text-[10px] font-semibold text-violet-200">Tim Anda{{ $homeMine ? ' (Home)' : ' (Away)' }}</span>
                                                    @else
                                                        Laga turnamen
                                                    @endif
                                                </p>
                                            @else
                                                <h3 class="mt-2 text-xl font-semibold text-white">{{ $opponent ?? 'TBD' }}</h3>
                                                <p class="mt-1 text-sm text-slate-400">{{ $homeMine ? 'Home' : 'Away' }}</p>
                                            @endif
                                        </div>
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </div>
                                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
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
                                            <p class="mt-2 text-lg font-semibold text-white">{{ $score }}</p>
                                            <p class="mt-1 text-sm text-slate-400">Venue: {{ $match->venue ?? 'Lokasi belum ditetapkan' }}</p>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <aside class="space-y-4">
                <div class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-6 shadow-2xl shadow-slate-950/40">
                    <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Ringkasan Jadwal</p>
                    <div class="mt-5 space-y-4">
                        <div class="rounded-3xl bg-slate-950 p-4">
                            <p class="text-sm text-slate-400">Filter saat ini</p>
                            <p class="mt-2 text-lg font-semibold text-white">{{ ucfirst($filter) }}</p>
                        </div>
                        <div class="rounded-3xl bg-slate-950 p-4">
                            <p class="text-sm text-slate-400">Total Pertandingan</p>
                            <p class="mt-2 text-2xl font-semibold text-white">{{ count($matches) }}</p>
                        </div>
                        <div class="rounded-3xl bg-slate-950 p-4">
                            <p class="text-sm text-slate-400">Lihat saja pertandingan tim Anda</p>
                            <p class="mt-2 text-sm text-slate-300">Halaman ini menampilkan semua pertandingan yang mencakup tim resmi Anda.</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection
