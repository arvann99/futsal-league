@extends('admin.layouts.tournament')

@section('title', 'Kelola Jadwal & Skor Liga Play Off - ' . $tournament->name)

@section('page-title', 'Kelola Jadwal & Skor Liga Play Off')
@section('page-subtitle', 'Atur jadwal pertandingan fase liga dan fase playoff (promosi/degradasi)')

@section('content')
    <div class="p-4 sm:p-6 max-w-full">
        @if(session('success'))
            <div class="mb-6 p-4 bg-green-900/20 border border-green-500/30 rounded-lg text-green-400 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @include('admin.tournaments.schedule.partials.match-table', [
            'matches' => $matches,
            'tabs' => [
                ['key' => 'all', 'label' => 'Semua Laga'],
                ['key' => 'group', 'label' => 'Penyisihan Grup'],
                ['key' => 'playoff', 'label' => 'Fase Gugur'],
            ],
            'selectedTab' => $selectedTab,
            'emptyMessage' => 'Tidak ada jadwal Liga Play Off yang tersedia.',
            'showActions' => true,
        ])
    </div>
@endsection
