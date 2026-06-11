@extends('admin.layouts.app')

@section('title', $tournament->name . ' | Futsal League')

@section('body')
    <!-- Header -->
    <header class="border-b border-slate-800 bg-slate-900 bg-opacity-50 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
            <div class="flex items-center gap-3 sm:gap-4 mb-4">
                <a href="{{ route('tournaments.index') }}" class="text-slate-400 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div class="min-w-0">
                    <p class="text-xs sm:text-sm text-indigo-400 font-semibold">DINAS KEPEMUDAAN, OLAHRAGA, DAN PARIWISATA</p>
                    <h1 class="text-xl sm:text-3xl font-bold truncate">{{ $tournament->name }}</h1>
                </div>
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

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-8">
            <!-- Main Details Card -->
            <div class="sm:col-span-2 bg-slate-900 rounded-xl border border-slate-800 p-4 sm:p-8">
                <!-- Status Badge -->
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold">Informasi Turnamen</h2>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 pb-6 border-b border-slate-700 mb-6">
                    <div>
                        <h3 class="text-xs sm:text-sm font-semibold text-slate-400 mb-2">Tanggal Pertandingan</h3>
                        <p class="text-base sm:text-lg text-white">{{ optional($tournament->match_date)->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-semibold text-slate-400 mb-2">Kategori Divisi</h3>
                        <p class="text-base sm:text-lg text-white">{{ $tournament->division }}</p>
                    </div>
                </div>

                <!-- Venue -->
                <div class="pb-6 border-b border-slate-700 mb-6">
                    <h3 class="text-xs sm:text-sm font-semibold text-slate-400 mb-2">Lokasi Lapangan</h3>
                    <p class="text-base sm:text-lg text-white break-words">{{ $tournament->venue }}</p>
                </div>

                <!-- Organizer -->
                <div class="mb-8">
                    <h3 class="text-xs sm:text-sm font-semibold text-slate-400 mb-2">Diselenggarakan oleh</h3>
                    <p class="text-base sm:text-lg text-white">{{ $tournament->creator->name ?? 'System' }}</p>
                </div>

                <!-- Action Buttons -->
                <div class="pt-6 border-t border-slate-700 flex flex-col sm:flex-row gap-3 sm:gap-4">
                    <a href="{{ route('tournaments.edit', $tournament) }}" class="flex-1 py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition text-center flex items-center justify-center gap-2 text-sm sm:text-base">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit
                    </a>
                    <form action="{{ route('tournaments.destroy', $tournament) }}" method="POST" class="flex-1" onsubmit="return confirm('Yakin ingin menghapus turnamen ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full py-3 px-4 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition flex items-center justify-center gap-2 text-sm sm:text-base">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Hapus
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Stats Card -->
            <div class="space-y-4">
                <!-- Created Date -->
                <div class="bg-gradient-to-br from-indigo-600/20 to-purple-600/20 border border-indigo-500/30 rounded-xl p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-slate-400 mb-2">Dibuat pada</p>
                    <p class="text-base sm:text-lg font-semibold text-white">{{ $tournament->created_at->format('d M Y') }}</p>
                    <p class="text-xs text-slate-500">{{ $tournament->created_at->diffForHumans() }}</p>
                </div>

                <!-- Last Updated -->
                <div class="bg-gradient-to-br from-blue-600/20 to-cyan-600/20 border border-blue-500/30 rounded-xl p-4 sm:p-6">
                    <p class="text-xs sm:text-sm text-slate-400 mb-2">Terakhir diubah</p>
                    <p class="text-base sm:text-lg font-semibold text-white">{{ $tournament->updated_at->format('d M Y') }}</p>
                    <p class="text-xs text-slate-500">{{ $tournament->updated_at->diffForHumans() }}</p>
                </div>

                <!-- Back Button -->
                <a href="{{ route('tournaments.index') }}" class="block w-full py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-lg transition text-center text-sm sm:text-base">
                    Kembali ke Daftar
                </a>
            </div>
        </div>

        <!-- Future Sections (Placeholder for Teams, Matches, etc) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 text-center py-12">
                <svg class="w-10 sm:w-12 h-10 sm:h-12 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m6 0a6 6 0 11-12 0 6 6 0 0112 0z"></path>
                </svg>
                <h3 class="text-base sm:text-lg font-semibold text-slate-300">Tim Terdaftar</h3>
                <p class="text-slate-400 text-xs sm:text-sm mt-2">Fitur tim sedang dalam pengembangan</p>
            </div>

            <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 text-center py-12">
                <svg class="w-10 sm:w-12 h-10 sm:h-12 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <h3 class="text-base sm:text-lg font-semibold text-slate-300">Pertandingan</h3>
                <p class="text-slate-400 text-xs sm:text-sm mt-2">Fitur pertandingan sedang dalam pengembangan</p>
            </div>
        </div>
    </main>
@endsection
