@extends('admin.layouts.tournament')

@section('title', 'Undian Grup - ' . $tournament->name)

@section('page-label', 'UNDIAN GRUP')
@section('page-title', 'Undian / Drawing Tim ke Grup')
@section('page-subtitle', $tournament->name)

@section('header-actions')
    <a href="{{ route('tournaments.groupSettings', $tournament) }}" class="px-5 py-3 bg-slate-800 border border-slate-700 hover:bg-slate-700 rounded-xl text-slate-200 transition">Kembali ke Pengaturan</a>
@endsection

@section('content')
    <div class="p-4 sm:p-6 max-w-5xl" id="drawRoot"
         data-draw-url="{{ route('tournaments.performGroupDraw', $tournament) }}"
         data-csrf="{{ csrf_token() }}">

        @if($teams->isEmpty())
            <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-10 text-center">
                <p class="text-slate-400 mb-3">Belum ada peserta untuk diundi.</p>
                <a href="{{ route('tournaments.participants.index', $tournament) }}" class="inline-block px-5 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition">Tambah Peserta</a>
            </div>
        @else
            <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 mb-6">
                <p class="text-slate-300 text-sm mb-2">
                    {{ $teams->count() }} tim akan diacak dan dibagikan ke {{ count($groupLabels) }} grup
                    ({{ $tournament->groupSetting->teams_per_group }} tim per grup).
                </p>
                <p class="text-amber-300/80 text-xs mb-4">⚠️ Hasil undian akan menimpa penempatan grup saat ini dan menandainya sebagai penempatan tetap (tidak ditimpa auto-assign). Jadwal grup akan diperbarui.</p>

                <div class="flex flex-wrap items-center gap-4">
                    <div id="spinDisplay" class="flex-1 min-w-[240px] h-20 rounded-2xl bg-slate-950 border border-slate-700 flex items-center justify-center overflow-hidden">
                        <span id="spinText" class="text-2xl font-bold text-white tracking-wide">Siap mengundi…</span>
                    </div>
                    <button id="spinBtn" type="button" class="px-6 py-4 bg-emerald-600 hover:bg-emerald-700 rounded-xl text-white font-semibold transition">
                        🎲 Mulai Undian
                    </button>
                </div>
            </div>

            <div id="resultArea" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"></div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.getElementById('drawRoot');
    if (!root) return;

    const teamNames = @json($teams->pluck('name')->values());
    const spinBtn = document.getElementById('spinBtn');
    const spinText = document.getElementById('spinText');
    const resultArea = document.getElementById('resultArea');
    const drawUrl = root.dataset.drawUrl;
    const csrf = root.dataset.csrf;

    let spinning = false;

    function renderResults(assignments) {
        resultArea.innerHTML = '';
        Object.keys(assignments).sort().forEach(function (label) {
            const card = document.createElement('div');
            card.className = 'rounded-2xl border border-slate-800 bg-slate-900/80 p-4';
            const list = assignments[label].map(function (n) {
                return '<li class="py-1 text-sm text-slate-200 border-b border-slate-800 last:border-0">' + n + '</li>';
            }).join('');
            card.innerHTML =
                '<p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-400 mb-2">Grup ' + label + '</p>' +
                '<ul>' + list + '</ul>';
            resultArea.appendChild(card);
        });
    }

    spinBtn.addEventListener('click', function () {
        if (spinning) return;
        spinning = true;
        spinBtn.disabled = true;
        spinBtn.classList.add('opacity-60');
        resultArea.innerHTML = '';

        // Animasi slot-machine: cycle nama tim sambil menunggu hasil server.
        let i = 0;
        const cycle = setInterval(function () {
            spinText.textContent = teamNames[i % teamNames.length];
            i++;
        }, 70);

        fetch(drawUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (res) {
            // Biarkan animasi berputar minimal ~2.2 detik untuk efek dramatis.
            setTimeout(function () {
                clearInterval(cycle);
                spinning = false;
                spinBtn.disabled = false;
                spinBtn.classList.remove('opacity-60');

                if (!res.ok || !res.body.success) {
                    spinText.textContent = '⚠️ Gagal';
                    alert(res.body.message || 'Undian gagal.');
                    return;
                }

                spinText.textContent = '✅ Undian Selesai!';
                renderResults(res.body.assignments || {});
            }, 2200);
        })
        .catch(function () {
            clearInterval(cycle);
            spinning = false;
            spinBtn.disabled = false;
            spinBtn.classList.remove('opacity-60');
            spinText.textContent = '⚠️ Error';
            alert('Terjadi kesalahan jaringan.');
        });
    });
})();
</script>
@endpush
