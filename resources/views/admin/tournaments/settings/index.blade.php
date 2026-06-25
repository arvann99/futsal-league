@extends('admin.layouts.tournament')

@section('title', 'Pengaturan Turnamen - ' . $tournament->name)

@section('page-label', 'PENGATURAN TURNAMEN')
@section('page-title', 'Pengaturan Sistem Kompetisi')
@section('page-subtitle', 'Atur sistem kompetisi, poin klasemen, dan bagan bracket')

@section('content')
<div class="p-4 sm:p-6 max-w-4xl">
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">

        {{-- Card 1: Sistem Kompetisi --}}
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 flex flex-col gap-4 hover:border-slate-700 transition-colors">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-indigo-500/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Pengaturan Turnamen</p>
                    <h2 class="text-base font-bold text-white mt-0.5">Sistem Kompetisi</h2>
                </div>
            </div>
            <p class="text-slate-400 text-sm leading-relaxed">
                Pilih sistem Turnamen (gugur murni), Liga (klasemen), atau Liga + Play Off, beserta aturan kelolosan/degradasinya.
            </p>
            <a href="{{ route('tournaments.groupSettings', $tournament) }}"
               class="mt-auto inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition-colors w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                Buka Pengaturan
            </a>
        </div>

        {{-- Card 2: Poin Liga --}}
        @if(($competitionType ?? 'tournament') !== 'tournament')
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 flex flex-col gap-4 hover:border-slate-700 transition-colors">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-emerald-500/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a1 1 0 001-1V6a1 1 0 00-1-1H4a1 1 0 00-1 1v12a1 1 0 001 1z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Standar Liga Poin</p>
                    <h2 class="text-base font-bold text-white mt-0.5">Pengaturan Poin</h2>
                </div>
            </div>
            <p class="text-slate-400 text-sm leading-relaxed">
                Sesuaikan skor menang, imbang, dan kalah untuk perhitungan klasemen.
            </p>
            <a href="{{ route('tournaments.pointsSettings', $tournament) }}"
               class="mt-auto inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg transition-colors w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                Buka Pengaturan
            </a>
        </div>
        @else
        <div class="bg-slate-900/50 rounded-xl border border-slate-800/60 border-dashed p-6 flex flex-col gap-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a1 1 0 001-1V6a1 1 0 00-1-1H4a1 1 0 00-1 1v12a1 1 0 001 1z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-600">Standar Liga Poin</p>
                    <h2 class="text-base font-bold text-slate-500 mt-0.5">Poin Tidak Dipakai</h2>
                </div>
            </div>
            <p class="text-slate-600 text-sm leading-relaxed">
                Sistem gugur murni tidak menggunakan klasemen poin — tidak diperlukan konfigurasi.
            </p>
            <span class="mt-auto inline-flex items-center gap-1.5 text-xs text-slate-600 font-medium">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                Tidak tersedia untuk sistem ini
            </span>
        </div>
        @endif

        {{-- Card 3: Bracket / Knockout --}}
        @if(($competitionType ?? 'tournament') !== 'league')
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 flex flex-col gap-4 hover:border-slate-700 transition-colors">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-violet-500/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h4v4H4zm12 0h4v4h-4zM4 14h4v4H4zm8-4h4v4h-4zM8 8h4M14 8h2M8 16h2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Bagan Bracket</p>
                    <h2 class="text-base font-bold text-white mt-0.5">Pengaturan Knockout</h2>
                </div>
            </div>
            <p class="text-slate-400 text-sm leading-relaxed">
                @if(($competitionType ?? 'tournament') === 'tournament')
                    Atur format babak gugur (single leg / home-away, perebutan tempat ketiga).
                @else
                    Atur babak play off untuk tim sesuai ranking klasemen liga.
                @endif
            </p>
            <a href="{{ route('tournaments.bracketSettings', $tournament) }}"
               class="mt-auto inline-flex items-center gap-2 px-4 py-2.5 bg-violet-600 hover:bg-violet-500 text-white text-sm font-semibold rounded-lg transition-colors w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                Buka Bracket
            </a>
        </div>
        @else
        <div class="bg-slate-900/50 rounded-xl border border-slate-800/60 border-dashed p-6 flex flex-col gap-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h4v4H4zm12 0h4v4h-4zM4 14h4v4H4zm8-4h4v4h-4z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-600">Bagan Bracket</p>
                    <h2 class="text-base font-bold text-slate-500 mt-0.5">Bracket Tidak Dipakai</h2>
                </div>
            </div>
            <p class="text-slate-600 text-sm leading-relaxed">
                Sistem Liga murni tidak menggunakan babak gugur — juara ditentukan dari tabel klasemen.
            </p>
            <span class="mt-auto inline-flex items-center gap-1.5 text-xs text-slate-600 font-medium">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 115.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                Tidak tersedia untuk sistem ini
            </span>
        </div>
        @endif

    </div>
</div>
@endsection
