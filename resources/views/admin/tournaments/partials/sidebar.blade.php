{{--
    Sidebar Admin Turnamen (dinamis)
    Dipakai oleh semua halaman area kelola turnamen.
    Item aktif ditentukan otomatis dari route yang sedang dibuka,
    jadi menu selalu sama di semua halaman.

    Kebutuhan: variabel $tournament tersedia di view pemanggil.
--}}
@php
    // Tipe kompetisi menentukan menu yang relevan:
    // - tournament (gugur murni): tanpa klasemen grup
    // - league: tanpa bracket gugur
    // - league_playoff: keduanya tampil
    $sidebarBracketSetting = \App\Models\AppSetting::where('key', 'tournament_' . $tournament->id . '_bracket_settings')->first();
    $sidebarCompetitionType = $sidebarBracketSetting?->value['competition_type'] ?? 'tournament';
    $showStandingsMenu = $sidebarCompetitionType !== 'tournament';
    $showBracketMenu = $sidebarCompetitionType !== 'league';

    $adminSidebarMenu = [
        [
            'label' => 'Ikhtisar Sistem',
            'href' => route('tournaments.manage', $tournament),
            'active' => request()->routeIs('tournaments.manage'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        ],
        [
            'label' => 'Verifikasi Berkas',
            'href' => route('tournaments.verification', $tournament),
            'active' => request()->routeIs('tournaments.verification'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        ],
        [
            'label' => 'Pengaturan Turnamen',
            'href' => route('tournaments.settings', $tournament),
            'active' => request()->routeIs('tournaments.settings', 'tournaments.groupSettings', 'tournaments.pointsSettings', 'tournaments.bracketSettings'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>',
        ],
        [
            'label' => 'Kelola Jadwal & Skor',
            'href' => route('tournaments.manageSchedule', $tournament),
            'active' => request()->routeIs('tournaments.manageSchedule', 'tournaments.schedule'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>',
        ],
        ...($showStandingsMenu ? [[
            'label' => 'Bagan Klasemen',
            'href' => route('tournaments.standings', $tournament),
            'active' => request()->routeIs('tournaments.standings'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>',
        ]] : []),
        ...($showBracketMenu ? [[
            'label' => 'Bracket Gugur',
            'href' => route('tournaments.bracketAdmin', $tournament),
            'active' => request()->routeIs('tournaments.bracketAdmin'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"></path>',
        ]] : []),
        [
            'label' => 'Manajemen Peserta',
            'href' => route('tournaments.participants.index', $tournament),
            'active' => request()->routeIs('tournaments.participants.*'),
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 19H9a6 6 0 016-6h.01M15 19h4a2 2 0 002-2v-5a6 6 0 00-6-6h-4a6 6 0 00-6 6v5a2 2 0 002 2h4m-12 0a2 2 0 012-2h8a2 2 0 012 2"></path>',
        ],
    ];
@endphp

{{-- Sidebar desktop --}}
<aside class="hidden md:flex md:w-64 bg-slate-900 border-r border-slate-800 flex-col sticky top-0 h-screen">
    <div class="p-6 border-b border-slate-800">
        <h2 class="text-lg font-bold">{{ $tournament->name }}</h2>
        <p class="text-xs text-slate-400 mt-1">{{ $tournament->division }}</p>
    </div>

    <nav class="flex-1 overflow-y-auto p-4 space-y-2">
        @foreach($adminSidebarMenu as $item)
            @if($item['href'])
                <a href="{{ $item['href'] }}"
                   class="w-full text-left px-4 {{ $item['active'] ? 'py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold' : 'py-2 text-slate-300 hover:bg-slate-800' }} rounded-lg transition flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                    {{ $item['label'] }}
                </a>
            @else
                <span class="w-full text-left px-4 py-2 text-slate-500 rounded-lg flex items-center gap-3 cursor-not-allowed select-none">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                    {{ $item['label'] }}
                    <span class="ml-auto text-[9px] uppercase tracking-widest bg-slate-800 text-slate-400 rounded-full px-2 py-0.5">Segera</span>
                </span>
            @endif
        @endforeach
    </nav>

    <div class="p-4 border-t border-slate-800 space-y-2">
        <a href="{{ route('tournaments.index') }}" class="w-full text-left px-4 py-2 text-slate-300 hover:bg-slate-800 rounded-lg transition flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Kembali ke Daftar
        </a>
    </div>
</aside>

{{-- Tombol menu mobile (tampil hanya di layar kecil) --}}
<button type="button" onclick="toggleMobileMenu()"
        class="md:hidden fixed bottom-5 right-5 z-50 p-3.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full shadow-lg shadow-indigo-950/50 transition"
        aria-label="Buka menu navigasi">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>

{{-- Menu overlay mobile --}}
<div id="mobileMenu" class="hidden md:hidden fixed inset-0 bg-slate-950/95 z-50 p-4 overflow-y-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-lg font-bold">{{ $tournament->name }}</h2>
            <p class="text-xs text-slate-400 mt-1">{{ $tournament->division }}</p>
        </div>
        <button type="button" onclick="toggleMobileMenu()" class="text-slate-400 hover:text-white p-2" aria-label="Tutup menu navigasi">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <nav class="space-y-2">
        @foreach($adminSidebarMenu as $item)
            @if($item['href'])
                <a href="{{ $item['href'] }}"
                   class="w-full text-left px-4 py-3 {{ $item['active'] ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }} rounded-lg transition flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                    {{ $item['label'] }}
                </a>
            @else
                <span class="w-full text-left px-4 py-3 text-slate-500 rounded-lg flex items-center gap-3 select-none">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $item['icon'] !!}</svg>
                    {{ $item['label'] }}
                    <span class="ml-auto text-[9px] uppercase tracking-widest bg-slate-800 text-slate-400 rounded-full px-2 py-0.5">Segera</span>
                </span>
            @endif
        @endforeach

        <a href="{{ route('tournaments.index') }}" class="w-full text-left px-4 py-3 text-slate-300 hover:bg-slate-800 rounded-lg transition flex items-center gap-3 border-t border-slate-800 mt-4 pt-4">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Kembali ke Daftar
        </a>
    </nav>
</div>

<script>
    function toggleMobileMenu() {
        document.getElementById('mobileMenu').classList.toggle('hidden');
    }
</script>
