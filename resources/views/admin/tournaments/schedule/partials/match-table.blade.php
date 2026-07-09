@props(['matches' => [], 'tabs' => [], 'selectedTab' => 'all', 'emptyMessage' => 'Belum ada jadwal yang tersedia.', 'showActions' => true, 'tournament' => null])

<div class="bg-slate-900 rounded-[36px] border border-slate-800 p-4 sm:p-6">
    @if(count($tabs))
        <div class="flex flex-wrap gap-2 mb-6">
            @foreach($tabs as $tab)
                <a href="{{ request()->fullUrlWithQuery(['tab' => $tab['key']]) }}" class="inline-flex items-center justify-center rounded-full border px-4 py-2 text-sm font-semibold transition {{ $selectedTab === $tab['key'] ? 'bg-slate-800 border-slate-700 text-white' : 'bg-slate-950/70 border-slate-800 text-slate-400 hover:bg-slate-900' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    @endif

    <div class="overflow-x-auto">
        <div class="min-w-[1000px]">
            <div class="grid grid-cols-[2fr_2fr_1fr_2fr_1fr_1fr_1fr] gap-4 text-slate-400 text-[11px] uppercase tracking-[0.2em] font-semibold px-4 py-3 border-b border-slate-800">
                <div>BABAK / GRUP</div>
                <div>TIM RUMAH</div>
                <div>SKOR</div>
                <div>TIM TAMU</div>
                <div>WAKTU & TANGGAL</div>
                <div>STATUS</div>
                <div>TINDAKAN</div>
            </div>

            @forelse($matches as $match)
                @php
                    $isTie = ! empty($match['is_tie']) && ! empty($match['legs']) && count($match['legs']) === 2;
                @endphp
                <div class="grid grid-cols-[2fr_2fr_1fr_2fr_1fr_1fr_1fr] gap-4 items-center border-b border-slate-800 last:border-b-0 px-4 py-4">
                    <div class="text-slate-200 font-semibold">
                        {{ $match['round'] ?? $match['group'] ?? 'N/A' }}
                        @if($isTie)
                            <span class="block text-[10px] font-semibold uppercase tracking-[0.2em] text-sky-300 mt-1">Home & Away</span>
                        @endif
                    </div>
                    @php
                        $leftRaw = $match['left'] ?? 'TBD';
                        $leftIsPlaceholder = preg_match('/(Winner|Loser|Runner[- ]?up|^[A-Z]\d|Bye)/i', (string)$leftRaw);
                        $leftDisplay = isset($match['home_team_id']) ? $leftRaw : ($leftIsPlaceholder ? 'Menunggu...' : $leftRaw);
                    @endphp
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center justify-center rounded-full bg-slate-800 w-9 h-9 text-xs font-semibold text-slate-300">{{ strtoupper(substr($leftDisplay, 0, 2)) }}</span>
                        <div class="flex flex-col">
                            <span class="text-slate-300 font-semibold">{{ $leftDisplay }}</span>
                            @if(!empty($match['left_abbr']) && isset($match['home_team_id']))
                                <span class="text-slate-500 text-xs">{{ $match['left_abbr'] }}</span>
                            @endif
                        </div>
                    </div>
                    @if($isTie)
                        @php
                            // Skor tiap leg ditampilkan dalam orientasi row
                            // (kiri = home Leg 1 = away Leg 2).
                            $legOneRow = $match['legs'][0];
                            $legTwoRow = $match['legs'][1];
                        @endphp
                        <div class="text-center space-y-1">
                            <div class="text-xs text-slate-400">L1
                                <span class="text-slate-100 font-bold text-base ml-1">{{ $legOneRow['score_left'] ?? '-' }} - {{ $legOneRow['score_right'] ?? '-' }}</span>
                            </div>
                            <div class="text-xs text-slate-400">L2
                                <span class="text-slate-100 font-bold text-base ml-1">{{ $legTwoRow['score_right'] ?? '-' }} - {{ $legTwoRow['score_left'] ?? '-' }}</span>
                                @if(isset($legTwoRow['away_penalty_score']))
                                    <span class="text-[10px] font-semibold text-sky-300">(p {{ $legTwoRow['away_penalty_score'] }}-{{ $legTwoRow['home_penalty_score'] }})</span>
                                @endif
                            </div>
                            @if(isset($match['tie_agg_left']))
                                <div class="text-[10px] font-semibold uppercase tracking-[0.15em] text-sky-300">Agg {{ $match['tie_agg_left'] }} - {{ $match['tie_agg_right'] }}</div>
                            @endif
                        </div>
                    @else
                        <div class="text-center">
                            <div class="text-slate-100 font-bold text-lg">{{ isset($match['score_left']) ? $match['score_left'] : '-' }}@if(isset($match['home_penalty_score'])) <span class="text-xs font-semibold text-sky-300">({{ $match['home_penalty_score'] }})</span>@endif</div>
                            <div class="text-slate-500 text-xs">-</div>
                            <div class="text-slate-100 font-bold text-lg">{{ isset($match['score_right']) ? $match['score_right'] : '-' }}@if(isset($match['away_penalty_score'])) <span class="text-xs font-semibold text-sky-300">({{ $match['away_penalty_score'] }})</span>@endif</div>
                        </div>
                    @endif
                    @php
                        $rightRaw = $match['right'] ?? 'TBD';
                        $rightIsPlaceholder = preg_match('/(Winner|Loser|Runner[- ]?up|^[A-Z]\d|Bye)/i', (string)$rightRaw);
                        $rightDisplay = isset($match['away_team_id']) ? $rightRaw : ($rightIsPlaceholder ? 'Menunggu...' : $rightRaw);
                    @endphp
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center justify-center rounded-full bg-slate-800 w-9 h-9 text-xs font-semibold text-slate-300">{{ strtoupper(substr($rightDisplay, 0, 2)) }}</span>
                        <div class="flex flex-col">
                            <span class="text-slate-300 font-semibold">{{ $rightDisplay }}</span>
                            @if(!empty($match['right_abbr']) && isset($match['away_team_id']))
                                <span class="text-slate-500 text-xs">{{ $match['right_abbr'] }}</span>
                            @endif
                        </div>
                    </div>
                    @php
                        $matchDateTime = !empty($match['datetime']) ? \Carbon\Carbon::parse($match['datetime']) : \Carbon\Carbon::now();
                        $rawStatus = strtolower(trim($match['status'] ?? 'scheduled'));
                        $statusKey = match ($rawStatus) {
                            'berlangsung', 'live', 'live_match' => 'live_match',
                            'selesai', 'full_time', 'fulltime', 'finished', 'ft' => 'full_time',
                            'penalty_shootout' => 'penalty_shootout',
                            default => 'scheduled',
                        };

                        $statusLabel = match ($statusKey) {
                            'live_match' => 'Live Match',
                            'full_time' => 'Full Time',
                            'penalty_shootout' => 'Adu Penalti',
                            default => 'Scheduled',
                        };

                        $statusClass = match ($statusKey) {
                            'live_match' => 'bg-rose-500/15 text-rose-300',
                            'full_time' => 'bg-emerald-500/15 text-emerald-300',
                            'penalty_shootout' => 'bg-sky-500/15 text-sky-300',
                            default => 'bg-amber-500/15 text-amber-200',
                        };

                        $matchReady = isset($match['home_team_id']) && isset($match['away_team_id']);
                        // Leg 2 terkunci sampai Leg 1 Full Time
                        $leg2Locked = ($match['leg'] ?? null) === 2 && empty($match['leg1_completed']);
                        $matchLocked = ! $matchReady || $statusKey === 'full_time' || $leg2Locked;
                        // Saat adu penalti, edit manual dikunci tapi logger tetap aktif
                        $editLocked = $matchLocked || $statusKey === 'penalty_shootout';
                        $matchDisabled = $editLocked ? 'disabled' : '';

                        // N6 — logger butuh jadwal terisi SEBELUM laga dimulai. Cukup
                        // cek leg yang akan dibuka logger (logger_match_id) — menuntut
                        // jadwal KEDUA leg membuat deadlock: jadwal Leg 2 terkunci
                        // sampai Leg 1 selesai, sehingga Leg 1 tak pernah bisa dibuka
                        // via logger. Leg yang sudah berjalan (live/penalti, mis. Leg 2
                        // yang dinaikkan otomatis saat Leg 1 ditutup) selalu bisa
                        // dibuka kembali walau jadwalnya belum diisi.
                        $loggerEntry = $isTie
                            ? (collect($match['legs'])->firstWhere('id', $match['logger_match_id'] ?? null) ?? $match['legs'][0])
                            : $match;
                        $scheduleMissing = ($loggerEntry['status'] ?? 'scheduled') === 'scheduled'
                            && empty($loggerEntry['datetime']);
                        // Jadwal masih bisa diatur selama belum Full Time / penalti.
                        $scheduleLocked = ! $matchReady || in_array($statusKey, ['full_time', 'penalty_shootout'], true) || $leg2Locked;
                        // Live Logger butuh: tidak terkunci DAN jadwal sudah terisi.
                        $loggerDisabled = $matchLocked || $scheduleMissing;
                    @endphp
                    @if($isTie)
                        <div class="text-slate-300 text-sm leading-tight space-y-2">
                            @foreach($match['legs'] as $legIndex => $legRow)
                                @php
                                    $legDateTime = !empty($legRow['datetime']) ? \Carbon\Carbon::parse($legRow['datetime']) : null;
                                @endphp
                                <div>
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-500">Leg {{ $legIndex + 1 }}</span>
                                    @if($legDateTime)
                                        <div>{{ $legDateTime->format('H:i') }}</div>
                                        <div class="text-slate-500 text-xs">{{ $legDateTime->translatedFormat('D, j M Y') }}</div>
                                    @else
                                        <div class="text-slate-500 text-xs">Belum dijadwalkan</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-slate-300 text-sm leading-tight">
                            <div>{{ $matchDateTime->format('H:i') }}</div>
                            <div class="text-slate-500 text-xs">{{ $matchDateTime->translatedFormat('l, j F Y') }}</div>
                        </div>
                    @endif
                    <div>
                        <div class="flex flex-col gap-2">
                            <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                            @unless($matchReady)
                                <span class="inline-flex rounded-full bg-amber-500/10 px-3 py-1 text-[11px] font-semibold text-amber-200">Menunggu...</span>
                            @endunless
                            @if($leg2Locked && $matchReady)
                                <span class="inline-flex rounded-full bg-sky-500/10 px-3 py-1 text-[11px] font-semibold text-sky-200">Menunggu Leg 1</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        {{-- N5 — Edit khusus Skor --}}
                        <button type="button" data-match-edit-toggle="score-{{ $match['id'] }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-100 hover:border-slate-600 hover:bg-slate-700 {{ $editLocked ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $editLocked ? 'disabled' : '' }}>Edit Skor</button>
                        {{-- N6 — Jadwal khusus tanggal/waktu --}}
                        <button type="button" data-match-edit-toggle="schedule-{{ $match['id'] }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-100 hover:border-slate-600 hover:bg-slate-700 {{ $scheduleLocked ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $scheduleLocked ? 'disabled' : '' }}>Jadwal</button>
                        {{-- N6 — Live Logger nonaktif sampai jadwal diisi --}}
                        <form method="POST" action="{{ route('tournaments.matches.liveLogger', ['tournament' => $tournament, 'match' => $match['logger_match_id'] ?? $match['id']]) }}" class="inline-block">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 {{ $loggerDisabled ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $loggerDisabled ? 'disabled' : '' }}
                                @if($scheduleMissing && ! $matchLocked) title="Isi jadwal (tanggal & waktu) dulu lewat tombol Jadwal." @endif>Live Match Event Logger</button>
                        </form>
                    </div>
                </div>
                @php $editEntries = $isTie ? $match['legs'] : [$match]; @endphp

                {{-- N5 — Panel EDIT SKOR (skor saja) --}}
                <div id="score-{{ $match['id'] }}" class="hidden border-b border-slate-800 px-4 pb-4">
                    <div class="rounded-[28px] border border-slate-800 bg-slate-950 p-4 space-y-4">
                        @foreach($editEntries as $entryIndex => $entry)
                            @php
                                $entryStatusKey = match (strtolower(trim($entry['status'] ?? 'scheduled'))) {
                                    'berlangsung', 'live', 'live_match' => 'live_match',
                                    'selesai', 'full_time', 'fulltime', 'finished', 'ft' => 'full_time',
                                    'penalty_shootout' => 'penalty_shootout',
                                    default => 'scheduled',
                                };
                                $scoreLocked = ! $matchReady
                                    || in_array($entryStatusKey, ['full_time', 'penalty_shootout'], true)
                                    || ($isTie && $entryIndex === 1 && (($match['legs'][0]['status'] ?? '') !== 'full_time'));
                                $scoreDisabled = $scoreLocked ? 'disabled' : '';
                            @endphp
                            <form method="POST" action="{{ route('tournaments.matches.score', ['tournament' => $tournament, 'match' => $entry['id']]) }}" class="grid gap-4 lg:grid-cols-2 {{ $entryIndex > 0 ? 'border-t border-slate-800 pt-4' : '' }}">
                                @csrf
                                @method('PATCH')
                                @if($isTie)
                                    <div class="lg:col-span-2 flex items-center gap-2">
                                        <span class="inline-flex rounded-full bg-sky-500/10 px-3 py-1 text-[11px] font-semibold text-sky-200">Leg {{ $entryIndex + 1 }}</span>
                                        <span class="text-sm text-slate-300">{{ $entry['left'] ?? 'TBD' }} vs {{ $entry['right'] ?? 'TBD' }}</span>
                                        @if($isTie && $entryIndex === 1 && (($match['legs'][0]['status'] ?? '') !== 'full_time'))
                                            <span class="text-xs text-slate-500">— menunggu Leg 1 selesai</span>
                                        @endif
                                    </div>
                                @endif
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2" for="score_home_{{ $entry['id'] }}">Skor Home</label>
                                    <input id="score_home_{{ $entry['id'] }}" name="home_score" type="number" min="0" value="{{ isset($entry['score_left']) ? $entry['score_left'] : '' }}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" required {{ $scoreDisabled }} />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2" for="score_away_{{ $entry['id'] }}">Skor Away</label>
                                    <input id="score_away_{{ $entry['id'] }}" name="away_score" type="number" min="0" value="{{ isset($entry['score_right']) ? $entry['score_right'] : '' }}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" required {{ $scoreDisabled }} />
                                </div>
                                <div class="lg:col-span-2 flex flex-col gap-3">
                                    <div class="text-slate-400 text-sm">
                                        Mengisi skor akan menutup laga sebagai <strong>Full Time</strong>. Untuk hasil seri di babak gugur, gunakan adu penalti via Live Match Logger.
                                    </div>
                                    <div class="flex flex-wrap gap-2 justify-end">
                                        <button type="button" data-match-edit-toggle="score-{{ $match['id'] }}" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-slate-600 hover:bg-slate-700">Tutup</button>
                                        <button type="submit" class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" {{ $scoreDisabled }}>Simpan Skor{{ $isTie ? ' Leg ' . ($entryIndex + 1) : '' }}</button>
                                    </div>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>

                {{-- N6 — Panel JADWAL (tanggal/waktu/status saja) --}}
                <div id="schedule-{{ $match['id'] }}" class="hidden border-b border-slate-800 px-4 pb-4">
                    <div class="rounded-[28px] border border-slate-800 bg-slate-950 p-4 space-y-4">
                        @foreach($editEntries as $entryIndex => $entry)
                            @php
                                $entryDateTime = !empty($entry['datetime']) ? \Carbon\Carbon::parse($entry['datetime']) : \Carbon\Carbon::now();
                                $entryStatusKey = match (strtolower(trim($entry['status'] ?? 'scheduled'))) {
                                    'berlangsung', 'live', 'live_match' => 'live_match',
                                    'selesai', 'full_time', 'fulltime', 'finished', 'ft' => 'full_time',
                                    'penalty_shootout' => 'penalty_shootout',
                                    default => 'scheduled',
                                };
                                $schedEntryLocked = ! $matchReady
                                    || in_array($entryStatusKey, ['full_time', 'penalty_shootout'], true);
                                $schedEntryDisabled = $schedEntryLocked ? 'disabled' : '';
                                // Tanggal/waktu Leg 2 boleh diatur kapan pun (agar logger
                                // Leg 1 tidak deadlock); hanya status Live Match yang
                                // menunggu Leg 1 selesai — guard yang sama ditegakkan
                                // server-side di updateSchedule.
                                $legTwoWaitsLegOne = $isTie && $entryIndex === 1
                                    && (($match['legs'][0]['status'] ?? '') !== 'full_time');
                            @endphp
                            <form method="POST" action="{{ route('tournaments.matches.schedule', ['tournament' => $tournament, 'match' => $entry['id']]) }}" class="grid gap-4 lg:grid-cols-3 {{ $entryIndex > 0 ? 'border-t border-slate-800 pt-4' : '' }}">
                                @csrf
                                @method('PATCH')
                                @if($isTie)
                                    <div class="lg:col-span-3 flex items-center gap-2">
                                        <span class="inline-flex rounded-full bg-sky-500/10 px-3 py-1 text-[11px] font-semibold text-sky-200">Leg {{ $entryIndex + 1 }}</span>
                                        <span class="text-sm text-slate-300">{{ $entry['left'] ?? 'TBD' }} vs {{ $entry['right'] ?? 'TBD' }}</span>
                                        @if($legTwoWaitsLegOne)
                                            <span class="text-xs text-slate-500">— status Live menunggu Leg 1 selesai</span>
                                        @endif
                                    </div>
                                @endif
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2" for="match_date_{{ $entry['id'] }}">Tanggal Pertandingan</label>
                                    <input id="match_date_{{ $entry['id'] }}" name="match_date" type="date" value="{{ $entryDateTime->format('Y-m-d') }}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" required {{ $schedEntryDisabled }} />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2" for="match_time_{{ $entry['id'] }}">Waktu Pertandingan</label>
                                    <input id="match_time_{{ $entry['id'] }}" name="match_time" type="time" value="{{ $entryDateTime->format('H:i') }}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" required {{ $schedEntryDisabled }} />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2" for="match_status_{{ $entry['id'] }}">Status Laga</label>
                                    <select id="match_status_{{ $entry['id'] }}" name="match_status" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" required {{ $schedEntryDisabled }}>
                                        <option value="scheduled" {{ $entryStatusKey === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                                        <option value="live_match" {{ $entryStatusKey === 'live_match' ? 'selected' : '' }} {{ $legTwoWaitsLegOne ? 'disabled' : '' }}>Live Match</option>
                                    </select>
                                </div>
                                <div class="lg:col-span-3 flex flex-col gap-3">
                                    <div class="text-slate-400 text-sm">
                                        Atur tanggal, waktu, dan status laga. Skor diisi lewat tombol <strong>Edit Skor</strong> atau Live Match Logger.
                                    </div>
                                    <div class="flex flex-wrap gap-2 justify-end">
                                        <button type="button" data-match-edit-toggle="schedule-{{ $match['id'] }}" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-slate-600 hover:bg-slate-700">Tutup</button>
                                        <button type="submit" class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" {{ $schedEntryDisabled }}>Simpan Jadwal{{ $isTie ? ' Leg ' . ($entryIndex + 1) : '' }}</button>
                                    </div>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>
                <script type="application/json" id="match-data-{{ $match['id'] }}">@json($match)</script>
            @empty
                <div class="p-12 text-center text-slate-400">
                    {{ $emptyMessage }}
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- N5/N6 — Handler toggle panel Edit Skor & Jadwal. Memakai event delegation
     agar bekerja di semua halaman yang menyertakan partial ini (league,
     league-playoff, bracket, manage). Dibinding sekali via flag global; di
     halaman 'manage' yang sudah punya handler sendiri, flag ini mencegah
     double-toggle. --}}
<script>
(function () {
    if (window.__matchTableToggleBound) return;
    window.__matchTableToggleBound = true;
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-match-edit-toggle]');
        if (!btn) return;
        const target = document.getElementById(btn.getAttribute('data-match-edit-toggle'));
        if (target) target.classList.toggle('hidden');
    });
})();
</script>
