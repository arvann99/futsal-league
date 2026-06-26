@extends('admin.layouts.tournament')

@section('title', 'Undian Grup - ' . $tournament->name)

@section('page-label', 'UNDIAN GRUP')
@section('page-title', 'Undian / Drawing Tim ke Grup')
@section('page-subtitle', $tournament->name)

@section('header-actions')
    <a href="{{ route('tournaments.groupSettings', $tournament) }}" class="px-5 py-3 bg-slate-800 border border-slate-700 hover:bg-slate-700 rounded-xl text-slate-200 transition">Kembali ke Pengaturan</a>
@endsection

@section('content')
    <div class="p-4 sm:p-6 max-w-5xl">
        @include('admin.tournaments.settings.partials.group-draw-panel')
    </div>
@endsection
