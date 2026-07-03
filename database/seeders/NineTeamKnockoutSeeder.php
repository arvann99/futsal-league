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
 * Seeder demo: turnamen GUGUR MURNI dengan 9 tim (bukan pangkat 2) untuk
 * memvisualkan bagan bye pada halaman Bracket Gugur.
 *
 * Menghasilkan: 1 turnamen mode 'tournament' (knockout tanpa fase grup),
 * 9 tim approved dengan seed 1..9, setting bracket single-match, lalu
 * MatchGenerator::generateForTournament() dijalankan sehingga match langsung
 * tersimpan. Idempotent — aman dijalankan berulang (di-reset tiap run).
 */
class NineTeamKnockoutSeeder extends Seeder
{
    public function run(): void
    {
        $teamData = [
            ['name' => 'Garuda FC',          'city' => 'Jakarta'],
            ['name' => 'Elang Jaya',         'city' => 'Surabaya'],
            ['name' => 'Rajawali United',    'city' => 'Bandung'],
            ['name' => 'Singa Merah',        'city' => 'Medan'],
            ['name' => 'Barakuda FC',        'city' => 'Makassar'],
            ['name' => 'Nusantara Warriors', 'city' => 'Semarang'],
            ['name' => 'Badak Hitam',        'city' => 'Palembang'],
            ['name' => 'Persada Boys',       'city' => 'Yogyakarta'],
            ['name' => 'Harimau Muda',       'city' => 'Denpasar'],
        ];

        // --- Turnamen (reset bila sudah ada) ---
        $tournament = Tournament::firstOrCreate(
            ['name' => 'Demo Gugur 9 Tim 2026'],
            [
                'division'   => 'Open',
                'venue'      => 'GOR Demo Bracket',
                'match_date' => Carbon::create(2026, 9, 1, 8, 0),
                'created_by' => 1,
            ]
        );
        $this->command?->info("Turnamen: {$tournament->name} (id={$tournament->id})");

        // Bersihkan peserta lama agar seed 1..9 konsisten tiap run.
        TournamentTeam::where('tournament_id', $tournament->id)->delete();

        // groupSetting WAJIB ada — MatchGenerator return dini tanpa ini.
        // group_count tidak dipakai pada mode 'tournament', tapi tetap diisi.
        TournamentGroupSetting::updateOrCreate(
            ['tournament_id' => $tournament->id],
            [
                'teams_per_group'   => 0,
                'group_count'       => 0,
                'qualified_teams'   => [1, 2],
                'relegated_teams'   => [],
                'locked'            => false,
                'league_round_type' => 'single',
            ]
        );

        // --- Tim approved + daftarkan dengan seed 1..9 ---
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
            ]);
        }
        $this->command?->info('9 tim approved didaftarkan dengan seed 1..9.');

        // --- Setting bracket: gugur murni, single match ---
        $key = 'tournament_' . $tournament->id . '_bracket_settings';
        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => [
                'competition_type' => 'tournament',
                'match_type'       => 'single',
                'third_place'      => false,
                'group_count'      => 0,
                'matches'          => [],
            ]]
        );

        // --- Generate match ---
        $tournament->refresh();
        app(MatchGenerator::class)->generateForTournament($tournament);

        $matches = $tournament->matches()
            ->where('stage_type', 'knockout')
            ->orderBy('bracket_match_id')
            ->get();

        $this->command?->info("\n=== BAGAN GUGUR 9 TIM (tersimpan) ===");
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
