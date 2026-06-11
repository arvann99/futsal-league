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
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 p-4">
        <div
            class="mx-auto w-full max-w-6xl overflow-hidden rounded-[32px] border border-slate-800 bg-slate-900 shadow-2xl">
            <div
                class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-800 px-6 py-5">
                <div>
                    <p class="text-xs uppercase tracking-[0.25em] text-rose-300 font-semibold">Live Match Event Logger
                    </p>
                    <h2 id="loggerMatchTitle" class="mt-2 text-2xl font-bold text-white">Pertandingan Live</h2>
                    <p id="loggerMatchInfo" class="mt-1 text-sm text-slate-400">Pilih pemain, lalu tekan event. Tanpa
                        input manual tambahan.</p>
                </div>
                <button id="closeLiveMatchEventLoggerModal" type="button"
                    class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-900">Tutup</button>
            </div>
            <div class="space-y-6 p-6">
                <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr_1.4fr]">
                    <div class="rounded-[28px] border border-slate-800 bg-slate-950 p-4">
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400 font-semibold">Home Lineup</p>
                        <div id="homeRosterPanel" class="mt-4 space-y-3"></div>
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
                                    <div id="loggerHomeScore" class="text-5xl font-bold"></div>
                                </div>

                                <div class="text-center">
                                    <span class="text-slate-500 font-semibold uppercase">VS</span>
                                </div>

                                <div class="flex-1 text-center">
                                    <div id="loggerAwayTeam" class="text-sm text-slate-400"></div>
                                    <div id="loggerAwayScore" class="text-5xl font-bold"></div>
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
                        <div id="awayRosterPanel" class="mt-4 space-y-3"></div>
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
                        <div id="loggerEventList" class="mt-4 space-y-3 text-sm text-slate-300"></div>
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
            const editButtons = document.querySelectorAll('[data-match-edit-toggle]');
            editButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-match-edit-toggle');
                    const target = document.getElementById(targetId);
                    if (!target) {
                        return;
                    }

                    target.classList.toggle('hidden');
                });
            });

            const modal = document.getElementById('liveMatchEventLoggerModal');
            const closeModal = document.getElementById('closeLiveMatchEventLoggerModal');
            const eventList = document.getElementById('loggerEventList');
            const homeTeamLabel = document.getElementById('loggerHomeTeam');
            const awayTeamLabel = document.getElementById('loggerAwayTeam');
            const homeScoreLabel = document.getElementById('loggerHomeScore');
            const awayScoreLabel = document.getElementById('loggerAwayScore');
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
                yellow_card: 'Yellow Card',
                red_card: 'Red Card',
            };

            function createEventButton(type, side, playerName, disabled) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'rounded-lg px-2.5 py-1 text-[11px] font-medium text-white transition hover:opacity-90';
                button.textContent = eventLabels[type] || type.replace('_', ' ');

                if (type === 'goal') {
    button.classList.add('bg-emerald-600');
}
else if (type === 'own_goal') {
    button.classList.add('bg-violet-600');
}
else if (type === 'yellow_card') {
    button.classList.add('bg-yellow-400', 'text-black');
}
else if (type === 'red_card') {
    button.classList.add('bg-red-600');
} else {
                    button.classList.add('bg-slate-700', 'hover:bg-slate-600');
                }

                if (disabled) {
                    button.disabled = true;
                    button.classList.add('opacity-50', 'cursor-not-allowed');
                }

                button.addEventListener('click', function () {
                    liveEventType.value = type;
                    liveTeamSide.value = side;
                    livePlayerName.value = playerName || '';
                    eventForm.submit();
                });

                return button;
            }

            function renderRosterPanel(panel, side, roster, disabled) {
                panel.innerHTML = '';
                if (!roster || roster.length === 0) {
                    panel.innerHTML = '<p class="text-sm text-slate-500">Roster tidak tersedia.</p>';
                    return;
                }

                roster.forEach(player => {
                    const card = document.createElement('div');
                    card.className = 'rounded-xl border border-slate-800 bg-slate-950 p-3';
                    card.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-200">${player.label}</p>
                                <p class="text-xs text-slate-500">${side === 'home' ? 'Home' : 'Away'}</p>
                            </div>
                        </div>
                    `;

                    const actions = document.createElement('div');
                    actions.className = 'mt-3 flex flex-wrap gap-2';
                    ['goal', 'own_goal', 'yellow_card', 'red_card'].forEach(type => {
                        actions.appendChild(createEventButton(type, side, player.player_name, disabled));
                    });
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

            case 'yellow_card':
                icon = '🟨';
                break;

            case 'red_card':
                icon = '🟥';
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
            function openModal(matchData) {
                const disabled = matchData.status === 'full_time';

                titleLabel.textContent = `${matchData.left || 'TBD'} vs ${matchData.right || 'TBD'}`;
                homeTeamLabel.textContent = matchData.left || 'Home';
                awayTeamLabel.textContent = matchData.right || 'Away';
                homeScoreLabel.textContent = matchData.score_left ?? 0;
                awayScoreLabel.textContent = matchData.score_right ?? 0;
                statusLabel.innerHTML = `<span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold ${disabled ? 'bg-emerald-500/15 text-emerald-300' : matchData.status === 'live_match' ? 'bg-rose-500/15 text-rose-300' : 'bg-amber-500/15 text-amber-200'}">${disabled ? 'Full Time' : matchData.status === 'live_match' ? 'Live Match' : 'Scheduled'}</span>`;
                datetimeLabel.textContent = matchData.datetime ? new Date(matchData.datetime).toLocaleString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Waktu belum ditentukan';
                renderEventList(matchData.events || []);
                renderRosterPanel(homeRosterPanel, 'home', matchData.home_roster, disabled);
                renderRosterPanel(awayRosterPanel, 'away', matchData.away_roster, disabled);

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

                modal.classList.remove('hidden');
            }

            function closeModalHandler() {
                modal.classList.add('hidden');
            }

            closeModal.addEventListener('click', closeModalHandler);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModalHandler();
                }
            });

            const openMatchId = '{{ session('open_live_match', '') }}';
            if (openMatchId) {
                const matchScript = document.getElementById('match-data-' + openMatchId);
                if (matchScript) {
                    const matchData = JSON.parse(matchScript.textContent || '{}');
                    openModal(matchData);
                }
            }
        });
    </script>
@endpush