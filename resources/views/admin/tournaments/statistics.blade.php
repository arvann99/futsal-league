@extends('admin.layouts.tournament')

@section('title', 'Manajemen Pemain - ' . $tournament->name)

@section('page-label', 'MANAJEMEN PEMAIN')
@section('page-title', 'Statistik Pemain & Tim')
@section('page-subtitle', $tournament->name)

@section('content')
    <div class="p-4 sm:p-6 max-w-7xl">
        @include('partials.statistics.body')
    </div>
@endsection
