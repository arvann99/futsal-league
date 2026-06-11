@extends('admin.layouts.app')

@section('title', 'Buat Tim | Futsal League')

@section('body')    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs text-indigo-400 font-semibold uppercase mb-2">Tim Baru</p>
                <h1 class="text-3xl font-bold">Tambah Tim</h1>
                <p class="text-slate-400 mt-2">Isi data tim untuk siap digunakan pada turnamen.</p>
            </div>
            <a href="{{ route('teams.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-700 transition">
                Kembali ke Daftar Tim
            </a>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-900/10 p-4 text-rose-200">
                <ul class="list-disc list-inside text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('teams.store') }}" method="POST" class="space-y-6 bg-slate-900/90 rounded-3xl border border-slate-800 p-8">
            @csrf
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Nama Tim</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Negara</label>
                    <input type="text" name="country" value="{{ old('country') }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Kota</label>
                    <input type="text" name="city" value="{{ old('city') }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Catatan</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition">Simpan Tim</button>
                <a href="{{ route('teams.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-700 bg-slate-800 px-5 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-700 transition">Batal</a>
            </div>
        </form>
    </div>
@endsection
