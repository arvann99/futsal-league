@extends('admin.layouts.tournament')

@section('title', $tournament->name . ' - Ikhtisar | Futsal League')

@section('page-label', 'IKHTISAR TURNAMEN')
@section('page-title', $tournament->name)
@section('page-subtitle', 'Status, kuota pendaftaran, dan data statistik live saat ini.')

@section('content')
            <!-- Dashboard Content -->
            <div class="p-4 sm:p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <!-- Total Pendaftar -->
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs sm:text-sm text-slate-400 font-medium">TOTAL PENDAFTAR</p>
                            <div class="p-2 bg-indigo-600/20 rounded-lg">
                                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-2xl sm:text-3xl font-bold text-white">{{ $statistics['total_pendaftar'] }}</p>
                    </div>

                    <!-- Terverifikasi -->
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs sm:text-sm text-slate-400 font-medium">TERVERIFIKASI</p>
                            <div class="p-2 bg-green-600/20 rounded-lg">
                                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-2xl sm:text-3xl font-bold text-white">{{ $statistics['terverifikasi'] }}</p>
                    </div>

                    <!-- Butuh Verifikasi -->
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs sm:text-sm text-slate-400 font-medium">BUTUH VERIFIKASI</p>
                            <div class="p-2 bg-yellow-600/20 rounded-lg">
                                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-2xl sm:text-3xl font-bold text-white">{{ $statistics['butuh_verifikasi'] }}</p>
                    </div>

                    <!-- Ditolak / Draft -->
                    <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs sm:text-sm text-slate-400 font-medium">DITOLAK / DRAFT</p>
                            <div class="p-2 bg-red-600/20 rounded-lg">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-2xl sm:text-3xl font-bold text-white">{{ $statistics['ditolak_draft'] }}</p>
                    </div>
                </div>

                <!-- Team Readiness Section -->
                <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 sm:p-6 mb-8">
                    <h2 class="text-lg sm:text-xl font-bold text-white mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        Persentase Kesiapan Slot Tim
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-medium text-slate-300">ASA</p>
                                <p class="text-sm font-semibold text-indigo-400">0% Kesiapan Slot</p>
                            </div>
                            <div class="w-full bg-slate-800 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Verification Section -->
                <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-white mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Pusat Verifikasi Cepat
                            </h2>
                            <p class="text-slate-400 text-sm">Beberapa tim sekolah baru telah mengisi roster pendaftaran mandiri dan menunggu verifikasi berkas dari panitia pelaksana.</p>
                        </div>
                        <button class="w-full sm:w-auto px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition whitespace-nowrap flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Buka Halaman Verifikasi
                        </button>
                    </div>
                </div>
            </div>
@endsection
