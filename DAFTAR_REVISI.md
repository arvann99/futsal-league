# 📋 Daftar Revisi — Futsal League

> Disusun: 10 Juni 2026 • Centang `[x]` jika item sudah selesai dikerjakan.
>
> Format: setiap item punya ID (R1–R22) supaya mudah dirujuk, misalnya: *"kerjakan R6"*.

**Progress: 8 / 22 selesai** *(update 12 Juni 2026: R1, R2, R4, R6, R7, R8, R9, R10 — lihat juga daftar "Selesai di luar daftar" di bawah)*

---

## ✅ Pra-Revisi (infrastruktur development)

- [x] **R0 — Sidebar admin turnamen dibuat dinamis & seragam** *(selesai 11 Juni 2026)*
  - Satu komponen bersama: `resources/views/tournaments/partials/admin-sidebar.blade.php`
  - Item aktif otomatis via `request()->routeIs()` — menu sama di semua halaman
  - Sidebar statis dihapus dari 9 file (manage, settings, settings-group, settings-points, settings-bracket, bracket-admin, schedule-admin, standings, schedule/partials/base)
  - 3 halaman participants (index/create/edit) yang sebelumnya tanpa sidebar kini ikut layout yang sama
  - Menu mobile (overlay + tombol mengambang) kini berfungsi di semua halaman, bukan hanya manage
  - Link mati `#` (Ikhtisar, Kelola Jadwal, dll. di sebagian halaman) kini mengarah ke route benar; "Akses Manager" dihapus (fitur sudah pindah ke participants); "Verifikasi Berkas" ditandai *Segera* sampai R18 dikerjakan

---

## A. Bug & Perbaikan Tampilan

- [x] **R1 — Bracket 3 tim tidak muncul + peringkat 1 langsung ke final** `[BUG + ATURAN BARU]`
  - View bracket tidak tampil saat isi bracket gugur hanya 3 tim
  - Aturan baru: jika 3 tim → **peringkat 1 bye langsung ke Final**, peringkat 2 vs 3 main Semifinal
  - Catatan: generator saat ini memberi bye ke tim *terakhir*, bukan peringkat 1
  - 📁 `app/Http/Controllers/TournamentController.php` → `generateDefaultBracketMatches()` (baris ±1162–1270, logika bye ±1188–1213)
  - 📁 `resources/views/tournaments/partials/bracket-section.blade.php`
  - 📁 `resources/views/tournaments/schedule/bracket.blade.php`

- [x] **R2 — Bagan klasemen tampilkan slot kosong sebelum peserta terdaftar** `[PERBAIKAN UI]`
  - Saat belum ada peserta, jangan tampilkan "Belum ada data" — render slot placeholder sesuai `group_count × teams_per_group`
  - 📁 `resources/views/tournaments/standings.blade.php`
  - 📁 `TournamentController.php` → `buildStandingsGroups()` (±2186)

- [ ] **R3 — Cek pengaturan poin: poin kalah tidak pernah diterapkan** `[BUG]`
  - `buildStandingsGroups()` tidak pernah menambahkan `$pointSettings['loss']` ke tim yang kalah (±2242–2255)
  - Samakan urutan default tiebreaker yang beda antara `pointsSettings()` (±375) dan `resolvePointSettings()` (±2360)
  - 📁 `app/Http/Controllers/TournamentController.php`

- [x] **R4 — Bracket saat tipe Liga: halaman kosong / menu tetap tampil** `[BUG/UX]` *(selesai 11 Juni 2026)*
  - Menu "Bagan Bracket" tetap muncul untuk tipe `league` tapi halamannya kosong
  - ✔️ Diselesaikan bersamaan pemisahan 3 sistem kompetisi: tipe `league` menyembunyikan menu Bracket Gugur di sidebar dan `bracketAdmin()` redirect ke klasemen
  - 📁 `resources/views/tournaments/manage.blade.php` (±55) • `TournamentController.php` → `bracketAdmin()` (±604–614)

- [ ] **R5 — QA Liga Reguler dengan Playoff** `[PENGECEKAN]`
  - Tes menyeluruh alur `league_playoff` (promosi / degradasi / keduanya)
  - Catatan: saat promosi+degradasi aktif bersamaan, `bracketAdmin()` selalu default ke mode promotion (±633–636)
  - 📁 `app/Http/Controllers/TournamentController.php`

---

## B. Mekanik Pertandingan Knockout (engine inti)

- [x] **R6 — Jika imbang di knockout → adu penalti, pemenang +1 gol** `[FITUR BARU — KRITIS]` *(selesai 11–12 Juni 2026)*
  - Berlaku di: Kelola Jadwal & Skor, bracket gugur, dan Live Match
  - ✔️ Match knockout (termasuk Final, single leg maupun leg 2) yang seri masuk status `penalty_shootout`; tombol pemain berubah jadi "Penalti Gol / Penalti Gagal"; End Match ditolak selama skor penalti seri; pemenang otomatis maju ke babak berikut
  - ⚠️ Deviasi dari rencana awal: skor utama TIDAK ditambah +1 — skor penalti disimpan terpisah (`home/away_penalty_score`) dan ditampilkan sebagai "2 (4) - (3) 2" (lebih informatif); edit manual hasil seri di knockout diblokir (harus via logger)
  - Sub-tugas:
    - [x] Tambah field penalti di tabel `matches` (migration `add_home_away_support_to_matches_table`)
    - [x] Event type penalti (`penalty_goal`/`penalty_miss`) di `storeMatchEvent()` + UI Live Match Logger
    - [x] Validasi di `endMatch()` via helper `concludeMatch()`: match knockout tidak boleh selesai imbang
    - [x] `updateBracketForTournament()` di-redesign: pemenang (termasuk via penalti) maju berbasis label `Pemenang M{id}`
  - 📁 `app/Http/Controllers/TournamentController.php` • `app/Services/TieResolver.php` (baru) • `database/migrations/` • `resources/views/admin/tournaments/schedule/manage.blade.php`

- [x] **R7 — Leg ke-2 di Kelola Jadwal & Skor (Home & Away)** `[FITUR BARU]` *(selesai 11–12 Juni 2026)*
  - Opsi `match_type=home_away` kini berfungsi — 2 leg per pasangan, home/away dibalik di Leg 2 (lineup ikut bertukar)
  - ✔️ Final & Third Place tetap single match; Leg 2 terkunci sampai Leg 1 Full Time; End Match Leg 1 otomatis beralih ke Leg 2; ganti mode di pengaturan auto-regenerate jadwal (selama belum ada hasil)
  - Sub-tugas:
    - [x] Kolom `leg` di tabel `matches` (null = single; pasangan leg digrup via `bracket_match_id`)
    - [x] Generate leg 2 di `MatchGenerator::buildBracketMatchesFromArray()`
    - [x] Tampilan di Kelola Jadwal & Skor: **satu row per tie** dengan 2 jadwal (tanggal/jam per leg), skor per leg + agregat, dan **tab Leg 1 / Leg 2** di Live Match Logger
  - 📁 `app/Services/MatchGenerator.php` • `app/Services/TieResolver.php` • `resources/views/admin/tournaments/settings/partials/bracket-settings-panel.blade.php` • `schedule/partials/match-table.blade.php` • `schedule/manage.blade.php`

- [x] **R8 — Dropdown aturan pemenang 2 leg: Agregat / Win per Match** `[FITUR BARU]` *(selesai 11 Juni 2026)*
  - ✔️ Radio sub-opsi muncul saat Home & Away dipilih: **Agregat Skor** (total gol kedua leg) / **Jumlah Kemenangan** (menang per leg), tersimpan sebagai `home_away_calculation` di bracket settings
  - ✔️ Seri menurut mode terpilih setelah Leg 2 → langsung adu penalti (tanpa aturan gol tandang); gol extra time dicatat sebagai gol biasa Leg 2 sebelum End Match (tanpa fase ET eksplisit); pemenang penalti pakai skor penalti terpisah (bukan +1 gol — lihat catatan R6)
  - 📁 `bracket-settings-panel.blade.php` • `TournamentController.php` → `updateBracketSettings()` / `concludeMatch()` • `app/Services/TieResolver.php` → `tieOutcome()`

- [x] **R9 — Mode knockout: bye / single match otomatis maju** `[FITUR BARU]` *(selesai 11 Juni 2026)*
  - ✔️ Tim ber-bye tidak lagi membuat card/match — namanya langsung dipromosikan sebagai peserta card ronde berikutnya saat generate (`buildBracketStructure()`), team_id ikut terpasang otomatis; berlaku juga di mode Home & Away (bye tidak menghasilkan leg)
  - Teruji: 3 tim → seed 1 langsung ke Final, seed 2 vs 3 di Semifinal
  - 📁 `app/Services/MatchGenerator.php` • `TournamentController.php` → `updateBracketForTournament()`

---

## C. Format Kompetisi

- [x] **R10 — Tipe kompetisi baru: Knockout langsung (bracket gugur tanpa fase grup)** `[FITUR BARU]` *(selesai 11 Juni 2026)*
  - ✔️ Diimplementasikan dengan memisahkan 3 sistem kompetisi (bukan menambah tipe ke-4): tipe `tournament` = **gugur murni tanpa fase grup** — bracket dibuat otomatis dari tim `verification_status=approved` (`MatchGenerator::buildKnockoutMatchesFromTeams()`), team_id langsung terpasang, struktur disinkron ke bracket settings; `league` = klasemen saja; `league_playoff` = keduanya
  - Sub-tugas:
    - [x] Pilihan tipe di panel pengaturan + validasi `competition_type`
    - [x] Cabang generate di `MatchGenerator::generateForTournament()`
    - [x] Switch view jadwal + sidebar menu kondisional per tipe
  - 📁 `TournamentController.php` • `MatchGenerator.php` • `group-settings-panel.blade.php` • partial sidebar

- [ ] **R11 — Liga: pilihan Setengah Kompetisi vs Kompetisi Penuh (kandang-tandang)** `[FITUR BARU]`
  - Saat ini hanya single round robin; opsi penuh menggandakan jumlah match di Kelola Jadwal & Skor
  - 📁 `app/Services/MatchGenerator.php` → `buildLeagueStageMatches()` (±140)

---

## D. Klasemen & Poin

- [ ] **R12 — Standar poin liga berlaku semua tipe + hasil bracket langsung masuk klasemen** `[PENYEMPURNAAN]`
  - Untuk tipe knockout langsung (R10), hasil bracket harus memengaruhi klasemen
  - Saat ini klasemen hanya menghitung stage `group` dan `league` (±2217) — knockout dikecualikan
  - 📁 `TournamentController.php` → `buildStandingsGroups()`

- [ ] **R13 — Tie-breakers langsung memengaruhi klasemen + urutan prioritas** `[VERIFIKASI]`
  - Mekanisme sudah jalan via `compareTeamRows()` (±2301), dihitung ulang tiap match final
  - Verifikasi: urutan prioritas tersimpan sesuai pilihan admin (saat ini ikut urutan checkbox form, bukan prioritas yang bisa diatur/di-drag)
  - 📁 `TournamentController.php` • `resources/views/tournaments/partials/points-settings-panel.blade.php`

---

## E. Peserta, Grup & Undian

- [ ] **R14 — Sinkronisasi pengaturan grup ↔ manajemen peserta** `[PENYEMPURNAAN]`
  - Jumlah tim & grup di settings harus konsisten dengan jumlah peserta terdaftar (validasi dua arah)
  - Saat ini kelebihan tim ditumpuk ke grup terakhir tanpa peringatan
  - 📁 `TournamentController.php` → `assignGroupLabelsToTournamentTeams()` (±155–184)

- [ ] **R15 — Penempatan slot tim ke grup secara manual** `[FITUR BARU]`
  - Admin bisa memilih tim masuk grup mana (saat ini otomatis berdasarkan seed)
  - 📁 Halaman pengaturan grup / manajemen peserta

- [ ] **R16 — Spin / undian tim untuk grup** `[FITUR BARU]`
  - Fitur undian (animasi spin), hasil tersimpan ke `group_label` di `tournament_teams`
  - 📁 Fitur baru — view + endpoint penyimpanan hasil undian

- [ ] **R17 — Manajemen peserta + card pemain** `[PENYEMPURNAAN]`
  - Tampilkan list/card pemain per tim di halaman peserta sisi admin (data sudah ada di `tournament_team_players`, diisi manager via portal official)
  - 📁 `resources/views/tournaments/participants/index.blade.php` • `TournamentParticipantController.php`

---

## F. Verifikasi & Portal Manager

- [ ] **R18 — Verifikasi berkas per tim + kunci setelah disetujui + card list pemain** `[FITUR BARU]`
  - Fondasi sudah ada: kolom `verification_status` (pending/approved/rejected) + badge di halaman peserta
  - Sub-tugas:
    - [ ] Upload berkas per tim
    - [ ] Tombol Approve / Reject untuk admin
    - [ ] **Kunci data setelah approved** — manager tidak bisa edit pemain/ofisial (guard di `OfficialPlayerController` & `OfficialTeamOfficialController`)
    - [ ] Card tim menampilkan list pemain
  - 📁 `app/Http/Controllers/OfficialPlayerController.php` • `OfficialTeamOfficialController.php` • `participants/index.blade.php` (±59)

- [ ] **R19 — Live Match terhubung card pemain tim** `[FITUR BARU]` `[SEBAGIAN — 12 Juni 2026]`
  - Sub-tugas:
    - [x] Roster Live Match Logger dari pemain asli (`buildMatchRoster()` membaca `tournament_team_players`: filter aktif, urut nomor punggung, tanda kapten; fallback kartu tim bila roster kosong; lineup otomatis bertukar sisi di Leg 2)
    - [ ] Tambah relasi `player_id` di `match_events` (saat ini masih `player_name` string)
    - [ ] Akumulasi statistik (gol/kartu) ke card pemain di halaman peserta
  - 📁 `TournamentController.php` • `app/Models/MatchEvent.php` • migration `match_events`

- [ ] **R20 — Sistem manager view jadwal** `[QA/PENYEMPURNAAN]`
  - Sudah ada di `/official/schedule` — sifatnya penyempurnaan
  - Catatan: match tanpa tanggal tidak tampil karena filter `whereNotNull('match_date')` (±103)
  - 📁 `app/Http/Controllers/OfficialAuthController.php` → `schedule()` (±90)

---

## G. Platform (perubahan arsitektur — paling besar)

- [ ] **R21 — Multi-admin: daftar via Gmail masing-masing** `[FITUR BESAR]`
  - Saat ini hanya ada login (tidak ada registrasi); `tournaments.created_by` sudah ada tapi data tidak di-scope per admin
  - Sub-tugas:
    - [ ] Halaman registrasi / Google OAuth (Laravel Socialite)
    - [ ] Scoping: semua query turnamen & tim difilter per admin yang login
  - 📁 `app/Http/Controllers/AuthController.php` • `routes/web.php` • semua controller turnamen

- [ ] **R22 — Paket berlangganan Free / Reguler / Ultimate + anti-abuse** `[FITUR BESAR]`
  - **Free**: maks 1 turnamen, 8 tim • **Reguler**: maks 3 turnamen, 32 tim • **Ultimate**: unlimited
  - Sub-tugas:
    - [ ] Tabel plans/subscriptions + relasi ke user
    - [ ] Enforcement limit di `TournamentController::store` & `TournamentParticipantController::store`
    - [ ] Anti-abuse: verifikasi email + device fingerprint + rate limit per IP (+ verifikasi HP/pembayaran untuk upgrade)
  - ⚠️ Catatan: block IP saja **lemah** (IP dinamis, VPN, IP kantor/warnet bersama) — pakai kombinasi beberapa lapis; tidak ada yang 100% tapi cukup mahal untuk diakali
  - Bergantung pada: R21

---

## ➕ Selesai di luar daftar (11–12 Juni 2026)

Perbaikan/fitur yang dikerjakan menyertai R6–R10 tapi tidak ada di daftar asli:

- [x] **Urutan jadwal mengikuti progresi bracket** (QF → SF → Final) — sebelumnya terurut alfabetis sehingga Final tampil paling atas
- [x] **Satu row jadwal per tie Home & Away** dengan 2 jadwal (tanggal/jam per leg), skor per leg + agregat, dan panel edit berisi form per leg
- [x] **Tab Leg 1 / Leg 2 di Live Match Logger** — tab Leg 2 terkunci sampai Leg 1 selesai; End Match Leg 1 otomatis pindah ke tab Leg 2
- [x] **Event logger tanpa reload halaman** — goal/own goal/kartu/penalti dikirim via fetch, scoreboard & timeline dirender ulang dari respons JSON; error guard tampil sebagai banner di dalam modal
- [x] **Tanda kartu kuning/merah** — badge 🟨/🟥 di kartu pemain + rekap per tim di scoreboard; **pemain kartu merah otomatis nonaktif** (semua tombol event disabled + guard server menolak event untuk pemain tersebut)
- [x] **Fix scroll modal logger** — lineup & timeline scroll internal (scoreboard/timeline tetap terlihat), scroll halaman belakang terkunci saat modal terbuka
- [x] **Seeder pemain** — `php artisan db:seed --class=TournamentTeamPlayerSeeder` (11 pemain futsal/tim: 2 GK, 3 Anchor, 4 Flank, 2 Pivot, kapten #10; tim yang sudah punya pemain dilewati)
- [x] **Guard edit manual** — Leg 2 sebelum Leg 1 selesai, match dalam fase adu penalti, dan menutup match knockout dengan hasil seri lewat edit manual: semuanya ditolak
- [x] **Regenerasi jadwal cerdas** di simpan pengaturan bracket — terpicu saat mode laga berubah ATAU struktur row tidak cocok dengan mode; ditolak dengan peringatan bila sudah ada hasil pertandingan

---

## 🔢 Usulan Urutan Pengerjaan

| Tahap | Item | Alasan | Status |
|-------|------|--------|--------|
| 1 | R3 → R1 → R2 → R4 | Bug cepat & berdampak luas | ⏳ Sisa **R3** (poin kalah) |
| 2 | R6 → R9 → R7 → R8 | Engine knockout (R6 paling kritis: bracket macet saat seri) | ✅ Selesai semua |
| 3 | R10 → R11 → R12 → R13 → R5 | Format kompetisi & klasemen | ⏳ R10 selesai; sisa R11, R12, R13, R5 |
| 4 | R14 → R15 → R16 → R17 | Peserta, grup & undian | ⬜ Belum |
| 5 | R18 → R19 → R20 | Verifikasi & pemain | ⏳ R19 sebagian (roster asli) |
| 6 | R21 → R22 | Platform (menyentuh auth & seluruh data) | ⬜ Belum |

> **Rekomendasi berikutnya:** R3 (bug poin kalah — cepat dan berdampak ke semua klasemen), lalu lanjut sisa Tahap 3.

---

## 🧹 Housekeeping (opsional)

- [ ] Bersihkan 20+ file `tmp_*.php` dan laporan audit (`AUDIT_*.md`, `*_REPORT.txt`, dll.) di root project sebelum deploy

---

*Daftar asli berisi ±24 entri; duplikat sudah digabung (penalti 3×, bracket langsung 2×, home-away 2×, sinkronisasi grup 2×) menjadi 22 item unik.*
