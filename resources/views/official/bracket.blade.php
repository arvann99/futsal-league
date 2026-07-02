@extends('official.layouts.app')

@section('title', 'Bagan / Bracket')

@section('content')
    <div class="mb-6">
        <p class="text-xs uppercase tracking-[0.35em] text-violet-300">Bagan Gugur</p>
        <h1 class="mt-3 text-3xl font-semibold text-white">Bracket Pertandingan</h1>
        <p class="mt-2 text-sm text-slate-400">Tampilan hanya-baca bagan babak gugur untuk turnamen yang diikuti tim Anda.</p>
    </div>

    @if($brackets->isEmpty())
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center text-slate-400">
            <p>Belum ada bagan gugur untuk ditampilkan.</p>
            <p class="mt-2 text-sm">Bracket akan muncul setelah panitia menyusun babak gugur turnamen Anda.</p>
        </div>
    @else
        @foreach($brackets as $bracketIndex => $bracket)
            @php
                $tournament = $bracket['tournament'];
                $columns = $bracket['columns'];
                $cardTops = $bracket['card_tops'];
                $canvasHeight = $bracket['canvas_height'];
                $rowUnit = $bracket['row_unit'];
                $headerHeight = $bracket['header_height'];
                $assignedMatches = $bracket['assigned_matches'];
                $scores = $bracket['scores'];
                $myTeamId = $bracket['team_id'];

                // N14 — pecah kolom jadi model dua sisi (mirror). Bila tak layak
                // (bracket terlalu kecil/struktur ganjil), $mirror['enabled'] false
                // → render fallback layout satu arah lama.
                $mirror = \App\Services\MatchGenerator::splitBracketColumnsMirror($columns);
                // Estimasi tinggi kartu untuk kalkulasi tinggi kanvas mirror.
                // Kartu Official (header TIM + 2 baris tim + status) ~250px;
                // estimasi terlalu kecil membuat kanvas pendek dan SVG konektor
                // TERPOTONG di bawah (garis menggantung tak sampai kartu).
                $cardHeight = 250;

                // Posisi vertikal khusus mirror: bagan mengerucut ke PUSAT, Final
                // tepat di tengah kanvas, dengan ruang di atasnya untuk centerpiece.
                $mirrorTops = null;
                $mirrorCanvasHeight = $canvasHeight;
                if ($mirror['enabled']) {
                    $mirrorTopPadding = 160;
                    $mirrorTops = \App\Services\MatchGenerator::computeMirrorCardTops($mirror, $rowUnit, $cardHeight, $mirrorTopPadding);
                    $mirrorCanvasHeight = $mirrorTops['height'] + $headerHeight;
                }

                $layoutId = 'official-bracket-' . $bracketIndex;
            @endphp

            <section class="mb-8">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-white">{{ $tournament->name }}</h2>
                    {{-- N14 — tombol Layar Penuh untuk bagan ini --}}
                    <button type="button" data-fullscreen-btn="{{ $layoutId }}"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-violet-500 hover:bg-slate-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"></path></svg>
                        <span data-fullscreen-label>Layar Penuh</span>
                    </button>
                </div>

                {{-- N8 — hint scroll horizontal saat bagan melebar --}}
                <p class="mb-2 flex items-center gap-2 text-xs text-slate-500 lg:hidden">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7l-4 5 4 5m8-10l4 5-4 5"></path></svg>
                    Geser ke kiri/kanan untuk melihat seluruh bagan
                </p>
                <div class="bracket-scroll relative rounded-2xl border border-slate-800 bg-slate-900/70 p-4 overflow-x-auto" data-bracket-scroll="{{ $layoutId }}">
                    {{-- N-zoom — tombol zoom mengambang, hanya relevan/terlihat saat layar penuh.
                         Harus berada DI DALAM elemen fullscreen (Fullscreen API tidak
                         menampilkan elemen di luar target requestFullscreen()). --}}
                    <div data-zoom-controls="{{ $layoutId }}" class="hidden sticky top-2 z-10 mb-2 items-center gap-1 self-start rounded-lg border border-slate-700 bg-slate-800/95 px-1.5 py-1 shadow-lg" style="width: max-content;">
                        <button type="button" data-zoom-out="{{ $layoutId }}" title="Perkecil"
                                class="flex h-6 w-6 items-center justify-center rounded text-slate-200 transition hover:bg-slate-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                        </button>
                        <span data-zoom-value="{{ $layoutId }}" class="w-10 text-center text-[11px] font-semibold text-slate-300">100%</span>
                        <button type="button" data-zoom-in="{{ $layoutId }}" title="Perbesar"
                                class="flex h-6 w-6 items-center justify-center rounded text-slate-200 transition hover:bg-slate-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        </button>
                    </div>
                    <div class="official-bracket-layout relative min-w-max" data-bracket-layout data-bracket-zoom-target id="{{ $layoutId }}">
                        {{-- overflow-visible: bila kartu nyata lebih tinggi dari estimasi
                             (nama tim wrap), garis konektor tetap tergambar utuh. --}}
                        <svg class="absolute inset-0 w-full h-full overflow-visible pointer-events-none" data-bracket-svg xmlns="http://www.w3.org/2000/svg"></svg>

                        @if($mirror['enabled'])
                            {{-- N14 — layout mirror dua sisi: kiri → FINAL (tengah) ← kanan.
                                 Posisi vertikal mengerucut ke pusat ($mirrorTops). --}}
                            <div class="relative flex gap-12 w-full items-start justify-center">
                                {{-- Sisi kiri: ronde awal → mendekati final --}}
                                @foreach($mirror['left'] as $leftColIdx => $column)
                                    <div class="relative flex-shrink-0 w-[200px]" style="min-height: {{ $mirrorCanvasHeight }}px;">
                                        <div class="mb-4">
                                            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">{{ $column['label'] }} ({{ $column['teams'] }} Tim)</p>
                                        </div>
                                        @foreach($column['matches'] as $localMatchIdx => $match)
                                            @include('official.partials.bracket-card', [
                                                'match' => $match,
                                                'matchIndex' => $match['match_index'],
                                                'top' => ($mirrorTops['left'][$leftColIdx][$localMatchIdx] ?? 0) + $headerHeight,
                                                'side' => 'left',
                                                'assignedMatches' => $assignedMatches,
                                                'scores' => $scores,
                                                'myTeamId' => $myTeamId,
                                            ])
                                        @endforeach
                                    </div>
                                @endforeach

                                {{-- Zona tengah: FINAL + piala + label juara, dipusatkan vertikal --}}
                                @php $finalMatch = $mirror['final']['matches'][0] ?? null; @endphp
                                <div class="relative flex-shrink-0 w-[200px] mx-6" style="min-height: {{ $mirrorCanvasHeight }}px;">
                                    @if($finalMatch)
                                        @php
                                            $finalScore = $scores[$finalMatch['id']] ?? null;
                                            $finalChampion = null;
                                            if (($finalScore['winner_side'] ?? null) === 'home') {
                                                $finalChampion = data_get($assignedMatches[$finalMatch['id']] ?? null, 'homeTeam.team.name');
                                            } elseif (($finalScore['winner_side'] ?? null) === 'away') {
                                                $finalChampion = data_get($assignedMatches[$finalMatch['id']] ?? null, 'awayTeam.team.name');
                                            }
                                            $finalTopPx = ($mirrorTops['final'] ?? 0) + $headerHeight;
                                        @endphp
                                        {{-- Zona di ATAS kartu Final: piala besar → JUARA → label FINAL. --}}
                                        <div class="absolute left-0 right-0 flex flex-col items-center justify-end text-center" style="top: 0; height: {{ max($finalTopPx - 8, 0) }}px;">
                                            <div class="text-6xl leading-none drop-shadow-[0_0_22px_rgba(245,197,24,0.45)] select-none" aria-hidden="true">🏆</div>
                                            @if($finalChampion)
                                                <span class="mt-3 text-[9px] uppercase tracking-[0.3em] text-amber-300 font-semibold">Juara</span>
                                                <span class="mt-1 rounded-full bg-amber-500/15 border border-amber-500/40 px-3 py-1 text-sm font-bold text-amber-200">{{ $finalChampion }}</span>
                                            @endif
                                            <p class="mt-3 mb-2 text-[11px] uppercase tracking-[0.3em] text-amber-300 font-bold">{{ $mirror['final']['label'] }}</p>
                                        </div>
                                        @include('official.partials.bracket-card', [
                                            'match' => $finalMatch,
                                            'matchIndex' => $finalMatch['match_index'],
                                            'top' => $finalTopPx,
                                            'side' => 'final',
                                            'assignedMatches' => $assignedMatches,
                                            'scores' => $scores,
                                            'myTeamId' => $myTeamId,
                                        ])
                                    @endif
                                </div>

                                {{-- Sisi kanan: mendekati final → ronde awal (cermin) --}}
                                @foreach($mirror['right'] as $rightColIdx => $column)
                                    <div class="relative flex-shrink-0 w-[200px]" style="min-height: {{ $mirrorCanvasHeight }}px;">
                                        {{-- Kolom kosong (spacer penyejajar ronde) tak diberi header. --}}
                                        @if(($column['matches'] ?? []) !== [])
                                        <div class="mb-4 text-right">
                                            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">{{ $column['label'] }} ({{ $column['teams'] }} Tim)</p>
                                        </div>
                                        @endif
                                        @foreach($column['matches'] as $localMatchIdx => $match)
                                            @include('official.partials.bracket-card', [
                                                'match' => $match,
                                                'matchIndex' => $match['match_index'],
                                                'top' => ($mirrorTops['right'][$rightColIdx][$localMatchIdx] ?? 0) + $headerHeight,
                                                'side' => 'right',
                                                'assignedMatches' => $assignedMatches,
                                                'scores' => $scores,
                                                'myTeamId' => $myTeamId,
                                            ])
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- Fallback: layout satu arah (kiri → kanan) seperti semula --}}
                            <div class="relative flex gap-12 w-full items-start">
                                @foreach($columns as $columnIndex => $column)
                                    <div class="relative flex-shrink-0 w-[200px]" style="min-height: {{ $canvasHeight }}px;">
                                        <div class="mb-4">
                                            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">{{ $column['label'] }} ({{ $column['teams'] }} Tim)</p>
                                        </div>
                                        @foreach($column['matches'] as $matchIndex => $match)
                                            @include('official.partials.bracket-card', [
                                                'match' => $match,
                                                'matchIndex' => $matchIndex,
                                                'top' => ($cardTops[$columnIndex][$matchIndex] ?? 0) + $headerHeight,
                                                'side' => '',
                                                'assignedMatches' => $assignedMatches,
                                                'scores' => $scores,
                                                'myTeamId' => $myTeamId,
                                            ])
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach
    @endif
@endsection

@push('styles')
<style>
    /* N8 — scrollbar tipis & rapi untuk bagan yang melebar. */
    .bracket-scroll { scrollbar-width: thin; scrollbar-color: #8b5cf6 #1e293b; scroll-behavior: smooth; }
    .bracket-scroll::-webkit-scrollbar { height: 10px; }
    .bracket-scroll::-webkit-scrollbar-track { background: #1e293b; border-radius: 9999px; }
    .bracket-scroll::-webkit-scrollbar-thumb { background: #8b5cf6; border-radius: 9999px; }
    .bracket-scroll::-webkit-scrollbar-thumb:hover { background: #a78bfa; }

    /* Mode layar penuh untuk bagan (Fullscreen API). */
    .bracket-fullscreen {
        width: 100vw;
        height: 100vh;
        max-width: none;
        overflow: auto;
        padding: 2rem;
        background: #0f172a;
    }
    [data-bracket-scroll]:fullscreen,
    [data-bracket-scroll]:-webkit-full-screen {
        width: 100vw;
        height: 100vh;
        overflow: auto;
        padding: 2rem;
        background: #0f172a;
    }

    [data-bracket-zoom-target] { transform-origin: top left; transition: transform 0.15s ease; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    function drawConnectorsFor(layout) {
        const svg = layout.querySelector('[data-bracket-svg]');
        if (!svg) return;

        const cards = Array.from(layout.querySelectorAll('.official-bracket-card[data-match-id]'));
        const map = new Map(cards.map(c => [c.dataset.matchId, c]));
        const rect = layout.getBoundingClientRect();
        const width = Math.max(rect.width, 0);
        const height = Math.max(rect.height, 0);

        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('width', width);
        svg.setAttribute('height', height);
        svg.innerHTML = '';

        // Anchor Y memakai tinggi NOMINAL (median) kartu, bukan tinggi kartu
        // masing-masing: satu kartu yang lebih tinggi (nama wrap, catatan
        // penalti) tidak boleh menggeser sikunya sendiri — garis kiri/kanan
        // harus tetap sejajar cermin.
        const cardHeights = cards
            .map(c => c.getBoundingClientRect().height)
            .sort((a, b) => a - b);
        const nominalHalf = cardHeights.length
            ? cardHeights[Math.floor(cardHeights.length / 2)] / 2
            : 0;

        const anchor = (el, side) => {
            const r = el.getBoundingClientRect();
            return {
                x: r.left - rect.left + (side === 'right' ? r.width : 0),
                y: r.top - rect.top + nominalHalf,
            };
        };

        cards.forEach(source => {
            const nextId = source.dataset.nextMatchId;
            if (!nextId) return;
            const target = map.get(nextId);
            if (!target) return;

            // N14 — pada sisi kanan (mirror), aliran mengarah ke KIRI menuju Final
            // di tengah: ambil tepi kiri sumber → tepi kanan target.
            const isRightSide = source.dataset.bracketSide === 'right';
            const s = anchor(source, isRightSide ? 'left' : 'right');
            const t = anchor(target, isRightSide ? 'right' : 'left');
            const midX = s.x + (t.x - s.x) / 2;

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', `M ${s.x} ${s.y} H ${midX} V ${t.y} H ${t.x}`);
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', '#8b5cf6');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('stroke-linecap', 'round');
            path.setAttribute('stroke-linejoin', 'round');
            svg.appendChild(path);
        });
    }

    function drawAll() {
        document.querySelectorAll('[data-bracket-layout]').forEach(drawConnectorsFor);
    }

    function setupFullscreen() {
        document.querySelectorAll('[data-fullscreen-btn]').forEach(btn => {
            const targetId = btn.dataset.fullscreenBtn;
            const container = document.querySelector(`[data-bracket-scroll="${targetId}"]`);
            const label = btn.querySelector('[data-fullscreen-label]');
            if (!container) return;

            btn.addEventListener('click', () => {
                const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
                if (!fsEl) {
                    (container.requestFullscreen || container.webkitRequestFullscreen)?.call(container);
                } else {
                    (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
                }
            });
        });

        const onChange = () => {
            document.querySelectorAll('[data-bracket-scroll]').forEach(container => {
                const targetId = container.dataset.bracketScroll;
                const active = document.fullscreenElement === container
                    || document.webkitFullscreenElement === container;
                container.classList.toggle('bracket-fullscreen', active);
                const btn = document.querySelector(`[data-fullscreen-btn="${targetId}"]`);
                const label = btn?.querySelector('[data-fullscreen-label]');
                if (label) label.textContent = active ? 'Keluar Layar Penuh' : 'Layar Penuh';

                const zoomControls = document.querySelector(`[data-zoom-controls="${targetId}"]`);
                zoomControls?.classList.toggle('hidden', !active);
                zoomControls?.classList.toggle('flex', active);
                if (!active) setZoom(targetId, 1);
            });
            // Redraw konektor setelah layout fullscreen stabil.
            setTimeout(drawAll, 120);
        };
        document.addEventListener('fullscreenchange', onChange);
        document.addEventListener('webkitfullscreenchange', onChange);
    }

    const zoomLevels = new Map();

    function setZoom(targetId, level) {
        const clamped = Math.min(2, Math.max(0.5, level));
        zoomLevels.set(targetId, clamped);
        const target = document.getElementById(targetId);
        if (target) target.style.transform = `scale(${clamped})`;
        const valueEl = document.querySelector(`[data-zoom-value="${targetId}"]`);
        if (valueEl) valueEl.textContent = Math.round(clamped * 100) + '%';
        setTimeout(drawAll, 160);
    }

    function setupZoom() {
        document.querySelectorAll('[data-zoom-in]').forEach(btn => {
            const targetId = btn.dataset.zoomIn;
            btn.addEventListener('click', () => setZoom(targetId, (zoomLevels.get(targetId) ?? 1) + 0.1));
        });
        document.querySelectorAll('[data-zoom-out]').forEach(btn => {
            const targetId = btn.dataset.zoomOut;
            btn.addEventListener('click', () => setZoom(targetId, (zoomLevels.get(targetId) ?? 1) - 0.1));
        });
    }

    window.addEventListener('load', drawAll);
    window.addEventListener('resize', drawAll);
    document.addEventListener('DOMContentLoaded', () => {
        setupFullscreen();
        setupZoom();
        setTimeout(drawAll, 50);
    });
})();
</script>
@endpush
