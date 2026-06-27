{{--
    N12/N13 — Body statistik turnamen (reusable lintas role: Admin, Manager, Publik).

    Variabel yang diharapkan (output TournamentStatisticsService::forTournament):
      $top_scorers, $top_assists, $top_yellow_cards, $top_red_cards,
      $most_productive_teams, $most_conceded_teams, $fairplay_teams,
      $has_assist_data (bool)

    Markup ini netral (tanpa layout/header) agar bisa di-embed di mana saja.
--}}
@php
    $totalGoals = $most_productive_teams->sum('goals');
    $topScorer = $top_scorers->first();
    $totalCards = $fairplay_teams->sum('total_cards');
@endphp

{{-- Ringkasan singkat --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <div class="bg-slate-900/80 rounded-xl border border-slate-800 p-4">
        <p class="text-xs text-slate-400 uppercase tracking-wider">Total Gol</p>
        <p class="text-2xl font-bold text-emerald-400">{{ $totalGoals }}</p>
    </div>
    <div class="bg-slate-900/80 rounded-xl border border-slate-800 p-4">
        <p class="text-xs text-slate-400 uppercase tracking-wider">Top Skor</p>
        <p class="text-base font-bold text-white truncate">{{ $topScorer->player_name ?? '—' }}</p>
        <p class="text-xs text-slate-400">{{ $topScorer ? $topScorer->goals . ' gol' : 'Belum ada' }}</p>
    </div>
    <div class="bg-slate-900/80 rounded-xl border border-slate-800 p-4">
        <p class="text-xs text-slate-400 uppercase tracking-wider">Total Kartu</p>
        <p class="text-2xl font-bold text-amber-400">{{ $totalCards }}</p>
    </div>
    <div class="bg-slate-900/80 rounded-xl border border-slate-800 p-4">
        <p class="text-xs text-slate-400 uppercase tracking-wider">Tim Terdata</p>
        <p class="text-2xl font-bold text-white">{{ $fairplay_teams->count() }}</p>
    </div>
</div>

{{-- Statistik Pemain --}}
<h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-3">Statistik Pemain</h2>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
    @include('partials.statistics.player-table', [
        'title' => 'Top Skor',
        'accent' => 'text-emerald-400',
        'rows' => $top_scorers,
        'metric' => 'goals',
        'metricLabel' => 'Gol',
        'emptyText' => 'Belum ada gol tercatat.',
    ])

    @include('partials.statistics.player-table', [
        'title' => 'Top Assist',
        'accent' => 'text-sky-400',
        'rows' => $top_assists,
        'metric' => 'assists',
        'metricLabel' => 'Assist',
        'emptyText' => 'Pencatatan assist belum tersedia.',
    ])

    @include('partials.statistics.player-table', [
        'title' => 'Kartu Kuning Terbanyak',
        'accent' => 'text-amber-400',
        'rows' => $top_yellow_cards,
        'metric' => 'yellow_cards',
        'metricLabel' => 'KK',
        'emptyText' => 'Belum ada kartu kuning.',
    ])

    @include('partials.statistics.player-table', [
        'title' => 'Kartu Merah Terbanyak',
        'accent' => 'text-rose-400',
        'rows' => $top_red_cards,
        'metric' => 'red_cards',
        'metricLabel' => 'KM',
        'emptyText' => 'Belum ada kartu merah.',
    ])
</div>

@unless($has_assist_data)
    <p class="-mt-6 mb-8 text-xs text-slate-500 flex items-start gap-1.5">
        <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Top Assist masih kosong: fitur pencatatan assist pada Live Match Logger belum diaktifkan.
    </p>
@endunless

{{-- Statistik Tim --}}
<h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-3">Statistik Tim</h2>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    @include('partials.statistics.team-table', [
        'title' => 'Tim Paling Produktif',
        'accent' => 'text-emerald-400',
        'rows' => $most_productive_teams,
        'mode' => 'value',
        'metric' => 'goals',
        'metricLabel' => 'Gol',
        'emptyText' => 'Belum ada pertandingan selesai.',
    ])

    @include('partials.statistics.team-table', [
        'title' => 'Tim Paling Kebobolan',
        'accent' => 'text-rose-400',
        'rows' => $most_conceded_teams,
        'mode' => 'value',
        'metric' => 'goals',
        'metricLabel' => 'Kebobolan',
        'emptyText' => 'Belum ada pertandingan selesai.',
    ])

    @include('partials.statistics.team-table', [
        'title' => 'Tim Paling Fairplay',
        'accent' => 'text-sky-400',
        'rows' => $fairplay_teams,
        'mode' => 'fairplay',
        'metricLabel' => 'Total Kartu',
        'emptyText' => 'Belum ada pertandingan selesai.',
    ])
</div>

<p class="mt-6 text-xs text-slate-500">
    Statistik tim dihitung dari skor akhir pertandingan yang sudah selesai. Statistik pemain
    (gol, kartu) berasal dari Live Match Logger dan hanya mencakup event yang terhubung ke pemain terdaftar.
</p>
