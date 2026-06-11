@extends('admin.layouts.tournament')

@section('title', 'Pengaturan Sistem Kompetisi - ' . $tournament->name)

@section('page-label', 'PENGATURAN TURNAMEN')
@section('page-title', 'Pengaturan Sistem Kompetisi')
@section('page-subtitle', 'Pilih sistem kompetisi: Turnamen (gugur murni), Liga (klasemen), atau Liga + Play Off')

@section('content')
            @php
                $groupCount = $tournament->groupSetting->group_count ?? 4;
                $teamsPerGroup = $tournament->groupSetting->teams_per_group ?? 4;
                $groupLetters = array_slice(range('A', 'Z'), 0, max(1, $groupCount));
            @endphp

            <div class="p-4 sm:p-6 max-w-full">
                <div class="grid gap-6">
                    <section>
                        @include('admin.tournaments.settings.partials.group-settings-panel')
                    </section>
                </div>
            </div>
@endsection
