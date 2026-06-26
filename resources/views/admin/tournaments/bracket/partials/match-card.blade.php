{{--
    Kartu satu match bracket (admin, editable).
    Diekstrak agar dipakai layout satu-arah lama maupun mirror N14 tanpa duplikasi.

    Variabel yang diharapkan:
      $match        array data match (id, index, left, right, next_match_id, ...)
      $column       array kolom (untuk label/round)
      $top          int   posisi top (px) absolut dalam kolom
      $side         string '' | 'left' | 'right' (N14; '' = satu arah lama)
      $teamsToUse, $assignedMatches, $bracketScores, $qualifiedTeamOptions, $tournamentTeams
--}}
@php
    $side = $side ?? '';
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
    <div id="bracket-card-{{ $matchId }}" class="relative z-10 rounded-2xl border border-slate-700 bg-slate-950 p-3 shadow-sm min-h-[120px] overflow-hidden bracket-card" data-match-id="{{ $matchId }}" data-next-match-id="{{ $match['next_match_id'] ?? '' }}" data-match-round="{{ $column['label'] }}" @if($side) data-bracket-side="{{ $side }}" @endif>
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
