# 📋 Daftar Revisi — Futsal League

> Disusun: 10 Juni 2026 • Centang `[x]` jika item sudah selesai dikerjakan.
>
> Format: setiap item punya ID (R1–R22) supaya mudah dirujuk, misalnya: *"kerjakan R6"*.

**Progress: 0 / 22 selesai**

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

- [ ] **R4 — Bracket saat tipe Liga: halaman kosong / menu tetap tampil** `[BUG/UX]`
  - Menu "Bagan Bracket" tetap muncul untuk tipe `league` tapi halamannya kosong
  - Putuskan: sembunyikan menu, atau tampilkan pesan informatif
  - 📁 `resources/views/tournaments/manage.blade.php` (±55) • `TournamentController.php` → `bracketAdmin()` (±604–614)

- [ ] **R5 — QA Liga Reguler dengan Playoff** `[PENGECEKAN]`
  - Tes menyeluruh alur `league_playoff` (promosi / degradasi / keduanya)
  - Catatan: saat promosi+degradasi aktif bersamaan, `bracketAdmin()` selalu default ke mode promotion (±633–636)
  - 📁 `app/Http/Controllers/TournamentController.php`

---

## B. Mekanik Pertandingan Knockout (engine inti)

- [ ] **R6 — Jika imbang di knockout → adu penalti, pemenang +1 gol** `[FITUR BARU — KRITIS]`
  - Berlaku di: Kelola Jadwal & Skor, bracket gugur, dan Live Match
  - Aturan: siapapun menang adu penalti, **skor hanya bertambah +1** (bukan skor penalti penuh)
  - Saat ini skor seri membuat bracket macet — pemenang tidak pernah maju
  - Sub-tugas:
    - [ ] Tambah field penalti di tabel `matches` (migration)
    - [ ] Event type penalti di `storeMatchEvent()` (±1758) + UI Live Match Logger
    - [ ] Validasi di `endMatch()` (±1860): match knockout tidak boleh selesai imbang
    - [ ] Perbaiki `updateBracketForTournament()` (±1939): pemenang penalti maju ke babak berikut
  - 📁 `app/Http/Controllers/TournamentController.php` • `database/migrations/` • `resources/views/tournaments/schedule-admin.blade.php`

- [ ] **R7 — Leg ke-2 di Kelola Jadwal & Skor (Home & Away)** `[FITUR BARU]`
  - Opsi `match_type=home_away` sudah ada di UI tapi belum berfungsi — generate 2 leg per pasangan
  - Sub-tugas:
    - [ ] Kolom `leg_number` / penanda pasangan leg di tabel `matches`
    - [ ] Generate leg 2 di `MatchGenerator::buildBracketMatchesFromArray()` (±267)
    - [ ] Tampilan Leg 1 / Leg 2 di Kelola Jadwal & Skor
  - 📁 `app/Services/MatchGenerator.php` • `resources/views/tournaments/partials/bracket-settings-panel.blade.php` (±102)

- [ ] **R8 — Dropdown aturan pemenang 2 leg: Agregat / Win per Match** `[FITUR BARU]`
  - Opsi 1: **By agregat gol**
  - Opsi 2: **Win per match** — jika 1-1 match: extra time di leg 2 (gol ET = +1 skor), masih imbang → adu penalti (pemenang +1 gol)
  - Bergantung pada: R6 dan R7
  - 📁 Panel bracket settings + `TournamentController.php` → `finalizeMatchResult()` (±1922)

- [ ] **R9 — Mode knockout: bye / single match otomatis maju** `[FITUR BARU]`
  - Tim dengan slot Bye atau tanpa lawan otomatis lolos ke babak selanjutnya tanpa input skor manual
  - 📁 `app/Services/MatchGenerator.php` • `TournamentController.php` → `updateBracketForTournament()`

---

## C. Format Kompetisi

- [ ] **R10 — Tipe kompetisi baru: Knockout langsung (bracket gugur tanpa fase grup)** `[FITUR BARU]`
  - Tambah pilihan `knockout` di samping tournament / league / league_playoff
  - Sub-tugas:
    - [ ] Radio pilihan di `group-settings-panel.blade.php` (±61–73)
    - [ ] Validasi `competition_type` di `updateSettings()` (±1323)
    - [ ] Cabang generate di `MatchGenerator::generateForTournament()` (±26)
    - [ ] Switch view jadwal (±223–227)
  - 📁 `TournamentController.php` • `MatchGenerator.php` • `group-settings-panel.blade.php`

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

- [ ] **R19 — Live Match terhubung card pemain tim** `[FITUR BARU]`
  - Roster Live Match Logger masih dummy hardcode (`generateLiveMatchRoster()` ±315–330) — ganti dengan pemain asli
  - Tambah relasi `player_id` di `match_events`, lalu akumulasi statistik (gol/kartu) ke card pemain
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

## 🔢 Usulan Urutan Pengerjaan

| Tahap | Item | Alasan |
|-------|------|--------|
| 1 | R3 → R1 → R2 → R4 | Bug cepat & berdampak luas |
| 2 | R6 → R9 → R7 → R8 | Engine knockout (R6 paling kritis: bracket macet saat seri) |
| 3 | R10 → R11 → R12 → R13 → R5 | Format kompetisi & klasemen |
| 4 | R14 → R15 → R16 → R17 | Peserta, grup & undian |
| 5 | R18 → R19 → R20 | Verifikasi & pemain |
| 6 | R21 → R22 | Platform (menyentuh auth & seluruh data) |

---

## 🧹 Housekeeping (opsional)

- [ ] Bersihkan 20+ file `tmp_*.php` dan laporan audit (`AUDIT_*.md`, `*_REPORT.txt`, dll.) di root project sebelum deploy

---

*Daftar asli berisi ±24 entri; duplikat sudah digabung (penalti 3×, bracket langsung 2×, home-away 2×, sinkronisasi grup 2×) menjadi 22 item unik.*
