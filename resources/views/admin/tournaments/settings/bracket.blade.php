@extends('admin.layouts.tournament')

@section('title', 'Bagan Bracket - ' . $tournament->name)

@section('page-label', 'BAGAN BRACKET')
@section('page-label-class', 'text-emerald-400')
@section('page-title', 'Atur Babak Knockout')
@section('page-subtitle', $competitionType === 'tournament'
    ? 'Sesuaikan format babak gugur. Slot bracket diisi otomatis dari tim yang lolos verifikasi.'
    : 'Sesuaikan struktur babak knockout/play off setelah fase liga selesai.')

@section('content')
            @if($competitionType === 'league')
                <div class="p-4 sm:p-6 max-w-full">
                    <div class="mb-6 p-4 bg-amber-900/20 border border-amber-500/30 rounded-lg text-amber-200 text-sm flex items-start gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <strong class="block mb-1">⊘ Bracket Gugur Tidak Tersedia</strong>
                            <p>Sistem liga biasa tidak menggunakan bracket gugur.</p>
                        </div>
                    </div>
                </div>
            @else
                @include('admin.tournaments.settings.partials.bracket-settings-panel')
            @endif
@endsection
