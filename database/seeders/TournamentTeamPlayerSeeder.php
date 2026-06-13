<?php

namespace Database\Seeders;

use App\Models\TournamentTeam;
use App\Models\TournamentTeamPlayer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seed 11 pemain futsal per tim untuk setiap tournament team yang belum punya
 * pemain terdaftar. Aman dijalankan berulang: tim yang sudah punya pemain
 * dilewati.
 */
class TournamentTeamPlayerSeeder extends Seeder
{
    public function run(): void
    {
        $firstNames = [
            'Ahmad', 'Rizky', 'Budi', 'Andre', 'Yoga', 'Dedi', 'Fajar', 'Gilang',
            'Hendra', 'Ilham', 'Joko', 'Krisna', 'Lukman', 'Naufal', 'Oka',
            'Putra', 'Raka', 'Surya', 'Taufik', 'Bayu', 'Dimas', 'Eko', 'Farhan',
            'Galih', 'Iqbal', 'Reza', 'Aldi', 'Vino', 'Wahyu', 'Zaki',
        ];

        $lastNames = [
            'Pratama', 'Saputra', 'Hidayat', 'Santoso', 'Wijaya', 'Ramadhan',
            'Nugroho', 'Setiawan', 'Firmansyah', 'Kurniawan', 'Maulana',
            'Siregar', 'Gunawan', 'Permana', 'Hakim', 'Prasetyo',
        ];

        // Komposisi 11 pemain: 2 GK, 3 Anchor, 4 Flank, 2 Pivot.
        // Nomor punggung mengikuti urutan posisi; kapten = pemain bernomor 10.
        $slots = [
            ['position' => 'GK', 'number' => 1],
            ['position' => 'GK', 'number' => 12],
            ['position' => 'Anchor', 'number' => 2],
            ['position' => 'Anchor', 'number' => 3],
            ['position' => 'Anchor', 'number' => 4],
            ['position' => 'Flank', 'number' => 5],
            ['position' => 'Flank', 'number' => 6],
            ['position' => 'Flank', 'number' => 7],
            ['position' => 'Flank', 'number' => 8],
            ['position' => 'Pivot', 'number' => 9],
            ['position' => 'Pivot', 'number' => 10],
        ];

        $teams = TournamentTeam::with('team')->withCount('players')->get();
        $seeded = 0;

        foreach ($teams as $tournamentTeam) {
            if ($tournamentTeam->players_count > 0) {
                continue;
            }

            $names = collect($firstNames)
                ->shuffle()
                ->take(count($slots))
                ->map(fn ($first) => $first . ' ' . collect($lastNames)->random())
                ->values();

            foreach ($slots as $index => $slot) {
                TournamentTeamPlayer::create([
                    'tournament_team_id' => $tournamentTeam->id,
                    'player_name' => $names[$index],
                    'shirt_number' => $slot['number'],
                    'positions' => [$slot['position']],
                    'dominant_position' => $slot['position'],
                    'is_captain' => $slot['number'] === 10,
                    'status' => 'active',
                    'registered_at' => Carbon::now(),
                ]);
            }

            $seeded++;
            $this->command?->info(sprintf(
                'Seeded 11 pemain untuk %s (tournament_team_id=%d)',
                $tournamentTeam->team?->name ?? 'Tim ' . $tournamentTeam->id,
                $tournamentTeam->id
            ));
        }

        if ($seeded === 0) {
            $this->command?->warn('Semua tim sudah punya pemain — tidak ada yang di-seed.');
        }
    }
}
