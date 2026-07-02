{{--
    Kartu satu match bracket (Publik/Tamu, hanya-baca).
    Sama dengan kartu Official namun tanpa badge "Tim Anda" (publik bukan tim
    tertentu). Dipakai layout mirror N14 maupun fallback satu-arah.

    Variabel yang diharapkan:
      $match          array data match (id, index, left, right, next_match_id, ...)
      $matchIndex     int   nomor urut kartu dalam kolomnya (untuk label "Match N")
      $top            int   posisi top (px) absolut dalam kolom
      $side           string '' | 'left' | 'right' | 'final'
      $assignedMatches, $scores
--}}
@php
    $side = $side ?? '';
    $matchId = $match['id'] ?? "generated-{$match['index']}";
    $assigned = $assignedMatches[$match['id']] ?? null;

    $leftRaw = data_get($assigned, 'homeTeam.team.name') ?: data_get($assigned, 'source_home') ?: data_get($match, 'left') ?: 'Menunggu...';
    $rightRaw = data_get($assigned, 'awayTeam.team.name') ?: data_get($assigned, 'source_away') ?: data_get($match, 'right') ?: 'Menunggu...';
    $leftIsPlaceholder = preg_match('/(Winner|Loser|Runner[- ]?up|Pemenang|^[A-Z]\d|Bye)/i', (string) $leftRaw);
    $rightIsPlaceholder = preg_match('/(Winner|Loser|Runner[- ]?up|Pemenang|^[A-Z]\d|Bye)/i', (string) $rightRaw);
    $leftDisplay = isset($assigned->home_team_id) ? $leftRaw : ($leftIsPlaceholder ? 'Menunggu...' : $leftRaw);
    $rightDisplay = isset($assigned->away_team_id) ? $rightRaw : ($rightIsPlaceholder ? 'Menunggu...' : $rightRaw);

    $score = $scores[$match['id']] ?? null;
    $played = $score['played'] ?? false;
    $homeScoreText = ($played && isset($score['home']['score'])) ? $score['home']['score'] : '-';
    $awayScoreText = ($played && isset($score['away']['score'])) ? $score['away']['score'] : '-';
    $homeIsWinner = ($score['winner_side'] ?? null) === 'home';
    $awayIsWinner = ($score['winner_side'] ?? null) === 'away';
@endphp

<div class="absolute left-0 right-0" style="top: {{ $top }}px;">
    <div class="public-bracket-card relative z-10 rounded-2xl border border-slate-700 bg-slate-950 p-3 shadow-sm min-h-[120px] overflow-hidden"
         data-match-id="{{ $matchId }}" data-next-match-id="{{ $match['next_match_id'] ?? '' }}" @if($side) data-bracket-side="{{ $side }}" @endif>
        <div class="text-[9px] uppercase tracking-[0.24em] text-slate-500 font-semibold mb-2">Match {{ $matchIndex + 1 }}</div>
        <div class="space-y-3">
            <div class="rounded-2xl bg-slate-900 p-3 border {{ $homeIsWinner ? 'border-emerald-500/50' : 'border-slate-700' }}">
                <div class="flex items-center justify-between gap-2">
                    {{-- truncate: nama panjang tak boleh wrap — kartu yang membengkak
                         membuat titik tengahnya bergeser dan konektor jadi asimetris. --}}
                    <p class="min-w-0 flex-1 truncate text-sm {{ $homeIsWinner ? 'text-emerald-300 font-semibold' : 'text-slate-200' }}" title="{{ $leftDisplay }}">{{ $leftDisplay }}</p>
                    <span class="shrink-0 min-w-[28px] text-center text-base font-bold tabular-nums {{ $homeIsWinner ? 'text-emerald-300' : ($played ? 'text-slate-100' : 'text-slate-500') }}">{{ $homeScoreText }}</span>
                </div>
            </div>
            <div class="rounded-2xl bg-slate-900 p-3 border {{ $awayIsWinner ? 'border-emerald-500/50' : 'border-slate-700' }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="min-w-0 flex-1 truncate text-sm {{ $awayIsWinner ? 'text-emerald-300 font-semibold' : 'text-slate-200' }}" title="{{ $rightDisplay }}">{{ $rightDisplay }}</p>
                    <span class="shrink-0 min-w-[28px] text-center text-base font-bold tabular-nums {{ $awayIsWinner ? 'text-emerald-300' : ($played ? 'text-slate-100' : 'text-slate-500') }}">{{ $awayScoreText }}</span>
                </div>
            </div>
        </div>
        @if($score && ($score['pen_decides'] ?? false))
            <p class="mt-2 text-[10px] text-amber-300/80">Pen: {{ $score['home']['pen'] ?? 0 }} - {{ $score['away']['pen'] ?? 0 }}</p>
        @endif
    </div>
</div>
