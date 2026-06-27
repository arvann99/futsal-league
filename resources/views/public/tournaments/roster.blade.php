@extends('public.tournaments.layout')

@section('title', 'Roster - ' . $tournament->name)

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-emerald-300">Roster</p>
                <h1 class="mt-3 text-3xl font-semibold text-white">Pemain {{ $tournament->name }}</h1>
                <p class="mt-2 text-sm text-slate-400">Daftar pemain tiap tim peserta ({{ $totalPlayers }} pemain).</p>
            </div>
        </div>

        @if($rosters->isEmpty())
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center text-slate-400">
                <p class="text-lg font-semibold text-white">Belum ada pemain terdaftar.</p>
                <p class="mt-2 text-sm">Roster akan muncul setelah tim mendaftarkan pemain.</p>
            </div>
        @else
            <label class="block max-w-md">
                <span class="text-xs uppercase tracking-[0.35em] text-slate-500">Cari Pemain / Tim</span>
                <input id="rosterSearch" type="text" placeholder="Cari nama pemain atau tim" class="mt-2 w-full rounded-3xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 focus:border-emerald-400 focus:outline-none" />
            </label>

            <div id="rosterList" class="space-y-5">
                @foreach($rosters as $roster)
                    <section class="roster-team rounded-[2rem] border border-slate-800 bg-slate-900/95 p-5 shadow-2xl shadow-slate-950/40" data-team="{{ strtolower($roster['team_name']) }}">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="h-12 w-12 overflow-hidden rounded-2xl bg-slate-800 border border-slate-700 shrink-0">
                                @if($roster['logo'])
                                    <img src="{{ Storage::url($roster['logo']) }}" alt="Logo {{ $roster['team_name'] }}" class="h-full w-full object-cover" />
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-slate-500">⚽</div>
                                @endif
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-white">{{ $roster['team_name'] }}</h2>
                                <p class="text-xs text-slate-500">{{ $roster['players']->count() }} pemain @if($roster['group_label']) · Grup {{ $roster['group_label'] }}@endif</p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($roster['players'] as $player)
                                <article class="roster-player flex items-center gap-3 rounded-3xl bg-slate-950 px-4 py-3 border border-slate-800" data-name="{{ strtolower($player->player_name) }}">
                                    <div class="h-12 w-12 overflow-hidden rounded-2xl bg-slate-800 border border-slate-700 shrink-0">
                                        @if($player->photo)
                                            <img src="{{ Storage::url($player->photo) }}" alt="Foto {{ $player->player_name }}" class="h-full w-full object-cover" />
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-slate-500">👤</div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-white truncate">
                                            {{ $player->player_name }}
                                            @if($player->is_captain)<span class="ml-1 inline-flex rounded-full bg-yellow-500/10 px-2 py-0.5 text-[9px] font-semibold text-yellow-300">C</span>@endif
                                        </p>
                                        <p class="text-xs text-slate-400">#{{ $player->shirt_number ?? '-' }} • {{ $player->dominant_position ?? '-' }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div id="rosterNoResults" class="hidden rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center text-slate-400">
                <p class="text-lg font-semibold text-white">Tidak ada hasil.</p>
                <p class="mt-2 text-sm">Ubah kata kunci pencarian Anda.</p>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('rosterSearch');
    if (!input) return;
    const teams = Array.from(document.querySelectorAll('.roster-team'));
    const noResults = document.getElementById('rosterNoResults');

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        let anyVisible = false;

        teams.forEach(team => {
            const teamName = team.dataset.team || '';
            const players = Array.from(team.querySelectorAll('.roster-player'));
            let teamMatch = !q || teamName.includes(q);
            let visiblePlayers = 0;

            players.forEach(p => {
                const show = !q || teamMatch || (p.dataset.name || '').includes(q);
                p.classList.toggle('hidden', !show);
                if (show) visiblePlayers++;
            });

            const showTeam = visiblePlayers > 0;
            team.classList.toggle('hidden', !showTeam);
            if (showTeam) anyVisible = true;
        });

        noResults.classList.toggle('hidden', anyVisible);
    });
})();
</script>
@endpush
