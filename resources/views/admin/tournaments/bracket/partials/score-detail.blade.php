@php
    /** @var array|null $score */
    $score = $score ?? null;
    $played = $played ?? false;
    $twoLeg = $score['two_leg'] ?? false;
    $legs = $score['legs'] ?? [];
    $penDecides = $score['pen_decides'] ?? false;
    $penHome = $score['home']['pen'] ?? null;
    $penAway = $score['away']['pen'] ?? null;
    $hasPenalty = $penHome !== null && $penAway !== null;
@endphp

@if(! $played)
    <div class="mt-2 text-center text-[10px] uppercase tracking-[0.18em] text-slate-600 font-semibold">
        Belum bertanding
    </div>
@else
    @if($twoLeg && count($legs))
        <div class="mt-2 flex flex-wrap items-center justify-center gap-1.5 text-[10px] font-semibold">
            @foreach($legs as $i => $leg)
                <span class="rounded-md bg-slate-800 px-2 py-0.5 text-slate-300">
                    Leg {{ $i + 1 }}: {{ $leg['home'] ?? '-' }}–{{ $leg['away'] ?? '-' }}
                </span>
            @endforeach
            <span class="rounded-md bg-slate-700/60 px-2 py-0.5 text-slate-200">
                Agg: {{ $score['home']['score'] ?? '-' }}–{{ $score['away']['score'] ?? '-' }}
            </span>
        </div>
    @endif

    @if($hasPenalty && $penDecides)
        <div class="mt-2 text-center">
            <span class="inline-flex items-center gap-1 rounded-md bg-amber-500/15 px-2 py-0.5 text-[10px] font-semibold text-amber-300">
                Adu Penalti: {{ $penHome }}–{{ $penAway }}
            </span>
        </div>
    @endif
@endif
