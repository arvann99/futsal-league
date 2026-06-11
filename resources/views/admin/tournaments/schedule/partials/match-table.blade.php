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
                <div class="grid grid-cols-[2fr_2fr_1fr_2fr_1fr_1fr_1fr] gap-4 items-center border-b border-slate-800 last:border-b-0 px-4 py-4">
                    <div class="text-slate-200 font-semibold">{{ $match['round'] ?? $match['group'] ?? 'N/A' }}</div>
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
                    <div class="text-center">
                        <div class="text-slate-100 font-bold text-lg">{{ isset($match['score_left']) ? $match['score_left'] : '-' }}</div>
                        <div class="text-slate-500 text-xs">-</div>
                        <div class="text-slate-100 font-bold text-lg">{{ isset($match['score_right']) ? $match['score_right'] : '-' }}</div>
                    </div>
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
                            default => 'scheduled',
                        };

                        $statusLabel = match ($statusKey) {
                            'live_match' => 'Live Match',
                            'full_time' => 'Full Time',
                            default => 'Scheduled',
                        };

                        $statusClass = match ($statusKey) {
                            'live_match' => 'bg-rose-500/15 text-rose-300',
                            'full_time' => 'bg-emerald-500/15 text-emerald-300',
                            default => 'bg-amber-500/15 text-amber-200',
                        };

                        $matchReady = isset($match['home_team_id']) && isset($match['away_team_id']);
                        $matchLocked = ! $matchReady || $statusKey === 'full_time';
                        $matchDisabled = $matchLocked ? 'disabled' : '';
                    @endphp
                    <div class="text-slate-300 text-sm leading-tight">
                        <div>{{ $matchDateTime->format('H:i') }}</div>
                        <div class="text-slate-500 text-xs">{{ $matchDateTime->translatedFormat('l, j F Y') }}</div>
                    </div>
                    <div>
                        <div class="flex flex-col gap-2">
                            <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                            @unless($matchReady)
                                <span class="inline-flex rounded-full bg-amber-500/10 px-3 py-1 text-[11px] font-semibold text-amber-200">Menunggu...</span>
                            @endunless
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" data-match-edit-toggle="match-{{ $match['id'] }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-100 hover:border-slate-600 hover:bg-slate-700 {{ $matchLocked ? 'opacity-50 cursor-not-allowed' : '' }}" {{ $matchLocked ? 'disabled' : '' }}>Edit Match</button>
                        <form method="POST" action="{{ route('tournaments.matches.liveLogger', ['tournament' => $tournament, 'match' => $match['id']]) }}" class="inline-block">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500" {{ $matchLocked ? 'disabled' : '' }}>Live Match Event Logger</button>
                        </form>
                    </div>
                </div>
                <div id="match-{{ $match['id'] }}" class="hidden border-b border-slate-800 px-4 pb-4">
                    <div class="rounded-[28px] border border-slate-800 bg-slate-950 p-4">
                        <form method="POST" action="{{ route('tournaments.matches.update', ['tournament' => $tournament, 'match' => $match['id']]) }}" class="grid gap-4 lg:grid-cols-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2" for="match_date_{{ $match['id'] }}">Tanggal Pertandingan</label>
                                <input id="match_date_{{ $match['id'] }}" name="match_date" type="date" value="{{ $matchDateTime->format('Y-m-d') }}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" required {{ $matchDisabled }} />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2" for="match_time_{{ $match['id'] }}">Waktu Pertandingan</label>
                                <input id="match_time_{{ $match['id'] }}" name="match_time" type="time" value="{{ $matchDateTime->format('H:i') }}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" required {{ $matchDisabled }} />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2" for="match_home_score_{{ $match['id'] }}">Skor Home</label>
                                <input id="match_home_score_{{ $match['id'] }}" name="home_score" type="number" min="0" value="{{ isset($match['score_left']) ? $match['score_left'] : '' }}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" {{ $matchDisabled }} />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2" for="match_away_score_{{ $match['id'] }}">Skor Away</label>
                                <input id="match_away_score_{{ $match['id'] }}" name="away_score" type="number" min="0" value="{{ isset($match['score_right']) ? $match['score_right'] : '' }}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" {{ $matchDisabled }} />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2" for="match_status_{{ $match['id'] }}">Status Laga</label>
                                <select id="match_status_{{ $match['id'] }}" name="match_status" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" required {{ $matchDisabled }}>
                                    <option value="scheduled" {{ $statusKey === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                                    <option value="live_match" {{ $statusKey === 'live_match' ? 'selected' : '' }}>Live Match</option>
                                    <option value="full_time" {{ $statusKey === 'full_time' ? 'selected' : '' }}>Full Time</option>
                                </select>
                            </div>
                            <div class="lg:col-span-3 flex flex-col gap-3">
                                <div class="text-slate-400 text-sm">
                                    Edit hanya tanggal, waktu, dan status pertandingan. Skor disimpan otomatis melalui Live Match Logger.
                                </div>
                                <div class="flex flex-wrap gap-2 justify-end">
                                    <button type="button" data-match-edit-toggle="match-{{ $match['id'] }}" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-slate-600 hover:bg-slate-700">Tutup</button>
                                    <button type="submit" class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" {{ $matchDisabled }}>Simpan</button>
                                </div>
                            </div>
                        </form>
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
