@extends('admin.layouts.tournament')

@section('title', $tournament->name . ' - Ikhtisar | Futsal League')

@section('page-label', 'IKHTISAR TURNAMEN')
@section('page-title', $tournament->name)
@section('page-subtitle', 'Status, kemajuan pertandingan, dan data statistik live saat ini.')

@php
    $stageDone = $matchProgress['total'] > 0 && $matchProgress['finished'] === $matchProgress['total'];
@endphp

@section('content')
            <div class="p-4 sm:p-6 space-y-6">

                {{-- ===================== JUARA ===================== --}}
                @if($champion)
                    <div class="relative overflow-hidden rounded-2xl border border-amber-500/30 bg-gradient-to-r from-amber-500/15 via-amber-400/10 to-slate-900 p-5 sm:p-7">
                        <div class="absolute -right-8 -top-8 text-amber-500/10">
                            <svg class="w-40 h-40" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M5 3h14a1 1 0 011 1v2a4 4 0 01-3 3.87A6 6 0 0113 13.92V17h2a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1v1a1 1 0 11-2 0v-1H9a1 1 0 110-2h2v-3.08A6 6 0 017 9.87 4 4 0 014 6V4a1 1 0 011-1zm0 2v1a2 2 0 002 2V5H5zm14 0h-2v3a2 2 0 002-2V5z"/>
                            </svg>
                        </div>
                        <div class="relative flex flex-col sm:flex-row items-start sm:items-center gap-5">
                            <div class="flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-amber-500/20 ring-1 ring-amber-400/40 shrink-0 overflow-hidden">
                                @if($champion['logo'])
                                    <img src="{{ asset('storage/' . $champion['logo']) }}" alt="{{ $champion['name'] }}" class="w-full h-full object-cover">
                                @else
                                    <svg class="w-9 h-9 text-amber-300" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M5 3h14a1 1 0 011 1v2a4 4 0 01-3 3.87A6 6 0 0113 13.92V17h2a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1v1a1 1 0 11-2 0v-1H9a1 1 0 110-2h2v-3.08A6 6 0 017 9.87 4 4 0 014 6V4a1 1 0 011-1zm0 2v1a2 2 0 002 2V5H5zm14 0h-2v3a2 2 0 002-2V5z"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-widest text-amber-300 mb-1 flex items-center gap-2">
                                    <span>🏆 Turnamen Selesai</span>
                                </p>
                                <h2 class="text-2xl sm:text-4xl font-extrabold text-white truncate">{{ $champion['name'] }}</h2>
                                <p class="text-amber-200/80 text-sm mt-1">{{ $champion['context'] }}</p>
                            </div>
                            <a href="{{ route('tournaments.bracketAdmin', $tournament) }}"
                               class="sm:ml-auto shrink-0 px-5 py-2.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold text-sm transition">
                                Lihat Bagan
                            </a>
                        </div>
                    </div>
                @endif

                {{-- ===================== STATISTIK PENDAFTARAN ===================== --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    @php
                        $cards = [
                            ['label' => 'TOTAL PENDAFTAR', 'value' => $statistics['total_pendaftar'], 'tint' => 'indigo',
                             'icon' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z'],
                            ['label' => 'TERVERIFIKASI', 'value' => $statistics['terverifikasi'], 'tint' => 'emerald',
                             'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                            ['label' => 'BUTUH VERIFIKASI', 'value' => $statistics['butuh_verifikasi'], 'tint' => 'amber',
                             'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                            ['label' => 'DITOLAK / DRAFT', 'value' => $statistics['ditolak_draft'], 'tint' => 'rose',
                             'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ];
                        $tints = [
                            'indigo'  => ['bg' => 'bg-indigo-500/15',  'text' => 'text-indigo-400'],
                            'emerald' => ['bg' => 'bg-emerald-500/15', 'text' => 'text-emerald-400'],
                            'amber'   => ['bg' => 'bg-amber-500/15',   'text' => 'text-amber-400'],
                            'rose'    => ['bg' => 'bg-rose-500/15',     'text' => 'text-rose-400'],
                        ];
                    @endphp
                    @foreach($cards as $card)
                        @php $t = $tints[$card['tint']]; @endphp
                        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 sm:p-5">
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-[11px] sm:text-xs text-slate-400 font-semibold tracking-wide">{{ $card['label'] }}</p>
                                <div class="p-1.5 {{ $t['bg'] }} rounded-lg">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 {{ $t['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-3xl sm:text-4xl font-bold text-white leading-none">{{ $card['value'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- ===================== KEMAJUAN PERTANDINGAN + VERIFIKASI ===================== --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {{-- Progress pertandingan --}}
                    <div class="lg:col-span-2 bg-slate-900 rounded-xl border border-slate-800 p-5 sm:p-6">
                        <div class="flex items-center justify-between mb-5">
                            <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                Kemajuan Pertandingan
                            </h2>
                            <span class="text-sm font-bold {{ $stageDone ? 'text-emerald-400' : 'text-indigo-400' }}">{{ $matchProgress['percent'] }}%</span>
                        </div>

                        @if($matchProgress['total'] > 0)
                            <div class="w-full bg-slate-800 rounded-full h-2.5 mb-5">
                                <div class="{{ $stageDone ? 'bg-emerald-500' : 'bg-indigo-500' }} h-2.5 rounded-full transition-all" style="width: {{ $matchProgress['percent'] }}%"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div class="rounded-lg bg-slate-800/60 py-3">
                                    <p class="text-2xl font-bold text-emerald-400">{{ $matchProgress['finished'] }}</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5 uppercase tracking-wide">Selesai</p>
                                </div>
                                <div class="rounded-lg bg-slate-800/60 py-3">
                                    <p class="text-2xl font-bold {{ $matchProgress['live'] > 0 ? 'text-rose-400' : 'text-slate-300' }}">{{ $matchProgress['live'] }}</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5 uppercase tracking-wide">Berlangsung</p>
                                </div>
                                <div class="rounded-lg bg-slate-800/60 py-3">
                                    <p class="text-2xl font-bold text-slate-300">{{ $matchProgress['upcoming'] }}</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5 uppercase tracking-wide">Terjadwal</p>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-6">
                                <p class="text-slate-400 text-sm mb-3">Jadwal pertandingan belum dibuat.</p>
                                <a href="{{ route('tournaments.manageSchedule', $tournament) }}" class="inline-flex items-center gap-2 text-indigo-400 hover:text-indigo-300 text-sm font-semibold">
                                    Buat Jadwal & Skor
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </a>
                            </div>
                        @endif
                    </div>

                    {{-- Verifikasi cepat --}}
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-5 sm:p-6 flex flex-col">
                        <h2 class="text-base sm:text-lg font-bold text-white mb-2 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Verifikasi Cepat
                        </h2>
                        @if($statistics['butuh_verifikasi'] > 0)
                            <p class="text-slate-400 text-sm">
                                <span class="font-semibold text-amber-400">{{ $statistics['butuh_verifikasi'] }} tim</span> menunggu verifikasi berkas dari panitia.
                            </p>
                        @else
                            <p class="text-slate-400 text-sm">Tidak ada tim yang menunggu verifikasi saat ini.</p>
                        @endif
                        <a href="{{ route('tournaments.verification', $tournament) }}"
                           class="mt-auto pt-4 w-full px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition flex items-center justify-center gap-2 text-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Buka Halaman Verifikasi
                        </a>
                    </div>
                </div>

                {{-- ===================== LAGA BERIKUTNYA + HASIL TERAKHIR ===================== --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {{-- Laga berikutnya --}}
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-5 sm:p-6">
                        <h2 class="text-base sm:text-lg font-bold text-white mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Laga Berikutnya
                        </h2>
                        @if($nextMatch)
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold text-slate-400">{{ $nextMatch['stage'] }}</span>
                                @if($nextMatch['is_live'])
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-rose-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span> LIVE
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex-1 text-right min-w-0">
                                    <p class="font-semibold text-white truncate">{{ $nextMatch['home'] }}</p>
                                </div>
                                <span class="px-3 py-1 rounded-md bg-slate-800 text-slate-300 text-xs font-bold shrink-0">VS</span>
                                <div class="flex-1 text-left min-w-0">
                                    <p class="font-semibold text-white truncate">{{ $nextMatch['away'] }}</p>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ $nextMatch['date'] ? $nextMatch['date']->translatedFormat('d M Y, H:i') : 'Belum dijadwalkan' }}
                                </span>
                                @if($nextMatch['venue'])
                                    <span class="flex items-center gap-1.5 truncate">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        <span class="truncate">{{ $nextMatch['venue'] }}</span>
                                    </span>
                                @endif
                            </div>
                        @else
                            <div class="text-center py-8 text-slate-500 text-sm">
                                {{ $stageDone ? 'Semua pertandingan telah selesai.' : 'Belum ada laga terjadwal.' }}
                            </div>
                        @endif
                    </div>

                    {{-- Hasil terakhir --}}
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Hasil Terakhir
                            </h2>
                            <a href="{{ route('tournaments.manageSchedule', $tournament) }}" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">Lihat semua</a>
                        </div>
                        @if(count($recentResults) > 0)
                            <div class="divide-y divide-slate-800/70">
                                @foreach($recentResults as $r)
                                    <div class="flex items-center gap-3 py-2.5">
                                        <p class="flex-1 text-right text-sm text-white truncate {{ $r['home_score'] > $r['away_score'] ? 'font-bold' : '' }}">{{ $r['home'] }}</p>
                                        <div class="shrink-0 px-2.5 py-1 rounded-md bg-slate-800 font-bold text-sm text-white tabular-nums">
                                            {{ $r['home_score'] }} - {{ $r['away_score'] }}
                                            @if(!is_null($r['home_pen']) || !is_null($r['away_pen']))
                                                <span class="text-[10px] text-sky-300 font-semibold">(p {{ $r['home_pen'] ?? 0 }}-{{ $r['away_pen'] ?? 0 }})</span>
                                            @endif
                                        </div>
                                        <p class="flex-1 text-left text-sm text-white truncate {{ $r['away_score'] > $r['home_score'] ? 'font-bold' : '' }}">{{ $r['away'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-slate-500 text-sm">Belum ada pertandingan yang selesai.</div>
                        @endif
                    </div>
                </div>
            </div>
@endsection
