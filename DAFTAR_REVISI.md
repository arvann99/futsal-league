# 📋 Daftar Revisi — Futsal League

> Disusun: 10 Juni 2026 • Centang `[x]` jika item sudah selesai dikerjakan.
>
> Format: setiap item punya ID (R1–R22) supaya mudah dirujuk, misalnya: *"kerjakan R6"*.

**Progress: 22 / 22 selesai ✅** *(update 14 Juni 2026: R22 paket langganan (Free/Pro/Ultimate) + admin root ACC pembayaran + upload bukti transfer + enforcement limit selesai. Semua item R0–R22 tuntas. Sebelumnya: R21 multi-admin (14 Juni); R3,R5,R11–R20 (13 Juni). Lihat juga "Selesai di luar daftar".)*

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

- [x] **R3 — Cek pengaturan poin: poin kalah tidak pernah diterapkan** `[BUG]` *(selesai 13 Juni 2026)*
  - ✔️ `buildStandingsGroups()` kini menambahkan `$pointSettings['loss']` ke tim yang kalah (kedua cabang menang/kalah)
  - ✔️ Default tiebreaker `pointsSettings()` disamakan dengan `resolvePointSettings()` → `['points','goal_difference','goals_scored','head_to_head']`
  - ✔️ Teruji: dengan loss=1, tim kalah dapat 1 poin (sebelumnya 0)
  - 📁 `app/Http/Controllers/TournamentController.php`

- [x] **R4 — Bracket saat tipe Liga: halaman kosong / menu tetap tampil** `[BUG/UX]` *(selesai 11 Juni 2026)*
  - Menu "Bagan Bracket" tetap muncul untuk tipe `league` tapi halamannya kosong
  - ✔️ Diselesaikan bersamaan pemisahan 3 sistem kompetisi: tipe `league` menyembunyikan menu Bracket Gugur di sidebar dan `bracketAdmin()` redirect ke klasemen
  - 📁 `resources/views/tournaments/manage.blade.php` (±55) • `TournamentController.php` → `bracketAdmin()` (±604–614)

- [x] **R5 — QA Liga Reguler dengan Playoff** `[PENGECEKAN → DIPERBAIKI]` *(selesai 13 Juni 2026)*
  - Bug ditemukan & diperbaiki: saat promosi+degradasi aktif bersamaan, `bracketAdmin()`/`saveBracketAssignments()` selalu default ke mode promotion sehingga bracket degradasi tak bisa diakses/disimpan
  - ✔️ Mode `both` kini didukung: `?mode=promotion|relegation` memilih bracket yang diedit; baca/tulis ke key terpisah `matches_promotion` / `matches_relegation`; query slot disaring per `stage_type` agar tim promosi & degradasi tidak tertukar
  - ✔️ Tab **Bracket Promosi / Bracket Degradasi** di halaman Bracket Gugur (muncul hanya saat both); form submit membawa mode aktif
  - 📁 `TournamentController.php` → `bracketAdmin()` / `saveBracketAssignments()` • `resources/views/admin/tournaments/bracket/manage.blade.php`

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

- [x] **R11 — Liga: pilihan Setengah Kompetisi vs Kompetisi Penuh (kandang-tandang)** `[FITUR BARU]` *(selesai 13 Juni 2026)*
  - ✔️ Kolom baru `league_round_type` (single/double) di `tournament_group_settings`; radio "Format Liga" di panel pengaturan grup (muncul untuk league & league_playoff)
  - ✔️ `buildLeagueStageMatches()` membangun putaran kedua (home/away dibalik, Matchday melanjutkan) saat `double` → jumlah match jadi 2×. Teruji: 4 tim single=6 match → double=12 match (6 matchday)
  - 📁 migration `add_league_round_type...` • `TournamentGroupSetting.php` • `app/Services/MatchGenerator.php` → `buildLeagueStageMatches()`/`buildReversedLeg()` • `TournamentController.php` → `updateSettings()` • `group-settings-panel.blade.php`

---

## D. Klasemen & Poin

- [x] **R12 — Standar poin liga berlaku semua tipe + hasil bracket langsung masuk klasemen** `[PENYEMPURNAAN]` *(selesai 13 Juni 2026)*
  - ✔️ Standar poin (win/draw/loss) kini diterapkan konsisten ke klasemen `league`/`league_playoff` (lihat R3); hasil match `full_time` langsung masuk klasemen (teruji)
  - ✔️ Keputusan desain (disetujui user): tipe **Turnamen (gugur murni)** tetap memakai bagan bracket — tidak dipaksa punya tabel klasemen; poin playoff TIDAK dicampur ke klasemen liga reguler agar peringkat liga tidak tercemar
  - ✔️ Bonus: `OfficialStandingsController` disamakan — klasemen manager hanya menghitung stage `group`/`league` + `status=full_time` (sebelumnya ikut menghitung semua match termasuk playoff)
  - 📁 `TournamentController.php` → `buildStandingsGroups()` • `OfficialStandingsController.php`

- [x] **R13 — Tie-breakers langsung memengaruhi klasemen + urutan prioritas** `[VERIFIKASI]` *(dicek 13 Juni 2026 — sudah terpenuhi, tidak diubah)*
  - ✔️ Tervalidasi: `compareTeamRows()` mengiterasi tiebreaker sesuai urutan tersimpan; klasemen di-`usort` memakai urutan itu
  - ✔️ Urutan prioritas **sudah bisa diatur admin** lewat tombol naik/turun (▲▼) di `points-settings-panel.blade.php` + JS `moveTiebreaker()` → tersimpan ke `AppSetting`. Tidak perlu drag-and-drop tambahan
  - 📁 `TournamentController.php` • `points-settings-panel.blade.php` *(tidak ada perubahan kode)*

---

## E. Peserta, Grup & Undian

- [x] **R14 — Sinkronisasi pengaturan grup ↔ manajemen peserta** `[PENYEMPURNAAN]` *(selesai 13 Juni 2026)*
  - ✔️ `store()` peserta menolak penambahan bila kapasitas grup penuh (`group_count × teams_per_group`) dengan pesan jelas
  - ✔️ Halaman Peserta menampilkan banner kapasitas "X / Y slot" (merah jika lebih, kuning jika ≥80%)
  - ✔️ `assignGroupLabelsToTournamentTeams()` dirombak: isi grup ke slot kosong & hormati penempatan manual (tidak menumpuk buta ke grup terakhir kecuali semua penuh)
  - 📁 `TournamentParticipantController.php` → `store()`/`index()` • `TournamentController.php` → `assignGroupLabelsToTournamentTeams()` • `participants/index.blade.php`

- [x] **R15 — Penempatan slot tim ke grup secara manual** `[FITUR BARU]` *(selesai 13 Juni 2026)*
  - ✔️ Dropdown grup per tim di halaman Peserta (route `PATCH .../participants/{participant}/group` → `assignGroupManually()`)
  - ✔️ Kolom baru `group_assigned_manually` di `tournament_teams` menandai penempatan manual/undian; ditandai 🔒 dan TIDAK ditimpa auto-assign berbasis seed
  - ✔️ Jadwal grup di-regenerate otomatis setelah penempatan diubah
  - 📁 migration `add_group_assigned_manually...` • `TournamentParticipantController.php` • `participants/index.blade.php` • `routes/web.php`

- [x] **R16 — Spin / undian tim untuk grup** `[FITUR BARU]` *(selesai 13 Juni 2026)*
  - ✔️ Halaman Undian (`tournaments.groupDraw`) dengan animasi spin (slot-machine), tombol "Mulai Undian" → `performGroupDraw()` mengacak (`shuffle`) lalu membagi round-robin ke grup
  - ✔️ Hasil tersimpan ke `group_label` + ditandai `group_assigned_manually=true`; jadwal grup di-regenerate; tombol "🎲 Undian Grup" di panel pengaturan grup
  - 📁 `group-draw.blade.php` (baru) • `TournamentController.php` → `groupDraw()`/`performGroupDraw()` • `routes/web.php`

- [x] **R17 — Manajemen peserta + card pemain** `[PENYEMPURNAAN]` *(selesai 13 Juni 2026)*
  - ✔️ Halaman Peserta admin: kolom "Pemain" dengan tombol expand menampilkan card pemain per tim (foto, nama, nomor, posisi, kapten, status) + statistik gol/kartu (R19)
  - ✔️ Sekalian fix bug: `copyToken()` yang dipanggil tapi belum didefinisikan kini ada
  - 📁 `TournamentParticipantController.php` → `index()` • `resources/views/admin/tournaments/participants/index.blade.php`

---

## F. Verifikasi & Portal Manager

- [x] **R18 — Verifikasi berkas per tim + kunci setelah disetujui + card list pemain** `[FITUR BARU]` *(selesai 13 Juni 2026)*
  - Fondasi sudah ada: kolom `verification_status` (pending/approved/rejected) + badge di halaman peserta
  - Sub-tugas:
    - [x] Upload berkas per tim — tabel baru `team_verification_documents` (PDF/gambar, maks 8MB); form unggah + daftar berkas + tombol Lihat/Hapus di halaman Verifikasi
    - [x] Tombol Approve / Reject untuk admin (sudah ada sebelumnya — diverifikasi)
    - [x] **Kunci data setelah approved** — `guardLockedTeam()` di `OfficialPlayerController` & `OfficialTeamOfficialController` menolak tambah/ubah/hapus pemain & ofisial saat tim `approved`; banner kunci 🔒 di portal manager
    - [x] Card tim menampilkan list pemain (sudah ada di verification + kini juga di halaman Peserta — lihat R17)
  - ⚠️ Catatan: form unggah berkas otomatis disembunyikan & berkas dikunci setelah tim approved. `php artisan storage:link` sudah dijalankan agar berkas/foto dapat diakses publik
  - 📁 migration `create_team_verification_documents_table` • `TeamVerificationDocument.php` (model baru) • `OfficialPlayerController.php` • `OfficialTeamOfficialController.php` • `TournamentController.php` → `uploadVerificationDocument()`/`deleteVerificationDocument()` • `verification.blade.php` • `official/layouts/app.blade.php`

- [x] **R19 — Live Match terhubung card pemain tim** `[FITUR BARU]` *(selesai 13 Juni 2026)*
  - Sub-tugas:
    - [x] Roster Live Match Logger dari pemain asli (`buildMatchRoster()` — sudah ada)
    - [x] Kolom `player_id` (nullable FK `nullOnDelete`) di `match_events` + relasi `MatchEvent::player()`; diisi saat `storeMatchEvent()`; roster & event logger membawa `player_id` (event lama/tanpa roster tetap valid via `player_name`)
    - [x] Akumulasi statistik gol/kartu kuning/merah per pemain (query agregat `match_events.player_id`) tampil di card pemain halaman Peserta (⚽ 🟨 🟥)
  - 📁 migration `add_player_id_to_match_events_table` • `MatchEvent.php` • `TournamentController.php` (`buildMatchRoster`/`storeMatchEvent`/`buildLoggerMatchPayload`) • `schedule/manage.blade.php` • `TournamentParticipantController.php`

- [x] **R20 — Sistem manager view jadwal** `[QA/PENYEMPURNAAN]` *(selesai 13 Juni 2026)*
  - ✔️ Match tanpa tanggal (TBD) kini ikut tampil di `/official/schedule` (filter `whereNotNull('match_date')` dihapus; diurutkan tanggal, TBD di akhir)
  - ✔️ Tab filter baru **"Belum Dijadwalkan"** + tampilan "Belum dijadwalkan / Menunggu jadwal dari panitia" untuk match tanpa tanggal
  - 📁 `app/Http/Controllers/OfficialAuthController.php` → `schedule()` • `resources/views/official/schedule.blade.php`

---

## G. Platform (perubahan arsitektur — paling besar)

- [x] **R21 — Multi-admin: daftar via Gmail masing-masing** `[FITUR BESAR]` *(selesai 14 Juni 2026)*
  - Sub-tugas:
    - [x] Halaman registrasi (email+password, `Password::min(8)` + konfirmasi) **dan** Google OAuth via `laravel/socialite` (^5.27): `redirectToGoogle()`/`handleGoogleCallback()` — user Google disimpan/di-link di tabel `users` (kolom baru `google_id`, `avatar`; `password` kini nullable untuk akun Google-only). Tombol "Masuk/Daftar dengan Google" + link daftar/masuk + "Ingat saya" di halaman login/register
    - [x] Scoping: `TournamentController::index()` & `TeamController::index()` difilter `where('created_by', Auth::id())`; `store()` mengeset `created_by`; tim peserta (`TournamentParticipantController::store`) mewarisi `created_by` dari pemilik turnamen
    - [x] **Middleware `owns`** (`EnsureResourceOwnership`) di group route ber-auth: setiap akses route `{tournament}`/`{team}` milik admin lain → **403**. Teruji isolasi 2 admin (index scoping, cross-admin 403, owner lolos, scoping tim) — semua PASS
    - [x] Header dashboard menampilkan nama/avatar admin yang login + tombol Logout
    - [x] **Hardening keamanan** (dari review adversarial): (a) anti **account-takeover** — auto-link Google ke akun email lama HANYA jika Google `email_verified=true`, kalau tidak ditolak; (b) `/api/data` & `/api/save` (legacy, tak terpakai) kini di balik `auth`; (c) race-condition Google callback ditangani try/catch `QueryException`; (d) guard email Google kosong; (e) `throttle` di login (10/mnt) & register (5/mnt); (f) middleware tim: `created_by=null` ikut ditolak. Teruji: unverified-email TIDAK ter-link, verified ter-link, akun null-password tak bisa login via password — semua PASS
  - ⚙️ **Setup Google OAuth (perlu Anda lakukan)**: isi `GOOGLE_CLIENT_ID` & `GOOGLE_CLIENT_SECRET` di `.env` (sudah ada placeholder); daftarkan Authorized redirect URI `<APP_URL>/auth/google/callback` di Google Cloud Console. Tanpa kredensial, tombol Google menampilkan pesan "belum dikonfigurasi" (email+password tetap jalan)
  - ℹ️ Catatan desain: verifikasi email tidak diwajibkan (pilihan Anda: registrasi terbuka). Jika nanti ingin lebih ketat, aktifkan `MustVerifyEmail` + SMTP.
  - 🗃️ Data lama: turnamen & tim tanpa `created_by` di-backfill ke admin pertama (admin#1) lewat migrasi — PON 2026 tetap milik `admin@gmail.com`
  - ⚠️ Catatan: portal manager/official (token-based) **tidak** di-scope `owns` (memang akses lewat `manager_token`, bukan akun admin) — tetap berfungsi
  - 📁 migration `add_oauth_and_scoping_for_multi_admin` • `AuthController.php` • `app/Http/Middleware/EnsureResourceOwnership.php` (baru) • `bootstrap/app.php` • `routes/web.php` • `User.php`/`Team.php` • `config/services.php` • `admin/auth/login.blade.php` + `register.blade.php` (baru) • `admin/tournaments/index.blade.php`

- [x] **R22 — Paket berlangganan Free / Pro / Ultimate + admin root ACC pembayaran** `[FITUR BESAR]` *(selesai 14 Juni 2026)*
  - **Free**: maks 1 turnamen, 8 tim/turnamen • **Pro**: maks 3 turnamen, 32 tim • **Ultimate**: unlimited (default user baru = Free; limit di `User::PLAN_LIMITS`)
  - Sub-tugas:
    - [x] Kolom `plan` (free/pro/ultimate) + `is_root` di `users`; tabel `subscription_requests` (user_id, requested_plan, payment_proof, amount, status, reviewed_by/at, note) + model `SubscriptionRequest`
    - [x] **Admin root**: kolom `is_root`; admin#1 (admin@gmail.com) di-set root + ultimate via migrasi. Middleware `root` (`EnsureRoot`) proteksi area `/root/*` — non-root → 403 (teruji)
    - [x] **Alur upgrade**: admin pilih paket berbayar → **upload bukti transfer** → status pending; admin root tinjau di `/root/requests` → **Setujui** (plan user naik) / **Tolak** (plan tetap + catatan). Double-approve diblokir; double-submit dicegah (`hasPendingSubscriptionRequest`)
    - [x] Enforcement limit di `TournamentController::store` (jumlah turnamen) & `TournamentParticipantController::store` (jumlah tim) → diarahkan ke halaman paket bila tercapai. Teruji 7 skenario (free keblok di turnamen ke-2 & tim ke-9; pro 3/32; ultimate & root unlimited; approve/reject; guard) — semua PASS
    - [x] Helper `User`: `planLimits()/tournamentLimit()/teamLimit()/canCreateTournament()/canAddTeamTo()` (root & ultimate bypass)
    - [~] Anti-abuse: rate-limit `throttle` di register/login (R21) & upgrade (6/mnt); verifikasi pembayaran via ACC manual root. Device fingerprint / verifikasi HP **belum** (bisa ditambah nanti — lihat catatan)
    - [x] **Hardening pasca review keamanan adversarial**: (a) bukti transfer dipindah ke disk **PRIVAT** (`local`, bukan `public`) + route `root.requests.proof` khusus root (sebelumnya bukti finansial bisa diakses publik via /storage); (b) migrasi root dibuat deterministik (satu root: admin@gmail.com → fallback id terkecil, hapus `orWhere` rapuh); (c) **race condition (TOCTOU)** ditutup dengan `DB::transaction`+`lockForUpdate` di `TournamentController::store` (limit turnamen), `TournamentParticipantController::store` (limit tim + kapasitas grup), `requestUpgrade` (double-pending), `approve`/`reject`; (d) bukti TF yang ditolak dihapus dari storage. Teruji ulang (limit, disk privat, approve, reject+hapus file) — semua PASS
  - ⚠️ Catatan anti-abuse lanjutan (opsional, belum): block IP lemah (IP dinamis/VPN). Bila perlu lebih ketat → kombinasi verifikasi email (`MustVerifyEmail`) + verifikasi HP/OTP + device fingerprint. Saat ini gating utama = ACC pembayaran manual oleh root
  - 💳 Info rekening transfer di halaman paket masih placeholder ("BCA 1234567890 a.n. Futsal League") — ganti dengan rekening asli Anda di `resources/views/admin/subscription/plans.blade.php`. Harga: Pro Rp50.000, Ultimate Rp150.000 (`SubscriptionRequest::PRICES`)
  - 📁 migration `add_plan_and_root_to_users_table` + `create_subscription_requests_table` • `User.php` • `SubscriptionRequest.php` (baru) • `EnsureRoot.php` (baru) • `SubscriptionController.php` (baru) • `Admin/RootController.php` (baru) • `bootstrap/app.php` • `routes/web.php` • `TournamentController.php`/`TournamentParticipantController.php` • `admin/subscription/plans.blade.php` + `admin/root/requests.blade.php` (baru) • `admin/tournaments/index.blade.php`

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
| 1 | R3 → R1 → R2 → R4 | Bug cepat & berdampak luas | ✅ Selesai semua |
| 2 | R6 → R9 → R7 → R8 | Engine knockout (R6 paling kritis: bracket macet saat seri) | ✅ Selesai semua |
| 3 | R10 → R11 → R12 → R13 → R5 | Format kompetisi & klasemen | ✅ Selesai semua |
| 4 | R14 → R15 → R16 → R17 | Peserta, grup & undian | ✅ Selesai semua |
| 5 | R18 → R19 → R20 | Verifikasi & pemain | ✅ Selesai semua |
| 6 | R21 → R22 | Platform (menyentuh auth & seluruh data) | ✅ Selesai semua |

> **Semua revisi R0–R22 selesai.** Tindak lanjut opsional (bukan revisi): ganti rekening transfer placeholder di halaman paket dengan rekening asli; pertimbangkan anti-abuse lanjutan (verifikasi email/HP) bila perlu; housekeeping file `tmp_*.php`/`AUDIT_*` & `app/Debug/MatchTimelineTracer` sebelum deploy.

---

## 🧹 Housekeeping (opsional)

- [ ] Bersihkan 20+ file `tmp_*.php` dan laporan audit (`AUDIT_*.md`, `*_REPORT.txt`, dll.) di root project sebelum deploy

---

*Daftar asli berisi ±24 entri; duplikat sudah digabung (penalti 3×, bracket langsung 2×, home-away 2×, sinkronisasi grup 2×) menjadi 22 item unik.*
