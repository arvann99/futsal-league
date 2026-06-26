@extends('admin.layouts.tournament')

@section('title', 'Manajemen Peserta | ' . $tournament->name)

@section('page-label', 'MANAJEMEN PESERTA')
@section('page-title', 'Manajemen Peserta')
@section('page-subtitle', $tournament->name)

@section('header-actions')
    <div class="flex flex-col sm:flex-row gap-3">
        @if($isFull)
            {{-- N1 — slot penuh: tombol dinonaktifkan & tidak bisa diklik --}}
            <button type="button" disabled title="{{ $fullReason }}"
                class="px-5 py-3 bg-indigo-600/40 rounded-xl text-white/60 font-semibold cursor-not-allowed select-none">Tambah Peserta</button>
        @else
            <a href="{{ route('tournaments.participants.create', $tournament) }}" class="px-5 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition">Tambah Peserta</a>
        @endif
        <a href="{{ route('tournaments.manage', $tournament) }}" class="px-5 py-3 bg-slate-800 border border-slate-700 hover:bg-slate-700 rounded-xl text-slate-200 transition">Kembali ke Manajemen</a>
    </div>
@endsection

@section('content')
    <div class="p-4 sm:p-6 max-w-7xl">

        @if(session('success'))
            <div class="mb-6 rounded-xl bg-emerald-900/20 border border-emerald-500/30 p-4 text-emerald-200">
                <p>{{ session('success') }}</p>
                @if(session('new_manager_token'))
                    {{-- N3 — token baru ditampilkan langsung + tombol salin --}}
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-2 rounded-lg bg-emerald-950/60 border border-emerald-500/40 px-3 py-2 font-mono text-sm tracking-wide text-emerald-100">
                            🔑 {{ session('new_manager_token') }}
                        </span>
                        <button type="button" onclick="copyToken('{{ session('new_manager_token') }}')"
                            class="inline-flex items-center px-3 py-2 bg-emerald-700 hover:bg-emerald-600 rounded-lg text-sm font-medium text-white transition">Salin Token</button>
                    </div>
                @endif
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 rounded-xl bg-rose-900/20 border border-rose-500/30 p-4 text-rose-200">
                {{ session('error') }}
            </div>
        @endif

        @if($usesGroups)
            @php
                $capacity = ($tournament->groupSetting->group_count ?? 0) * ($tournament->groupSetting->teams_per_group ?? 0);
                $current = $participants->count();
                $pct = $capacity > 0 ? ($current / $capacity) : 0;
            @endphp
            <div class="mb-6 rounded-xl border p-4 {{ $current > $capacity ? 'bg-rose-900/20 border-rose-500/30 text-rose-200' : ($pct >= 0.8 ? 'bg-amber-900/20 border-amber-500/30 text-amber-200' : 'bg-slate-900/60 border-slate-800 text-slate-300') }}">
                <span class="font-semibold">Kapasitas Grup:</span> {{ $current }} / {{ $capacity }} slot
                ({{ $tournament->groupSetting->group_count }} grup × {{ $tournament->groupSetting->teams_per_group }} tim per grup)
                @if($current > $capacity)
                    — <span class="font-semibold">melebihi kapasitas!</span> Ubah pengaturan grup atau kurangi peserta.
                @elseif($isFull)
                    — <span class="font-semibold">slot penuh.</span> Tombol "Tambah Peserta" dinonaktifkan.
                @endif
            </div>
        @endif

        @if($participants->isEmpty())
            <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-10 text-center">
                <p class="text-slate-400 mb-3">Belum ada peserta terdaftar untuk turnamen ini.</p>
                @if($isFull)
                    <button type="button" disabled title="{{ $fullReason }}" class="inline-block px-5 py-3 bg-indigo-600/40 rounded-xl text-white/60 font-semibold cursor-not-allowed select-none">Tambah Peserta Sekarang</button>
                @else
                    <a href="{{ route('tournaments.participants.create', $tournament) }}" class="inline-block px-5 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition">Tambah Peserta Sekarang</a>
                @endif
            </div>
        @else
            <div class="overflow-x-auto rounded-3xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-black/20">
                <table class="min-w-full text-left divide-y divide-slate-800">
                    <thead class="bg-slate-950/90">
                        <tr>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">No</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Nama Tim</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Kota</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Negara</th>
                            @if($usesGroups)
                                <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Grup</th>
                            @endif
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Pemain</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Ofisial</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Manager Token</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Status Verifikasi</th>
                            <th class="px-5 py-4 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($participants as $index => $participant)
                            <tr class="hover:bg-slate-900/70 transition">
                                <td class="px-5 py-4 text-sm text-slate-300">{{ $index + 1 }}</td>
                                <td class="px-5 py-4 text-sm text-white">{{ $participant->team->name }}</td>
                                <td class="px-5 py-4 text-sm text-slate-300">{{ $participant->team->city ?? '-' }}</td>
                                <td class="px-5 py-4 text-sm text-slate-300">{{ $participant->team->country ?? 'Indonesia' }}</td>
                                @if($usesGroups)
                                    <td class="px-5 py-4 text-sm text-slate-300">
                                        <form action="{{ route('tournaments.participants.assignGroup', [$tournament, $participant]) }}" method="POST" class="inline-flex items-center gap-1">
                                            @csrf
                                            @method('PATCH')
                                            <select name="group_label" onchange="this.form.submit()" class="rounded-lg bg-slate-800 border border-slate-700 text-slate-200 text-sm px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                <option value="">—</option>
                                                @foreach($groupLabels as $g)
                                                    <option value="{{ $g }}" {{ $participant->group_label === $g ? 'selected' : '' }}>Grup {{ $g }}</option>
                                                @endforeach
                                            </select>
                                            @if($participant->group_assigned_manually)
                                                <span title="Grup ditetapkan manual / hasil undian — tidak akan ditimpa auto" class="text-amber-400 text-xs">🔒</span>
                                            @endif
                                        </form>
                                    </td>
                                @endif
                                <td class="px-5 py-4 text-sm text-slate-300">
                                    <button type="button" onclick="togglePlayers({{ $index }})" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-200 text-xs font-medium transition">
                                        {{ $participant->players->count() }} Pemain
                                        <svg id="arrow-{{ $index }}" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-300">
                                    {{-- N9 — daftar Ofisial/Manager dari portal manager --}}
                                    <button type="button" onclick="toggleOfficials({{ $index }})" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-200 text-xs font-medium transition">
                                        {{ $participant->officials->count() }} Ofisial
                                        <svg id="arrow-off-{{ $index }}" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-300 break-all">{{ $participant->team->manager_token ?? 'N/A' }}</td>
                                <td class="px-5 py-4 text-sm text-slate-300">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] {{ $participant->team->verification_status === 'approved' ? 'bg-emerald-700 text-emerald-200' : ($participant->team->verification_status === 'rejected' ? 'bg-rose-700 text-rose-200' : 'bg-slate-700 text-slate-200') }}">
                                        {{ ucfirst($participant->team->verification_status ?? 'pending') }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-200 space-x-2">
                                    <a href="{{ route('tournaments.participants.edit', [$tournament, $participant]) }}" class="inline-flex items-center px-3 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium">Edit</a>
                                    <form action="{{ route('tournaments.participants.destroy', [$tournament, $participant]) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus peserta ini dari turnamen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 rounded-lg font-medium">Hapus</button>
                                    </form>
                                    <button type="button" onclick="copyToken('{{ $participant->team->manager_token ?? '' }}')" class="inline-flex items-center px-3 py-2 bg-slate-800 hover:bg-slate-700 rounded-lg font-medium">Copy Token</button>
                                    <form action="{{ route('teams.resetToken', $participant->team) }}" method="POST" class="inline-block" onsubmit="return confirm('Reset token manager untuk tim ini?');">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-3 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium">Reset Token</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="players-{{ $index }}" class="hidden bg-slate-950/60">
                                <td colspan="{{ $usesGroups ? 9 : 8 }}" class="px-5 py-4">
                                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Daftar Pemain ({{ $participant->players->count() }})</p>
                                    @if($participant->players->isEmpty())
                                        <p class="text-sm text-slate-500 italic">Belum ada pemain terdaftar. Manager mengisi pemain lewat portal official.</p>
                                    @else
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                            @foreach($participant->players->sortBy('shirt_number') as $player)
                                                @php $stat = $playerStats[$player->id] ?? null; @endphp
                                                <div class="flex items-center gap-3 p-2 bg-slate-900/50 rounded-lg border border-slate-800">
                                                    @if($player->photo)
                                                        <img src="{{ asset('storage/' . $player->photo) }}" alt="{{ $player->player_name }}" class="w-8 h-8 rounded-full object-cover bg-slate-800">
                                                    @else
                                                        <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 text-xs font-bold">{{ substr($player->player_name, 0, 1) }}</div>
                                                    @endif
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-white truncate">{{ $player->player_name }} {{ $player->is_captain ? '(C)' : '' }}</p>
                                                        <p class="text-xs text-slate-400">#{{ $player->shirt_number ?? '-' }} | {{ $player->dominant_position ?? '-' }}</p>
                                                        <p class="mt-0.5 text-[11px] text-slate-300 flex items-center gap-2">
                                                            <span title="Gol">⚽ {{ (int) ($stat->goals ?? 0) }}</span>
                                                            <span title="Kartu kuning">🟨 {{ (int) ($stat->yellow_cards ?? 0) }}</span>
                                                            <span title="Kartu merah">🟥 {{ (int) ($stat->red_cards ?? 0) }}</span>
                                                        </p>
                                                    </div>
                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $player->status === 'active' ? 'bg-emerald-700/40 text-emerald-200' : 'bg-slate-700 text-slate-300' }}">
                                                        {{ ucfirst($player->status ?? 'active') }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            {{-- N9 — baris daftar Ofisial/Manager (data dari portal manager) --}}
                            <tr id="officials-{{ $index }}" class="hidden bg-slate-950/60">
                                <td colspan="{{ $usesGroups ? 9 : 8 }}" class="px-5 py-4">
                                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Daftar Ofisial / Manager ({{ $participant->officials->count() }})</p>
                                    @if($participant->officials->isEmpty())
                                        <p class="text-sm text-slate-500 italic">Belum ada ofisial terdaftar. Manager mengisi data ofisial lewat portal official.</p>
                                    @else
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                            @foreach($participant->officials->sortBy('role') as $official)
                                                <div class="flex items-center gap-3 p-2 bg-slate-900/50 rounded-lg border border-slate-800">
                                                    <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 text-xs font-bold">{{ strtoupper(substr($official->official_name, 0, 1)) }}</div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-white truncate">{{ $official->official_name }}</p>
                                                        <p class="text-xs text-slate-400 truncate">
                                                            {{ $official->contact_phone ?? '' }}{{ $official->contact_phone && $official->contact_email ? ' · ' : '' }}{{ $official->contact_email ?? '' }}
                                                        </p>
                                                    </div>
                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium bg-indigo-700/40 text-indigo-200">{{ $official->role }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    function togglePlayers(index) {
        const row = document.getElementById('players-' + index);
        const arrow = document.getElementById('arrow-' + index);
        if (!row) return;
        if (row.classList.contains('hidden')) {
            row.classList.remove('hidden');
            arrow && arrow.classList.add('rotate-180');
        } else {
            row.classList.add('hidden');
            arrow && arrow.classList.remove('rotate-180');
        }
    }

    function toggleOfficials(index) {
        const row = document.getElementById('officials-' + index);
        const arrow = document.getElementById('arrow-off-' + index);
        if (!row) return;
        if (row.classList.contains('hidden')) {
            row.classList.remove('hidden');
            arrow && arrow.classList.add('rotate-180');
        } else {
            row.classList.add('hidden');
            arrow && arrow.classList.remove('rotate-180');
        }
    }

    function copyToken(token) {
        if (!token) {
            alert('Token belum tersedia.');
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(token).then(
                () => alert('Token manager disalin ke clipboard.'),
                () => window.prompt('Salin token manager:', token)
            );
        } else {
            window.prompt('Salin token manager:', token);
        }
    }
</script>
@endpush
