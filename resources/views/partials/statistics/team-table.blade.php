{{--
    N12 — Partial tabel ranking tim (reusable; dipakai juga oleh N13).

    Variabel yang diharapkan:
      $title       string  judul kartu
      $accent      string  kelas warna aksen
      $rows        Collection objek dengan: team_name + atribut $metric (mode 'value')
                            atau fairplay (yellow_cards, red_cards, total_cards)
      $mode        string  'value' (satu metrik) | 'fairplay' (KK/KM/total)
      $metric      string  (mode 'value') nama atribut nilai
      $metricLabel string  label kolom nilai
      $emptyText   string  (opsional)
--}}
@php
    $mode = $mode ?? 'value';
    $emptyText = $emptyText ?? 'Belum ada data.';
@endphp
<div class="bg-slate-900/80 rounded-xl border border-slate-800 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
        <h3 class="font-semibold text-sm text-white">{{ $title }}</h3>
        <span class="text-[10px] uppercase tracking-widest text-slate-500">{{ $metricLabel }}</span>
    </div>

    @if($rows->isEmpty())
        <div class="px-4 py-8 text-center text-sm text-slate-500">{{ $emptyText }}</div>
    @else
        <ul class="divide-y divide-slate-800/80">
            @foreach($rows as $i => $row)
                <li class="flex items-center gap-3 px-4 py-2.5">
                    <span class="w-6 text-center text-xs font-bold {{ $i < 3 ? $accent : 'text-slate-500' }}">{{ $i + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-white truncate">{{ $row->team_name }}</p>
                    </div>
                    @if($mode === 'fairplay')
                        <div class="flex items-center gap-2 text-xs">
                            <span class="inline-flex items-center gap-1 text-amber-300" title="Kartu Kuning">
                                <span class="w-2.5 h-3.5 rounded-[2px] bg-amber-400/80 inline-block"></span>{{ $row->yellow_cards }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-rose-300" title="Kartu Merah">
                                <span class="w-2.5 h-3.5 rounded-[2px] bg-rose-500/80 inline-block"></span>{{ $row->red_cards }}
                            </span>
                            <span class="ml-1 text-lg font-bold {{ $accent }} tabular-nums">{{ $row->total_cards }}</span>
                        </div>
                    @else
                        <span class="text-lg font-bold {{ $accent }} tabular-nums">{{ $row->{$metric} }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
