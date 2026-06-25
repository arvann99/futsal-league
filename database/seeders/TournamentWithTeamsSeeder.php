<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentTeam;
use App\Models\TournamentTeamPlayer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TournamentWithTeamsSeeder extends Seeder
{
    public function run(): void
    {
        // Logo files yang tersedia di storage/app/public/team-logos/
        $logos = [
            'AC-Milan.png',
            'All-Blacks.png',
            'Arizona-Cardinals-2048x1279.png',
            'Boston-Celtics.png',
            'Brisbane-Lions.png',
            'LA-Lakers.png',
            'Manchester-United.png',
            'New-York-Yankees.png',
            'New_York_Mets.svg_.png',
            'Pittsburg-Steelers.png',
            'Royal-Challengers-Bangalore.png',
            '25643150_7020917.jpg',
            '28240104_7412601.jpg',
            '342502100_ea70f164-3907-4fb1-ad41-5ce342185189.jpg',
            '34991443_8224881.jpg',
            '35063212_8255944.jpg',
        ];

        // 16 Tim futsal dengan nama Indonesia
        $teamData = [
            ['name' => 'Garuda FC',             'city' => 'Jakarta',    'logo' => 'AC-Milan.png'],
            ['name' => 'Elang Jaya',            'city' => 'Surabaya',   'logo' => 'Manchester-United.png'],
            ['name' => 'Rajawali United',       'city' => 'Bandung',    'logo' => 'All-Blacks.png'],
            ['name' => 'Singa Merah',           'city' => 'Medan',      'logo' => 'Boston-Celtics.png'],
            ['name' => 'Barakuda FC',           'city' => 'Makassar',   'logo' => 'LA-Lakers.png'],
            ['name' => 'Nusantara Warriors',    'city' => 'Semarang',   'logo' => 'Brisbane-Lions.png'],
            ['name' => 'Badak Hitam',           'city' => 'Palembang',  'logo' => 'Arizona-Cardinals-2048x1279.png'],
            ['name' => 'Persada Boys',          'city' => 'Yogyakarta', 'logo' => 'Pittsburg-Steelers.png'],
            ['name' => 'Harimau Muda',          'city' => 'Denpasar',   'logo' => 'Royal-Challengers-Bangalore.png'],
            ['name' => 'Bintang Timur',         'city' => 'Malang',     'logo' => 'New-York-Yankees.png'],
            ['name' => 'Satria FC',             'city' => 'Pekanbaru',  'logo' => 'New_York_Mets.svg_.png'],
            ['name' => 'Merah Putih FC',        'city' => 'Banjarmasin','logo' => '25643150_7020917.jpg'],
            ['name' => 'Timur Tengah FC',       'city' => 'Samarinda',  'logo' => '28240104_7412601.jpg'],
            ['name' => 'Ksatria Muda',          'city' => 'Manado',     'logo' => '342502100_ea70f164-3907-4fb1-ad41-5ce342185189.jpg'],
            ['name' => 'Putera Bangsa',         'city' => 'Pontianak',  'logo' => '34991443_8224881.jpg'],
            ['name' => 'Benteng FC',            'city' => 'Mataram',    'logo' => '35063212_8255944.jpg'],
        ];

        // 3 Turnamen
        $tournaments = [
            [
                'name'       => 'Piala Walikota Cup 2026',
                'division'   => 'Open',
                'venue'      => 'GOR Serbaguna Jakarta Pusat',
                'match_date' => Carbon::create(2026, 7, 15, 8, 0),
                'team_count' => 8,  // tim indeks 0–7
            ],
            [
                'name'       => 'Liga Futsal Regional Jawa 2026',
                'division'   => 'Senior',
                'venue'      => 'Sport Hall Surabaya',
                'match_date' => Carbon::create(2026, 8, 5, 9, 0),
                'team_count' => 12, // tim indeks 0–11
            ],
            [
                'name'       => 'Turnamen Kemerdekaan 17 Agustus 2026',
                'division'   => 'U-23',
                'venue'      => 'GOR Mandala Krida Yogyakarta',
                'match_date' => Carbon::create(2026, 8, 17, 7, 0),
                'team_count' => 16, // semua tim
            ],
        ];

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

        // Slot 11 pemain: 2 GK, 3 Anchor, 4 Flank, 2 Pivot
        $slots = [
            ['position' => 'GK',     'number' => 1],
            ['position' => 'GK',     'number' => 12],
            ['position' => 'Anchor', 'number' => 2],
            ['position' => 'Anchor', 'number' => 3],
            ['position' => 'Anchor', 'number' => 4],
            ['position' => 'Flank',  'number' => 5],
            ['position' => 'Flank',  'number' => 6],
            ['position' => 'Flank',  'number' => 7],
            ['position' => 'Flank',  'number' => 8],
            ['position' => 'Pivot',  'number' => 9],
            ['position' => 'Pivot',  'number' => 10],
        ];

        // --- Buat atau ambil semua tim ---
        $teams = [];
        foreach ($teamData as $data) {
            $team = Team::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name'                => $data['name'],
                    'city'                => $data['city'],
                    'country'             => 'Indonesia',
                    'logo'                => 'team-logos/' . $data['logo'],
                    'verification_status' => 'approved',
                ]
            );
            $teams[] = $team;
            $this->command?->info("Tim: {$team->name} (id={$team->id})");
        }

        // --- Buat turnamen & daftarkan tim ---
        foreach ($tournaments as $tData) {
            $tournament = Tournament::firstOrCreate(
                ['name' => $tData['name']],
                [
                    'division'   => $tData['division'],
                    'venue'      => $tData['venue'],
                    'match_date' => $tData['match_date'],
                    'created_by' => 1,
                ]
            );
            $this->command?->info("\nTurnamen: {$tournament->name} (id={$tournament->id})");

            $selectedTeams = array_slice($teams, 0, $tData['team_count']);

            foreach ($selectedTeams as $team) {
                $tournamentTeam = TournamentTeam::firstOrCreate(
                    ['tournament_id' => $tournament->id, 'team_id' => $team->id],
                    [
                        'manager_token'       => Str::random(32),
                        'registration_status' => 'registered',
                    ]
                );

                // Seed pemain kalau belum ada
                if ($tournamentTeam->players()->count() === 0) {
                    $names = collect($firstNames)
                        ->shuffle()
                        ->take(count($slots))
                        ->map(fn ($f) => $f . ' ' . collect($lastNames)->random())
                        ->values();

                    foreach ($slots as $i => $slot) {
                        TournamentTeamPlayer::create([
                            'tournament_team_id' => $tournamentTeam->id,
                            'player_name'        => $names[$i],
                            'shirt_number'       => $slot['number'],
                            'positions'          => [$slot['position']],
                            'dominant_position'  => $slot['position'],
                            'is_captain'         => $slot['number'] === 10,
                            'status'             => 'active',
                            'registered_at'      => Carbon::now(),
                        ]);
                    }
                    $this->command?->info("  → {$team->name}: 11 pemain di-seed");
                } else {
                    $this->command?->warn("  → {$team->name}: pemain sudah ada, dilewati");
                }
            }
        }

        $this->command?->info("\nSelesai! " . count($teams) . " tim, " . count($tournaments) . " turnamen.");
    }
}
