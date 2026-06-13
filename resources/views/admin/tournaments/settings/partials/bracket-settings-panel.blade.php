<div class="p-4 sm:p-6 max-w-full">
    @if(session('success'))
        <div class="mb-6 p-4 bg-fuchsia-900/20 border border-fuchsia-500/30 rounded-lg text-fuchsia-300 text-sm">
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

    <form action="{{ route('tournaments.updateBracketSettings', $tournament) }}" method="POST" class="space-y-6">
        @csrf

        @php
            $groupCount = optional($tournament->groupSetting)->group_count ?? ($setting->value['group_count'] ?? 4);
            
            $isPureKnockout = isset($competitionType) && $competitionType === 'tournament';

            // IMPORTANT: Always use $teamsToUse if available (from controller)
            // This ensures the displayed teams match exactly what's configured in group settings
            if (isset($teamsToUse) && !empty($teamsToUse)) {
                $teamNames = array_keys($teamsToUse); // Posisi: nama tim (mode turnamen) atau placeholder A1, B1, dst.
                $rankLabel = $isPureKnockout
                    ? 'Tim Peserta (Terverifikasi)'
                    : ((isset($playoffMode) && $playoffMode === 'relegation') ? 'Tim Degradasi' : 'Tim Qualified');
            } elseif ($isPureKnockout) {
                // Gugur murni tanpa tim terverifikasi: belum ada slot
                $teamNames = [];
                $rankLabel = 'Tim Peserta (Terverifikasi)';
            } else {
                // Fallback: If $teamsToUse is not provided, generate from tournament settings
                // This is a safety net for compatibility
                if (isset($playoffMode) && $playoffMode === 'relegation') {
                    $ranksToUse = optional($tournament->groupSetting)->relegated_teams ?? [];
                    $rankLabel = 'Tim Degradasi';
                } else {
                    $ranksToUse = optional($tournament->groupSetting)->qualified_teams ?? [1, 2];
                    $rankLabel = 'Tim Qualified';
                }

                $groupLabels = array_slice(['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P'], 0, $groupCount);
                $teamNames = [];
                foreach ($groupLabels as $group) {
                    foreach ($ranksToUse as $rank) {
                        $teamNames[] = strtoupper($group) . $rank;
                    }
                }
            }
            
            $teamNames = array_values(array_unique($teamNames));
            $positions = $teamNames;

            if (isset($hasBothOptions) && $hasBothOptions) {
                $matches = old('matches', $setting->value['matches_promotion'] ?? $setting->value['matches'] ?? []);
            } else {
                $matches = old('matches', $setting->value['matches'] ?? []);
            }

            if (! is_array($matches)) {
                $matches = [];
            }

            $hasThirdPlace = old('third_place', $setting->value['third_place'] ?? false);
            $hasThirdPlace = in_array($hasThirdPlace, [true, '1', 1, 'on'], true);

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

            $thirdPlaceRound = array_values(array_filter($rounds, fn($round) => $round['label'] === 'Third Place'));
            $thirdPlaceRound = $thirdPlaceRound[0] ?? null;
            $nonThirdRounds = array_values(array_filter($rounds, fn($round) => $round['label'] !== 'Third Place'));
            $finalRound = [];
            if (! empty($nonThirdRounds)) {
                $finalRound = end($nonThirdRounds);
            }
            $leadingRounds = array_slice($nonThirdRounds, 0, max(0, count($nonThirdRounds) - 1));
            $totalBracketMatches = count($matches);
            $roundSummary = implode(' → ', array_map(fn($round) => $round['label'], $nonThirdRounds));
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 mb-6">
            <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500 mb-2">Mode Knockout</p>
                <p class="text-sm text-slate-300">Pilih format pertandingan knockout dari babak awal hingga final.</p>
                <div class="space-y-3 mt-4">
                    <label class="flex items-center gap-3 p-4 bg-slate-800 rounded-xl cursor-pointer">
                        <input type="radio" name="match_type" value="single" class="text-fuchsia-500 focus:ring-fuchsia-400" {{ old('match_type', $setting->value['match_type'] ?? 'single') === 'single' ? 'checked' : '' }}>
                        <span class="text-sm text-slate-200">Single Leg (satu pertandingan per laga)</span>
                    </label>
                    <label class="flex items-center gap-3 p-4 bg-slate-800 rounded-xl cursor-pointer">
                        <input type="radio" name="match_type" value="home_away" class="text-fuchsia-500 focus:ring-fuchsia-400" {{ old('match_type', $setting->value['match_type'] ?? 'single') === 'home_away' ? 'checked' : '' }}>
                        <span class="text-sm text-slate-200">Home & Away (leg pulang-pergi)</span>
                    </label>
                    @php
                        $homeAwayCalculation = old('home_away_calculation', $setting->value['home_away_calculation'] ?? 'aggregate');
                        $isHomeAway = old('match_type', $setting->value['match_type'] ?? 'single') === 'home_away';
                    @endphp
                    <div id="homeAwayCalcOptions" class="ml-6 space-y-2 border-l-2 border-slate-700 pl-4 {{ $isHomeAway ? '' : 'hidden' }}">
                        <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Penentuan Pemenang Tie</p>
                        <label class="flex items-center gap-3 p-3 bg-slate-800 rounded-xl cursor-pointer">
                            <input type="radio" name="home_away_calculation" value="aggregate" class="text-fuchsia-500 focus:ring-fuchsia-400" {{ $homeAwayCalculation === 'aggregate' ? 'checked' : '' }}>
                            <span class="text-sm text-slate-200">Agregat Skor <span class="text-slate-400">— pemenang dari total gol kedua leg</span></span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-slate-800 rounded-xl cursor-pointer">
                            <input type="radio" name="home_away_calculation" value="wins" class="text-fuchsia-500 focus:ring-fuchsia-400" {{ $homeAwayCalculation === 'wins' ? 'checked' : '' }}>
                            <span class="text-sm text-slate-200">Jumlah Kemenangan <span class="text-slate-400">— pemenang dari jumlah leg yang dimenangkan</span></span>
                        </label>
                        <p class="text-xs text-slate-400">Jika hasil tetap seri setelah leg kedua, pemenang ditentukan melalui adu penalti (tanpa aturan gol tandang). Final dan perebutan tempat ketiga tetap satu pertandingan.</p>
                    </div>
                    <label class="flex flex-col gap-3 p-4 bg-slate-800 rounded-xl cursor-pointer">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" name="third_place" value="1" class="text-fuchsia-500 focus:ring-fuchsia-400" {{ $hasThirdPlace ? 'checked' : '' }}>
                            <span class="text-sm text-slate-200">Sertakan perebutan tempat ketiga</span>
                        </div>
                        <p class="text-xs text-slate-400">Jika dicentang, maka di Pengaturan Slot Bracket akan muncul babak "Third Place" yang diisi oleh runner-up semifinal.</p>
                    </label>
                </div>
            </div>
            

            <div class="grid gap-3 mt-6 sm:grid-cols-2">
                <div class="p-4 bg-slate-800 rounded-xl">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-400 mb-2">{{ isset($rankLabel) ? $rankLabel : 'Tim Qualified' }}</p>
                    <p class="text-sm text-slate-300">{{ implode(', ', array_slice($teamNames, 0, min(10, count($teamNames)))) }}{{ count($teamNames) > 10 ? ', ...' : '' }}</p>
                <br>
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-400 mb-2">Slot Default</p>
                    <p class="text-sm text-slate-300">{{ implode(' / ', array_slice($teamNames, 0, min(8, count($teamNames)))) }}{{ count($teamNames) > 8 ? ' / ...' : '' }}</p>
                </div>
            </div>
        </div>

        <input type="hidden" name="group_count" value="{{ $groupCount }}">

        @if(isset($hasBothOptions) && $hasBothOptions)
            <!-- Tab Navigation untuk Promosi & Degradasi -->
            <div class="flex gap-2 mb-6 border-b border-slate-800 pb-3">
                <button type="button" class="bracket-tab-btn active px-6 py-2 text-sm font-semibold text-emerald-400 border-b-2 border-emerald-400 transition" data-bracket-mode="promotion">
                    <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.3A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z"></path>
                    </svg>
                    Play Off Promosi
                </button>
                <button type="button" class="bracket-tab-btn px-6 py-2 text-sm font-semibold text-slate-400 border-b-2 border-transparent hover:border-red-400 transition" data-bracket-mode="relegation">
                    <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M14.5 7a3.5 3.5 0 01.369 6.98 4 4 0 11-7.753 1.3A4.5 4.5 0 1116.5 7H15v3.587l-1.293-1.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L15 10.587V7z"></path>
                    </svg>
                    Play Off Degradasi
                </button>
            </div>

            <!-- Bracket Sections untuk Promosi & Degradasi -->
            @php
                $promotionMatches = $setting->value['matches_promotion'] ?? $setting->value['matches'] ?? [];
                $degradationMatches = $setting->value['matches_relegation'] ?? $setting->value['matches'] ?? [];
            @endphp

            <!-- Promosi Section -->
            <div id="bracketPromotionSection" class="bracket-section" data-bracket-mode="promotion">
                @include('admin.tournaments.settings.partials.bracket-section', [
                    'mode' => 'promotion',
                    'matches' => $promotionMatches,
                    'roundSummary' => $roundSummary,
                    'hasThirdPlace' => $hasThirdPlace,
                    'thirdPlaceRound' => $thirdPlaceRound
                ])
            </div>

            <!-- Degradasi Section (hidden by default) -->
            <div id="bracketRelegationSection" class="bracket-section hidden" data-bracket-mode="relegation">
                @include('admin.tournaments.settings.partials.bracket-section', [
                    'mode' => 'relegation',
                    'matches' => $degradationMatches,
                    'roundSummary' => $roundSummary,
                    'hasThirdPlace' => $hasThirdPlace,
                    'thirdPlaceRound' => $thirdPlaceRound
                ])
            </div>
        @else

        <!-- Single Bracket Rendering (when not hasBothOptions) -->
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Pengaturan Slot Bracket</p>
                    @if(isset($playoffMode) && $playoffMode === 'relegation')
                        <p class="text-sm text-slate-400 mt-2">Sistem playoff degradasi otomatis berdasarkan tim yang terdegradasi dari fase liga. Setiap card memiliki <code>data-match-id</code> dan <code>data-next-match-id</code>, lalu konektor SVG menggambar jalur siku-siku antar card. Jika opsi perebutan tempat ketiga dicentang, babak Third Place akan muncul di Pengaturan Slot Bracket.</p>
                    @elseif(isset($competitionType) && $competitionType === 'tournament')
                        <p class="text-sm text-slate-400 mt-2">Bracket gugur murni: slot diisi otomatis oleh semua tim yang lolos verifikasi (tanpa fase grup) dan diperbarui saat daftar peserta berubah. Jika opsi perebutan tempat ketiga dicentang, babak Third Place akan muncul dan diisi oleh runner-up semifinal.</p>
                    @else
                        <p class="text-sm text-slate-400 mt-2">Sistem knockout otomatis berdasarkan jumlah tim yang lolos dari fase liga. Setiap card memiliki <code>data-match-id</code> dan <code>data-next-match-id</code>, lalu konektor SVG menggambar jalur siku-siku antar card. Jika opsi perebutan tempat ketiga dicentang, babak Third Place akan muncul di Pengaturan Slot Bracket dan diisi oleh runner-up semifinal.</p>
                    @endif
                </div>
            </div>

            @php
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
            @endphp

            <div class="relative overflow-x-auto pb-8">
                <div id="bracketConnectorLayout" class="relative min-w-max">
                    <svg id="bracketConnectorSvg" class="absolute inset-0 w-full h-full pointer-events-none" xmlns="http://www.w3.org/2000/svg"></svg>

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
                                    @endphp
                                    <div class="absolute left-0 right-0" style="top: {{ $top }}px;">
                                        <div
                                            id="bracket-card-{{ $matchId }}"
                                            class="relative z-10 rounded-2xl border border-slate-700 bg-slate-950 p-2 shadow-sm h-[120px] overflow-visible bracket-card"
                                            data-match-id="{{ $matchId }}"
                                            data-match-round="{{ $column['label'] }}"
                                            @if(! empty($match['next_match_id'])) data-next-match-id="{{ $match['next_match_id'] }}" @endif
                                        >
                                            <div class="text-[8px] uppercase tracking-[0.24em] text-slate-500 font-semibold mb-1">Match {{ $matchIndex + 1 }}</div>
                                            <div class="space-y-1">
                                                <div class="rounded-lg bg-slate-900 p-1.5 border border-slate-700">
                                                    <p class="text-[7px] uppercase tracking-[0.20em] text-slate-500 mb-0.5">{{ $isBye ? $teamLabel : 'Tim 1' }}</p>
                                                    <p class="text-xs text-slate-100">{{ $isBye ? $teamName : $match['left'] }}</p>
                                                </div>

                                                @unless($isBye)
                                                    <div class="rounded-lg bg-slate-900 p-1.5 border border-slate-700">
                                                        <p class="text-[7px] uppercase tracking-[0.20em] text-slate-500 mb-0.5">Tim 2</p>
                                                        <p class="text-xs text-slate-100">{{ $match['right'] }}</p>
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

                    <div id="thirdPlacePanel"
                        class="third-place-panel absolute hidden transition-all duration-200"
                        data-has-server="{{ ! empty($thirdPlaceRound) ? '1' : '0' }}">
                        @if(! empty($thirdPlaceRound))
                            <div class="w-[220px]">
                                <div class="mb-4">
                                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">{{ $thirdPlaceRound['label'] }} ({{ $thirdPlaceRound['teams'] }} Tim)</p>
                                </div>

                                @foreach($thirdPlaceRound['matches'] as $matchIndex => $match)
                                    @php
                                        $matchId = $match['id'] ?? "third-place-{$matchIndex}";
                                    @endphp
                                    <div class="relative rounded-2xl border border-slate-700 bg-slate-950 p-2 shadow-sm h-[120px] overflow-visible bracket-card mb-4" id="bracket-card-{{ $matchId }}" data-match-id="{{ $matchId }}" data-match-round="Third Place">
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
                <p class="text-xs uppercase tracking-[0.24em] text-slate-500 mb-3 font-semibold">📋 Logika Turnamen</p>
                <div class="text-sm text-slate-300 space-y-2">
                    <p><strong>Bracket:</strong> {{ count($positions) }} tim babak awal → {{ $roundSummary }}</p>
                    <p><strong>Koneksi Laga:</strong> Setiap match menunjuk ke <code>nextMatch</code> agar alur pemenang tertata dinamis.</p>
                    <p><strong>Byes:</strong> Jika jumlah tim tidak kelipatan 2, tim yang tidak memiliki lawan pada putaran pertama akan otomatis maju.</p>
                    @if($hasThirdPlace)
                        <p><strong>3rd Place Playoff:</strong> Runner-up semifinal melawan runner-up semifinal lain untuk juara ke-3</p>
                    @else
                        <p class="text-slate-400"><em>3rd Place Playoff: Nonaktif (centang pengaturan untuk mengaktifkan)</em></p>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 grid gap-4 sm:grid-cols-2">
            <div class="p-4 bg-slate-800 rounded-xl">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400 mb-2">Ringkasan</p>
                <p class="text-sm text-slate-300">{{ count($positions) }} tim bracket → {{ $roundSummary }}{{ $hasThirdPlace ? ' + 3rd Place' : '' }}</p>
            </div>
            <div class="p-4 bg-slate-800 rounded-xl">
                <p class="text-xs uppercase tracking-[0.24em] text-slate-400 mb-2">Total Pertandingan</p>
                <p class="text-lg font-bold text-fuchsia-300">{{ $totalBracketMatches }} Pertandingan</p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 pt-4">
            <a href="{{ route('tournaments.settings', $tournament) }}" class="flex-1 text-center py-3 px-6 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-lg transition">
                Kembali
            </a>
            <button type="button" onclick="submitResetBracket()" class="flex-1 py-3 px-6 bg-red-600/20 hover:bg-red-600/40 text-red-400 font-semibold rounded-lg transition">
                Reset Default
            </button>
            <button type="submit" class="flex-1 py-3 px-6 bg-fuchsia-600 hover:bg-fuchsia-700 text-white font-semibold rounded-lg transition">
                Simpan Pengaturan
            </button>
        </div>
    </form>

    <form id="resetBracketSettings" action="{{ route('tournaments.resetBracketSettings', $tournament) }}" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>

<script>
    function submitResetBracket() {
        if (confirm('Reset pengaturan Bagan Bracket ke default?')) {
            document.getElementById('resetBracketSettings').submit();
        }
    }

    function drawBracketConnections() {
        const defaultLayout = document.getElementById('bracketConnectorLayout');
        if (defaultLayout) {
            const svg = document.getElementById('bracketConnectorSvg');
            if (! svg) {
                return;
            }

            const cardElements = Array.from(defaultLayout.querySelectorAll('.bracket-card[data-match-id]'));
            const cardMap = new Map(cardElements.map(card => [card.dataset.matchId, card]));
            const layoutRect = defaultLayout.getBoundingClientRect();
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

            cardElements.forEach((sourceCard) => {
                const nextMatchId = sourceCard.dataset.nextMatchId;
                if (! nextMatchId) {
                    return;
                }

                const targetCard = cardMap.get(nextMatchId);
                if (! targetCard) {
                    return;
                }

                const sourcePoint = getAnchor(sourceCard, 'right');
                const targetPoint = getAnchor(targetCard, 'left');
                const midX = sourcePoint.x + Math.max((targetPoint.x - sourcePoint.x) / 2, 40);

                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', `M ${sourcePoint.x} ${sourcePoint.y} H ${midX} V ${targetPoint.y} H ${targetPoint.x}`);
                path.setAttribute('fill', 'none');
                path.setAttribute('stroke', '#7c3aed');
                path.setAttribute('stroke-width', '2');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('stroke-linejoin', 'round');
                svg.appendChild(path);

                const startDot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                startDot.setAttribute('cx', sourcePoint.x);
                startDot.setAttribute('cy', sourcePoint.y);
                startDot.setAttribute('r', '4');
                startDot.setAttribute('fill', '#7c3aed');
                svg.appendChild(startDot);

                const endDot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                endDot.setAttribute('cx', targetPoint.x);
                endDot.setAttribute('cy', targetPoint.y);
                endDot.setAttribute('r', '4');
                endDot.setAttribute('fill', '#7c3aed');
                svg.appendChild(endDot);
            });

            return;
        }

        ['promotion', 'relegation'].forEach(drawBracketConnectionsForMode);
    }

    function updateThirdPlacePanel() {
    const checkbox = document.querySelector('input[name="third_place"]');
    const panel = document.getElementById('thirdPlacePanel');
    const layout = document.getElementById('bracketConnectorLayout');

    if (!checkbox || !panel || !layout) {
        return;
    }

    // Jika tidak dicentang sembunyikan
    if (!checkbox.checked) {
        panel.classList.add('hidden');
        return;
    }

    // Cari semifinal
    const semifinalCards = Array.from(
        document.querySelectorAll(
            '.bracket-card[data-match-round="Semifinal"]'
        )
    );

    if (semifinalCards.length < 2) {
        panel.classList.add('hidden');
        return;
    }

    const semi1 = semifinalCards[0].dataset.matchId;
    const semi2 = semifinalCards[1].dataset.matchId;

    // Jika belum ada card dari server, buat otomatis
    if (panel.dataset.hasServer !== '1') {
        panel.innerHTML = `
            <div class="w-[220px]">
                <div class="mb-4">
                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">
                        Third Place (2 Tim)
                    </p>
                </div>

                <div
                    class="relative rounded-2xl border border-slate-700 bg-slate-950 p-2 shadow-sm h-[120px] overflow-visible bracket-card"
                    data-match-id="third-place"
                    data-match-round="Third Place"
                >
                    <div class="text-[8px] uppercase tracking-[0.24em] text-slate-500 font-semibold mb-1">
                        Match 1
                    </div>

                    <div class="space-y-1">
                        <div class="rounded-lg bg-slate-900 p-1.5 border border-slate-700">
                            <p class="text-[7px] uppercase tracking-[0.20em] text-slate-500 mb-0.5">
                                Tim 1
                            </p>
                            <p class="text-xs text-slate-100">
                                Runner-up ${semi1}
                            </p>
                        </div>

                        <div class="rounded-lg bg-slate-900 p-1.5 border border-slate-700">
                            <p class="text-[7px] uppercase tracking-[0.20em] text-slate-500 mb-0.5">
                                Tim 2
                            </p>
                            <p class="text-xs text-slate-100">
                                Runner-up ${semi2}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Posisi di bawah final
    const finalCard = document.querySelector(
        '.bracket-card[data-match-round="Final"]'
    );

    if (finalCard) {
        const layoutRect = layout.getBoundingClientRect();
        const finalRect = finalCard.getBoundingClientRect();

        panel.style.left =
            (finalRect.left - layoutRect.left) + 'px';

        panel.style.top =
            (finalRect.bottom - layoutRect.top + 30) + 'px';
    }

    panel.classList.remove('hidden');
}

    function updateHomeAwayCalcOptions() {
        const panel = document.getElementById('homeAwayCalcOptions');
        const selected = document.querySelector('input[name="match_type"]:checked');

        if (! panel || ! selected) {
            return;
        }

        panel.classList.toggle('hidden', selected.value !== 'home_away');
    }

    document.addEventListener('DOMContentLoaded', function () {

    drawBracketConnections(); // WAJIB

    document.querySelectorAll('input[name="match_type"]').forEach(function (radio) {
        radio.addEventListener('change', updateHomeAwayCalcOptions);
    });
    updateHomeAwayCalcOptions();

    const thirdPlaceCheckbox =
        document.querySelector('input[name="third_place"]');

    if (thirdPlaceCheckbox) {
        thirdPlaceCheckbox.addEventListener('change', function () {

            updateThirdPlacePanel();

            setTimeout(() => {
                drawBracketConnections();
            }, 100);

        });
    }

    updateThirdPlacePanel();

    window.addEventListener('resize', function () {
        drawBracketConnections();
        updateThirdPlacePanel();
    });

    initBracketTabNavigation();
});

    function initBracketTabNavigation() {
        const tabButtons = document.querySelectorAll('.bracket-tab-btn');
        if (tabButtons.length === 0) {
            return;
        }

        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const mode = this.dataset.bracketMode;
                
                // Update tab button styles
                tabButtons.forEach(btn => {
                    btn.classList.remove('border-b-emerald-400', 'border-b-red-400', 'text-emerald-400', 'text-red-400');
                    btn.classList.add('border-transparent', 'text-slate-400');
                });

                // Set active button
                this.classList.remove('border-transparent', 'text-slate-400');
                if (mode === 'promotion') {
                    this.classList.add('border-b-emerald-400', 'text-emerald-400');
                } else {
                    this.classList.add('border-b-red-400', 'text-red-400');
                }

                // Toggle bracket sections visibility
                const bracketSections = document.querySelectorAll('.bracket-section[data-bracket-mode]');
                bracketSections.forEach(section => {
                    if (section.dataset.bracketMode === mode) {
                        section.classList.remove('hidden');
                        // Re-draw connections for visible bracket
                        const layoutId = `bracketConnectorLayout${mode.charAt(0).toUpperCase() + mode.slice(1)}`;
                        const layout = document.getElementById(layoutId);
                        if (layout) {
                            drawBracketConnectionsForMode(mode);
                            updateThirdPlacePanel();
                        }
                    } else {
                        section.classList.add('hidden');
                    }
                });
            });
        });
    }

    function drawBracketConnectionsForMode(mode) {
        const suffix = mode.charAt(0).toUpperCase() + mode.slice(1);
        const layout = document.getElementById(`bracketConnectorLayout${suffix}`);
        const svg = document.getElementById(`bracketConnectorSvg${suffix}`);
        if (! layout || ! svg) {
            return;
        }

        const cardElements = Array.from(layout.querySelectorAll('.bracket-card[data-match-id][data-bracket-mode="' + mode + '"]'));
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

        cardElements.forEach((sourceCard) => {
            const nextMatchId = sourceCard.dataset.nextMatchId;
            if (! nextMatchId) {
                return;
            }

            const targetCard = cardMap.get(nextMatchId);
            if (! targetCard) {
                return;
            }

            const sourcePoint = getAnchor(sourceCard, 'right');
            const targetPoint = getAnchor(targetCard, 'left');
            const midX = sourcePoint.x + Math.max((targetPoint.x - sourcePoint.x) / 2, 40);

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', `M ${sourcePoint.x} ${sourcePoint.y} L ${midX} ${sourcePoint.y} L ${midX} ${targetPoint.y} L ${targetPoint.x} ${targetPoint.y}`);
            path.setAttribute('stroke', '#a78bfa');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke-linecap', 'round');
            svg.appendChild(path);

            const startDot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            startDot.setAttribute('cx', sourcePoint.x);
            startDot.setAttribute('cy', sourcePoint.y);
            startDot.setAttribute('r', '4');
            startDot.setAttribute('fill', '#7c3aed');
            svg.appendChild(startDot);

            const endDot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            endDot.setAttribute('cx', targetPoint.x);
            endDot.setAttribute('cy', targetPoint.y);
            endDot.setAttribute('r', '4');
            endDot.setAttribute('fill', '#7c3aed');
            svg.appendChild(endDot);
        });
    }

</script>
