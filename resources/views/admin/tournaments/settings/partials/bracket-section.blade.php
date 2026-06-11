@props(['mode' => 'promotion', 'matches' => [], 'roundSummary' => '', 'hasThirdPlace' => false, 'thirdPlaceRound' => null])

@php
    $teamsLabelMap = [
        'promotion' => 'Play Off Promosi',
        'relegation' => 'Play Off Degradasi'
    ];
    $teamsLabel = $teamsLabelMap[$mode] ?? 'Bracket';
    $iconColor = $mode === 'promotion' ? 'emerald' : 'red';
    $iconHtml = $mode === 'promotion' 
        ? '<svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20"><path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.3A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z"></path></svg>'
        : '<svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20"><path d="M14.5 7a3.5 3.5 0 01.369 6.98 4 4 0 11-7.753 1.3A4.5 4.5 0 1116.5 7H15v3.587l-1.293-1.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L15 10.587V7z"></path></svg>';

    $rounds = [];
    $roundIndex = [];
    foreach ($matches as $match) {
        $label = $match['round'] ?? 'Unknown Round';
        $roundIndex[$label][] = $match;
    }

    foreach ($roundIndex as $label => $matchGroup) {
        $rounds[] = [
            'label' => $label,
            'matches' => $matchGroup,
            'teams' => count($matchGroup) * 2,
        ];
    }

    $thirdPlaceRoundData = array_values(array_filter($rounds, fn($round) => $round['label'] === 'Third Place'));
    $thirdPlaceRoundData = $thirdPlaceRoundData[0] ?? null;
    $nonThirdRounds = array_values(array_filter($rounds, fn($round) => $round['label'] !== 'Third Place'));
    $finalRound = [];
    if (! empty($nonThirdRounds)) {
        $finalRound = end($nonThirdRounds);
    }
    $leadingRounds = array_slice($nonThirdRounds, 0, max(0, count($nonThirdRounds) - 1));

    $bracketColumns = $leadingRounds;
    if (! empty($finalRound)) {
        $bracketColumns[] = $finalRound;
    }
    $cardHeight = 120;
    $cardGap = 28;
    $rowUnit = $cardHeight + $cardGap;
    $columnWidth = 220;
    $columnGap = 24;
    $columnHeaderHeight = 38;

    // Posisi card mengikuti graf bracket (card di tengah para pengumpannya)
    // agar ronde pertama yang jarang karena bye tetap tersusun rapi.
    $cardTops = \App\Services\MatchGenerator::computeBracketCardTops($bracketColumns, $rowUnit);
    $bracketCanvasHeight = $columnHeaderHeight;
    foreach ($cardTops as $columnTops) {
        foreach ($columnTops as $topValue) {
            $bracketCanvasHeight = max($bracketCanvasHeight, $topValue + $rowUnit + $columnHeaderHeight);
        }
    }

    $displayLabel = function($match, $side) {
        // $side: 'left' or 'right'
        $teamRelationKey = $side === 'left' ? 'homeTeam.team.name' : 'awayTeam.team.name';
        $sourceField = $side === 'left' ? 'source_home' : 'source_away';

        $label = data_get($match, $teamRelationKey) ?: data_get($match, $sourceField) ?: data_get($match, $side) ?: 'TBD';
        return $label;
    };
@endphp

<div class="bracket-section" data-bracket-mode="{{ $mode }}">
    <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-{{ $iconColor }}-500">
                    {!! $iconHtml !!}
                    Pengaturan Slot Bracket - {{ $teamsLabel }}
                </p>
                @if($mode === 'promotion')
                    <p class="text-sm text-slate-400 mt-2">Sistem bracket promosi otomatis berdasarkan tim yang lolos dari fase grup. Pilihan tim ditentukan dari centangan di "Tim Play Off Promosi".</p>
                @else
                    <p class="text-sm text-slate-400 mt-2">Sistem bracket degradasi otomatis berdasarkan tim yang terdegradasi dari fase grup. Pilihan tim ditentukan dari centangan di "Tim Play Off Degradasi".</p>
                @endif
            </div>
        </div>

        <div class="relative overflow-x-auto pb-8">
            <div id="bracketConnectorLayout{{ ucfirst($mode) }}" class="relative min-w-max">
                <svg id="bracketConnectorSvg{{ ucfirst($mode) }}" class="absolute inset-0 w-full h-full pointer-events-none" xmlns="http://www.w3.org/2000/svg"></svg>

                <div class="relative flex gap-24 w-full items-start">
                    @foreach($bracketColumns as $columnIndex => $column)
                        <div class="relative flex-shrink-0 w-[220px]" data-final-column="{{ $columnIndex === count($bracketColumns) - 1 ? '1' : '0' }}" style="min-height: {{ $bracketCanvasHeight }}px;">
                            <div class="mb-4">
                                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">{{ $column['label'] }} ({{ $column['teams'] }} Tim)</p>
                            </div>

                            @foreach($column['matches'] as $matchIndex => $match)
                                                @php
                                                    $top = ($cardTops[$columnIndex][$matchIndex] ?? 0) + $columnHeaderHeight;
                                                    $matchId = $match['id'] ?? "generated-{$columnIndex}-{$matchIndex}";
                                                    $isBye = ! empty($match['is_bye']) && ($match['left'] === 'Bye' || $match['right'] === 'Bye');
                                                    $teamName = $match['left'] === 'Bye' ? $match['right'] : $match['left'];
                                                    $teamLabel = $match['left'] === 'Bye' ? 'Tim 2' : 'Tim 1';
                                                    $cardIdPrefix = $mode === 'promotion' ? '' : 'rel-';

                                                    $leftDisplay = $displayLabel($match, 'left');
                                                    $rightDisplay = $displayLabel($match, 'right');
                                                @endphp
                                <div class="absolute left-0 right-0" style="top: {{ $top }}px;">
                                    <div
                                        id="bracket-card-{{ $cardIdPrefix }}{{ $matchId }}"
                                        class="relative z-10 rounded-2xl border border-slate-700 bg-slate-950 p-2 shadow-sm h-[120px] overflow-visible bracket-card"
                                        data-match-id="{{ $matchId }}"
                                        data-match-round="{{ $column['label'] }}"
                                        data-bracket-mode="{{ $mode }}"
                                        @if(! empty($match['next_match_id'])) data-next-match-id="{{ $match['next_match_id'] }}" @endif
                                    >
                                        <div class="text-[8px] uppercase tracking-[0.24em] text-slate-500 font-semibold mb-1">Match {{ $matchIndex + 1 }}</div>
                                        <div class="space-y-1">
                                                <div class="rounded-lg bg-slate-900 p-1.5 border border-slate-700">
                                                <p class="text-[7px] uppercase tracking-[0.20em] text-slate-500 mb-0.5">{{ $isBye ? $teamLabel : 'Tim 1' }}</p>
                                                <p class="text-xs text-slate-100">{{ $isBye ? $teamName : $leftDisplay }}</p>
                                            </div>

                                            @unless($isBye)
                                                    <div class="rounded-lg bg-slate-900 p-1.5 border border-slate-700">
                                                    <p class="text-[7px] uppercase tracking-[0.20em] text-slate-500 mb-0.5">Tim 2</p>
                                                    <p class="text-xs text-slate-100">{{ $rightDisplay }}</p>
                                                </div>
                                            @endunless
                                        </div>
                                        @if($isBye)
                                            <div class="mt-2 inline-flex items-center gap-2 rounded-full bg-amber-500/10 px-2 py-1 text-[7px] uppercase tracking-[0.20em] text-amber-300 font-semibold">Bye</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div id="thirdPlacePanel{{ ucfirst($mode) }}" class="absolute hidden transition-all duration-200 third-place-panel" data-has-server="{{ ! empty($thirdPlaceRoundData) ? '1' : '0' }}">
                    @if(! empty($thirdPlaceRoundData))
                        <div class="w-[220px]">
                            <div class="mb-4">
                                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">{{ $thirdPlaceRoundData['label'] }} ({{ $thirdPlaceRoundData['teams'] }} Tim)</p>
                            </div>

                            @foreach($thirdPlaceRoundData['matches'] as $matchIndex => $match)
                                @php
                                    $matchId = $match['id'] ?? "third-place-{$matchIndex}";
                                @endphp
                                <div class="relative rounded-2xl border border-slate-700 bg-slate-950 p-2 shadow-sm h-[120px] overflow-visible bracket-card mb-4" id="bracket-card-{{ $matchId }}" data-match-id="{{ $matchId }}" data-match-round="Third Place" data-bracket-mode="{{ $mode }}">
                                    <div class="text-[8px] uppercase tracking-[0.24em] text-slate-500 font-semibold mb-1">Match {{ $matchIndex + 1 }}</div>
                                    <div class="space-y-1">
                                        <div class="rounded-lg bg-slate-900 p-1.5 border border-slate-700">
                                            <p class="text-[7px] uppercase tracking-[0.20em] text-slate-500 mb-0.5">Tim 1</p>
                                            <p class="text-xs text-slate-100">{{ $match['left'] }}</p>
                                        </div>
                                        <div class="rounded-lg bg-slate-900 p-1.5 border border-slate-700">
                                            <p class="text-[7px] uppercase tracking-[0.20em] text-slate-500 mb-0.5">Tim 2</p>
                                            <p class="text-xs text-slate-100">{{ $match['right'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Tournament Logic Info -->
        <div class="mt-8 p-4 bg-slate-800/50 rounded-xl border border-slate-700">
            <p class="text-xs uppercase tracking-[0.24em] text-slate-500 mb-3 font-semibold">📋 Logika {{ $teamsLabel }}</p>
            <div class="text-sm text-slate-300 space-y-2">
                <p><strong>Bracket:</strong> Tim dari centangan "{{ $teamsLabel }}" babak awal → {{ $roundSummary }}</p>
                <p><strong>Koneksi Laga:</strong> Setiap match menunjuk ke <code>nextMatch</code> agar alur pemenang tertata dinamis.</p>
                <p><strong>Byes:</strong> Jika jumlah tim tidak kelipatan 2, tim yang tidak memiliki lawan pada putaran pertama akan otomatis maju.</p>
            </div>
        </div>
    </div>
</div>
