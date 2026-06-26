{{--
    N12 — Partial tabel ranking pemain (reusable; dipakai juga oleh N13).

    Variabel yang diharapkan:
      $title       string  judul kartu
      $accent      string  kelas warna aksen (mis. 'text-emerald-400')
      $rows        Collection objek dengan: player_name, shirt_number, team_name, dan $metric
      $metric      string  nama atribut nilai (mis. 'goals', 'assists', 'yellow_cards')
      $metricLabel string  label kolom nilai (mis. 'Gol', 'Assist', 'KK')
      $emptyText   string  (opsional) teks saat data kosong
--}}
@php
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
                        <p class="text-sm font-medium text-white truncate">
                            {{ $row->player_name }}
                            @if(!is_null($row->shirt_number))
                                <span class="text-slate-500 font-normal">#{{ $row->shirt_number }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-slate-400 truncate">{{ $row->team_name }}</p>
                    </div>
                    <span class="text-lg font-bold {{ $accent }} tabular-nums">{{ $row->{$metric} }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
