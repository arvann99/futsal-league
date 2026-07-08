<?php

namespace App\Console\Commands;

use App\Http\Controllers\TournamentController;
use App\Models\AppSetting;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use App\Models\TournamentTeamOfficial;
use App\Models\TournamentTeamPlayer;
use App\Services\MatchGenerator;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Simulator turnamen skala besar untuk PENGUJIAN sistem.
 *
 * Meng-generate tim, pemain, dan official dummy lalu memainkan SELURUH
 * pertandingan (skor, pencetak gol, assist, kartu, hingga adu penalti)
 * sesuai pengaturan turnamen yang sudah dikonfigurasi admin di UI.
 *
 * Penting: simulasi TIDAK menduplikasi logika bisnis — semua hasil dicatat
 * lewat jalur yang sama dengan input manual admin:
 *   - event gol/kartu   → TournamentController::storeMatchEvent (Live Logger)
 *   - penutupan laga    → TournamentController::endMatch → concludeMatch
 *   - undian grup       → TournamentController::performGroupDraw
 * sehingga klasemen, pengisian bracket, playoff, dan adu penalti dihitung
 * oleh kode produksi yang sebenarnya. Di akhir, serangkaian validasi
 * otomatis memeriksa konsistensi hasil (laga tuntas, bracket terisi,
 * skor vs event gol, jumlah main per tim, juara).
 *
 * Contoh:
 *   php artisan tournament:simulate 3                  # isi tim sampai kapasitas & mainkan semua
 *   php artisan tournament:simulate 3 --teams=32       # target 32 tim
 *   php artisan tournament:simulate 3 --fresh          # bersihkan tim simulasi lama, ulang dari nol
 *   php artisan tournament:simulate 3 --seed=42        # skor bisa direproduksi
 */
class SimulateTournament extends Command
{
    protected $signature = 'tournament:simulate
        {tournament? : ID turnamen yang akan disimulasikan}
        {--teams= : Target total tim peserta (default: kapasitas grup, atau 8 bila tanpa grup)}
        {--players=10 : Jumlah pemain per tim yang digenerate (2-15)}
        {--officials=2 : Jumlah official per tim (1-3)}
        {--seed= : Seed RNG agar hasil skor bisa direproduksi}
        {--fresh : Hapus tim hasil simulasi sebelumnya & regenerasi jadwal dari nol}';

    protected $description = 'Generate tim/pemain/official dummy lalu simulasikan seluruh pertandingan (skor, pencetak gol, kartu, adu penalti) sesuai pengaturan turnamen — untuk pengujian skala besar.';

    // Penanda tim buatan simulator (kolom teams.notes) agar bisa dibersihkan
    // dengan --fresh tanpa menyentuh tim asli.
    private const TEAM_MARKER = 'auto-generated:tournament-simulator';

    private const TEAM_PREFIXES = [
        'Garuda', 'Rajawali', 'Bintang', 'Satria', 'Naga', 'Benteng', 'Halilintar',
        'Cakra', 'Perkasa', 'Samudra', 'Mutiara', 'Kilat', 'Elang', 'Harimau',
        'Macan', 'Banteng', 'Komodo', 'Cendrawasih', 'Gemilang', 'Persada',
        'Buana', 'Taruna', 'Bahari', 'Merapi', 'Krakatau', 'Semeru', 'Rinjani',
        'Bromo', 'Sriwijaya', 'Majapahit', 'Pandawa', 'Bima', 'Arjuna', 'Gatotkaca',
    ];

    private const TEAM_SUFFIXES = ['FC', 'FA', 'United', 'Jaya', 'Putra', 'Muda', 'Sakti', 'Academy', 'City', 'Squad'];

    private const CITIES = [
        'Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Makassar', 'Semarang',
        'Yogyakarta', 'Palembang', 'Denpasar', 'Balikpapan', 'Pontianak',
        'Manado', 'Padang', 'Pekanbaru', 'Malang', 'Solo', 'Banjarmasin',
        'Samarinda', 'Batam', 'Bogor',
    ];

    private const FIRST_NAMES = [
        'Andi', 'Budi', 'Rizky', 'Fajar', 'Dimas', 'Eko', 'Galih', 'Hendra',
        'Ilham', 'Joko', 'Krisna', 'Lutfi', 'Reza', 'Bayu', 'Aldi', 'Fikri',
        'Gilang', 'Rafi', 'Yoga', 'Damar', 'Panji', 'Sigit', 'Teguh', 'Wahyu',
        'Zaki', 'Arif', 'Bagas', 'Candra', 'Dani', 'Egi', 'Farhan', 'Irfan',
        'Rangga', 'Satria', 'Yusuf', 'Fadli', 'Rian', 'Doni', 'Agus', 'Beni',
    ];

    private const LAST_NAMES = [
        'Pratama', 'Saputra', 'Wijaya', 'Santoso', 'Hidayat', 'Nugroho',
        'Ramadhan', 'Firmansyah', 'Kurniawan', 'Setiawan', 'Maulana', 'Hakim',
        'Siregar', 'Nasution', 'Gunawan', 'Prasetyo', 'Utama', 'Mahendra',
        'Ardiansyah', 'Syahputra', 'Wibowo', 'Hardiansyah', 'Pamungkas', 'Putra',
    ];

    private const OFFICIAL_ROLES = ['Manager', 'Coach', 'Assistant Coach'];

    private ?TournamentController $controller = null;

    /** @var array<int, array<int, array{id:int, name:string, weight:float, is_gk:bool}>> roster per tournament_team_id */
    private array $rosters = [];

    /** @var array<int, float> kekuatan serang per tournament_team_id (memengaruhi peluang skor) */
    private array $strengths = [];

    /** @var array<int, array<string, array<int, string>>> pemain kartu merah per match ("home"/"away" => nama) */
    private array $redCarded = [];

    /** @var array<int, int> id match yang berhasil disimulasikan run ini */
    private array $simulatedMatchIds = [];

    private array $eventCounts = ['goal' => 0, 'own_goal' => 0, 'assist' => 0, 'yellow_card' => 0, 'red_card' => 0];

    private int $penaltyShootouts = 0;

    private int $kickoffCounter = 0;

    public function handle(): int
    {
        if (($seed = $this->option('seed')) !== null) {
            mt_srand((int) $seed);
        }

        $tournament = $this->resolveTournament();
        if (! $tournament) {
            return Command::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->cleanupSimulatedTeams($tournament);
        }

        // ---- Baca pengaturan turnamen (sumber yang sama dengan UI) ----
        $tournament->load('groupSetting');
        $bracketSetting = AppSetting::where('key', 'tournament_' . $tournament->id . '_bracket_settings')->first();
        $bracketValue = $bracketSetting?->value ?? [];
        $competitionType = $bracketValue['competition_type'] ?? 'tournament';
        $groupCount = (int) ($tournament->groupSetting?->group_count ?? 0);
        $teamsPerGroup = (int) ($tournament->groupSetting?->teams_per_group ?? 0);
        $capacity = $groupCount * $teamsPerGroup;
        $usesGroups = $competitionType !== 'tournament' && $groupCount > 0;

        if ($competitionType !== 'tournament' && ! $tournament->groupSetting) {
            $this->error('Turnamen ini belum punya pengaturan grup. Atur dulu lewat menu Pengaturan sebelum simulasi.');

            return Command::FAILURE;
        }

        $this->newLine();
        $this->components->info("Simulasi turnamen: {$tournament->name} (ID {$tournament->id})");
        $this->components->twoColumnDetail('Tipe kompetisi', $competitionType);
        $this->components->twoColumnDetail('Match type', $bracketValue['match_type'] ?? 'single');
        if ($usesGroups) {
            $this->components->twoColumnDetail('Grup', "{$groupCount} grup × {$teamsPerGroup} tim (kapasitas {$capacity})");
            $this->components->twoColumnDetail('Putaran grup/liga', $tournament->groupSetting->league_round_type ?? 'single');
        }

        // ---- Generate peserta dummy ----
        $created = $this->ensureTeams($tournament, $competitionType, $capacity);
        if ($created === null) {
            return Command::FAILURE;
        }

        // ---- Undian grup + generasi jadwal (jalur admin yang sama) ----
        if (! $this->prepareSchedule($tournament, $usesGroups, $created)) {
            return Command::FAILURE;
        }

        // ---- Simulasi seluruh pertandingan ----
        $this->loadRosters($tournament);
        $this->assignStrengths($tournament);
        $this->simulateAllMatches($tournament);

        // ---- Laporan & validasi ----
        $this->report($tournament, $competitionType, $usesGroups);

        return $this->runValidations($tournament) ? Command::SUCCESS : Command::FAILURE;
    }

    // =========================================================================
    // Setup peserta
    // =========================================================================

    private function resolveTournament(): ?Tournament
    {
        if ($id = $this->argument('tournament')) {
            $tournament = Tournament::find($id);
            if (! $tournament) {
                $this->error("Turnamen dengan ID {$id} tidak ditemukan.");

                return null;
            }

            return $tournament;
        }

        $tournaments = Tournament::orderByDesc('id')->get();
        if ($tournaments->isEmpty()) {
            $this->error('Belum ada turnamen. Buat turnamen + pengaturannya dulu lewat UI.');

            return null;
        }

        $options = $tournaments->mapWithKeys(fn ($t) => [$t->id => "[{$t->id}] {$t->name}"])->all();
        $choice = $this->choice('Pilih turnamen yang akan disimulasikan', array_values($options));
        $id = (int) Str::before(Str::after($choice, '['), ']');

        return $tournaments->firstWhere('id', $id);
    }

    /**
     * Tambahkan tim dummy (lengkap dengan pemain & official) sampai jumlah
     * peserta mencapai target. Tim langsung berstatus approved — setara admin
     * yang memverifikasi lewat UI. Return jumlah tim baru, atau null bila gagal.
     */
    private function ensureTeams(Tournament $tournament, string $competitionType, int $capacity): ?int
    {
        $existing = TournamentTeam::where('tournament_id', $tournament->id)->count();
        $target = $this->option('teams') !== null
            ? (int) $this->option('teams')
            : ($capacity > 0 ? $capacity : max($existing, 8));

        if ($capacity > 0 && $target > $capacity) {
            $this->warn("Target {$target} tim melebihi kapasitas grup ({$capacity}) — dipangkas ke {$capacity} agar undian valid.");
            $target = $capacity;
        }

        if ($target < 2) {
            $this->error('Minimal 2 tim untuk menjalankan simulasi.');

            return null;
        }

        $toCreate = $target - $existing;
        if ($toCreate <= 0) {
            $this->components->twoColumnDetail('Peserta', "{$existing} tim sudah terdaftar (target {$target}) — tidak ada tim baru");

            return 0;
        }

        // Menambah tim MEREGENERASI seluruh jadwal (perilaku sistem yang sama
        // dengan menambah peserta di UI). Lindungi hasil yang sudah ada.
        $hasResults = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('status', 'full_time')->exists();
        if ($hasResults && ! $this->option('fresh')) {
            if (! $this->confirm("Turnamen sudah punya hasil pertandingan. Menambah {$toCreate} tim akan MEREGENERASI jadwal & menghapus hasil tersebut. Lanjut?", false)) {
                $this->line('Dibatalkan. Gunakan --fresh untuk mengulang bersih, atau jalankan tanpa --teams agar hanya melanjutkan laga tersisa.');

                return null;
            }
        }

        $teamLimit = $tournament->creator?->teamLimit();
        if ($teamLimit !== null && $target > $teamLimit) {
            $this->warn("Catatan: target {$target} tim melebihi limit paket admin ({$teamLimit}); simulator tetap lanjut (bypass khusus pengujian).");
        }

        $playersPerTeam = min(15, max(2, (int) $this->option('players')));
        $officialsPerTeam = min(3, max(1, (int) $this->option('officials')));

        $this->components->task("Generate {$toCreate} tim dummy ({$playersPerTeam} pemain + {$officialsPerTeam} official per tim)", function () use ($tournament, $toCreate, $playersPerTeam, $officialsPerTeam) {
            for ($i = 0; $i < $toCreate; $i++) {
                $this->createSimulatedTeam($tournament, $playersPerTeam, $officialsPerTeam);
            }

            return true;
        });

        return $toCreate;
    }

    private function createSimulatedTeam(Tournament $tournament, int $playerCount, int $officialCount): void
    {
        $name = $this->uniqueTeamName();
        $city = self::CITIES[mt_rand(0, count(self::CITIES) - 1)];

        $team = Team::create([
            'name' => $name,
            'slug' => $this->uniqueTeamSlug($name),
            'city' => $city,
            'country' => 'Indonesia',
            'notes' => self::TEAM_MARKER,
            'manager_token' => Team::generateUniqueManagerToken($name),
            'verification_status' => 'approved',
            'created_by' => $tournament->created_by,
        ]);

        $tournamentTeam = TournamentTeam::create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'group_label' => null, // grup diisi lewat undian resmi (performGroupDraw)
        ]);

        $this->generatePlayers($tournamentTeam->id, $playerCount, $city);
        $this->generateOfficials($tournamentTeam->id, $officialCount);
    }

    private function generatePlayers(int $tournamentTeamId, int $count, string $city): void
    {
        $usedNames = [];
        $usedNumbers = [];
        $gkCount = $count >= 8 ? 2 : 1;

        for ($i = 0; $i < $count; $i++) {
            $isGk = $i < $gkCount;
            $dominant = $isGk
                ? 'GK'
                : ['Anchor', 'Flank', 'Flank', 'Pivot'][mt_rand(0, 3)];

            $positions = [$dominant];
            if (! $isGk && mt_rand(1, 100) <= 30) {
                $secondary = ['Anchor', 'Flank', 'Pivot'][mt_rand(0, 2)];
                if ($secondary !== $dominant) {
                    $positions[] = $secondary;
                }
            }

            if ($isGk) {
                $number = $i === 0 ? 1 : 12;
            } else {
                do {
                    $number = mt_rand(2, 40);
                } while (in_array($number, $usedNumbers, true) || $number === 12);
            }
            $usedNumbers[] = $number;

            do {
                $playerName = strtoupper($this->randomPersonName());
            } while (in_array($playerName, $usedNames, true));
            $usedNames[] = $playerName;

            TournamentTeamPlayer::create([
                'tournament_team_id' => $tournamentTeamId,
                'player_name' => $playerName, // sistem menyimpan uppercase (lihat OfficialPlayerController)
                'shirt_number' => $number,
                'positions' => $positions,
                'dominant_position' => $dominant,
                'phone' => '08' . mt_rand(11, 99) . mt_rand(10000000, 99999999),
                'birth_place' => $city,
                'birth_date' => Carbon::create(mt_rand(1994, 2007), mt_rand(1, 12), mt_rand(1, 28)),
                'is_captain' => $i === $gkCount, // pemain lapangan pertama jadi kapten
                'status' => 'active',
                'registered_at' => now(),
            ]);
        }
    }

    private function generateOfficials(int $tournamentTeamId, int $count): void
    {
        foreach (array_slice(self::OFFICIAL_ROLES, 0, $count) as $role) {
            TournamentTeamOfficial::create([
                'tournament_team_id' => $tournamentTeamId,
                'official_name' => $this->randomPersonName(),
                'role' => $role,
                'contact_phone' => '08' . mt_rand(11, 99) . mt_rand(10000000, 99999999),
            ]);
        }
    }

    private function uniqueTeamName(): string
    {
        do {
            $name = self::TEAM_PREFIXES[mt_rand(0, count(self::TEAM_PREFIXES) - 1)]
                . ' ' . self::TEAM_SUFFIXES[mt_rand(0, count(self::TEAM_SUFFIXES) - 1)];

            if (Team::where('name', $name)->exists()) {
                $name .= ' ' . mt_rand(2, 99);
            }
        } while (Team::where('name', $name)->exists());

        return $name;
    }

    private function uniqueTeamSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::random(10);
        $slug = $base;
        $counter = 1;
        while (Team::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function randomPersonName(): string
    {
        return self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)]
            . ' ' . self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)];
    }

    /**
     * Pastikan grup terundi & jadwal ter-generate — memakai endpoint admin asli
     * (performGroupDraw) sehingga aturan kapasitas/verifikasi ikut teruji.
     */
    private function prepareSchedule(Tournament $tournament, bool $usesGroups, int $createdTeams): bool
    {
        $needsDraw = $usesGroups && TournamentTeam::where('tournament_id', $tournament->id)
            ->whereNull('group_label')
            ->whereHas('team', fn ($q) => $q->where('verification_status', 'approved'))
            ->exists();

        if ($needsDraw) {
            $request = Request::create('/simulator/group-draw', 'POST');
            $request->setLaravelSession(app('session.store'));

            $response = $this->controller()->performGroupDraw($request, $tournament);
            $payload = $response instanceof JsonResponse ? $response->getData(true) : [];

            if (! ($payload['success'] ?? false)) {
                $this->error('Undian grup gagal: ' . ($payload['message'] ?? 'alasan tidak diketahui'));

                return false;
            }

            $this->components->twoColumnDetail('Undian grup', 'selesai via performGroupDraw (jadwal ter-generate)');
        } elseif ($createdTeams > 0 || ! TournamentMatch::where('tournament_id', $tournament->id)->exists()) {
            // Tanpa grup (atau semua sudah terplot): generate jadwal langsung.
            app(MatchGenerator::class)->generateForTournament($tournament->fresh());
            $this->components->twoColumnDetail('Jadwal', 'ter-generate via MatchGenerator');
        }

        $total = TournamentMatch::where('tournament_id', $tournament->id)->where('is_bye', false)->count();
        if ($total === 0) {
            $this->error('Tidak ada pertandingan yang bisa disimulasikan. Cek pengaturan turnamen (tipe kompetisi, grup, verifikasi tim).');

            return false;
        }

        $this->components->twoColumnDetail('Total laga (non-bye)', (string) $total);

        return true;
    }

    // =========================================================================
    // Simulasi pertandingan
    // =========================================================================

    private function loadRosters(Tournament $tournament): void
    {
        $players = TournamentTeamPlayer::whereIn(
            'tournament_team_id',
            TournamentTeam::where('tournament_id', $tournament->id)->select('id')
        )->where('status', 'active')->get();

        foreach ($players as $player) {
            $isGk = $player->dominant_position === 'GK';
            // Bobot peluang mencetak gol: pivot paling tajam, kiper hampir tidak.
            $weight = match ($player->dominant_position) {
                'Pivot' => 4.0,
                'Flank' => 3.0,
                'Anchor' => 1.5,
                default => 0.15,
            } * (mt_rand(60, 140) / 100); // variasi "skill" antar pemain

            $this->rosters[$player->tournament_team_id][] = [
                'id' => $player->id,
                'name' => $player->player_name,
                'weight' => $weight,
                'is_gk' => $isGk,
            ];
        }
    }

    private function assignStrengths(Tournament $tournament): void
    {
        $ids = TournamentTeam::where('tournament_id', $tournament->id)->pluck('id');
        foreach ($ids as $id) {
            $this->strengths[$id] = mt_rand(55, 165) / 100;
        }
    }

    /**
     * Mainkan laga yang siap dimainkan satu per satu (urutan ID = urutan ronde).
     * Setiap laga selesai, sistem produksi mengisi slot bracket/playoff
     * berikutnya — karena itu daftar laga siap-main diambil ulang tiap iterasi.
     */
    private function simulateAllMatches(Tournament $tournament): void
    {
        $totalPlayable = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('is_bye', false)
            ->where('status', '!=', 'full_time')
            ->count();

        if ($totalPlayable === 0) {
            $this->components->twoColumnDetail('Simulasi', 'semua laga sudah selesai — tidak ada yang dimainkan');

            return;
        }

        $this->newLine();
        $this->components->info("Memainkan {$totalPlayable} pertandingan...");
        $bar = $this->output->createProgressBar($totalPlayable);
        $bar->start();

        $safety = $totalPlayable * 3 + 20;
        while ($safety-- > 0) {
            $match = TournamentMatch::where('tournament_id', $tournament->id)
                ->where('is_bye', false)
                ->where('status', '!=', 'full_time')
                ->whereNotNull('home_team_id')
                ->whereNotNull('away_team_id')
                ->orderBy('id')
                ->first();

            if (! $match) {
                break;
            }

            $this->playMatch($tournament, $match);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function playMatch(Tournament $tournament, TournamentMatch $match): void
    {
        if ($match->status === 'scheduled') {
            // Setara tombol "Jadwal" + buka Live Logger di UI.
            $base = $tournament->match_date ?? now()->startOfDay()->addHours(8);
            $match->update([
                'match_date' => $base->copy()->addMinutes(75 * $this->kickoffCounter++),
                'status' => 'live_match',
            ]);
        }

        if ($match->status === 'live_match') {
            [$homeGoals, $awayGoals] = $this->sampleScore($match);

            foreach ($this->buildTimeline($match, $homeGoals, $awayGoals) as $event) {
                $this->sendEvent($tournament, $match, $event);
            }

            $match->refresh();

            if ($match->home_score === null || $match->away_score === null) {
                // Laga 0-0: belum ada event gol yang menyentuh skor — tutup via
                // End Match (memperlakukan NULL sebagai 0, jalur produksi).
                $this->endMatchAction($tournament, $match);
            } else {
                // Penutupan normal: event full_time (jalur Live Logger).
                // team_side dikirim eksplisit null — form UI selalu menyertakan
                // field ini sehingga controller mengasumsikan kuncinya ada.
                $this->sendEvent($tournament, $match, ['event_type' => 'full_time', 'team_side' => null, 'minute' => 40]);
            }

            $match->refresh();
        }

        // Seri pada laga penentu babak gugur → sistem membuka fase adu penalti.
        $rounds = 0;
        while ($match->status === 'penalty_shootout' && $rounds < 15) {
            $rounds++;
            $this->simulatePenaltyRound($tournament, $match, $rounds);
            $this->endMatchAction($tournament, $match);
            $match->refresh();
        }
        if ($rounds > 0) {
            $this->penaltyShootouts++;
        }

        if ($match->status === 'full_time') {
            $this->simulatedMatchIds[] = $match->id;

            if ($this->output->isVerbose()) {
                $pen = $match->home_penalty_score !== null
                    ? " (pen {$match->home_penalty_score}-{$match->away_penalty_score})"
                    : '';
                $this->line(sprintf(
                    '  <fg=gray>[%s%s]</> %s <options=bold>%d - %d</>%s %s',
                    $match->stage_type,
                    $match->leg ? " leg {$match->leg}" : '',
                    $match->home_team_key ?? '?',
                    $match->home_score,
                    $match->away_score,
                    $pen,
                    $match->away_team_key ?? '?'
                ));
            }
        } else {
            $this->warn("  Laga #{$match->id} tidak bisa dituntaskan (status: {$match->status}).");
        }
    }

    /**
     * Skor waktu normal dari distribusi Poisson berbobot kekuatan tim —
     * rata-rata khas futsal (± 2-4 gol per tim), tim kuat lebih sering menang.
     */
    private function sampleScore(TournamentMatch $match): array
    {
        $home = $this->strengths[$match->home_team_id] ?? 1.0;
        $away = $this->strengths[$match->away_team_id] ?? 1.0;
        $ratio = $home / ($home + $away);

        $homeGoals = min(12, $this->poisson(0.4 + 4.4 * $ratio + 0.15)); // +0.15 keuntungan kandang
        $awayGoals = min(12, $this->poisson(0.4 + 4.4 * (1 - $ratio)));

        return [$homeGoals, $awayGoals];
    }

    private function poisson(float $lambda): int
    {
        $limit = exp(-$lambda);
        $count = 0;
        $product = 1.0;

        do {
            $count++;
            $product *= mt_rand() / mt_getrandmax();
        } while ($product > $limit);

        return $count - 1;
    }

    /**
     * Susun kronologi event laga: gol (+pencetak asli dari roster), assist,
     * kartu kuning/merah, dan jeda halftime — diurutkan per menit.
     */
    private function buildTimeline(TournamentMatch $match, int $homeGoals, int $awayGoals): array
    {
        $events = [['event_type' => 'halftime', 'team_side' => null, 'minute' => 20]];
        $busyPlayers = []; // pemain yang punya event — kandidat kartu merah dikecualikan

        foreach (['home' => $homeGoals, 'away' => $awayGoals] as $side => $goals) {
            for ($i = 0; $i < $goals; $i++) {
                $minute = mt_rand(1, 40);

                // ± 4% gol bunuh diri: event own_goal dicatat atas nama pemain
                // lawan (sistem otomatis menambah skor sisi seberang).
                if (mt_rand(1, 100) <= 4) {
                    $ownSide = $side === 'home' ? 'away' : 'home';
                    $player = $this->pickPlayer($match, $ownSide, true);
                    if ($player) {
                        $events[] = [
                            'event_type' => 'own_goal',
                            'team_side' => $ownSide,
                            'player_id' => $player['id'],
                            'player_name' => $player['name'],
                            'minute' => $minute,
                        ];
                        $busyPlayers[$player['name']] = true;
                        continue;
                    }
                }

                $scorer = $this->pickPlayer($match, $side);
                if (! $scorer) {
                    continue;
                }

                $events[] = [
                    'event_type' => 'goal',
                    'team_side' => $side,
                    'player_id' => $scorer['id'],
                    'player_name' => $scorer['name'],
                    'minute' => $minute,
                ];
                $busyPlayers[$scorer['name']] = true;

                if (mt_rand(1, 100) <= 55) {
                    $assist = $this->pickPlayer($match, $side, false, [$scorer['name']]);
                    if ($assist) {
                        $events[] = [
                            'event_type' => 'assist',
                            'team_side' => $side,
                            'player_id' => $assist['id'],
                            'player_name' => $assist['name'],
                            'minute' => $minute,
                        ];
                        $busyPlayers[$assist['name']] = true;
                    }
                }
            }
        }

        // Kartu kuning: 0-3 per laga, satu pemain maksimal satu kuning agar
        // tidak melanggar aturan "merah menonaktifkan pemain" milik sistem.
        $booked = [];
        $yellowCount = [0, 0, 1, 1, 2, 3][mt_rand(0, 5)];
        for ($i = 0; $i < $yellowCount; $i++) {
            $side = mt_rand(0, 1) === 0 ? 'home' : 'away';
            $player = $this->pickPlayer($match, $side, true, $booked);
            if ($player) {
                $events[] = [
                    'event_type' => 'yellow_card',
                    'team_side' => $side,
                    'player_id' => $player['id'],
                    'player_name' => $player['name'],
                    'minute' => mt_rand(5, 40),
                ];
                $booked[] = $player['name'];
            }
        }

        // ± 3% laga ada kartu merah di menit akhir, untuk pemain tanpa event
        // lain — pemain merah otomatis ditolak sistem untuk event berikutnya.
        if (mt_rand(1, 100) <= 3) {
            $side = mt_rand(0, 1) === 0 ? 'home' : 'away';
            $exclude = array_merge(array_keys($busyPlayers), $booked);
            $player = $this->pickPlayer($match, $side, true, $exclude);
            if ($player) {
                $events[] = [
                    'event_type' => 'red_card',
                    'team_side' => $side,
                    'player_id' => $player['id'],
                    'player_name' => $player['name'],
                    'minute' => mt_rand(36, 40),
                ];
                $this->redCarded[$match->id][$side][] = $player['name'];
            }
        }

        usort($events, fn ($a, $b) => ($a['minute'] ?? 0) <=> ($b['minute'] ?? 0));

        return $events;
    }

    /**
     * Pilih pemain dari roster sisi tertentu, berbobot posisi+skill.
     * $uniform = semua pemain berpeluang sama (untuk kartu / gol bunuh diri).
     */
    private function pickPlayer(TournamentMatch $match, string $side, bool $uniform = false, array $excludeNames = []): ?array
    {
        $teamId = $side === 'home' ? $match->home_team_id : $match->away_team_id;
        $exclude = array_merge($excludeNames, $this->redCarded[$match->id][$side] ?? []);

        $candidates = array_values(array_filter(
            $this->rosters[$teamId] ?? [],
            fn ($p) => ! in_array($p['name'], $exclude, true)
        ));

        if ($candidates === []) {
            return null;
        }

        if ($uniform) {
            return $candidates[mt_rand(0, count($candidates) - 1)];
        }

        $totalWeight = array_sum(array_column($candidates, 'weight'));
        $roll = mt_rand() / mt_getrandmax() * $totalWeight;
        foreach ($candidates as $candidate) {
            $roll -= $candidate['weight'];
            if ($roll <= 0) {
                return $candidate;
            }
        }

        return end($candidates) ?: null;
    }

    /**
     * Ronde 1 = 5 penendang per tim; ronde berikutnya sudden death dengan
     * tepat satu tim yang mencetak — dijamin selesai saat End Match berikutnya.
     */
    private function simulatePenaltyRound(Tournament $tournament, TournamentMatch $match, int $round): void
    {
        $kick = function (string $side, bool $scored) use ($tournament, $match) {
            $player = $this->pickPlayer($match, $side);
            $this->sendEvent($tournament, $match, [
                'event_type' => $scored ? 'penalty_goal' : 'penalty_miss',
                'team_side' => $side,
                'player_id' => $player['id'] ?? null,
                'player_name' => $player['name'] ?? null,
            ]);
        };

        if ($round === 1) {
            for ($i = 0; $i < 5; $i++) {
                $kick('home', mt_rand(1, 100) <= 75);
                $kick('away', mt_rand(1, 100) <= 75);
            }

            return;
        }

        $homeScores = mt_rand(0, 1) === 1;
        $kick('home', $homeScores);
        $kick('away', ! $homeScores);
    }

    // =========================================================================
    // Jembatan ke controller produksi
    // =========================================================================

    private function controller(): TournamentController
    {
        return $this->controller ??= app(TournamentController::class);
    }

    /**
     * Catat satu event lewat storeMatchEvent — jalur yang sama persis dengan
     * tombol Live Match Event Logger di UI (lock, validasi, update skor).
     */
    private function sendEvent(Tournament $tournament, TournamentMatch $match, array $payload): void
    {
        $request = Request::create('/simulator/event', 'POST', $payload, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->setLaravelSession(app('session.store'));

        try {
            $response = $this->controller()->storeMatchEvent($request, $tournament, $match);
        } catch (ValidationException $e) {
            $this->warn("  Event ditolak (validasi) pada laga #{$match->id}: " . collect($e->errors())->flatten()->first());

            return;
        }

        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            $message = $response->getData(true)['message'] ?? 'alasan tidak diketahui';
            $this->warn("  Event ditolak sistem pada laga #{$match->id}: {$message}");

            return;
        }

        $type = $payload['event_type'];
        if (isset($this->eventCounts[$type])) {
            $this->eventCounts[$type]++;
        }
    }

    private function endMatchAction(Tournament $tournament, TournamentMatch $match): void
    {
        // endMatch mengembalikan redirect (jalur web); hasil sebenarnya dibaca
        // dari status match setelah refresh oleh pemanggil.
        $this->controller()->endMatch($tournament, $match);
    }

    private function callControllerPrivate(string $method, ...$args): mixed
    {
        $reflection = new \ReflectionMethod(TournamentController::class, $method);

        return $reflection->invoke($this->controller(), ...$args);
    }

    // =========================================================================
    // Laporan & validasi
    // =========================================================================

    private function report(Tournament $tournament, string $competitionType, bool $usesGroups): void
    {
        $matches = TournamentMatch::where('tournament_id', $tournament->id)->get();
        $finished = $matches->where('status', 'full_time')->where('is_bye', false);

        $this->components->info('Ringkasan simulasi');
        $this->components->twoColumnDetail('Laga selesai run ini', (string) count($this->simulatedMatchIds));
        $this->components->twoColumnDetail('Total laga selesai', $finished->count() . ' / ' . $matches->where('is_bye', false)->count());
        $this->components->twoColumnDetail(
            'Event tercatat',
            "{$this->eventCounts['goal']} gol, {$this->eventCounts['own_goal']} gol bunuh diri, {$this->eventCounts['assist']} assist, "
            . "{$this->eventCounts['yellow_card']} kuning, {$this->eventCounts['red_card']} merah"
        );
        $this->components->twoColumnDetail('Adu penalti', (string) $this->penaltyShootouts);

        // Klasemen per grup — dihitung fungsi produksi yang sama dengan UI.
        if ($usesGroups || in_array($competitionType, ['league', 'league_playoff'], true)) {
            $groups = $this->callControllerPrivate('buildStandingsGroups', $tournament);
            foreach ($groups as $label => $rows) {
                if ($rows === []) {
                    continue;
                }

                $this->newLine();
                $this->line("  <options=bold>Klasemen Grup {$label}</>");
                $this->table(
                    ['#', 'Tim', 'Ma', 'M', 'S', 'K', 'GM', 'GK', 'SG', 'Poin'],
                    array_map(fn ($row) => [
                        $row['ranking'],
                        $row['name'],
                        $row['played'],
                        $row['wins'],
                        $row['draws'],
                        $row['losses'],
                        $row['goals_scored'],
                        $row['goals_conceded'],
                        $row['goal_difference'],
                        $row['points'],
                    ], $rows)
                );
            }
        }

        // Juara — resolusi produksi (Final bracket atau puncak klasemen liga).
        $champion = $this->callControllerPrivate('resolveChampion', $tournament, $competitionType);
        if ($champion) {
            $this->components->twoColumnDetail('🏆 ' . ($champion['context'] ?? 'Juara'), $champion['name']);
        }

        // Top skor dari event gol ber-player_id (data yang dipakai halaman statistik).
        $topScorers = MatchEvent::query()
            ->whereIn('match_id', TournamentMatch::where('tournament_id', $tournament->id)->select('id'))
            ->where('event_type', 'goal')
            ->whereNotNull('player_id')
            ->selectRaw('player_id, COUNT(*) as total')
            ->groupBy('player_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        if ($topScorers->isNotEmpty()) {
            $players = TournamentTeamPlayer::with('tournamentTeam.team')
                ->whereIn('id', $topScorers->pluck('player_id'))
                ->get()
                ->keyBy('id');

            $this->newLine();
            $this->line('  <options=bold>Top Skor</>');
            $this->table(
                ['Pemain', 'Tim', 'Gol'],
                $topScorers->map(fn ($row) => [
                    $players[$row->player_id]?->player_name ?? '?',
                    $players[$row->player_id]?->tournamentTeam?->team?->name ?? '?',
                    $row->total,
                ])->all()
            );
        }

        $this->line('  Cek visual: buka menu Klasemen, Bracket, Jadwal & Statistik turnamen ini di UI admin.');
    }

    /**
     * Validasi otomatis — inti "sistem testing": memastikan hasil simulasi
     * konsisten di seluruh modul tanpa harus dicek manual satu-satu.
     */
    private function runValidations(Tournament $tournament): bool
    {
        $this->newLine();
        $this->components->info('Validasi konsistensi sistem');
        $allPassed = true;

        $check = function (string $label, bool $passed, string $detail = '') use (&$allPassed) {
            $allPassed = $allPassed && $passed;
            $this->components->twoColumnDetail(
                ($passed ? '<fg=green>PASS</> ' : '<fg=red>FAIL</> ') . $label,
                $detail
            );
        };

        // 1. Semua laga non-bye tuntas.
        $unfinished = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('is_bye', false)
            ->where('status', '!=', 'full_time')
            ->get();
        $check(
            'Seluruh laga non-bye berstatus full_time',
            $unfinished->isEmpty(),
            $unfinished->isEmpty() ? 'OK' : $unfinished->count() . ' laga menggantung (id: ' . $unfinished->pluck('id')->take(10)->implode(',') . ')'
        );

        // 2. Bracket terisi penuh: tidak ada slot knockout non-bye tanpa tim.
        $emptySlots = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereIn('stage_type', ['knockout', 'promotion_playoff', 'relegation_playoff'])
            ->where('is_bye', false)
            ->where(fn ($q) => $q->whereNull('home_team_id')->orWhereNull('away_team_id'))
            ->count();
        $knockoutExists = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereIn('stage_type', ['knockout', 'promotion_playoff', 'relegation_playoff'])
            ->exists();
        if ($knockoutExists) {
            $check(
                'Semua slot bracket/playoff terisi tim',
                $emptySlots === 0,
                $emptySlots === 0 ? 'OK' : "{$emptySlots} slot kosong"
            );
        }

        // 3. Skor akhir == jumlah event gol per laga (hanya laga run ini,
        //    laga input manual lama bisa saja tanpa event).
        if ($this->simulatedMatchIds !== []) {
            $mismatches = [];
            $eventGoals = MatchEvent::whereIn('match_id', $this->simulatedMatchIds)
                ->whereIn('event_type', ['goal', 'own_goal'])
                ->get()
                ->groupBy('match_id');

            foreach (TournamentMatch::whereIn('id', $this->simulatedMatchIds)->get() as $match) {
                $events = $eventGoals->get($match->id, collect());
                $homeFromEvents = $events->where('event_type', 'goal')->where('team_side', 'home')->count()
                    + $events->where('event_type', 'own_goal')->where('team_side', 'away')->count();
                $awayFromEvents = $events->where('event_type', 'goal')->where('team_side', 'away')->count()
                    + $events->where('event_type', 'own_goal')->where('team_side', 'home')->count();

                if ($homeFromEvents !== (int) $match->home_score || $awayFromEvents !== (int) $match->away_score) {
                    $mismatches[] = $match->id;
                }
            }

            $check(
                'Skor akhir = jumlah event gol (per laga simulasi)',
                $mismatches === [],
                $mismatches === [] ? 'OK' : 'selisih pada laga id: ' . implode(',', array_slice($mismatches, 0, 10))
            );
        }

        // 4. Jumlah main tiap tim sesuai format grup/liga (round robin penuh).
        $tournament->load('groupSetting');
        $legs = ($tournament->groupSetting?->league_round_type ?? 'single') === 'double' ? 2 : 1;
        $groups = $this->callControllerPrivate('buildStandingsGroups', $tournament);
        $playedIssues = [];
        foreach ($groups as $label => $rows) {
            $expected = (count($rows) - 1) * $legs;
            foreach ($rows as $row) {
                if (count($rows) >= 2 && $row['played'] !== $expected) {
                    $playedIssues[] = "{$row['name']} (Grup {$label}: {$row['played']}/{$expected})";
                }
            }
        }
        if ($groups !== []) {
            $check(
                'Jumlah main tiap tim sesuai format round robin',
                $playedIssues === [],
                $playedIssues === [] ? 'OK' : implode('; ', array_slice($playedIssues, 0, 5))
            );
        }

        // 5. Juara berhasil ditentukan (bila format punya Final / liga tuntas).
        $bracketSetting = AppSetting::where('key', 'tournament_' . $tournament->id . '_bracket_settings')->first();
        $competitionType = $bracketSetting?->value['competition_type'] ?? 'tournament';
        $hasFinal = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('round_name', 'Final')->where('is_third_place', false)->exists();
        if ($hasFinal || $competitionType === 'league') {
            $champion = $this->callControllerPrivate('resolveChampion', $tournament, $competitionType);
            $check(
                'Juara turnamen berhasil ditentukan sistem',
                $champion !== null,
                $champion['name'] ?? 'tidak ada juara terdeteksi'
            );
        }

        $this->newLine();
        $this->line($allPassed
            ? '  <fg=green;options=bold>✔ Semua validasi lolos — sistem konsisten pada skala ini.</>'
            : '  <fg=red;options=bold>✘ Ada validasi yang gagal — periksa detail FAIL di atas.</>');

        return $allPassed;
    }

    // =========================================================================
    // Pembersihan (--fresh)
    // =========================================================================

    /**
     * Hapus semua tim buatan simulator dari turnamen ini (pemain & official
     * ikut terhapus via cascade), lalu regenerasi jadwal bersih. Tim asli
     * (tanpa penanda) tidak disentuh.
     */
    private function cleanupSimulatedTeams(Tournament $tournament): void
    {
        $simTeamIds = Team::where('notes', self::TEAM_MARKER)->pluck('id');

        $removedParticipants = TournamentTeam::where('tournament_id', $tournament->id)
            ->whereIn('team_id', $simTeamIds)
            ->delete();

        // Tim simulator yang tidak lagi dipakai turnamen mana pun ikut dihapus.
        $removedTeams = 0;
        foreach ($simTeamIds as $teamId) {
            if (! TournamentTeam::where('team_id', $teamId)->exists()) {
                Team::where('id', $teamId)->delete();
                $removedTeams++;
            }
        }

        // Jadwal diregenerasi tanpa tim simulasi (hasil lama ikut terhapus —
        // memang tujuan --fresh).
        app(MatchGenerator::class)->generateForTournament($tournament->fresh());

        $this->components->twoColumnDetail(
            '--fresh',
            "{$removedParticipants} peserta simulasi dilepas, {$removedTeams} tim dihapus, jadwal direset"
        );
    }
}
