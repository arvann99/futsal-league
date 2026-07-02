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
 * Seeder turnamen "Piala Dunia" bergaya group_knockout:
 *   - 8 grup (A–H), 4 tim per grup = 32 tim.
 *   - Sistem kompetisi Grup → Gugur (fase grup round-robin lalu babak gugur).
 *   - Ranking 1 & 2 tiap grup lolos ke babak gugur (16 besar).
 *
 * Jalankan hanya seeder ini:
 *   php artisan db:seed --class=Database\\Seeders\\PialaDuniaTournamentSeeder
 */
class PialaDuniaTournamentSeeder extends Seeder
{
    public function run(): void
    {
        // 32 negara peserta, disusun per pot lalu ditempatkan A–H (4/grup).
        // Urutan array = urutan penempatan grup: 8 pertama → slot 1 tiap grup,
        // 8 berikutnya → slot 2 tiap grup, dst. (mirip distribusi undian Piala Dunia).
        $nations = [
            // Pot 1 (unggulan) → slot 1 grup A..H
            'Brasil', 'Argentina', 'Prancis', 'Inggris',
            'Spanyol', 'Portugal', 'Belanda', 'Jerman',
            // Pot 2 → slot 2 grup A..H
            'Kroasia', 'Italia', 'Uruguay', 'Belgia',
            'Meksiko', 'Amerika Serikat', 'Jepang', 'Senegal',
            // Pot 3 → slot 3 grup A..H
            'Maroko', 'Swiss', 'Kolombia', 'Denmark',
            'Korea Selatan', 'Australia', 'Ekuador', 'Serbia',
            // Pot 4 → slot 4 grup A..H
            'Ghana', 'Kamerun', 'Polandia', 'Arab Saudi',
            'Iran', 'Tunisia', 'Kanada', 'Qatar',
        ];

        $creatorId = Tournament::query()->value('created_by')
            ?? \App\Models\User::query()->value('id')
            ?? 1;

        // --- Turnamen ---
        $tournament = Tournament::firstOrCreate(
            ['name' => 'Piala Dunia Futsal 2026'],
            [
                'division'   => 'Open',
                'venue'      => 'Istora Senayan, Jakarta',
                'match_date' => Carbon::create(2026, 11, 21, 8, 0),
                'created_by' => $creatorId,
            ]
        );
        $this->command?->info("Turnamen: {$tournament->name} (id={$tournament->id})");

        // --- Pengaturan grup: 8 grup × 4 tim, ranking 1 & 2 lolos ---
        TournamentGroupSetting::updateOrCreate(
            ['tournament_id' => $tournament->id],
            [
                'teams_per_group'   => 4,
                'group_count'       => 8,
                'qualified_teams'   => [1, 2],
                'relegated_teams'   => [],
                'league_round_type' => 'single',
                'locked'            => true,
            ]
        );

        // --- Pengaturan bracket: mode Grup → Gugur ---
        AppSetting::updateOrCreate(
            ['key' => 'tournament_' . $tournament->id . '_bracket_settings'],
            ['value' => [
                'match_type'            => 'single',
                'home_away_calculation' => 'aggregate',
                'third_place'           => true,
                'competition_type'      => 'group_knockout',
                'group_count'           => 8,
                'matches'               => [], // diisi otomatis oleh MatchGenerator
            ]]
        );

        $groupLabels = range('A', 'H');

        // --- Buat tim & daftarkan ke grup ---
        foreach ($nations as $index => $name) {
            $team = Team::firstOrCreate(
                ['slug' => Str::slug('Timnas ' . $name)],
                [
                    'name'                => $name,
                    'city'                => $name,
                    'country'             => $name,
                    'verification_status' => 'approved',
                ]
            );

            // Grup ditentukan siklis: indeks 0→A,1→B,...,7→H,8→A,... sehingga
            // tiap grup mendapat tepat 4 tim (8 × 4 = 32). Ditandai manual agar
            // penempatan hasil "undian" ini tidak ditimpa auto-assign.
            $groupLabel = $groupLabels[$index % 8];

            TournamentTeam::updateOrCreate(
                ['tournament_id' => $tournament->id, 'team_id' => $team->id],
                [
                    'registration_status'     => 'registered',
                    'group_label'             => $groupLabel,
                    'group_assigned_manually' => true,
                    'seed'                    => $index + 1,
                ]
            );
        }

        $this->command?->info('  → 32 tim didaftarkan ke 8 grup (A–H), 4 tim/grup.');

        // --- Generate jadwal grup + bracket gugur (alur asli aplikasi) ---
        app(MatchGenerator::class)->generateForTournament($tournament);

        $matchCount = \App\Models\TournamentMatch::where('tournament_id', $tournament->id)->count();
        $this->command?->info("  → Jadwal & bracket dibuat: {$matchCount} pertandingan.");
        $this->command?->info('Selesai! Piala Dunia Futsal 2026 siap (8 grup × 4 tim).');
    }
}
