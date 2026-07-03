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
 * Seeder demo: turnamen GRUP → GUGUR dengan 3 grup × 3 tim dan seluruh 9 tim
 * lolos ke babak gugur (qualified_teams [1,2,3]) — konfigurasi yang memicu
 * keluhan bagan miring: 9 tim bukan pangkat 2 sehingga bracket ber-bye.
 *
 * Menghasilkan: 1 turnamen mode 'group_knockout', 9 tim approved pada grup
 * A/B/C, fase grup round-robin, dan bracket gugur ber-placeholder posisi grup
 * dengan bye terkumpul di ronde pertama (prioritas per peringkat). Idempotent —
 * aman dijalankan berulang (di-reset tiap run).
 */
class GroupKnockoutNineTeamSeeder extends Seeder
{
    public function run(): void
    {
        $teamData = [
            // Grup A
            ['name' => 'Garuda FC',          'city' => 'Jakarta',     'group' => 'A'],
            ['name' => 'Elang Jaya',         'city' => 'Surabaya',    'group' => 'A'],
            ['name' => 'Rajawali United',    'city' => 'Bandung',     'group' => 'A'],
            // Grup B
            ['name' => 'Singa Merah',        'city' => 'Medan',       'group' => 'B'],
            ['name' => 'Barakuda FC',        'city' => 'Makassar',    'group' => 'B'],
            ['name' => 'Nusantara Warriors', 'city' => 'Semarang',    'group' => 'B'],
            // Grup C
            ['name' => 'Badak Hitam',        'city' => 'Palembang',   'group' => 'C'],
            ['name' => 'Persada Boys',       'city' => 'Yogyakarta',  'group' => 'C'],
            ['name' => 'Harimau Muda',       'city' => 'Denpasar',    'group' => 'C'],
        ];

        // --- Turnamen (reset bila sudah ada) ---
        $tournament = Tournament::firstOrCreate(
            ['name' => 'Demo Grup Gugur 3x3 2026'],
            [
                'division'   => 'Open',
                'venue'      => 'GOR Demo Bracket',
                'match_date' => Carbon::create(2026, 10, 1, 8, 0),
                'created_by' => 1,
            ]
        );
        $this->command?->info("Turnamen: {$tournament->name} (id={$tournament->id})");

        // Bersihkan peserta lama agar seed & grup konsisten tiap run.
        TournamentTeam::where('tournament_id', $tournament->id)->delete();

        TournamentGroupSetting::updateOrCreate(
            ['tournament_id' => $tournament->id],
            [
                'teams_per_group'   => 3,
                'group_count'       => 3,
                'qualified_teams'   => [1, 2, 3],
                'relegated_teams'   => [],
                'locked'            => false,
                'league_round_type' => 'single',
            ]
        );

        // --- Tim approved + daftarkan ke grup A/B/C dengan seed 1..9 ---
        foreach ($teamData as $i => $data) {
            $team = Team::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'city' => $data['city'],
                    'country' => 'Indonesia',
                ]
            );
            // Pastikan approved (MatchGenerator hanya pakai tim approved).
            $team->update(['verification_status' => 'approved']);

            TournamentTeam::create([
                'tournament_id'       => $tournament->id,
                'team_id'             => $team->id,
                'manager_token'       => Str::random(32),
                'registration_status' => 'registered',
                'seed'                => $i + 1,
                'group_label'         => $data['group'],
            ]);
        }
        $this->command?->info('9 tim approved didaftarkan ke grup A/B/C (3 tim per grup).');

        // --- Setting bracket: grup → gugur, single match ---
        $key = 'tournament_' . $tournament->id . '_bracket_settings';
        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => [
                'competition_type' => 'group_knockout',
                'match_type'       => 'single',
                'third_place'      => false,
                'group_count'      => 3,
                'matches'          => [],
            ]]
        );

        // --- Generate match (fase grup + bracket gugur) ---
        $tournament->refresh();
        app(MatchGenerator::class)->generateForTournament($tournament);

        $groupCount = $tournament->matches()->where('stage_type', 'group')->count();
        $this->command?->info("\nMatch fase grup: {$groupCount} (round robin per grup).");

        $matches = $tournament->matches()
            ->where('stage_type', 'knockout')
            ->orderBy('bracket_match_id')
            ->get();

        $this->command?->info("\n=== BAGAN GUGUR 9 TIM LOLOS (tersimpan) ===");
        $this->command?->info('Total match knockout: ' . $matches->count());
        foreach ($matches as $m) {
            $this->command?->info(sprintf(
                '  M%-2d [%-13s] %-20s vs %-20s -> %s',
                $m->bracket_match_id,
                $m->round_name,
                $m->home_team_key,
                $m->away_team_key,
                $m->next_bracket_match_id ? 'M' . $m->next_bracket_match_id : 'JUARA'
            ));
        }

        $this->command?->info("\nSelesai. Buka halaman Bracket Gugur turnamen id={$tournament->id}.");
    }
}
