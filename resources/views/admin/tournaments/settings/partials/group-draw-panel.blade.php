{{--
    Panel Plotting / Undian tim ke grup (reusable).
    Dipakai halaman Undian standalone (settings/group-draw) & bisa di-embed.
    Membutuhkan variabel:
      $tournament, $teams (collection {id,name,group_label}), $groupLabels (array).

    Fitur:
      - Menampilkan penempatan grup TERSIMPAN saat halaman dibuka (tidak kosong).
      - Undian acak (spin) → menyebar tim & menata ulang kolom grup.
      - Edit manual DRAG & DROP: seret kartu tim antar kolom grup / ke area
        "Belum ada grup", lalu klik "Simpan Penempatan".
--}}
<div id="drawRoot"
     data-draw-url="{{ route('tournaments.performGroupDraw', $tournament) }}"
     data-save-url="{{ route('tournaments.saveGroupPlotting', $tournament) }}"
     data-csrf="{{ csrf_token() }}"
     data-teams-per-group="{{ (int) $tournament->groupSetting->teams_per_group }}">

    @if($teams->isEmpty())
        <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-10 text-center">
            <p class="text-slate-400 mb-3">Belum ada peserta untuk diplot / diundi.</p>
            <a href="{{ route('tournaments.participants.index', $tournament) }}" class="inline-block px-5 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition">Tambah Peserta</a>
        </div>
    @else
        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 mb-6">
            <div class="flex flex-wrap items-center gap-4">
                <div id="spinDisplay" class="flex-1 min-w-[220px] h-16 rounded-2xl bg-slate-950 border border-slate-700 flex items-center justify-center overflow-hidden">
                    <span id="spinText" class="text-xl font-bold text-white tracking-wide">Siap plotting…</span>
                </div>
                <button id="spinBtn" type="button" class="px-6 py-3.5 bg-emerald-600 hover:bg-emerald-700 rounded-xl text-white font-semibold transition">
                    🎲 Acak Ulang (Undian)
                </button>
                <button id="saveBtn" type="button" class="px-6 py-3.5 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition disabled:opacity-50" disabled>
                    💾 Simpan Penempatan
                </button>
            </div>
            <p class="text-amber-300/80 text-xs mt-4">
                💡 Seret kartu tim antar grup untuk mengatur manual, lalu <strong>Simpan Penempatan</strong>.
                Tombol Acak akan menyebar ulang semua tim. Kapasitas {{ (int) $tournament->groupSetting->teams_per_group }} tim per grup.
            </p>
            <p id="dirtyHint" class="hidden text-emerald-300 text-xs mt-2">● Ada perubahan belum tersimpan.</p>
        </div>

        {{-- Area tim belum bergrup (target drop juga) --}}
        <div id="unassignedZone" data-group=""
             class="dropzone mb-6 rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 p-4 min-h-[72px]">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400 mb-3">Belum ada grup <span class="text-slate-600">(seret ke sini untuk melepas)</span></p>
            <div class="flex flex-wrap gap-2" data-slot></div>
        </div>

        {{-- Kolom grup --}}
        <div id="groupsGrid" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($groupLabels as $label)
                <div class="dropzone rounded-2xl border border-slate-800 bg-slate-900/80 p-4 flex flex-col" data-group="{{ $label }}">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-400">Grup {{ $label }}</p>
                        <span class="text-[11px] text-slate-500" data-count>0/{{ (int) $tournament->groupSetting->teams_per_group }}</span>
                    </div>
                    <div class="flex-1 space-y-2 min-h-[48px]" data-slot></div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const root = document.getElementById('drawRoot');
    if (!root) return;

    const teams = @json($teams->map(fn ($t) => ['id' => $t['id'], 'name' => $t['name'], 'group_label' => $t['group_label']])->values());
    const groupLabels = @json(array_values($groupLabels));
    const perGroup = parseInt(root.dataset.teamsPerGroup) || 0;
    const drawUrl = root.dataset.drawUrl;
    const saveUrl = root.dataset.saveUrl;
    const csrf = root.dataset.csrf;

    const spinBtn = document.getElementById('spinBtn');
    const saveBtn = document.getElementById('saveBtn');
    const spinText = document.getElementById('spinText');
    const dirtyHint = document.getElementById('dirtyHint');
    const teamNames = teams.map(t => t.name);

    // State penempatan saat ini: { teamId: groupLabel|'' }
    const placement = {};
    teams.forEach(t => {
        placement[t.id] = (t.group_label && groupLabels.includes(t.group_label)) ? t.group_label : '';
    });

    let dirty = false;
    let spinning = false;

    function markDirty(v) {
        dirty = v;
        saveBtn.disabled = !v;
        dirtyHint.classList.toggle('hidden', !v);
    }

    function makeCard(team) {
        const card = document.createElement('div');
        card.className = 'group-card cursor-grab active:cursor-grabbing select-none rounded-lg border border-slate-700 bg-slate-800 hover:bg-slate-750 px-3 py-2 text-sm text-slate-100 flex items-center gap-2';
        card.setAttribute('draggable', 'true');
        card.dataset.teamId = team.id;
        card.innerHTML = '<span class="text-slate-500">⠿</span><span class="truncate">' + escapeHtml(team.name) + '</span>';
        card.addEventListener('dragstart', function (e) {
            card.classList.add('opacity-40');
            e.dataTransfer.setData('text/plain', String(team.id));
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('opacity-40');
        });
        return card;
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // Render ulang seluruh papan dari state `placement`.
    function render() {
        document.querySelectorAll('#drawRoot .dropzone').forEach(zone => {
            const slot = zone.querySelector('[data-slot]');
            slot.innerHTML = '';
        });

        teams.forEach(team => {
            const label = placement[team.id] || '';
            const zone = document.querySelector('#drawRoot .dropzone[data-group="' + cssEscape(label) + '"]');
            if (zone) zone.querySelector('[data-slot]').appendChild(makeCard(team));
        });

        updateCounts();
    }

    function cssEscape(v) {
        return v.replace(/"/g, '\\"');
    }

    function updateCounts() {
        groupLabels.forEach(label => {
            const zone = document.querySelector('#drawRoot .dropzone[data-group="' + cssEscape(label) + '"]');
            if (!zone) return;
            const n = zone.querySelectorAll('.group-card').length;
            const badge = zone.querySelector('[data-count]');
            if (badge) badge.textContent = n + '/' + perGroup;
            zone.classList.toggle('border-rose-500/50', perGroup > 0 && n > perGroup);
        });
    }

    // Pasang handler dropzone.
    document.querySelectorAll('#drawRoot .dropzone').forEach(zone => {
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            zone.classList.add('ring-2', 'ring-indigo-500/60');
        });
        zone.addEventListener('dragleave', function () {
            zone.classList.remove('ring-2', 'ring-indigo-500/60');
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('ring-2', 'ring-indigo-500/60');
            const teamId = e.dataTransfer.getData('text/plain');
            if (!teamId) return;
            const newLabel = zone.dataset.group || '';
            if (placement[teamId] === newLabel) return;
            placement[teamId] = newLabel;
            render();
            markDirty(true);
        });
    });

    // Simpan penempatan manual ke server.
    saveBtn.addEventListener('click', function () {
        // Cegah simpan bila ada grup melebihi kapasitas.
        const over = groupLabels.find(label => {
            const zone = document.querySelector('#drawRoot .dropzone[data-group="' + cssEscape(label) + '"]');
            return zone && zone.querySelectorAll('.group-card').length > perGroup && perGroup > 0;
        });
        if (over) {
            alert('Grup ' + over + ' melebihi kapasitas (' + perGroup + ' tim). Sesuaikan dulu sebelum menyimpan.');
            return;
        }

        saveBtn.disabled = true;
        const original = saveBtn.textContent;
        saveBtn.textContent = 'Menyimpan…';

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ assignments: placement }),
        })
        .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
        .then(res => {
            saveBtn.textContent = original;
            if (!res.ok || !res.body.success) {
                saveBtn.disabled = false;
                alert(res.body.message || 'Gagal menyimpan penempatan.');
                return;
            }
            markDirty(false);
            spinText.textContent = '✅ Tersimpan';
        })
        .catch(() => {
            saveBtn.textContent = original;
            saveBtn.disabled = false;
            alert('Terjadi kesalahan jaringan saat menyimpan.');
        });
    });

    // Undian acak (spin). Server yang mengacak & menyimpan; hasilnya kita
    // gunakan untuk memperbarui state + render kolom.
    if (spinBtn) {
        spinBtn.addEventListener('click', function () {
            if (spinning) return;
            if (dirty && !confirm('Ada perubahan manual belum disimpan. Acak ulang akan menimpanya. Lanjutkan?')) return;
            spinning = true;
            spinBtn.disabled = true;
            spinBtn.classList.add('opacity-60');

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
            .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
            .then(res => {
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

                    // Terapkan hasil undian (assignments = { label: [namaTim,...] })
                    // ke state placement berdasarkan pencocokan nama.
                    const assignments = res.body.assignments || {};
                    const nameToLabel = {};
                    Object.keys(assignments).forEach(label => {
                        (assignments[label] || []).forEach(name => { nameToLabel[name] = label; });
                    });
                    teams.forEach(t => {
                        placement[t.id] = nameToLabel[t.name] || '';
                    });

                    render();
                    markDirty(false); // undian sudah tersimpan di server
                    spinText.textContent = '✅ Undian Selesai!';
                }, 1800);
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
    }

    // Peringatkan bila meninggalkan halaman dengan perubahan belum disimpan.
    window.addEventListener('beforeunload', function (e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // Render awal dari penempatan tersimpan.
    render();
})();
</script>
@endpush
