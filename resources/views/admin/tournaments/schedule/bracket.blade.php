@extends('admin.layouts.tournament')

@section('title', 'Kelola Jadwal & Skor Bracket - ' . $tournament->name)

@section('page-title', 'Kelola Jadwal & Skor Bracket')
@section('page-subtitle', 'Atur jadwal pertandingan dan masukkan skor untuk sistem turnamen knockout')

@section('content')
    <div class="p-4 sm:p-6 max-w-full">
        @if(session('success'))
            <div class="mb-6 p-4 bg-green-900/20 border border-green-500/30 rounded-lg text-green-400 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-900/20 border border-red-500/30 rounded-lg text-red-400 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if(empty($matches))
            <div class="bg-slate-900 rounded-xl border border-slate-800 p-8 text-center">
                <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <p class="text-slate-300 font-semibold mb-2">Belum ada jadwal</p>
                <p class="text-slate-400 text-sm mb-4">Silakan atur pengaturan bracket terlebih dahulu untuk membuat jadwal pertandingan</p>
                <a href="{{ route('tournaments.bracketSettings', $tournament) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Atur Bracket
                </a>
            </div>
        @else
            @include('admin.tournaments.schedule.partials.match-table', [
                'matches' => $matches,
                'tabs' => [
                    ['key' => 'all', 'label' => 'Semua Laga'],
                    ['key' => 'bracket', 'label' => 'Fase Gugur'],
                ],
                'selectedTab' => $selectedTab,
                'emptyMessage' => 'Belum ada jadwal bracket yang tersedia.',
                'showActions' => true,
            ])
        @endif
    </div>
@endsection
