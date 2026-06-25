@extends('admin.layouts.tournament')

@section('title', 'Grup & Bagan Klasemen - ' . $tournament->name)

@section('page-label', 'GRUP & BAGAN KLASEMEN')
@section('page-title', 'Bagan Klasemen Grup')
@section('page-subtitle')
    @if(($setting->group_count ?? 0) > 0)
        @php
            $totalTeams = 0;
            foreach($groups as $gTeams) { $totalTeams += count($gTeams); }
        @endphp
        @if($totalTeams > 0)
            Tim yang lolos:
            <span class="text-indigo-400 font-semibold">{{ $setting->getQualifiedTeamsLabel() }}</span>
        @else
            <span class="text-slate-400">Pengaturan grup sudah dibuat. Tambahkan peserta untuk melihat klasemen.</span>
        @endif
    @else
        <span class="text-slate-400">Pengaturan grup belum dibuat.</span>
    @endif
@endsection

@section('content')
            <!-- Content -->
            <div class="p-4 sm:p-6">
                <div class="grid gap-4 mb-6 sm:grid-cols-3">
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-4">
                        <p class="text-xs text-slate-400">Jumlah Grup</p>
                        <p class="text-2xl font-bold text-white">{{ $setting->group_count ?? 0 }}</p>
                    </div>
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-4">
                        <p class="text-xs text-slate-400">Tim Per Grup</p>
                        <p class="text-2xl font-bold text-white">{{ $setting->teams_per_group ?? 0 }}</p>
                    </div>
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-4">
                        <p class="text-xs text-slate-400">Tim Lolos Per Grup</p>
                        <p class="text-2xl font-bold text-green-400">{{ count($setting->qualified_teams) }}</p>
                    </div>
                </div>

                <!-- Groups Grid -->
                @if(($setting->group_count ?? 0) > 0)
                    @php
                        // Satu grup (mis. Liga) tampil full-width; >1 grup tampil 2 kolom.
                        $groupGridCols = ($setting->group_count ?? 0) <= 1 ? 'grid-cols-1' : 'grid-cols-1 lg:grid-cols-2';
                    @endphp
                    <div class="grid {{ $groupGridCols }} gap-6">
                        @php
                            $groupLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                        @endphp
                        @for($i = 1; $i <= $setting->group_count; $i++)
                            @php
                                $groupName = $groupLetters[$i - 1] ?? $i;
                                $teams = $groups[$groupName] ?? [];
                            @endphp
                            <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
                                <!-- Group Header -->
                                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex items-center justify-between">
                                    <h2 class="text-2xl font-bold">Grup {{ $groupName }}</h2>
                                    @if(count($teams) > 0)
                                        <span class="text-sm text-white/70">{{ count($teams) }} tim</span>
                                    @endif
                                </div>

                                <!-- Group Table -->
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                    <thead>
                                        <tr class="bg-slate-800 border-b border-slate-700">
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-300">#</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-300">TIM</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-300">M</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-300">W</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-300">D</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-300">L</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-300">GM</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-300">GK</th>
                                            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-300">SG</th>
                                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-300">PTS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(count($teams) > 0)
                                            @foreach($teams as $team)
                                                @php
                                                    $isQualified = $setting->isQualified($team['ranking']);
                                                    $isRelegated = in_array($team['ranking'], $setting->relegated_teams ?? []);
                                                    $isLeaguePlayoffPromotion = $competitionType === 'league_playoff' && in_array($playoffType, ['promotion', 'both'], true);
                                                    $isLeaguePlayoffRelegation = $competitionType === 'league_playoff' && in_array($playoffType, ['relegation', 'both'], true);
                                                    $bgClass = '';
                                                    $badgeClass = '';
                                                    $badgeText = '';
                                                    
                                                    if ($competitionType === 'league') {
                                                        if ($team['ranking'] === 1) {
                                                            $bgClass = 'bg-yellow-900/30 border-l-4 border-yellow-500';
                                                            $badgeClass = 'bg-yellow-600/30 text-yellow-300';
                                                            $badgeText = '👑 Champions';
                                                        } elseif ($isRelegated) {
                                                            $bgClass = 'bg-red-900/20 border-l-4 border-red-500';
                                                            $badgeClass = 'bg-red-600/30 text-red-300';
                                                            $badgeText = '↓ Relegation';
                                                        } else {
                                                            $bgClass = 'hover:bg-slate-800/50';
                                                        }
                                                    } elseif ($isLeaguePlayoffPromotion && $isQualified) {
                                                        $bgClass = 'bg-sky-900/20 border-l-4 border-sky-500';
                                                        $badgeClass = 'bg-sky-600/30 text-sky-300';
                                                        $badgeText = 'Play Off Promosi';
                                                    } elseif ($isLeaguePlayoffRelegation && $isRelegated) {
                                                        $bgClass = 'bg-red-900/20 border-l-4 border-red-500';
                                                        $badgeClass = 'bg-red-600/30 text-red-300';
                                                        $badgeText = 'Play Off Degradasi';
                                                    } else {
                                                        $bgClass = $isQualified ? 'bg-green-900/20 border-l-4 border-green-500' : 'hover:bg-slate-800/50';
                                                    }
                                                @endphp
                                                <tr class="border-b border-slate-700 transition {{ $bgClass }}">
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm font-bold text-slate-300">{{ $team['ranking'] }}</span>
                                                            @if($competitionType === 'league' && $badgeText)
                                                                <span class="inline-flex items-center gap-1 px-2 py-1 {{ $badgeClass }} text-xs font-semibold rounded">
                                                                    {{ $badgeText }}
                                                                </span>
                                                            @elseif($competitionType === 'league_playoff' && $isLeaguePlayoffPromotion && $isQualified)
                                                                <span class="inline-flex items-center gap-1 px-2 py-1 {{ $badgeClass }} text-xs font-semibold rounded">
                                                                    {{ $badgeText }}
                                                                </span>
                                                            @elseif($competitionType !== 'league' && !($competitionType === 'league_playoff' && $isLeaguePlayoffPromotion) && $isQualified)
                                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-600/30 text-green-300 text-xs font-semibold rounded">
                                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                                    </svg>
                                                                    Lolos
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <p class="text-sm font-medium text-white">{{ $team['name'] }}</p>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-300">
                                                        {{ $team['wins'] + $team['draws'] + $team['losses'] }}
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-sm font-semibold text-white">
                                                        {{ $team['wins'] }}
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-sm font-semibold text-white">
                                                        {{ $team['draws'] }}
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-sm font-semibold text-white">
                                                        {{ $team['losses'] }}
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-300">
                                                        {{ $team['goals_scored'] }}
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-300">
                                                        {{ $team['goals_conceded'] }}
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-sm font-semibold text-white">
                                                        {{ $team['goal_difference'] >= 0 ? '+' . $team['goal_difference'] : $team['goal_difference'] }}
                                                    </td>
                                                    <td class="px-4 py-3 text-right text-sm font-bold text-indigo-400">
                                                        {{ $team['points'] }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            @for($j = 1; $j <= $setting->teams_per_group; $j++)
                                                <tr class="border-b border-slate-700/50">
                                                    <td class="px-4 py-3">
                                                        <span class="text-sm font-bold text-slate-600">{{ $j }}</span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <p class="text-sm font-medium text-slate-600 italic">Belum ada peserta</p>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-600">-</td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-600">-</td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-600">-</td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-600">-</td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-600">-</td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-600">-</td>
                                                    <td class="px-4 py-3 text-center text-sm text-slate-600">-</td>
                                                    <td class="px-4 py-3 text-right text-sm text-slate-600">-</td>
                                                </tr>
                                            @endfor
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            <!-- Legend -->
                            <div class="bg-slate-800/50 px-6 py-4 border-t border-slate-700">
                                @if(count($teams) > 0)
                                <p class="text-xs text-slate-400">
                                    M = PERTANDINGAN | W = MENANG | D = SERI | L = KALAH | GM = GOL MASUK | GK = GOL KEMASUKAN | SG = SELISIH GOL | PTS = POIN
                                </p>
                                @else
                                <p class="text-xs text-slate-500 italic">
                                    Slot kosong - Tambahkan tim untuk mengisi grup ini
                                </p>
                                @endif
                            </div>
                        </div>
                    @endfor
                </div>
                @else
                    <div class="rounded-[2rem] border border-slate-800 bg-slate-950/95 p-12 text-center">
                        <p class="text-2xl font-semibold text-white">Pengaturan grup belum dibuat.</p>
                        <p class="mt-3 text-slate-400">Silakan atur jumlah grup dan tim terlebih dahulu di menu <span class="font-semibold">Pengaturan Turnamen</span>.</p>
                    </div>
                @endif

                <!-- Tournament Mode: Qualified Teams Summary -->
                @if($competitionType === 'tournament')
                    <div class="mt-8 bg-slate-900 rounded-xl border border-slate-800 p-6">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            Tim yang Lolos ke Babak Berikutnya
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                            @foreach($groups as $groupName => $teamsList)
                                @foreach($teamsList as $team)
                                    @if($setting->isQualified($team['ranking']))
                                        <div class="bg-green-900/20 border border-green-500/30 rounded-lg p-3">
                                            <p class="text-sm font-semibold text-green-300">{{ $team['name'] }}</p>
                                            <p class="text-xs text-green-200">Grup {{ $groupName }} - Ranking {{ $team['ranking'] }}</p>
                                        </div>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @else
                    <!-- League Mode: Categories Summary (berdampingan) -->
                    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <!-- Champions -->
                        <div class="bg-slate-900 rounded-xl border border-slate-800 p-5">
                            <h3 class="text-base font-bold text-white mb-3 flex items-center gap-2">
                                <span class="text-xl">👑</span>
                                <span>Champions</span>
                            </h3>
                            <div class="space-y-2">
                                @php $found = false; @endphp
                                @foreach($groups as $groupName => $teamsList)
                                    @foreach($teamsList as $team)
                                        @if($team['ranking'] === 1)
                                            <div class="bg-yellow-900/30 border border-yellow-500/50 rounded-lg p-3">
                                                <p class="text-sm font-semibold text-yellow-300">{{ $team['name'] }}</p>
                                                <p class="text-xs text-yellow-200">Grup {{ $groupName }}</p>
                                            </div>
                                            @php $found = true; @endphp
                                        @endif
                                    @endforeach
                                @endforeach
                                @if(!$found)
                                    <p class="text-sm text-slate-400 py-2">Tidak ada data</p>
                                @endif
                            </div>
                        </div>

                        <!-- Runner-up -->
                        <div class="bg-slate-900 rounded-xl border border-slate-800 p-5">
                            <h3 class="text-base font-bold text-white mb-3 flex items-center gap-2">
                                <span class="text-xl">🥈</span>
                                <span>Runner-up</span>
                            </h3>
                            <div class="space-y-2">
                                @php $found = false; @endphp
                                @foreach($groups as $groupName => $teamsList)
                                    @foreach($teamsList as $team)
                                        @if($team['ranking'] === 2)
                                            <div class="bg-gray-700/30 border border-gray-400/50 rounded-lg p-3">
                                                <p class="text-sm font-semibold text-gray-300">{{ $team['name'] }}</p>
                                                <p class="text-xs text-gray-200">Grup {{ $groupName }}</p>
                                            </div>
                                            @php $found = true; @endphp
                                        @endif
                                    @endforeach
                                @endforeach
                                @if(!$found)
                                    <p class="text-sm text-slate-400 py-2">Tidak ada data</p>
                                @endif
                            </div>
                        </div>

                        <!-- Third Place -->
                        <div class="bg-slate-900 rounded-xl border border-slate-800 p-5">
                            <h3 class="text-base font-bold text-white mb-3 flex items-center gap-2">
                                <span class="text-xl">🥉</span>
                                <span>Third Place</span>
                            </h3>
                            <div class="space-y-2">
                                @php $found = false; @endphp
                                @foreach($groups as $groupName => $teamsList)
                                    @foreach($teamsList as $team)
                                        @if($team['ranking'] === 3)
                                            <div class="bg-orange-900/30 border border-orange-600/50 rounded-lg p-3">
                                                <p class="text-sm font-semibold text-orange-300">{{ $team['name'] }}</p>
                                                <p class="text-xs text-orange-200">Grup {{ $groupName }}</p>
                                            </div>
                                            @php $found = true; @endphp
                                        @endif
                                    @endforeach
                                @endforeach
                                @if(!$found)
                                    <p class="text-sm text-slate-400 py-2">Tidak ada data</p>
                                @endif
                            </div>
                        </div>

                        <!-- Relegation -->
                        <div class="bg-slate-900 rounded-xl border border-slate-800 p-5">
                            <h3 class="text-base font-bold text-white mb-3 flex items-center gap-2">
                                <span class="text-xl">↓</span>
                                <span>Relegation</span>
                            </h3>
                            <div class="space-y-2">
                                @php $found = false; @endphp
                                @if(!empty($setting->relegated_teams))
                                    @foreach($groups as $groupName => $teamsList)
                                        @foreach($teamsList as $team)
                                            @if(in_array($team['ranking'], $setting->relegated_teams))
                                                <div class="bg-red-900/20 border border-red-500/50 rounded-lg p-3">
                                                    <p class="text-sm font-semibold text-red-300">{{ $team['name'] }}</p>
                                                    <p class="text-xs text-red-200">Grup {{ $groupName }} - Ranking {{ $team['ranking'] }}</p>
                                                </div>
                                                @php $found = true; @endphp
                                            @endif
                                        @endforeach
                                    @endforeach
                                @endif
                                @if(!$found)
                                    <p class="text-sm text-slate-400 py-2">Tidak ada degradasi</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- League Playoff: Promotion Bracket -->
                @if($hasPlayoffPromotion && !empty($playoffPromotionTeams))
                    <div class="mt-8 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
                        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
                            <h2 class="text-2xl font-bold">Bracket Gugur - Play Off Promosi</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                @foreach($playoffPromotionTeams as $slot => $team)
                                    <div class="bg-sky-900/20 border border-sky-500/50 rounded-lg p-3">
                                        <p class="text-sm font-semibold text-sky-300">{{ $team }}</p>
                                        <p class="text-xs text-sky-200">{{ $slot }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- League Playoff: Relegation Bracket -->
                @if($hasPlayoffRelegation && !empty($playoffRelegationTeams))
                    <div class="mt-8 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
                        <div class="bg-gradient-to-r from-red-600 to-rose-600 px-6 py-4">
                            <h2 class="text-2xl font-bold">Bracket Gugur - Play Off Degradasi</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                @foreach($playoffRelegationTeams as $slot => $team)
                                    <div class="bg-red-900/20 border border-red-500/50 rounded-lg p-3">
                                        <p class="text-sm font-semibold text-red-300">{{ $team }}</p>
                                        <p class="text-xs text-red-200">{{ $slot }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Info Card -->
                <div class="mt-6 bg-indigo-900/20 border border-indigo-500/30 rounded-lg p-4">
                    <p class="text-sm text-indigo-200">
                        <strong>💡 Info:</strong>
                        @if($competitionType === 'league')
                            Sistem Liga menampilkan kategori Champions (Peringkat 1) dan Relegation.
                        @else
                            Pengaturan kelolosan grup dapat diubah di menu <span class="font-semibold">Pengaturan Turnamen</span>. Setiap perubahan akan langsung mempengaruhi tampilan klasemen ini.
                        @endif
                    </p>
                </div>
            </div>
@endsection
