@extends('admin.layouts.tournament')

@section('title', 'Pengaturan Turnamen - ' . $tournament->name)

@section('page-label', 'PENGATURAN TURNAMEN')
@section('page-title', 'Pengaturan Sistem Kompetisi')
@section('page-subtitle', 'Atur sistem kompetisi, poin klasemen, dan bagan bracket')

@section('content')
            <!-- Content -->
            <div class="p-4 sm:p-6 max-w-4xl">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 mb-2">Pengaturan Turnamen</p>
                            <h2 class="text-2xl font-bold text-white">Sistem Kompetisi</h2>
                            <p class="text-slate-400 mt-3 text-sm">Pilih sistem Turnamen (gugur murni), Liga (klasemen), atau Liga + Play Off, beserta aturan kelolosan/degradasinya.</p>
                        </div>
                        <a href="{{ route('tournaments.groupSettings', $tournament) }}" class="mt-6 inline-flex items-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                            Buka Pengaturan Sistem
                        </a>
                    </div>

                    @if(($competitionType ?? 'tournament') !== 'tournament')
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 mb-2">Standar Liga Poin</p>
                            <h2 class="text-2xl font-bold text-white">Pengaturan Poin</h2>
                            <p class="text-slate-400 mt-3 text-sm">Sesuaikan skor menang, imbang, dan kalah untuk perhitungan klasemen.</p>
                        </div>
                        <a href="{{ route('tournaments.pointsSettings', $tournament) }}" class="mt-6 inline-flex items-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"></path>
                            </svg>
                            Buka Pengaturan Poin
                        </a>
                    </div>
                    @else
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 mb-2">Standar Liga Poin</p>
                        <h2 class="text-2xl font-bold text-slate-400">Tidak Dipakai pada Sistem Turnamen</h2>
                        <p class="text-slate-500 mt-3 text-sm">Sistem Turnamen (gugur murni) tidak memiliki klasemen, sehingga pengaturan poin tidak diperlukan.</p>
                    </div>
                    @endif
                </div>

                @if(($competitionType ?? 'tournament') !== 'league')
                <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 flex flex-col justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 mb-2">Bagan Bracket</p>
                        <h2 class="text-2xl font-bold text-white">Pengaturan Knockout</h2>
                        @if(($competitionType ?? 'tournament') === 'tournament')
                            <p class="text-slate-400 mt-3 text-sm">Atur format babak gugur (single leg / home-away, perebutan tempat ketiga). Slot bracket diisi otomatis dari tim yang lolos verifikasi.</p>
                        @else
                            <p class="text-slate-400 mt-3 text-sm">Atur babak play off dan format pertandingan untuk tim sesuai ranking klasemen liga.</p>
                        @endif
                    </div>
                    <a href="{{ route('tournaments.bracketSettings', $tournament) }}" class="mt-6 inline-flex items-center gap-2 px-5 py-3 bg-fuchsia-600 hover:bg-fuchsia-700 text-white font-semibold rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"></path>
                        </svg>
                        Buka Bagan Bracket
                    </a>
                </div>
                @else
                <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 mb-2">Bagan Bracket</p>
                    <h2 class="text-2xl font-bold text-slate-400">Tidak Tersedia untuk Sistem Liga</h2>
                    <p class="text-slate-500 mt-3 text-sm">Sistem Liga murni tidak menggunakan babak gugur — juara dan degradasi ditentukan dari tabel klasemen.</p>
                </div>
                @endif
            </div>
@endsection
