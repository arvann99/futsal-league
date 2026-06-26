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
        @foreach($brackets as $bracket)
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
            @endphp

            <section class="mb-8">
                <h2 class="mb-3 text-lg font-semibold text-white">{{ $tournament->name }}</h2>

                {{-- N8 — hint scroll horizontal saat bagan melebar --}}
                <p class="mb-2 flex items-center gap-2 text-xs text-slate-500 sm:hidden">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7l-4 5 4 5m8-10l4 5-4 5"></path></svg>
                    Geser ke kiri/kanan untuk melihat seluruh bagan
                </p>
                <div class="bracket-scroll rounded-2xl border border-slate-800 bg-slate-900/70 p-4 overflow-x-auto">
                    <div class="official-bracket-layout relative min-w-max" data-bracket-layout>
                        <svg class="absolute inset-0 w-full h-full pointer-events-none" data-bracket-svg xmlns="http://www.w3.org/2000/svg"></svg>

                        <div class="relative flex gap-12 w-full items-start">
                            @foreach($columns as $columnIndex => $column)
                                <div class="relative flex-shrink-0 w-[200px]" style="min-height: {{ $canvasHeight }}px;">
                                    <div class="mb-4">
                                        <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 font-semibold">{{ $column['label'] }} ({{ $column['teams'] }} Tim)</p>
                                    </div>

                                    @foreach($column['matches'] as $matchIndex => $match)
                                        @php
                                            $top = ($cardTops[$columnIndex][$matchIndex] ?? 0) + $headerHeight;
                                            $matchId = $match['id'] ?? "generated-{$match['index']}";
                                            $assigned = $assignedMatches[$match['id']] ?? null;

                                            $leftRaw = data_get($assigned, 'homeTeam.team.name') ?: data_get($assigned, 'source_home') ?: data_get($match, 'left') ?: 'Menunggu...';
                                            $rightRaw = data_get($assigned, 'awayTeam.team.name') ?: data_get($assigned, 'source_away') ?: data_get($match, 'right') ?: 'Menunggu...';
                                            $leftIsPlaceholder = preg_match('/(Winner|Loser|Runner[- ]?up|^[A-Z]\d|Bye)/i', (string) $leftRaw);
                                            $rightIsPlaceholder = preg_match('/(Winner|Loser|Runner[- ]?up|^[A-Z]\d|Bye)/i', (string) $rightRaw);
                                            $leftDisplay = isset($assigned->home_team_id) ? $leftRaw : ($leftIsPlaceholder ? 'Menunggu...' : $leftRaw);
                                            $rightDisplay = isset($assigned->away_team_id) ? $rightRaw : ($rightIsPlaceholder ? 'Menunggu...' : $rightRaw);

                                            $score = $scores[$match['id']] ?? null;
                                            $played = $score['played'] ?? false;
                                            $homeScoreText = ($played && isset($score['home']['score'])) ? $score['home']['score'] : '-';
                                            $awayScoreText = ($played && isset($score['away']['score'])) ? $score['away']['score'] : '-';
                                            $homeIsWinner = ($score['winner_side'] ?? null) === 'home';
                                            $awayIsWinner = ($score['winner_side'] ?? null) === 'away';

                                            $homeIsMine = $assigned && $assigned->home_team_id && optional($assigned->homeTeam)->team_id == $myTeamId;
                                            $awayIsMine = $assigned && $assigned->away_team_id && optional($assigned->awayTeam)->team_id == $myTeamId;
                                        @endphp

                                        <div class="absolute left-0 right-0" style="top: {{ $top }}px;">
                                            <div class="official-bracket-card relative z-10 rounded-2xl border border-slate-700 bg-slate-950 p-3 shadow-sm min-h-[120px] overflow-hidden"
                                                 data-match-id="{{ $matchId }}" data-next-match-id="{{ $match['next_match_id'] ?? '' }}">
                                                <div class="text-[9px] uppercase tracking-[0.24em] text-slate-500 font-semibold mb-2">Match {{ $matchIndex + 1 }}</div>
                                                <div class="space-y-3">
                                                    <div class="rounded-2xl bg-slate-900 p-3 border {{ $homeIsWinner ? 'border-emerald-500/50' : 'border-slate-700' }}">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <p class="text-sm {{ $homeIsWinner ? 'text-emerald-300 font-semibold' : 'text-slate-200' }}">
                                                                {{ $leftDisplay }}
                                                                @if($homeIsMine)<span class="ml-1 inline-flex rounded-full bg-violet-500/20 px-2 py-0.5 text-[9px] font-semibold text-violet-200">Tim Anda</span>@endif
                                                            </p>
                                                            <span class="shrink-0 min-w-[28px] text-center text-base font-bold tabular-nums {{ $homeIsWinner ? 'text-emerald-300' : ($played ? 'text-slate-100' : 'text-slate-500') }}">{{ $homeScoreText }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="rounded-2xl bg-slate-900 p-3 border {{ $awayIsWinner ? 'border-emerald-500/50' : 'border-slate-700' }}">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <p class="text-sm {{ $awayIsWinner ? 'text-emerald-300 font-semibold' : 'text-slate-200' }}">
                                                                {{ $rightDisplay }}
                                                                @if($awayIsMine)<span class="ml-1 inline-flex rounded-full bg-violet-500/20 px-2 py-0.5 text-[9px] font-semibold text-violet-200">Tim Anda</span>@endif
                                                            </p>
                                                            <span class="shrink-0 min-w-[28px] text-center text-base font-bold tabular-nums {{ $awayIsWinner ? 'text-emerald-300' : ($played ? 'text-slate-100' : 'text-slate-500') }}">{{ $awayScoreText }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                @if($score && ($score['pen_decides'] ?? false))
                                                    <p class="mt-2 text-[10px] text-amber-300/80">Pen: {{ $score['home']['pen'] ?? 0 }} - {{ $score['away']['pen'] ?? 0 }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
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

        const anchor = (el, side) => {
            const r = el.getBoundingClientRect();
            return {
                x: r.left - rect.left + (side === 'right' ? r.width : 0),
                y: r.top - rect.top + r.height / 2,
            };
        };

        cards.forEach(source => {
            const nextId = source.dataset.nextMatchId;
            if (!nextId) return;
            const target = map.get(nextId);
            if (!target) return;

            const s = anchor(source, 'right');
            const t = anchor(target, 'left');
            const midX = s.x + Math.max((t.x - s.x) / 2, 40);

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

    window.addEventListener('load', drawAll);
    window.addEventListener('resize', drawAll);
    document.addEventListener('DOMContentLoaded', () => setTimeout(drawAll, 50));
})();
</script>
@endpush
