@extends('admin.layouts.tournament')

@section('title', 'Bracket Gugur - ' . $tournament->name)

@section('body-class', 'bg-slate-950 text-white m-0 p-0')
@section('wrapper-class', 'h-screen overflow-hidden')
@section('main-class', 'flex-1 overflow-y-auto')
        
@section('page-header')
            <header class="border-b border-slate-800 bg-slate-900/50 backdrop-blur sticky top-0 z-40">
                <div class="px-4 sm:px-6 py-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <p class="text-xs sm:text-sm {{ $playoffMode === 'relegation' ? 'text-red-400' : 'text-violet-400' }} font-semibold mb-2">BRACKET GUGUR</p>
                            @if($competitionType === 'league')
                                <h1 class="text-2xl sm:text-3xl font-bold">⊘ Bracket Gugur Tidak Tersedia</h1>
                                <p class="text-slate-400 text-sm mt-2">Sistem liga biasa tidak menggunakan bracket gugur.</p>
                            @elseif($playoffMode === 'relegation')
                                <h1 class="text-2xl sm:text-3xl font-bold">Isi Slot Tim Play Off Degradasi</h1>
                                <p class="text-slate-400 text-sm mt-2">Pilih tim degradasi dari fase grup untuk mengisi slot awal bracket degradasi. Hanya slot putaran pertama yang dapat diisi langsung.</p>
                            @elseif($competitionType === 'tournament')
                                <h1 class="text-2xl sm:text-3xl font-bold">Bracket Babak Gugur</h1>
                                <p class="text-slate-400 text-sm mt-2">Slot bracket terisi otomatis dari tim yang lolos verifikasi (tanpa fase grup). Susunan slot putaran pertama dapat diatur ulang secara manual.</p>
                            @else
                                <h1 class="text-2xl sm:text-3xl font-bold">Isi Slot Tim Knockout</h1>
                                <p class="text-slate-400 text-sm mt-2">Pilih tim yang lolos dari fase grup untuk mengisi slot awal bracket. Hanya slot putaran pertama yang dapat diisi langsung.</p>
                            @endif
                        </div>
                        @unless($competitionType === 'league')
                        <div class="rounded-xl bg-slate-900 border {{ $playoffMode === 'relegation' ? 'border-red-600/30' : 'border-slate-800' }} p-4 text-sm text-slate-300">
                            @if($playoffMode === 'relegation')
                                <p><strong class="text-red-400">Tim degradasi:</strong> @if(isset($tournamentTeams) && count($tournamentTeams)) {{ $tournamentTeams->map(fn($tt) => $tt->team?->name ?? ('Team '.$tt->id))->implode(', ') }} @else {{ implode(', ', array_keys($teamsToUse)) }} @endif</p>
                            @elseif($competitionType === 'tournament')
                                <p><strong>Tim peserta (terverifikasi):</strong> {{ count($teamsToUse) ? implode(', ', array_keys($teamsToUse)) : 'Belum ada tim terverifikasi' }}</p>
                            @else
                                <p><strong>Tim qualified:</strong> @if(isset($tournamentTeams) && count($tournamentTeams)) {{ $tournamentTeams->map(fn($tt) => $tt->team?->name ?? ('Team '.$tt->id))->implode(', ') }} @else {{ implode(', ', array_keys($teamsToUse)) }} @endif</p>
                            @endif
                        </div>
                        @endunless
                    </div>

                    @if(!empty($hasBothOptions))
                        {{-- R5 — beralih antara bracket Promosi & Degradasi (mode both) --}}
                        <div class="mt-5 flex gap-2">
                            <a href="{{ route('tournaments.bracketAdmin', [$tournament, 'mode' => 'promotion']) }}"
                               class="px-4 py-2 rounded-lg text-sm font-semibold transition {{ $playoffMode === 'promotion' ? 'bg-violet-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                                ⬆️ Bracket Promosi
                            </a>
                            <a href="{{ route('tournaments.bracketAdmin', [$tournament, 'mode' => 'relegation']) }}"
                               class="px-4 py-2 rounded-lg text-sm font-semibold transition {{ $playoffMode === 'relegation' ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                                ⬇️ Bracket Degradasi
                            </a>
                        </div>
                    @endif
                </div>
            </header>
@endsection

@section('content')
            <div class="p-4 sm:p-6">
                @if($competitionType === 'league')
                    <div class="mb-6 p-4 bg-amber-900/20 border border-amber-500/30 rounded-lg text-amber-300 text-sm flex items-start gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <strong class="block mb-1">⊘ Bracket Gugur Tidak Tersedia</strong>
                            <p>Sistem liga biasa tidak menggunakan bracket gugur.</p>
                        </div>
                    </div>
                @endif

                @if(session('success'))
                    <div class="mb-6 p-4 bg-emerald-900/20 border border-emerald-500/30 rounded-lg text-emerald-300 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 p-4 bg-red-900/20 border border-red-500/30 rounded-lg text-red-400 text-sm">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(! $groupStageComplete && ($competitionType === 'tournament' || $isLeaguePlayoffWithPromotion || $isLeaguePlayoffWithRelegation || ($isGroupKnockout ?? false)))
                    <div class="mb-6 p-4 bg-amber-900/20 border border-amber-500/30 rounded-lg text-amber-300 text-sm">
                        <strong>Menunggu seluruh pertandingan grup selesai</strong>
                        <p>Bracket otomatis akan terisi setelah semua pertandingan grup berstatus <em>full_time</em>.</p>
                    </div>
                @endif

                @if($competitionType === 'tournament' || $isLeaguePlayoffWithPromotion || $isLeaguePlayoffWithRelegation || ($isGroupKnockout ?? false))
                <form action="{{ route('tournaments.saveBracketAssignments', !empty($hasBothOptions) ? [$tournament, 'mode' => $playoffMode] : [$tournament]) }}" method="POST" class="space-y-6">
                    @csrf

                    <div class="mb-4 flex items-center gap-4">
                        <label class="text-sm text-slate-300 font-semibold">Mode Slot Bracket:</label>
                        <div class="flex items-center gap-3">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                                <input type="radio" name="bracket_mode" value="auto" id="bracketModeAuto" checked>
                                <span>Otomatis</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                                <input type="radio" name="bracket_mode" value="manual" id="bracketModeManual">
                                <span>Manual</span>
                            </label>
                        </div>
                    </div>

                    @php
                        $settingValue = $setting->value ?? [];
                        $rawMatches = $settingValue['matches'] ?? [];
                        $submittedMatches = old('matches', []);
                        $matches = [];

                        if (! is_array($submittedMatches)) {
                            $submittedMatches = [];
                        }

                        foreach ($rawMatches as $index => $match) {
                            if (isset($submittedMatches[$index]) && is_array($submittedMatches[$index])) {
                                $match['left'] = $submittedMatches[$index]['left'] ?? $match['left'];
                                $match['right'] = $submittedMatches[$index]['right'] ?? $match['right'];
                            }
                            $match['index'] = $index;
                            $matches[] = $match;
                        }

                        $roundIndex = [];
                        foreach ($matches as $match) {
                            $label = $match['round'] ?? 'Unknown Round';
                            $roundIndex[$label][] = $match;
                        }

                        $thirdPlaceRound = null;
                        $rounds = [];
                        foreach ($roundIndex as $label => $matchGroup) {
                            if ($label === 'Third Place') {
                                $thirdPlaceRound = [
                                    'label' => 'Third Place',
                                    'matches' => $matchGroup,
                                    'teams' => count($matchGroup) * 2,
                                ];
                                continue;
                            }

                            $rounds[] = [
                                'label' => $label,
                                'matches' => $matchGroup,
                                'teams' => count($matchGroup) * 2,
                            ];
                        }

                        $finalRound = [];
                        if (! empty($rounds)) {
                            $finalRound = array_pop($rounds);
                        }

                        $bracketColumns = $rounds;
                        if (! empty($finalRound)) {
                            $bracketColumns[] = $finalRound;
                        }

                        $cardHeight = 120;
                        $cardGap = 120;
                        $rowUnit = $cardHeight + $cardGap;
                        $columnHeaderHeight = 38;

                        // Posisi card mengikuti graf bracket (card di tengah para
                        // pengumpannya) agar ronde pertama yang jarang karena bye
                        // tetap tersusun rapi.
                        $cardTops = \App\Services\MatchGenerator::computeBracketCardTops($bracketColumns, $rowUnit);
                        $bracketCanvasHeight = $columnHeaderHeight;
                        foreach ($cardTops as $columnTops) {
                            foreach ($columnTops as $topValue) {
                                $bracketCanvasHeight = max($bracketCanvasHeight, $topValue + $rowUnit + $columnHeaderHeight);
                            }
                        }

                        $qualifiedTeamKeys = array_keys($teamsToUse);
                        $qualifiedTeamOptions = $qualifiedTeamOptions ?? [];
                    @endphp

                    {{-- N8 — hint scroll horizontal saat bagan melebar (tim banyak) --}}
                    <p class="mb-2 flex items-center gap-2 text-xs text-slate-500 lg:hidden">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7l-4 5 4 5m8-10l4 5-4 5"></path></svg>
                        Geser ke kiri/kanan untuk melihat seluruh bagan
                    </p>
                    <div class="grid gap-4 mb-6 lg:grid-cols-[1fr_260px]">
                        <div class="bracket-scroll bg-slate-900 rounded-xl border border-slate-800 p-4 overflow-x-auto">
                            <div id="bracketConnectorLayout" class="relative min-w-max">
                                <svg id="bracketConnectorSvg" class="absolute inset-0 w-full h-full pointer-events-none" xmlns="http://www.w3.org/2000/svg"></svg>

                                <div class="relative flex gap-12 w-full items-start">
                                    @foreach($bracketColumns as $columnIndex => $column)
                                        <div class="relative flex-shrink-0 w-[200px]" data-final-column="{{ $columnIndex === count($bracketColumns) - 1 ? '1' : '0' }}" style="min-height: {{ $bracketCanvasHeight }}px;">
                                            <div class="mb-4">
                                                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">{{ $column['label'] }} ({{ $column['teams'] }} Tim)</p>
                                            </div>

                                            @foreach($column['matches'] as $matchIndex => $match)
                                                @php
                                                    $top = ($cardTops[$columnIndex][$matchIndex] ?? 0) + $columnHeaderHeight;
                                                    $matchId = $match['id'] ?? "generated-{$match['index']}";
                                                    $leftSlot = $match['left'];
                                                    $rightSlot = $match['right'];
                                                    $leftEditable = isset($teamsToUse[$leftSlot]);
                                                    $rightEditable = isset($teamsToUse[$rightSlot]);

                                                    // Determine assigned match (if persisted)
                                                    $assigned = $assignedMatches[$match['id']] ?? null;
                                                    $leftRaw = data_get($assigned, 'homeTeam.team.name') ?: data_get($assigned, 'source_home') ?: data_get($match, 'left') ?: 'Winner-up M1';
                                                    $rightRaw = data_get($assigned, 'awayTeam.team.name') ?: data_get($assigned, 'source_away') ?: data_get($match, 'right') ?: 'Winner-up M2';
                                                    $leftIsPlaceholder = preg_match('/(Winner|Loser|Runner[- ]?up|^[A-Z]\\d|Bye)/i', (string)$leftRaw);
                                                    $rightIsPlaceholder = preg_match('/(Winner|Loser|Runner[- ]?up|^[A-Z]\\d|Bye)/i', (string)$rightRaw);
                                                    $leftDisplay = isset($assigned->home_team_id) ? $leftRaw : ($leftIsPlaceholder ? 'Menunggu...' : $leftRaw);
                                                    $rightDisplay = isset($assigned->away_team_id) ? $rightRaw : ($rightIsPlaceholder ? 'Menunggu...' : $rightRaw);

                                                    // Ringkasan skor kartu (single / 2-leg / penalti). '-' bila belum main.
                                                    $score = $bracketScores[$match['id']] ?? null;
                                                    $played = $score['played'] ?? false;
                                                    $homeScore = $played ? ($score['home']['score'] ?? null) : null;
                                                    $awayScore = $played ? ($score['away']['score'] ?? null) : null;
                                                    $homeScoreText = $homeScore === null ? '-' : $homeScore;
                                                    $awayScoreText = $awayScore === null ? '-' : $awayScore;
                                                    $homeIsWinner = ($score['winner_side'] ?? null) === 'home';
                                                    $awayIsWinner = ($score['winner_side'] ?? null) === 'away';
                                                @endphp

                                                <div class="absolute left-0 right-0" style="top: {{ $top }}px;">
                                                    <div id="bracket-card-{{ $matchId }}" class="relative z-10 rounded-2xl border border-slate-700 bg-slate-950 p-3 shadow-sm min-h-[120px] overflow-hidden bracket-card" data-match-id="{{ $matchId }}" data-next-match-id="{{ $match['next_match_id'] ?? '' }}" data-match-round="{{ $column['label'] }}">
                                                        <div class="text-[9px] uppercase tracking-[0.24em] text-slate-500 font-semibold mb-2">Match {{ $matchIndex + 1 }}</div>
                                                        <div class="space-y-3">
                                                                <div class="rounded-2xl bg-slate-900 p-3 border {{ $homeIsWinner ? 'border-emerald-500/50' : 'border-slate-700' }}">
                                                                <div class="flex items-center justify-between mb-2 text-[8px] uppercase tracking-[0.24em] text-slate-500 font-semibold">
                                                                    <span>Tim 1</span>
                                                                    <span class="text-slate-400">{{ $leftDisplay }}</span>
                                                                </div>
                                                                        <div class="flex items-center justify-between gap-2">
                                                                        @if($leftEditable)
                                                                            <div class="auto-select">
                                                                                <p class="text-sm {{ $homeIsWinner ? 'text-emerald-300 font-semibold' : 'text-slate-200' }}">{{ $leftDisplay }}</p>
                                                                                <input type="hidden" name="matches[{{ $match['index'] }}][left]" value="{{ $leftSlot }}">
                                                                                <input type="hidden" name="matches[{{ $match['index'] }}][left_id]" value="{{ optional($assigned)->home_team_id ?? '' }}">
                                                                            </div>

                                                                            <div class="manual-select hidden">
                                                                                @php
                                                                                    $teamSelectOptions = ! empty($qualifiedTeamOptions) ? $qualifiedTeamOptions : $tournamentTeams->mapWithKeys(fn($tt) => [$tt->id => ['name' => $tt->team?->name ?? 'Team ' . $tt->id]])->all();
                                                                                @endphp
                                                                                <select name="matches[{{ $match['index'] }}][left_id]" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
                                                                                    @foreach($teamSelectOptions as $teamId => $option)
                                                                                        <option value="{{ $teamId }}" {{ optional($assigned)->home_team_id == $teamId ? 'selected' : '' }}>{{ data_get($option, 'name') }}</option>
                                                                                    @endforeach
                                                                                </select>
                                                                            </div>
                                                                        @else
                                                                            <p class="text-sm text-slate-200">{{ $leftDisplay }}</p>
                                                                            <input type="hidden" name="matches[{{ $match['index'] }}][left]" value="{{ $leftSlot }}">
                                                                            <input type="hidden" name="matches[{{ $match['index'] }}][left_id]" value="">
                                                                        @endif
                                                                            <span class="shrink-0 min-w-[28px] text-center text-base font-bold tabular-nums {{ $homeIsWinner ? 'text-emerald-300' : ($played ? 'text-slate-100' : 'text-slate-500') }}">{{ $homeScoreText }}</span>
                                                                        </div>
                                                            </div>

                                                            <div class="rounded-2xl bg-slate-900 p-3 border {{ $awayIsWinner ? 'border-emerald-500/50' : 'border-slate-700' }}">
                                                                <div class="flex items-center justify-between mb-2 text-[8px] uppercase tracking-[0.24em] text-slate-500 font-semibold">
                                                                    <span>Tim 2</span>
                                                                    <span class="text-slate-400">{{ $rightDisplay }}</span>
                                                                </div>
                                                                <div class="flex items-center justify-between gap-2">
                                                                @if($rightEditable)
                                                                    <div class="auto-select">
                                                                        <p class="text-sm {{ $awayIsWinner ? 'text-emerald-300 font-semibold' : 'text-slate-200' }}">{{ $rightDisplay }}</p>
                                                                        <input type="hidden" name="matches[{{ $match['index'] }}][right]" value="{{ $rightSlot }}">
                                                                        <input type="hidden" name="matches[{{ $match['index'] }}][right_id]" value="{{ optional($assigned)->away_team_id ?? '' }}">
                                                                    </div>

                                                                    <div class="manual-select hidden">
                                                                        @php
                                                                            $teamSelectOptions = ! empty($qualifiedTeamOptions) ? $qualifiedTeamOptions : $tournamentTeams->mapWithKeys(fn($tt) => [$tt->id => ['name' => $tt->team?->name ?? 'Team ' . $tt->id]])->all();
                                                                        @endphp
                                                                        <select name="matches[{{ $match['index'] }}][right_id]" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-2 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500">
                                                                            @foreach($teamSelectOptions as $teamId => $option)
                                                                                <option value="{{ $teamId }}" {{ optional($assigned)->away_team_id == $teamId ? 'selected' : '' }}>{{ data_get($option, 'name') }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                @else
                                                                    <p class="text-sm text-slate-200">{{ $rightDisplay }}</p>
                                                                    <input type="hidden" name="matches[{{ $match['index'] }}][right]" value="{{ $rightSlot }}">
                                                                    <input type="hidden" name="matches[{{ $match['index'] }}][right_id]" value="">
                                                                @endif
                                                                    <span class="shrink-0 min-w-[28px] text-center text-base font-bold tabular-nums {{ $awayIsWinner ? 'text-emerald-300' : ($played ? 'text-slate-100' : 'text-slate-500') }}">{{ $awayScoreText }}</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        @include('admin.tournaments.bracket.partials.score-detail', ['score' => $score, 'played' => $played])
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>

                                @if(! empty($thirdPlaceRound))
                                    <div id="thirdPlacePanel" class="absolute transition-all duration-200" style="top: 0; left: 0;">
                                        <div class="w-[200px]">
                                            <div class="mb-4">
                                                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">{{ $thirdPlaceRound['label'] }} ({{ $thirdPlaceRound['teams'] }} Tim)</p>
                                            </div>

                                            @foreach($thirdPlaceRound['matches'] as $matchIndex => $match)
                                                @php
                                                    $matchId = $match['id'] ?? "third-place-{$match['index']}";
                                                @endphp
                                                <div class="relative rounded-2xl border border-slate-700 bg-slate-950 p-3 shadow-sm min-h-[120px] overflow-hidden bracket-card mb-4" id="bracket-card-{{ $matchId }}" data-match-id="{{ $matchId }}" data-match-round="Third Place">
                                                    <div class="text-[9px] uppercase tracking-[0.24em] text-slate-500 font-semibold mb-2">Match {{ $matchIndex + 1 }}</div>
                                                    <div class="space-y-3">
                                                        <div class="rounded-2xl bg-slate-900 p-3 border border-slate-700">
                                                            <div class="flex items-center justify-between mb-2 text-[8px] uppercase tracking-[0.24em] text-slate-500 font-semibold">
                                                                <span>Tim 1</span>
                                                                <span class="text-slate-400">{{ $match['left'] }}</span>
                                                            </div>
                                                            <p class="text-sm text-slate-200">{{ $match['left'] }}</p>
                                                            <input type="hidden" name="matches[{{ $match['index'] }}][left]" value="{{ $match['left'] }}">
                                                        </div>
                                                        <div class="rounded-2xl bg-slate-900 p-3 border border-slate-700">
                                                            <div class="flex items-center justify-between mb-2 text-[8px] uppercase tracking-[0.24em] text-slate-500 font-semibold">
                                                                <span>Tim 2</span>
                                                                <span class="text-slate-400">{{ $match['right'] }}</span>
                                                            </div>
                                                            <p class="text-sm text-slate-200">{{ $match['right'] }}</p>
                                                            <input type="hidden" name="matches[{{ $match['index'] }}][right]" value="{{ $match['right'] }}">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
                                <h2 class="text-lg font-semibold text-white mb-3">{{ $playoffMode === 'relegation' ? 'Tim Degradasi' : 'Tim Qualified' }}</h2>
                                <div class="grid grid-cols-2 gap-3 text-sm text-slate-300">
                                    @foreach($teamsToUse as $position => $label)
                                        @php
                                            // If we have TournamentTeam objects loaded, map position keys to their names where possible.
                                            $display = $label;
                                            if (isset($tournamentTeams) && $tournamentTeams->count()) {
                                                // tournamentTeams are not keyed by bracket key; attempt to find by bracket_position or id match
                                                $found = $tournamentTeams->first(function($tt) use ($position) {
                                                    return ($tt->bracket_position && $tt->bracket_position === $position) || (isset($tt->id) && (string)$tt->id === (string)$position);
                                                });
                                                if ($found) {
                                                    $display = $found->team?->name ?? $display;
                                                }
                                            }
                                        @endphp
                                        <div class="rounded-xl bg-slate-950/40 px-3 py-2 border border-slate-700">
                                            <p class="font-semibold text-slate-100">{{ $position }}</p>
                                            <p>{{ $display }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
                                <h2 class="text-lg font-semibold text-white mb-3">Panduan Singkat</h2>
                                <ul class="text-sm text-slate-300 list-disc list-inside space-y-2">
                                    <li>Pilih tim untuk setiap slot putaran pertama jika tersedia.</li>
                                    <li>Slot selanjutnya otomatis menunggu pemenang dari match sebelumnya.</li>
                                    <li>Jika slot sudah berisi <code>Bye</code> atau <code>Pemenang</code>, itu tidak dapat diubah secara manual.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-4">
                        <a href="{{ route('tournaments.settings', $tournament) }}" class="flex-1 text-center py-3 px-6 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-lg transition">Kembali</a>
                        <button type="submit" class="flex-1 py-3 px-6 bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-lg transition">Simpan Tim Bracket</button>
                    </div>
                </form>
                @else
                <div class="mt-8 p-6 bg-slate-900 rounded-xl border border-slate-800 text-center">
                    <p class="text-slate-400 mb-6">Fitur bracket gugur hanya tersedia untuk sistem turnamen.</p>
                    <a href="{{ route('tournaments.manage', $tournament) }}" class="inline-block py-3 px-6 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-lg transition">
                        Kembali ke Manajemen Turnamen
                    </a>
                </div>
                @endif
            </div>
@endsection

@push('scripts')
    <script>
        function drawBracketConnections() {
            const layout = document.getElementById('bracketConnectorLayout');
            const svg = document.getElementById('bracketConnectorSvg');
            if (!layout || !svg) return;

            const cardElements = Array.from(layout.querySelectorAll('.bracket-card[data-match-id]'));
            const cardMap = new Map(cardElements.map(card => [card.dataset.matchId, card]));
            const layoutRect = layout.getBoundingClientRect();
            const width = Math.max(layoutRect.width, 0);
            const height = Math.max(layoutRect.height, 0);

            svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
            svg.setAttribute('width', width);
            svg.setAttribute('height', height);
            svg.innerHTML = '';

            const getAnchor = (element, side) => {
                const rect = element.getBoundingClientRect();
                const x = rect.left - layoutRect.left + (side === 'right' ? rect.width : 0);
                const y = rect.top - layoutRect.top + rect.height / 2;
                return { x, y };
            };

            cardElements.forEach(sourceCard => {
                const nextMatchId = sourceCard.dataset.nextMatchId;
                if (!nextMatchId) return;
                const targetCard = cardMap.get(nextMatchId);
                if (!targetCard) return;

                const sourcePoint = getAnchor(sourceCard, 'right');
                const targetPoint = getAnchor(targetCard, 'left');
                const midX = sourcePoint.x + Math.max((targetPoint.x - sourcePoint.x) / 2, 40);

                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', `M ${sourcePoint.x} ${sourcePoint.y} H ${midX} V ${targetPoint.y} H ${targetPoint.x}`);
                path.setAttribute('fill', 'none');
                path.setAttribute('stroke', '#8b5cf6');
                path.setAttribute('stroke-width', '2');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('stroke-linejoin', 'round');
                svg.appendChild(path);
            });
        }

        function updateThirdPlacePanel() {
            const panel = document.getElementById('thirdPlacePanel');
            const layout = document.getElementById('bracketConnectorLayout');
            if (!panel || !layout) return;

            const finalColumn = layout.querySelector('[data-final-column="1"]');
            const finalCard = finalColumn?.querySelector('.bracket-card');
            if (!finalColumn || !finalCard) {
                panel.style.display = 'none';
                return;
            }

            panel.style.display = '';
            const layoutRect = layout.getBoundingClientRect();
            const finalColumnRect = finalColumn.getBoundingClientRect();
            const finalRect = finalCard.getBoundingClientRect();
            const panelRect = panel.getBoundingClientRect();
            const left = finalColumnRect.left - layoutRect.left + (finalColumnRect.width - panelRect.width) / 2;
            const top = finalRect.bottom - layoutRect.top + 16;

            panel.style.left = `${left}px`;
            panel.style.top = `${top}px`;
        }

        document.addEventListener('DOMContentLoaded', function () {
            drawBracketConnections();
            updateThirdPlacePanel();
            window.addEventListener('resize', function () {
                drawBracketConnections();
                updateThirdPlacePanel();
            });
            setupBracketSlotSwapping();
        });

        function setupBracketSlotSwapping() {
            const selects = Array.from(document.querySelectorAll('select[name^="matches"]'));
            selects.forEach(select => select.dataset.prevValue = select.value);

            selects.forEach(select => {
                select.addEventListener('change', () => {
                    const newValue = select.value;
                    const prevValue = select.dataset.prevValue;
                    const duplicate = selects.find(other => other !== select && other.value === newValue);

                    if (duplicate && prevValue && prevValue !== newValue) {
                        duplicate.value = prevValue;
                        duplicate.dataset.prevValue = prevValue;
                    }

                    selects.forEach(s => s.dataset.prevValue = s.value);
                });
            });
        }
    </script>

    <script>
        // Toggle between Auto and Manual select inputs
        function setBracketMode(mode) {
            const layout = document.getElementById('bracketConnectorLayout');
            if (!layout) return;
            const autoElems = layout.querySelectorAll('.auto-select');
            const manualElems = layout.querySelectorAll('.manual-select');

            if (mode === 'manual') {
                autoElems.forEach(e => e.classList.add('hidden'));
                manualElems.forEach(e => e.classList.remove('hidden'));
            } else {
                autoElems.forEach(e => e.classList.remove('hidden'));
                manualElems.forEach(e => e.classList.add('hidden'));
            }
        }

        document.getElementById('bracketModeAuto')?.addEventListener('change', () => setBracketMode('auto'));
        document.getElementById('bracketModeManual')?.addEventListener('change', () => setBracketMode('manual'));

        // initialize based on default radio
        setTimeout(() => {
            const manual = document.getElementById('bracketModeManual')?.checked;
            setBracketMode(manual ? 'manual' : 'auto');
        }, 50);
    </script>
@endpush

@push('styles')
<style>
    /* N8 — scrollbar tipis & rapi untuk kontainer bagan yang melebar. */
    .bracket-scroll { scrollbar-width: thin; scrollbar-color: #4f46e5 #1e293b; scroll-behavior: smooth; }
    .bracket-scroll::-webkit-scrollbar { height: 10px; }
    .bracket-scroll::-webkit-scrollbar-track { background: #1e293b; border-radius: 9999px; }
    .bracket-scroll::-webkit-scrollbar-thumb { background: #4f46e5; border-radius: 9999px; }
    .bracket-scroll::-webkit-scrollbar-thumb:hover { background: #6366f1; }
</style>
@endpush
