@extends('admin.layouts.tournament')

@section('title', 'Kelola Jadwal & Skor - ' . $tournament->name)

@section('page-label', 'KELOLA JADWAL & SKOR')
@section('page-title', 'Kelola Jadwal & Skor')
@section('page-subtitle')
    Turnamen: <span class="text-indigo-400 font-semibold">{{ $tournament->name }}</span>
@endsection

@section('content')
            <!-- Content -->
            <div class="p-6">
                @if(session('success'))
                    <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-900/10 p-4 text-emerald-200">
                        {{ session('success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="mb-6 p-4 rounded-xl border border-rose-500/30 bg-rose-900/10 text-rose-200">
                        <ul class="list-disc list-inside text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="grid md:grid-cols-4 gap-4 mb-6">
                    <div class="md:col-span-3 bg-slate-900 p-4 rounded-xl border border-slate-800">
                        <form id="filtersForm" method="GET" action="" class="flex flex-wrap gap-3 items-center">
                            <div>
                                <label class="text-xs text-slate-400 block mb-1">Group</label>
                                <select name="group_label" onchange="document.getElementById('filtersForm').submit()"
                                    class="bg-slate-950/60 border border-slate-800 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Semua Grup</option>
                                    @foreach($filterOptions['group_label'] as $g)
                                        <option value="{{ $g }}" {{ $filters['group_label'] === $g ? 'selected' : '' }}>
                                            {{ $g }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-xs text-slate-400 block mb-1">Babak / Round</label>
                                <select name="round_name" onchange="document.getElementById('filtersForm').submit()"
                                    class="bg-slate-950/60 border border-slate-800 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Semua Babak</option>
                                    @foreach($filterOptions['round_name'] as $r)
                                        <option value="{{ $r }}" {{ $filters['round_name'] === $r ? 'selected' : '' }}>
                                            {{ $r }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-xs text-slate-400 block mb-1">Tipe Tahap</label>
                                <select name="stage_type" onchange="document.getElementById('filtersForm').submit()"
                                    class="bg-slate-950/60 border border-slate-800 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Semua Tahap</option>
                                    @foreach($filterOptions['stage_type'] as $s)
                                        <option value="{{ $s }}" {{ $filters['stage_type'] === $s ? 'selected' : '' }}>
                                            {{ $s }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-xs text-slate-400 block mb-1">Playoff</label>
                                <select name="playoff_type" onchange="document.getElementById('filtersForm').submit()"
                                    class="bg-slate-950/60 border border-slate-800 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Semua Tipe</option>
                                    @foreach($filterOptions['playoff_type'] as $p)
                                        <option value="{{ $p }}" {{ $filters['playoff_type'] === $p ? 'selected' : '' }}>
                                            {{ $p }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="ml-auto flex items-center gap-2">
                                <a href="{{ request()->fullUrlWithQuery(['tab' => 'all']) }}"
                                    class="px-3 py-2 bg-slate-800 rounded-lg text-sm">Tampilkan Semua</a>
                                <a href="{{ request()->url() }}"
                                    class="px-3 py-2 bg-slate-800 rounded-lg text-sm">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="bg-slate-900 p-4 rounded-xl border border-slate-800">
                        <h3 class="text-sm font-semibold mb-3">Ringkasan</h3>
                        <div class="text-xs text-slate-400">
                            <div>Jumlah Pertandingan: <strong class="text-slate-200">{{ count($filtered) }}</strong>
                            </div>
                            <div>Groups: <strong
                                    class="text-slate-200">{{ count($filterOptions['group_label']) }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    @php $matches = $filtered; @endphp
                    @include('admin.tournaments.schedule.partials.match-table', ['matches' => $matches, 'tabs' => $tabs, 'selectedTab' => $filters['tab'] ?? 'all', 'tournament' => $tournament])
                </div>
            </div>
@endsection

@section('after-main')
    <div id="liveMatchEventLoggerModal"
        class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/80 p-4">
        <div
            class="mx-auto flex max-h-[calc(100vh-2rem)] w-full max-w-6xl flex-col overflow-hidden rounded-[32px] border border-slate-800 bg-slate-900 shadow-2xl">
            <div
                class="flex flex-shrink-0 flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-800 px-6 py-5">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-rose-300 font-semibold">Live Match Event Logger
                    </p>
                    <h2 id="loggerMatchTitle" class="mt-2 text-2xl font-bold text-white">Pertandingan Live</h2>
                    <p id="loggerMatchInfo" class="mt-1 text-sm text-slate-400">Pilih pemain, lalu tekan event. Tanpa
                        input manual tambahan.</p>
                    <div id="loggerTieContext"
                        class="mt-3 hidden space-y-1 rounded-2xl border border-sky-500/20 bg-sky-500/10 px-4 py-3 text-sm text-sky-200">
                    </div>
                    <div id="loggerFlash"
                        class="mt-3 hidden rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    </div>
                </div>
                <button id="closeLiveMatchEventLoggerModal" type="button"
                    class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-900">Tutup</button>
            </div>
            <div id="loggerLegTabs" class="hidden flex-shrink-0 items-center gap-2 border-b border-slate-800 px-6 pt-4 pb-4">
                <button type="button" data-leg-tab="0"
                    class="logger-leg-tab rounded-2xl px-5 py-2.5 text-sm font-semibold transition">Leg 1</button>
                <button type="button" data-leg-tab="1"
                    class="logger-leg-tab rounded-2xl px-5 py-2.5 text-sm font-semibold transition">Leg 2</button>
            </div>
            <div class="flex-1 space-y-6 overflow-y-auto p-6">
                <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr_1.4fr]">
                    <div class="rounded-[28px] border border-slate-800 bg-slate-950 p-4">
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400 font-semibold">Home Lineup</p>
                        <div id="homeRosterPanel" class="mt-4 max-h-[min(28rem,45vh)] space-y-3 overflow-y-auto pr-1"></div>
                    </div>
                    <div class="rounded-[28px] border border-slate-800 bg-slate-950 p-4 flex flex-col justify-between">
                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">

                            <!-- Title -->
                            <p class="text-center text-xs uppercase tracking-[0.25em] text-slate-400 font-semibold">
                                Scoreboard
                            </p>

                            <!-- Score -->
                            <div class="mt-5 flex items-center justify-between gap-4 text-white">

                                <div class="flex-1 text-center">
                                    <div id="loggerHomeTeam" class="text-sm text-slate-400"></div>
                                    <div id="loggerHomeCards" class="mt-1 hidden text-xs font-semibold text-slate-300"></div>
                                    <div id="loggerHomeScore" class="text-5xl font-bold"></div>
                                    <div id="loggerHomePenalty" class="hidden mt-1 text-base font-semibold text-sky-300"></div>
                                </div>

                                <div class="text-center">
                                    <span class="text-slate-500 font-semibold uppercase">VS</span>
                                    <div id="loggerAggregateScore" class="hidden mt-2">
                                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Agregat</p>
                                        <p id="loggerAggregateText" class="text-sm font-bold text-sky-300 mt-0.5"></p>
                                    </div>
                                </div>

                                <div class="flex-1 text-center">
                                    <div id="loggerAwayTeam" class="text-sm text-slate-400"></div>
                                    <div id="loggerAwayCards" class="mt-1 hidden text-xs font-semibold text-slate-300"></div>
                                    <div id="loggerAwayScore" class="text-5xl font-bold"></div>
                                    <div id="loggerAwayPenalty" class="hidden mt-1 text-base font-semibold text-sky-300"></div>
                                </div>

                            </div>

                            <!-- Status -->
                            <div class="mt-5 text-center">
                                <div id="loggerMatchStatus" class="text-sm font-semibold text-slate-300">
                                </div>
                            </div>

                            <!-- Match Time -->
                            <div id="loggerMatchDateTime" class="mt-2 text-center text-xs text-slate-500 empty:hidden">
                            </div>
                            <form id="endMatchForm" method="POST" action="" class="mt-4">
                                @csrf
                                @method('PATCH')

                                <button id="endMatchButton" type="submit"
                                    class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500">
                                    End Match
                                </button>
                            </form>
                        </div>

                        <div class="mt-6 rounded-[24px] border border-slate-800 bg-slate-900 p-4">
                            <div id="loggerReadonlyNote"
                                class="mt-4 rounded-2xl border border-rose-500/20 bg-rose-500/10 p-3 text-sm text-rose-200 hidden">
                                Pertandingan Full Time. Event logger dalam mode read-only.</div>
                        </div>
                    </div>
                    <div class="rounded-[28px] border border-slate-800 bg-slate-950 p-4">
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400 font-semibold">Away Lineup</p>
                        <div id="awayRosterPanel" class="mt-4 max-h-[min(28rem,45vh)] space-y-3 overflow-y-auto pr-1"></div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    <div class="rounded-[28px] border border-slate-800 bg-slate-950 p-4">
                        <div class="mb-5 flex items-center gap-3">
    <div class="h-px flex-1 bg-slate-800"></div>

    <h3 class="text-xs uppercase tracking-[0.25em] text-slate-400 font-semibold">
        Match Timeline
    </h3>

    <div class="h-px flex-1 bg-slate-800"></div>
</div>
                        <div id="loggerEventList" class="mt-4 max-h-[40vh] space-y-3 overflow-y-auto pr-1 text-sm text-slate-300"></div>
                    </div>
                    <div class="rounded-[28px] border border-slate-800 bg-slate-950 p-4" id="loggerEventFormContainer">
                        <h3 class="text-sm font-semibold text-slate-200">Aksi Cepat</h3>
                        <div
                            class="mt-4 rounded-[24px] border border-slate-800 bg-slate-900 p-4 text-sm text-slate-300">
                            <p class="font-medium text-slate-200">Tekan tombol event pada pemain yang sesuai.</p>
                            <p class="mt-2 text-slate-500">Semua event langsung tersimpan tanpa input manual menit atau
                                deskripsi.</p>
                        </div>
                        <form id="liveMatchEventForm" method="POST" action="" class="hidden">
                            @csrf
                            <input id="event_type" name="event_type" type="hidden" />
                            <input id="team_side" name="team_side" type="hidden" />
                            <input id="player_name" name="player_name" type="hidden" />
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // N5/N6 — toggle panel Edit Skor/Jadwal kini ditangani secara terpusat
            // (event delegation) di dalam partial match-table agar konsisten di
            // semua halaman jadwal. Handler per-tombol lama dihapus untuk hindari
            // double-toggle.

            const modal = document.getElementById('liveMatchEventLoggerModal');
            const closeModal = document.getElementById('closeLiveMatchEventLoggerModal');
            const eventList = document.getElementById('loggerEventList');
            const homeTeamLabel = document.getElementById('loggerHomeTeam');
            const awayTeamLabel = document.getElementById('loggerAwayTeam');
            const homeScoreLabel = document.getElementById('loggerHomeScore');
            const awayScoreLabel = document.getElementById('loggerAwayScore');
            const homePenaltyLabel = document.getElementById('loggerHomePenalty');
            const awayPenaltyLabel = document.getElementById('loggerAwayPenalty');
            const homeCardsLabel = document.getElementById('loggerHomeCards');
            const awayCardsLabel = document.getElementById('loggerAwayCards');
            const tieContextPanel = document.getElementById('loggerTieContext');
            const aggregateScoreBox = document.getElementById('loggerAggregateScore');
            const aggregateScoreText = document.getElementById('loggerAggregateText');
            const statusLabel = document.getElementById('loggerMatchStatus');
            const datetimeLabel = document.getElementById('loggerMatchDateTime');
            const titleLabel = document.getElementById('loggerMatchTitle');
            const eventForm = document.getElementById('liveMatchEventForm');
            const endMatchForm = document.getElementById('endMatchForm');
            const endMatchButton = document.getElementById('endMatchButton');
            const readonlyNote = document.getElementById('loggerReadonlyNote');
            const eventFormContainer = document.getElementById('loggerEventFormContainer');
            const homeRosterPanel = document.getElementById('homeRosterPanel');
            const awayRosterPanel = document.getElementById('awayRosterPanel');
            const liveEventType = document.getElementById('event_type');
            const liveTeamSide = document.getElementById('team_side');
            const livePlayerName = document.getElementById('player_name');

            const eventLabels = {
                goal: 'Goal',
                own_goal: 'Own Goal',
                assist: 'Assist',
                yellow_card: 'Yellow Card',
                red_card: 'Red Card',
                penalty_goal: 'Penalti Gol',
                penalty_miss: 'Penalti Gagal',
            };

            function createEventButton(type, side, playerName, disabled, playerId) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'rounded-lg px-2.5 py-1 text-[11px] font-medium text-white transition hover:opacity-90';
                button.textContent = eventLabels[type] || type.replace('_', ' ');

                if (type === 'goal' || type === 'penalty_goal') {
    button.classList.add('bg-emerald-600');
}
else if (type === 'assist') {
    button.classList.add('bg-sky-600');
}
else if (type === 'own_goal') {
    button.classList.add('bg-violet-600');
}
else if (type === 'yellow_card') {
    button.classList.add('bg-yellow-400', 'text-black');
}
else if (type === 'red_card') {
    button.classList.add('bg-red-600');
}
else if (type === 'penalty_miss') {
    button.classList.add('bg-rose-700');
} else {
                    button.classList.add('bg-slate-700', 'hover:bg-slate-600');
                }

                if (disabled) {
                    button.disabled = true;
                    button.classList.add('opacity-50', 'cursor-not-allowed');
                }

                button.addEventListener('click', function () {
                    submitLiveEvent(type, side, playerName, playerId);
                });

                return button;
            }

            // ---- Kirim event tanpa reload halaman ----
            const loggerFlash = document.getElementById('loggerFlash');
            let eventSubmitPending = false;

            function showLoggerFlash(message) {
                if (! message) {
                    loggerFlash.classList.add('hidden');
                    loggerFlash.textContent = '';
                    return;
                }

                loggerFlash.textContent = message;
                loggerFlash.classList.remove('hidden');
            }

            async function submitLiveEvent(type, side, playerName, playerId) {
                if (eventSubmitPending) {
                    return;
                }

                eventSubmitPending = true;
                showLoggerFlash('');

                try {
                    const bodyParams = {
                        event_type: type,
                        team_side: side,
                        player_name: playerName || '',
                    };
                    // R19 — sertakan player_id bila roster punya pemain asli.
                    if (playerId !== null && playerId !== undefined && playerId !== '') {
                        bodyParams.player_id = playerId;
                    }

                    const response = await fetch(eventForm.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': eventForm.querySelector('input[name="_token"]').value,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        },
                        body: new URLSearchParams(bodyParams).toString(),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (! response.ok) {
                        showLoggerFlash(data.message || 'Gagal menyimpan event. Coba lagi.');
                        return;
                    }

                    applyUpdatedLeg(data.match);
                } catch (error) {
                    showLoggerFlash('Gagal menyimpan event. Periksa koneksi lalu coba lagi.');
                } finally {
                    eventSubmitPending = false;
                }
            }

            function applyUpdatedLeg(freshLeg) {
                if (! freshLeg) {
                    return;
                }

                if (activeTieRow && Array.isArray(activeTieRow.legs)) {
                    activeTieRow.legs = activeTieRow.legs.map(leg =>
                        String(leg.id) === String(freshLeg.id) ? freshLeg : leg
                    );
                } else {
                    activeTieRow = freshLeg;
                }

                renderLoggerView(freshLeg);
            }

            // Rekap penalti per pemain: { 'NamaPemain': 'goal'|'miss' }
            function buildPenaltySummary(events) {
                const summary = { home: {}, away: {} };
                (events || []).forEach(event => {
                    if (event.event_type !== 'penalty_goal' && event.event_type !== 'penalty_miss') return;
                    const side = event.team_side === 'home' ? 'home' : 'away';
                    const key = event.player_name || '';
                    if (!summary[side][key]) {
                        summary[side][key] = event.event_type === 'penalty_goal' ? 'goal' : 'miss';
                    }
                });
                return summary;
            }

            // Rekap kartu per tim & per pemain dari timeline event
            function buildCardSummary(events) {
                const summary = {
                    home: { yellow: 0, red: 0, players: {} },
                    away: { yellow: 0, red: 0, players: {} },
                };

                (events || []).forEach(event => {
                    if (event.event_type !== 'yellow_card' && event.event_type !== 'red_card') {
                        return;
                    }

                    const bucket = summary[event.team_side === 'home' ? 'home' : 'away'];
                    const key = event.player_name || '';
                    bucket.players[key] = bucket.players[key] || { yellow: 0, red: 0 };

                    if (event.event_type === 'yellow_card') {
                        bucket.yellow++;
                        bucket.players[key].yellow++;
                    } else {
                        bucket.red++;
                        bucket.players[key].red++;
                    }
                });

                return summary;
            }

            function renderTeamCards(element, bucket) {
                const parts = [];
                if (bucket.yellow > 0) {
                    parts.push(`🟨 ${bucket.yellow}`);
                }
                if (bucket.red > 0) {
                    parts.push(`🟥 ${bucket.red}`);
                }

                element.textContent = parts.join(' · ');
                element.classList.toggle('hidden', parts.length === 0);
            }

            function renderRosterPanel(panel, side, roster, disabled, eventTypes, playerCards, penaltyTaken) {
                panel.innerHTML = '';
                if (!roster || roster.length === 0) {
                    panel.innerHTML = '<p class="text-sm text-slate-500">Roster tidak tersedia.</p>';
                    return;
                }

                const types = eventTypes || ['goal', 'assist', 'own_goal', 'yellow_card', 'red_card'];
                const cards = playerCards || {};
                const penalties = penaltyTaken || {};
                const isShootoutMode = types.includes('penalty_goal');

                roster.forEach(player => {
                    const cardInfo = cards[player.player_name || ''] || { yellow: 0, red: 0 };
                    const sentOff = cardInfo.red > 0;
                    const penaltyResult = penalties[player.player_name || ''] ?? null; // 'goal'|'miss'|null
                    const hasTakenPenalty = penaltyResult !== null;

                    let badges = '';
                    if (cardInfo.yellow > 0) {
                        badges += ` <span title="Kartu kuning">🟨${cardInfo.yellow > 1 ? '×' + cardInfo.yellow : ''}</span>`;
                    }
                    if (sentOff) {
                        badges += ' <span title="Kartu merah">🟥</span>';
                    }

                    // Sub-label baris kedua pada kartu pemain
                    let subLabel = side === 'home' ? 'Home' : 'Away';
                    let subClass = 'text-slate-500';
                    if (sentOff) { subLabel = 'Kartu Merah — nonaktif'; subClass = 'text-rose-300 font-semibold'; }
                    else if (isShootoutMode && hasTakenPenalty) {
                        subLabel = penaltyResult === 'goal' ? 'Sudah tendang — Gol ✓' : 'Sudah tendang — Gagal ✗';
                        subClass = penaltyResult === 'goal' ? 'text-emerald-400 font-semibold' : 'text-rose-400 font-semibold';
                    }

                    const card = document.createElement('div');
                    card.className = 'rounded-xl border bg-slate-950 p-3'
                        + (sentOff ? ' opacity-60 border-rose-500/30' : '')
                        + (!sentOff && isShootoutMode && hasTakenPenalty
                            ? (penaltyResult === 'goal' ? ' border-emerald-700/50' : ' border-rose-700/50')
                            : ' border-slate-800');
                    card.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-200">${player.label}${badges}</p>
                                <p class="text-xs ${subClass}">${subLabel}</p>
                            </div>
                        </div>
                    `;

                    const actions = document.createElement('div');
                    actions.className = 'mt-3 flex flex-wrap gap-2';

                    if (isShootoutMode && hasTakenPenalty) {
                        // Ganti tombol dengan badge hasil
                        const badge = document.createElement('span');
                        badge.className = penaltyResult === 'goal'
                            ? 'inline-flex items-center gap-1 rounded-lg px-3 py-1 text-xs font-semibold bg-emerald-600/20 text-emerald-300 border border-emerald-700/50'
                            : 'inline-flex items-center gap-1 rounded-lg px-3 py-1 text-xs font-semibold bg-rose-600/20 text-rose-300 border border-rose-700/50';
                        badge.textContent = penaltyResult === 'goal' ? '✓ Gol' : '✗ Gagal';
                        actions.appendChild(badge);
                    } else {
                        types.forEach(type => {
                            actions.appendChild(createEventButton(type, side, player.player_name, disabled || sentOff, player.player_id));
                        });
                    }

                    card.appendChild(actions);
                    panel.appendChild(card);
                });
            }

            function renderEventList(events) {
    eventList.innerHTML = '';

    if (!events || events.length === 0) {
        eventList.innerHTML = `
            <div class="text-center text-sm text-slate-500 py-8">
                Belum ada event pertandingan
            </div>
        `;
        return;
    }

    events.forEach(event => {

        let icon = '📌';

        switch (event.event_type) {
            case 'goal':
                icon = '⚽';
                break;

            case 'own_goal':
                icon = '🥅';
                break;

            case 'assist':
                icon = '👟';
                break;

            case 'yellow_card':
                icon = '🟨';
                break;

            case 'red_card':
                icon = '🟥';
                break;

            case 'penalty_goal':
                icon = '🎯';
                break;

            case 'penalty_miss':
                icon = '❌';
                break;
        }

        const isHome = event.team_side === 'home';

        const item = document.createElement('div');

        item.className =
            'grid grid-cols-[1fr_40px_1fr] items-center';

        item.innerHTML = isHome
            ? `
                <div class="text-right pr-3">
                    <span class="font-medium text-slate-200">
                        ${icon} ${event.player_name || '-'}
                    </span>
                </div>

                <div class="flex justify-center">
                    <div class="h-2.5 w-2.5 rounded-full bg-slate-500"></div>
                </div>

                <div></div>
            `
            : `
                <div></div>

                <div class="flex justify-center">
                    <div class="h-2.5 w-2.5 rounded-full bg-slate-500"></div>
                </div>

                <div class="pl-3">
                    <span class="font-medium text-slate-200">
                        ${icon} ${event.player_name || '-'}
                    </span>
                </div>
            `;

        eventList.appendChild(item);
    });
}
            function renderLoggerView(matchData) {
                showLoggerFlash('');
                const disabled = matchData.status === 'full_time';
                const isShootout = matchData.status === 'penalty_shootout';

                titleLabel.textContent = `${matchData.left || 'TBD'} vs ${matchData.right || 'TBD'}${matchData.leg ? ` — Leg ${matchData.leg}` : ''}`;
                homeTeamLabel.textContent = matchData.left || 'Home';
                awayTeamLabel.textContent = matchData.right || 'Away';
                homeScoreLabel.textContent = matchData.score_left ?? 0;
                awayScoreLabel.textContent = matchData.score_right ?? 0;

                // Skor adu penalti di bawah skor utama
                const hasPenaltyScore = matchData.home_penalty_score !== null && matchData.home_penalty_score !== undefined;
                if (isShootout || hasPenaltyScore) {
                    homePenaltyLabel.textContent = `Pen: ${matchData.home_penalty_score ?? 0}`;
                    awayPenaltyLabel.textContent = `Pen: ${matchData.away_penalty_score ?? 0}`;
                    homePenaltyLabel.classList.remove('hidden');
                    awayPenaltyLabel.classList.remove('hidden');
                } else {
                    homePenaltyLabel.classList.add('hidden');
                    awayPenaltyLabel.classList.add('hidden');
                }

                // Skor agregat di tengah scoreboard (hanya tampil di Leg 2)
                if (matchData.leg === 2 && (matchData.agg_home !== undefined || matchData.wins_home !== undefined)) {
                    if (matchData.calculation_mode === 'wins') {
                        aggregateScoreText.textContent = `${matchData.wins_home ?? 0} — ${matchData.wins_away ?? 0}`;
                    } else {
                        aggregateScoreText.textContent = `${matchData.agg_home ?? 0} — ${matchData.agg_away ?? 0}`;
                    }
                    aggregateScoreBox.classList.remove('hidden');
                } else {
                    aggregateScoreBox.classList.add('hidden');
                }

                let statusBadgeClass = 'bg-amber-500/15 text-amber-200';
                let statusBadgeText = 'Scheduled';
                if (disabled) {
                    statusBadgeClass = 'bg-emerald-500/15 text-emerald-300';
                    statusBadgeText = 'Full Time';
                } else if (isShootout) {
                    statusBadgeClass = 'bg-sky-500/15 text-sky-300';
                    statusBadgeText = 'Adu Penalti';
                } else if (matchData.status === 'live_match') {
                    statusBadgeClass = 'bg-rose-500/15 text-rose-300';
                    statusBadgeText = 'Live Match';
                }
                statusLabel.innerHTML = `<span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold ${statusBadgeClass}">${statusBadgeText}</span>`;

                // Konteks tie Home & Away (leg 1/leg 2/adu penalti)
                const tieNotes = [];
                if (matchData.leg === 1) {
                    tieNotes.push('Leg 1 dari 2 — Leg 2 dimainkan dengan tuan rumah dibalik.');
                }
                if (matchData.leg === 2) {
                    if (matchData.leg1) {
                        const leg1Score = matchData.leg1.status === 'full_time'
                            ? `${matchData.leg1.home_score ?? 0} - ${matchData.leg1.away_score ?? 0}`
                            : 'belum selesai';
                        tieNotes.push(`Leg 2 — Hasil Leg 1: ${matchData.leg1.home} ${leg1Score} ${matchData.leg1.away}.`);
                    }
                    if (matchData.calculation_mode === 'wins') {
                        tieNotes.push(`Rekap kemenangan: ${matchData.left} ${matchData.wins_home ?? 0} - ${matchData.wins_away ?? 0} ${matchData.right}.`);
                    } else {
                        tieNotes.push(`Agregat saat ini: ${matchData.left} ${matchData.agg_home ?? 0} - ${matchData.agg_away ?? 0} ${matchData.right}.`);
                    }
                }
                if (isShootout) {
                    tieNotes.push('Adu Penalti — tekan tombol "Penalti Gol" / "Penalti Gagal" pada pemain penendang.');
                }
                if (tieNotes.length > 0) {
                    tieContextPanel.innerHTML = tieNotes.map(note => `<p>${note}</p>`).join('');
                    tieContextPanel.classList.remove('hidden');
                } else {
                    tieContextPanel.classList.add('hidden');
                }

                datetimeLabel.textContent = matchData.datetime ? new Date(matchData.datetime).toLocaleString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Waktu belum ditentukan';
                renderEventList(matchData.events || []);

                const cardSummary = buildCardSummary(matchData.events);
                renderTeamCards(homeCardsLabel, cardSummary.home);
                renderTeamCards(awayCardsLabel, cardSummary.away);

                const penaltySummary = buildPenaltySummary(matchData.events);
                const rosterEventTypes = isShootout
                    ? ['penalty_goal', 'penalty_miss']
                    : ['goal', 'assist', 'own_goal', 'yellow_card', 'red_card'];
                renderRosterPanel(homeRosterPanel, 'home', matchData.home_roster, disabled, rosterEventTypes, cardSummary.home.players, penaltySummary.home);
                renderRosterPanel(awayRosterPanel, 'away', matchData.away_roster, disabled, rosterEventTypes, cardSummary.away.players, penaltySummary.away);

                endMatchButton.textContent = isShootout ? 'Akhiri Adu Penalti' : 'End Match';

                eventForm.action = '{{ route('tournaments.matches.events.store', ['tournament' => $tournament, 'match' => 'MATCH_ID_PLACEHOLDER']) }}'.replace('MATCH_ID_PLACEHOLDER', matchData.id);
                endMatchForm.action = '{{ route('tournaments.matches.end', ['tournament' => $tournament, 'match' => 'MATCH_ID_PLACEHOLDER']) }}'.replace('MATCH_ID_PLACEHOLDER', matchData.id);

                if (disabled) {
                    readonlyNote.classList.remove('hidden');
                    eventFormContainer.classList.add('opacity-50');
                    eventFormContainer.querySelectorAll('input, select, button').forEach(el => el.disabled = true);
                    endMatchButton.disabled = true;
                    endMatchButton.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    readonlyNote.classList.add('hidden');
                    eventFormContainer.classList.remove('opacity-50');
                    eventFormContainer.querySelectorAll('input, select, button').forEach(el => el.disabled = false);
                    endMatchButton.disabled = false;
                    endMatchButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            // ---- Tab Leg 1 / Leg 2 untuk tie Home & Away ----
            let activeTieRow = null;
            const legTabBar = document.getElementById('loggerLegTabs');
            const legTabButtons = legTabBar.querySelectorAll('.logger-leg-tab');

            function setActiveLegTab(index) {
                legTabButtons.forEach(button => {
                    const isActive = Number(button.dataset.legTab) === index;
                    button.classList.toggle('bg-indigo-600', isActive);
                    button.classList.toggle('text-white', isActive);
                    button.classList.toggle('bg-slate-950', !isActive);
                    button.classList.toggle('text-slate-400', !isActive);
                });

                renderLoggerView(activeTieRow.legs[index]);
            }

            function openModal(rowData, preferredMatchId) {
                activeTieRow = rowData;

                if (Array.isArray(rowData.legs) && rowData.legs.length === 2) {
                    legTabBar.classList.remove('hidden');
                    legTabBar.classList.add('flex');

                    const legOneDone = rowData.legs[0].status === 'full_time';
                    const legTwoButton = legTabBar.querySelector('[data-leg-tab="1"]');
                    legTwoButton.disabled = !legOneDone;
                    legTwoButton.classList.toggle('opacity-50', !legOneDone);
                    legTwoButton.classList.toggle('cursor-not-allowed', !legOneDone);
                    legTwoButton.title = legOneDone ? '' : 'Selesaikan Leg 1 terlebih dahulu';

                    let index = legOneDone ? 1 : 0;
                    if (preferredMatchId) {
                        rowData.legs.forEach((leg, i) => {
                            if (String(leg.id) === String(preferredMatchId)) {
                                index = i;
                            }
                        });
                    }
                    if (index === 1 && !legOneDone) {
                        index = 0;
                    }

                    setActiveLegTab(index);
                } else {
                    legTabBar.classList.add('hidden');
                    legTabBar.classList.remove('flex');
                    renderLoggerView(rowData);
                }

                modal.classList.remove('hidden');
                document.documentElement.classList.add('overflow-hidden');
                document.body.classList.add('overflow-hidden');
            }

            legTabButtons.forEach(button => {
                button.addEventListener('click', function () {
                    if (this.disabled || !activeTieRow) {
                        return;
                    }
                    setActiveLegTab(Number(this.dataset.legTab));
                });
            });

            function closeModalHandler() {
                modal.classList.add('hidden');
                document.documentElement.classList.remove('overflow-hidden');
                document.body.classList.remove('overflow-hidden');
            }

            closeModal.addEventListener('click', closeModalHandler);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModalHandler();
                }
            });

            const openMatchId = '{{ session('open_live_match', '') }}';
            if (openMatchId) {
                // Row tie dipayungi id Leg 1; cari row yang memuat match id ini
                // baik langsung maupun sebagai salah satu leg.
                const matchScripts = document.querySelectorAll('script[id^="match-data-"]');
                for (const node of matchScripts) {
                    let data;
                    try {
                        data = JSON.parse(node.textContent || '{}');
                    } catch (e) {
                        continue;
                    }

                    const direct = String(data.id) === String(openMatchId);
                    const viaLeg = Array.isArray(data.legs) && data.legs.some(leg => String(leg.id) === String(openMatchId));

                    if (direct || viaLeg) {
                        openModal(data, openMatchId);
                        break;
                    }
                }
            }
        });
    </script>
@endpush