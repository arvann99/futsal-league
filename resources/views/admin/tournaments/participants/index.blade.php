@extends('admin.layouts.tournament')

@section('title', 'Manajemen Peserta | ' . $tournament->name)

@section('page-label', 'MANAJEMEN PESERTA')
@section('page-title', 'Manajemen Peserta')
@section('page-subtitle', $tournament->name)

@section('header-actions')
    <div class="flex flex-col sm:flex-row gap-3">
        <a href="{{ route('tournaments.participants.create', $tournament) }}" class="px-5 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition">Tambah Peserta</a>
        <a href="{{ route('tournaments.manage', $tournament) }}" class="px-5 py-3 bg-slate-800 border border-slate-700 hover:bg-slate-700 rounded-xl text-slate-200 transition">Kembali ke Manajemen</a>
    </div>
@endsection

@section('content')
    <div class="p-4 sm:p-6 max-w-7xl">

        @if(session('success'))
            <div class="mb-6 rounded-xl bg-emerald-900/20 border border-emerald-500/30 p-4 text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if($participants->isEmpty())
            <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-10 text-center">
                <p class="text-slate-400 mb-3">Belum ada peserta terdaftar untuk turnamen ini.</p>
                <a href="{{ route('tournaments.participants.create', $tournament) }}" class="inline-block px-5 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition">Tambah Peserta Sekarang</a>
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
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
