@extends('admin.layouts.app')

@section('body')
    <div class="flex @yield('wrapper-class')">
        @include('admin.tournaments.partials.sidebar')

        <!-- Main Content -->
        <main class="@yield('main-class', 'flex-1 overflow-auto')">
            @hasSection('page-header')
                @yield('page-header')
            @elseif(View::hasSection('page-title'))
                <!-- Header -->
                <header class="border-b border-slate-800 bg-slate-900 bg-opacity-50 backdrop-blur sticky top-0 z-40">
                    <div class="px-4 sm:px-6 py-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                @hasSection('page-label')
                                    <p class="text-xs sm:text-sm @yield('page-label-class', 'text-indigo-400') font-semibold mb-2">@yield('page-label')</p>
                                @endif
                                <h1 class="text-2xl sm:text-3xl font-bold">@yield('page-title')</h1>
                                @hasSection('page-subtitle')
                                    <p class="text-slate-400 text-sm mt-2">@yield('page-subtitle')</p>
                                @endif
                            </div>
                            @yield('header-actions')
                        </div>
                    </div>
                </header>
            @endif

            @yield('content')
        </main>
    </div>

    @yield('after-main')
@endsection
