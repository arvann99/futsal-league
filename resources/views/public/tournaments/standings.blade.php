@extends('public.tournaments.layout')

@section('title', 'Klasemen - ' . $tournament->name)

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-emerald-300">Klasemen</p>
            <h1 class="mt-3 text-3xl font-semibold text-white">Klasemen {{ $tournament->name }}</h1>
            <p class="mt-2 text-sm text-slate-400">Posisi tim dihitung dari pertandingan grup/liga yang sudah selesai.</p>
        </div>

        @if(empty($groups))
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center text-slate-400">
                <p class="text-lg font-semibold text-white">Klasemen belum tersedia.</p>
                <p class="mt-2 text-sm">Tabel akan muncul setelah ada pertandingan grup yang selesai.</p>
            </div>
        @else
            @foreach($groups as $group)
                <section class="rounded-[2rem] border border-slate-800 bg-slate-900/95 p-5 shadow-2xl shadow-slate-950/40">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ $group['label'] }}</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr class="text-left text-[10px] uppercase tracking-[0.24em] text-slate-500">
                                    <th class="px-3 py-2 font-semibold">#</th>
                                    <th class="px-3 py-2 font-semibold">Tim</th>
                                    <th class="px-3 py-2 font-semibold text-center">M</th>
                                    <th class="px-3 py-2 font-semibold text-center">W</th>
                                    <th class="px-3 py-2 font-semibold text-center">D</th>
                                    <th class="px-3 py-2 font-semibold text-center">L</th>
                                    <th class="px-3 py-2 font-semibold text-center">GF</th>
                                    <th class="px-3 py-2 font-semibold text-center">GA</th>
                                    <th class="px-3 py-2 font-semibold text-center">GD</th>
                                    <th class="px-3 py-2 font-semibold text-center">Poin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @foreach($group['rows'] as $row)
                                    <tr class="text-slate-200">
                                        <td class="px-3 py-3 font-semibold text-slate-400">{{ $row['position'] }}</td>
                                        <td class="px-3 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="h-8 w-8 overflow-hidden rounded-xl bg-slate-800 border border-slate-700 shrink-0">
                                                    @if($row['logo'])
                                                        <img src="{{ Storage::url($row['logo']) }}" alt="Logo {{ $row['name'] }}" class="h-full w-full object-cover" />
                                                    @else
                                                        <div class="flex h-full w-full items-center justify-center text-slate-500 text-xs">⚽</div>
                                                    @endif
                                                </div>
                                                <span class="font-semibold text-white">{{ $row['name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-center tabular-nums">{{ $row['played'] }}</td>
                                        <td class="px-3 py-3 text-center tabular-nums">{{ $row['wins'] }}</td>
                                        <td class="px-3 py-3 text-center tabular-nums">{{ $row['draws'] }}</td>
                                        <td class="px-3 py-3 text-center tabular-nums">{{ $row['losses'] }}</td>
                                        <td class="px-3 py-3 text-center tabular-nums">{{ $row['goals_for'] }}</td>
                                        <td class="px-3 py-3 text-center tabular-nums">{{ $row['goals_against'] }}</td>
                                        <td class="px-3 py-3 text-center tabular-nums">{{ $row['goal_difference'] > 0 ? '+' : '' }}{{ $row['goal_difference'] }}</td>
                                        <td class="px-3 py-3 text-center font-bold text-emerald-300 tabular-nums">{{ $row['points'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        @endif
    </div>
@endsection
