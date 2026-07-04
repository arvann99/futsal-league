<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroupSetting;
use App\Models\TournamentTeam;
use App\Services\MatchGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seeder demo: satu turnamen GRUP → GUGUR per konfigurasi yang dilaporkan
 * (garis bracket melenceng). Tiap turnamen memakai fase grup round-robin lalu
 * bracket gugur ber-placeholder posisi grup (A1/B2/...), dengan 2 lolos per
 * grup (juara + runner-up) — jadi jumlah tim di BRACKET = group_count × 2.
 *
 * Beberapa konfigurasi menghasilkan tim bracket BUKAN pangkat 2 (6, 10, 12,
 * 14, 18, 22) → bracket ber-bye, yakni kasus yang dulu memicu garis konektor
 * miring. Dengan penjangkaran dua arah di MatchGenerator, semua garis kini
 * lurus/simetris. Idempotent — tiap turnamen di-reset di setiap run.
 *
 * Kolom ringkas (total peserta = group_count × teams_per_group):
 *   15 (3×5)  21 (3×7)  27 (3×9)  36 (3×12) 39 (3×13) 42 (3×14)
 *   25 (5×5)  35 (7×5)  40 (8×5)  45 (9×5)  42 (6×7)  63 (9×7)  44 (11×4)
 */
class GroupKnockoutSamplesSeeder extends Seeder
{
    /**
     * Tiap entri: [label total, group_count, teams_per_group]. qualified_teams
     * selalu [1, 2] (2 lolos per grup).
     */
    private const CONFIGS = [
        ['15 (3x5)',  3, 5],
        ['21 (3x7)',  3, 7],
        ['27 (3x9)',  3, 9],
        ['36 (3x12)', 3, 12],
        ['39 (3x13)', 3, 13],
        ['42 (3x14)', 3, 14],
        ['25 (5x5)',  5, 5],
        ['35 (7x5)',  7, 5],
        ['40 (8x5)',  8, 5],
        ['45 (9x5)',  9, 5],
        ['42 (6x7)',  6, 7],
        ['63 (9x7)',  9, 7],
        ['44 (11x4)', 11, 4],
    ];

    /** Kata pembentuk nama tim (dikombinasikan agar cukup untuk 63 tim/turnamen). */
    private const NOUNS = [
        'Garuda', 'Elang', 'Rajawali', 'Singa', 'Barakuda', 'Nusantara', 'Badak',
        'Persada', 'Harimau', 'Naga', 'Merpati', 'Kobra', 'Serigala', 'Banteng',
        'Macan', 'Cendrawasih', 'Komodo', 'Buaya', 'Gajah', 'Panther', 'Rusa',
        'Beruang', 'Hiu', 'Paus', 'Lumba', 'Kuda', 'Kijang', 'Landak',
    ];

    private const SUFFIXES = [
        'FC', 'United', 'Jaya', 'Warriors', 'Boys', 'Muda', 'Putra', 'Perkasa',
        'Mandiri', 'Sakti', 'Raya', 'Nusa', 'Pratama', 'Bersatu',
    ];

    private const CITIES = [
        'Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Makassar', 'Semarang',
        'Palembang', 'Yogyakarta', 'Denpasar', 'Malang', 'Bogor', 'Bekasi',
        'Depok', 'Tangerang', 'Solo', 'Padang', 'Pekanbaru', 'Manado',
        'Balikpapan', 'Samarinda', 'Pontianak', 'Jambi', 'Batam', 'Cirebon',
    ];

    public function run(): void
    {
        foreach (self::CONFIGS as $index => [$label, $groupCount, $teamsPerGroup]) {
            $this->seedTournament($index, $label, $groupCount, $teamsPerGroup);
        }

        $this->command?->info("\nSelesai. Semua turnamen contoh Grup → Gugur dibuat. Buka tab Bracket masing-masing.");
    }

    private function seedTournament(int $configIndex, string $label, int $groupCount, int $teamsPerGroup): void
    {
        $name = "Demo Grup Gugur {$label} 2026";
        $totalTeams = $groupCount * $teamsPerGroup;

        $tournament = Tournament::firstOrCreate(
            ['name' => $name],
            [
                'division'   => 'Open',
                'venue'      => 'GOR Demo Bracket',
                'match_date' => Carbon::create(2026, 10, 1 + $configIndex, 8, 0),
                'created_by' => 1,
            ]
        );

        // Reset peserta agar seed & grup konsisten tiap run.
        TournamentTeam::where('tournament_id', $tournament->id)->delete();

        TournamentGroupSetting::updateOrCreate(
            ['tournament_id' => $tournament->id],
            [
                'teams_per_group'   => $teamsPerGroup,
                'group_count'       => $groupCount,
                'qualified_teams'   => [1, 2], // 2 lolos per grup (juara + runner-up)
                'relegated_teams'   => [],
                'locked'            => false,
                'league_round_type' => 'single',
            ]
        );

        // Daftarkan tim ke grup A, B, C, ... (teamsPerGroup tim per grup).
        $groupLabels = range('A', 'Z');
        $seed = 1;
        for ($g = 0; $g < $groupCount; $g++) {
            $groupLabel = $groupLabels[$g];
            for ($t = 0; $t < $teamsPerGroup; $t++) {
                [$teamName, $city] = $this->teamIdentity($configIndex, $g, $t);

                $team = Team::firstOrCreate(
                    ['slug' => Str::slug($teamName)],
                    ['name' => $teamName, 'city' => $city, 'country' => 'Indonesia']
                );
                // MatchGenerator hanya memakai tim approved.
                $team->update(['verification_status' => 'approved']);

                TournamentTeam::create([
                    'tournament_id'       => $tournament->id,
                    'team_id'             => $team->id,
                    'manager_token'       => Str::random(32),
                    'registration_status' => 'registered',
                    'seed'                => $seed++,
                    'group_label'         => $groupLabel,
                ]);
            }
        }

        // Setting bracket: grup → gugur, single match.
        AppSetting::updateOrCreate(
            ['key' => 'tournament_' . $tournament->id . '_bracket_settings'],
            ['value' => [
                'competition_type' => 'group_knockout',
                'match_type'       => 'single',
                'third_place'      => false,
                'group_count'      => $groupCount,
                'matches'          => [],
            ]]
        );

        // Generate match (fase grup + bracket gugur berisi placeholder posisi).
        $tournament->refresh();
        app(MatchGenerator::class)->generateForTournament($tournament);

        $knockout = $tournament->matches()->where('stage_type', 'knockout')->count();
        $bracketTeams = $groupCount * 2;
        $this->command?->info(sprintf(
            '%-10s id=%-3d peserta=%-2d  bracket=%2d tim  (%2d kartu gugur)',
            $label, $tournament->id, $totalTeams, $bracketTeams, $knockout
        ));
    }

    /**
     * Nama tim unik & deterministik per (turnamen, grup, urutan) agar slug tak
     * bentrok antar-konfigurasi dan tim tampil realistis.
     */
    private function teamIdentity(int $configIndex, int $groupIndex, int $teamIndex): array
    {
        $noun = self::NOUNS[($groupIndex * 7 + $teamIndex) % count(self::NOUNS)];
        $suffix = self::SUFFIXES[($configIndex + $teamIndex) % count(self::SUFFIXES)];
        $city = self::CITIES[($groupIndex + $teamIndex + $configIndex) % count(self::CITIES)];

        // Prefix konfigurasi (huruf) menjaga keunikan slug lintas turnamen.
        $prefix = chr(ord('A') + ($configIndex % 26));
        $name = "{$noun} {$suffix} {$prefix}{$groupIndex}" . ($teamIndex + 1);

        return [$name, $city];
    }
}
