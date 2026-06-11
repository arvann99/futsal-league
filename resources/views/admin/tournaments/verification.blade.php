@extends('admin.layouts.tournament')

@section('title', 'Verifikasi Berkas - ' . $tournament->name)

@section('page-label', 'VERIFIKASI BERKAS')
@section('page-title', 'Verifikasi Berkas Peserta')
@section('page-subtitle', $tournament->name)

@section('content')
    <div class="p-4 sm:p-6 max-w-7xl">
        @if(session('success'))
            <div class="mb-6 rounded-xl bg-emerald-900/20 border border-emerald-500/30 p-4 text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @php
            $pending = $participants->filter(fn($p) => ($p->team->verification_status ?? 'pending') === 'pending')->count();
            $verified = $participants->filter(fn($p) => ($p->team->verification_status ?? 'pending') === 'approved')->count();
            $rejected = $participants->filter(fn($p) => ($p->team->verification_status ?? 'pending') === 'rejected')->count();
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-slate-900/80 rounded-xl border border-slate-800 p-4">
                <p class="text-xs text-slate-400 uppercase tracking-wider">Menunggu</p>
                <p class="text-3xl font-bold text-amber-400">{{ $pending }}</p>
            </div>
            <div class="bg-slate-900/80 rounded-xl border border-slate-800 p-4">
                <p class="text-xs text-slate-400 uppercase tracking-wider">Terverifikasi</p>
                <p class="text-3xl font-bold text-emerald-400">{{ $verified }}</p>
            </div>
            <div class="bg-slate-900/80 rounded-xl border border-slate-800 p-4">
                <p class="text-xs text-slate-400 uppercase tracking-wider">Ditolak</p>
                <p class="text-3xl font-bold text-rose-400">{{ $rejected }}</p>
            </div>
        </div>

        @if($participants->isEmpty())
            <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-10 text-center">
                <p class="text-slate-400 mb-3">Belum ada peserta terdaftar untuk turnamen ini.</p>
                <a href="{{ route('tournaments.participants.index', $tournament) }}" class="inline-block px-5 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition">Tambah Peserta</a>
            </div>
        @else
            <div class="space-y-3">
                @foreach($participants as $index => $participant)
                    @php
                        $participant->load(['team', 'players']);
                        $status = $participant->team->verification_status ?? 'pending';
                        $playerCount = $participant->players->count();
                        $statusConfig = [
                            'pending' => ['bg' => 'bg-amber-700/40', 'text' => 'text-amber-200', 'label' => 'Pending'],
                            'approved' => ['bg' => 'bg-emerald-700/40', 'text' => 'text-emerald-200', 'label' => 'Terverifikasi'],
                            'rejected' => ['bg' => 'bg-rose-700/40', 'text' => 'text-rose-200', 'label' => 'Ditolak'],
                        ];
                        $config = $statusConfig[$status] ?? $statusConfig['pending'];
                    @endphp
                    
                    <div class="rounded-xl border border-slate-800 bg-slate-900/80 overflow-hidden">
                        <div class="flex flex-wrap items-center gap-4 p-4 hover:bg-slate-800/30 transition">
                            <div class="flex items-center gap-3 flex-1 min-w-[200px]">
                                @if($participant->team?->logo)
                                    <img src="{{ asset('storage/' . $participant->team->logo) }}" alt="{{ $participant->team->name }}" class="w-12 h-12 rounded-lg object-cover bg-slate-800">
                                @else
                                    <div class="w-12 h-12 rounded-lg bg-slate-800 flex items-center justify-center text-slate-400 text-sm font-bold">{{ substr($participant->team->name ?? 'N', 0, 1) }}</div>
                                @endif
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ $participant->team->name ?? 'N/A' }}</p>
                                    <p class="text-xs text-slate-400">{{ $participant->team->city ?? '-' }}, {{ $participant->team->country ?? 'Indonesia' }}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="togglePlayers({{ $index }})" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-lg text-white text-xs font-medium transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    {{ $playerCount }} Pemain
                                    <svg id="arrow-{{ $index }}" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] {{ $config['bg'] }} {{ $config['text'] }}">
                                    {{ $config['label'] }}
                                </span>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <a href="{{ route('tournaments.participants.edit', [$tournament, $participant]) }}" class="px-3 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-white text-xs font-medium transition">
                                    Detail
                                </a>
                                @if($status !== 'approved')
                                    <form action="{{ route('tournaments.participants.verify', [$tournament, $participant]) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 rounded-lg text-white text-xs font-semibold transition">
                                            Verifikasi
                                        </button>
                                    </form>
                                @endif
                                @if($status !== 'rejected')
                                    <form action="{{ route('tournaments.participants.verify', [$tournament, $participant]) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="px-3 py-2 bg-rose-600 hover:bg-rose-700 rounded-lg text-white text-xs font-semibold transition">
                                            Tolak
                                        </button>
                                    </form>
                                @endif
                                @if($status !== 'pending')
                                    <form action="{{ route('tournaments.participants.verify', [$tournament, $participant]) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="pending">
                                        <button type="submit" class="px-3 py-2 bg-slate-600 hover:bg-slate-500 rounded-lg text-white text-xs font-semibold transition">
                                            Reset
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        
                        <div id="players-{{ $index }}" class="hidden border-t border-slate-800 bg-slate-950/50 p-4">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Daftar Pemain ({{ $playerCount }})</p>
                            @if($participant->players->isEmpty())
                                <p class="text-sm text-slate-500 italic">Belum ada pemain terdaftar</p>
                            @else
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                    @foreach($participant->players as $player)
                                        <div class="flex items-center gap-3 p-2 bg-slate-900/50 rounded-lg border border-slate-800">
                                            @if($player->photo)
                                                <img src="{{ asset('storage/' . $player->photo) }}" alt="{{ $player->player_name }}" class="w-8 h-8 rounded-full object-cover bg-slate-800">
                                            @else
                                                <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 text-xs font-bold">{{ substr($player->player_name, 0, 1) }}</div>
                                            @endif
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-white truncate">{{ $player->player_name }} {{ $player->is_captain ? '(C)' : '' }}</p>
                                                <p class="text-xs text-slate-400">#{{ $player->shirt_number ?? '-' }} | {{ $player->dominant_position ?? '-' }}</p>
                                            </div>
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $player->status === 'active' ? 'bg-emerald-700/40 text-emerald-200' : 'bg-slate-700 text-slate-300' }}">
                                                {{ ucfirst($player->status ?? 'active') }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 bg-indigo-900/20 border border-indigo-500/30 rounded-lg p-4">
                <p class="text-sm text-indigo-200">
                    <strong>Info:</strong> Peserta dengan status <strong>Terverifikasi</strong> akan ditampilkan dalam jadwal dan dapat mengikuti pertandingan. Peserta dengan status <strong>Pending</strong> belum bisa diikutkan dalam jadwal aktif.
                </p>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    function togglePlayers(index) {
        const playersDiv = document.getElementById('players-' + index);
        const arrow = document.getElementById('arrow-' + index);
        
        if (playersDiv.classList.contains('hidden')) {
            playersDiv.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        } else {
            playersDiv.classList.add('hidden');
            arrow.classList.remove('rotate-180');
        }
    }
</script>
@endpush
