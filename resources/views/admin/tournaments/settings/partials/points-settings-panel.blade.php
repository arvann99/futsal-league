<div class="p-4 sm:p-6 max-w-4xl">
    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-900/20 border border-emerald-500/30 rounded-lg text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 p-4 bg-red-900/20 border border-red-500/30 rounded-lg text-red-400 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('tournaments.updatePointSettings', $tournament) }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="space-y-2">
                    <span class="text-sm font-semibold text-white">Poin Menang</span>
                    <input type="number" name="win_points" min="0" max="99" step="1"
                        value="{{ old('win_points', $setting->value['win'] ?? 3) }}"
                        class="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-white focus:border-emerald-500 focus:outline-none transition" />
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-semibold text-white">Poin Imbang</span>
                    <input type="number" name="draw_points" min="0" max="99" step="1"
                        value="{{ old('draw_points', $setting->value['draw'] ?? 1) }}"
                        class="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-white focus:border-emerald-500 focus:outline-none transition" />
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-semibold text-white">Poin Kalah</span>
                    <input type="number" name="loss_points" min="0" max="99" step="1"
                        value="{{ old('loss_points', $setting->value['loss'] ?? 0) }}"
                        class="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-white focus:border-emerald-500 focus:outline-none transition" />
                </label>
            </div>

            <p class="text-slate-400 text-sm mt-4">Sesuaikan penghitungan poin di klasemen. Sistem standar biasanya 3-1-0, tetapi Anda dapat mengubah sesuai kebutuhan turnamen.</p>
        </div>

        <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 mt-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Pemeringkatan Klasemen (Tie-breakers)</p>
                    <p class="text-sm text-slate-400 mt-1">Atur prioritas kriteria jika dua tim memiliki poin yang sama.</p>
                </div>
                <span class="text-xs uppercase tracking-[0.24em] text-slate-500">Geser untuk mengubah urutan</span>
            </div>

            <div id="tiebreakerList" class="space-y-3">
                @php
                    $criteriaLabels = [
                        'points' => 'Jumlah Poin',
                        'head_to_head' => 'Head to head',
                        'goal_difference' => 'Selisih Gol (Goal Difference)',
                        'goals_scored' => 'Jumlah Gol yang Dicetak (Goals Scored)',
                    ];
                    $selectedTiebreakers = old('tiebreakers', $setting->value['tiebreakers'] ?? array_keys($criteriaLabels));
                @endphp

                @foreach($selectedTiebreakers as $index => $criterion)
                    <div class="tiebreaker-item flex items-center gap-3 p-4 bg-slate-800 rounded-xl border border-slate-700" data-index="{{ $index }}" data-criterion="{{ $criterion }}">
                        <div class="flex items-center gap-3 text-slate-300 w-full">
                            <span class="order-badge inline-flex items-center justify-center w-9 h-9 rounded-full bg-slate-700 text-sm font-semibold text-slate-100">{{ $index + 1 }}</span>
                            <div>
                                <p class="font-semibold text-white">{{ $criteriaLabels[$criterion] ?? $criterion }}</p>
                                <p class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $criterion)) }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <button type="button" data-direction="-1" class="move-tiebreaker w-9 h-9 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 transition" {{ $index === 0 ? 'disabled' : '' }}>
                                ▲
                            </button>
                            <button type="button" data-direction="1" class="move-tiebreaker w-9 h-9 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 transition" {{ $index === count($selectedTiebreakers) - 1 ? 'disabled' : '' }}>
                                ▼
                            </button>
                        </div>
                        <input type="hidden" name="tiebreakers[]" value="{{ $criterion }}">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 grid gap-4 sm:grid-cols-2">
            <div class="p-4 bg-slate-800 rounded-xl">
                <p class="text-xs text-slate-400 uppercase tracking-[0.24em] mb-2">Poin Saat Ini</p>
                <p class="text-lg font-bold text-emerald-400">Menang: {{ $setting->value['win'] ?? 3 }}</p>
                <p class="text-lg font-bold text-sky-400">Imbang: {{ $setting->value['draw'] ?? 1 }}</p>
                <p class="text-lg font-bold text-rose-400">Kalah: {{ $setting->value['loss'] ?? 0 }}</p>
            </div>
            <div class="p-4 bg-slate-800 rounded-xl">
                <p class="text-xs text-slate-400 uppercase tracking-[0.24em] mb-2">Ringkasan</p>
                <p class="text-sm text-slate-300">Poin menang lebih tinggi akan menguntungkan tim agresif, sedangkan imbang dan kalah tetap mempertahankan jarak klasemen.</p>
                <p class="text-sm text-slate-300 mt-3">Gunakan fitur Reset untuk kembali ke nilai default 3-1-0.</p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 pt-4">
            <a href="{{ route('tournaments.settings', $tournament) }}" class="flex-1 text-center py-3 px-6 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-lg transition">
                Kembali
            </a>

            <button type="button" onclick="submitResetPoints()" class="flex-1 py-3 px-6 bg-red-600/20 hover:bg-red-600/40 text-red-400 font-semibold rounded-lg transition flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Reset Default
            </button>

            <button type="submit" class="flex-1 py-3 px-6 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Simpan Perubahan
            </button>
        </div>
    </form>

    <form id="resetPointsSettings" action="{{ route('tournaments.resetPointSettings', $tournament) }}" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>

<script>
    function submitResetPoints() {
        if (confirm('Reset Standar Liga Poin ke nilai default 3-1-0?')) {
            document.getElementById('resetPointsSettings').submit();
        }
    }

    function moveTiebreaker(index, direction) {
        const list = document.getElementById('tiebreakerList');
        const items = Array.from(list.querySelectorAll('.tiebreaker-item'));
        const targetIndex = index + direction;

        if (targetIndex < 0 || targetIndex >= items.length) {
            return;
        }

        const current = items[index];
        const target = items[targetIndex];

        if (direction === -1) {
            list.insertBefore(current, target);
        } else {
            list.insertBefore(target, current);
        }

        refreshTiebreakerList();
    }

    function refreshTiebreakerList() {
        const items = Array.from(document.querySelectorAll('#tiebreakerList .tiebreaker-item'));

        items.forEach((item, index) => {
            item.dataset.index = index;
            item.querySelector('.order-badge').textContent = index + 1;

            const buttons = item.querySelectorAll('.move-tiebreaker');
            buttons.forEach(button => {
                button.disabled = (button.dataset.direction === '-1' && index === 0) || (button.dataset.direction === '1' && index === items.length - 1);
            });
        });
    }

    function attachTiebreakerListeners() {
        document.querySelectorAll('#tiebreakerList .move-tiebreaker').forEach(button => {
            button.addEventListener('click', function () {
                const item = this.closest('.tiebreaker-item');
                const index = Number(item.dataset.index);
                const direction = Number(this.dataset.direction);
                moveTiebreaker(index, direction);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshTiebreakerList();
        attachTiebreakerListeners();
    });
</script>
