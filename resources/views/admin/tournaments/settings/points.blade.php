@extends('admin.layouts.tournament')

@section('title', 'Standar Liga Poin - ' . $tournament->name)

@section('page-label', 'STANDAR LIGA POIN')
@section('page-label-class', 'text-emerald-400')
@section('page-title', 'Atur Skor Klasemen')
@section('page-subtitle', 'Tetapkan poin untuk menang, imbang, dan kalah sesuai sistem liga.')

@section('content')
    @include('admin.tournaments.settings.partials.points-settings-panel')
@endsection
