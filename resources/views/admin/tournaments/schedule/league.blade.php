@extends('admin.layouts.tournament')

@section('title', 'Kelola Jadwal & Skor Liga - ' . $tournament->name)

@section('page-title', 'Kelola Jadwal & Skor Liga')
@section('page-subtitle', 'Atur jadwal pertandingan per grup dan masukkan skor untuk sistem kompetisi liga')

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
            ],
            'selectedTab' => $selectedTab,
            'emptyMessage' => 'Belum ada jadwal liga yang tersedia.',
            'showActions' => true,
        ])
    </div>
@endsection
