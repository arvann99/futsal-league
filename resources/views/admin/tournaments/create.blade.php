@extends('admin.layouts.app')

@section('title', 'Buat Turnamen Baru | Futsal League')

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
                <div>
                    <p class="text-xs sm:text-sm text-indigo-400 font-semibold">DINAS KEPEMUDAAN, OLAHRAGA, DAN PARIWISATA</p>
                    <h1 class="text-xl sm:text-3xl font-bold">Buat Turnamen Baru</h1>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-6 sm:py-12">
        
        <!-- Form -->
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 sm:p-8">
            <form action="{{ route('tournaments.store') }}" method="POST" class="space-y-4 sm:space-y-6">
                @csrf

                <!-- Name Field -->
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Nama Turnamen *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition @error('name') border-red-500 @enderror text-base">
                    @error('name')
                        <p class="text-red-400 text-xs sm:text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Match Date Field -->
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Tanggal Pertandingan *</label>
                    <input type="date" name="match_date" value="{{ old('match_date') }}" required
                        class="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition @error('match_date') border-red-500 @enderror text-base">
                    @error('match_date')
                        <p class="text-red-400 text-xs sm:text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Division Field -->
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Kategori Divisi *</label>
                    <input type="text" name="division" value="{{ old('division') }}" placeholder="cth: Senior, Junior, U-16" required
                        class="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition @error('division') border-red-500 @enderror text-base">
                    @error('division')
                        <p class="text-red-400 text-xs sm:text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Venue Field -->
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Lokasi Lapangan (Venue) *</label>
                    <input type="text" name="venue" value="{{ old('venue') }}" placeholder="cth: Lapangan Sebenarnya, Jl. XX" required
                        class="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition @error('venue') border-red-500 @enderror text-base">
                    @error('venue')
                        <p class="text-red-400 text-xs sm:text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 pt-4 sm:pt-6 border-t border-slate-700">
                    <a href="{{ route('tournaments.index') }}" class="flex-1 py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-lg transition text-center text-sm sm:text-base">
                        Batal
                    </a>
                    <button type="submit" class="flex-1 py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition text-sm sm:text-base">
                        Buat Turnamen
                    </button>
                </div>
            </form>
        </div>
    </main>
@endsection
