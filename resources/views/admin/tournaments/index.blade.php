@extends('admin.layouts.app')

@section('title', 'Daftar Turnamen | Futsal League')

@section('body')
    <!-- Mobile Menu Toggle -->
    <div id="mobileMenuToggle" class="hidden">
        <button onclick="toggleMenu()" class="text-slate-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>

    <!-- Header -->
    <header class="border-b border-slate-800 bg-slate-900 bg-opacity-50 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-6">
                <div>
                    <p class="text-xs sm:text-sm text-indigo-400 font-semibold mb-1 sm:mb-2">DINAS KEPEMUDAAN, OLAHRAGA, DAN PARIWISATA</p>
                    <h1 class="text-2xl sm:text-4xl font-bold">Daftar Turnamen</h1>
                    <p class="text-slate-400 text-xs sm:text-sm mt-1 sm:mt-2">Kelola dan buat turnamen futsal baru untuk kompetisi Anda</p>
                </div>
                <a href="{{ route('tournaments.create') }}" class="w-full sm:w-auto px-4 sm:px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg shadow-indigo-500/50 flex items-center justify-center gap-2 whitespace-nowrap">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Buat Turnamen
                </a>
                <a href="{{ route('teams.index') }}" class="w-full sm:w-auto px-4 sm:px-6 py-3 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-xl transition duration-200 shadow-lg shadow-slate-500/50 flex items-center justify-center gap-2 whitespace-nowrap">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    Manajemen Tim
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-12">
        
        <!-- Success Message -->
        @if(session('success'))
            <div class="mb-6 p-4 bg-green-900/20 border border-green-500/30 rounded-lg text-green-400 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <!-- Tournaments Grid -->
        @if($tournaments->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                @foreach($tournaments as $tournament)
                    <div class="group bg-slate-900 rounded-xl border border-slate-800 hover:border-indigo-500/50 transition overflow-hidden">
                        <!-- Card Header with Status -->
                        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 sm:px-6 py-4">
                            <h3 class="text-base sm:text-lg font-bold text-white break-words">{{ $tournament->name }}</h3>
                            <p class="text-xs sm:text-sm text-indigo-200">{{ $tournament->division }}</p>
                        </div>

                        <!-- Card Body -->
                        <div class="px-4 sm:px-6 py-4">
                            <div class="space-y-2 sm:space-y-3 text-xs sm:text-sm text-slate-300 mb-4">
                                <div class="flex items-center gap-2 break-words">
                                    <svg class="w-4 h-4 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>{{ optional($tournament->match_date)->format('d M Y') ?? '-' }}</span>
                                </div>
                                <div class="flex items-center gap-2 break-words">
                                    <svg class="w-4 h-4 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span class="truncate">{{ $tournament->venue }}</span>
                                </div>
                                <div class="flex items-center gap-2 break-words">
                                    <svg class="w-4 h-4 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="truncate">{{ $tournament->creator->name ?? 'Admin' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="bg-slate-800/50 px-4 sm:px-6 py-3 space-y-2">
                            <a href="{{ route('tournaments.manage', $tournament) }}" class="w-full text-center py-3 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs sm:text-sm font-semibold rounded-lg transition flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                                Kelola Turnamen
                            </a>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <a href="{{ route('tournaments.show', $tournament) }}" class="flex-1 text-center py-2 px-3 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-400 text-xs sm:text-sm font-medium rounded-lg transition">
                                    Detail
                                </a>
                                <a href="{{ route('tournaments.edit', $tournament) }}" class="flex-1 text-center py-2 px-3 bg-purple-600/20 hover:bg-purple-600/40 text-purple-400 text-xs sm:text-sm font-medium rounded-lg transition">
                                    Edit
                                </a>
                                <form action="{{ route('tournaments.destroy', $tournament) }}" method="POST" class="flex-1" onsubmit="return confirm('Yakin ingin menghapus?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full py-2 px-3 bg-red-600/20 hover:bg-red-600/40 text-red-400 text-xs sm:text-sm font-medium rounded-lg transition">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 sm:py-16">
                <svg class="w-12 sm:w-16 h-12 sm:h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <h3 class="text-lg sm:text-xl font-semibold text-slate-300 mb-2">Belum ada turnamen</h3>
                <p class="text-slate-400 text-sm mb-6">Mulai dengan membuat turnamen baru untuk kompetisi Anda</p>
                <a href="{{ route('tournaments.create') }}" class="inline-block px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition duration-200">
                    Buat Turnamen Pertama
                </a>
            </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800 bg-slate-900/50 mt-8 sm:mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <p class="text-xs sm:text-sm text-slate-400">© 2026 Futsal League Management</p>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-xs sm:text-sm text-indigo-400 hover:text-indigo-300">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </footer>
@endsection
