@extends('admin.layouts.app')

@section('title', 'Manajemen Tim | Futsal League')

@section('body')    <header class="border-b border-slate-800 bg-slate-900 bg-opacity-50 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <p class="text-xs sm:text-sm text-indigo-400 font-semibold mb-1">MANAJEMEN TIM</p>
                <h1 class="text-2xl sm:text-4xl font-bold">Daftar Tim</h1>
                <p class="text-slate-400 text-sm mt-1">Tambahkan, sunting, atau hapus tim yang dapat digunakan di turnamen.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <a href="{{ route('teams.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Buat Tim Baru
                </a>
                <!-- Akses Manager removed; use Manajemen Peserta for token management -->
                <a href="{{ route('tournaments.index') }}" class="inline-flex items-center justify-center gap-2 px-4 py-3 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl font-semibold transition">
                    Kembali ke Turnamen
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-12">
        @if(session('success'))
            <div class="mb-6 p-4 rounded-xl border border-emerald-500/30 bg-emerald-900/10 text-emerald-200">{{ session('success') }}</div>
        @endif

        @if($teams->isEmpty())
            <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-10 text-center">
                <p class="text-slate-400 mb-4">Belum ada tim yang terdaftar.</p>
                <a href="{{ route('teams.create') }}" class="inline-block px-5 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition">Tambahkan Tim Pertama</a>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($teams as $team)
                    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-white">{{ $team->name }}</h2>
                                <p class="text-sm text-slate-400 mt-1">{{ $team->country ?? 'Negara belum diisi' }} · {{ $team->city ?? 'Kota belum diisi' }}</p>
                            </div>
                            <div class="inline-flex items-center gap-2 rounded-full bg-slate-800 px-3 py-2 text-xs text-slate-300">
                                ID {{ $team->id }}
                            </div>
                        </div>

                        @if($team->notes)
                            <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/50 p-4 text-slate-300 text-sm">
                                {{ $team->notes }}
                            </div>
                        @endif

                        <div class="mt-6 flex flex-col sm:flex-row gap-2">
                            <a href="{{ route('teams.edit', $team) }}" class="flex-1 text-center px-4 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold transition">Sunting</a>
                            <form action="{{ route('teams.destroy', $team) }}" method="POST" class="flex-1" onsubmit="return confirm('Yakin ingin menghapus tim ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full px-4 py-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold transition">Hapus</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </main>
@endsection
