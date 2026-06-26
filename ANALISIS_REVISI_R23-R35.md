# Analisis Revisi Lanjutan (Gelombang Baru — 14 Poin)

> Dokumen analisis untuk 14 poin revisi baru. Disusun setelah penelusuran kode aktual
> (per branch `revisi/r13-r22-finalize`). Untuk tiap poin: **temuan kondisi saat ini**,
> **analisis dampak**, **rencana implementasi**, **file yang terdampak**, **tingkat kesulitan**,
> dan **risiko/catatan**.
>
> Penomoran internal memakai label **N1–N14** (revisi baru #1–#14) agar tidak bentrok dengan
> backlog lama R1–R22 yang sudah selesai (lihat `DAFTAR_REVISI.md`).
>
> **Pemetaan role:** sistem memiliki **4 level role** → **Public** (Tamu/Visitor), **Official**
> (Official/Manager/Peserta, portal token), **Admin** (Pemilik Turnamen), **Root** (super-admin).
> Setiap revisi dipetakan ke role yang terdampak. Lihat bagian **"Pemetaan Revisi ↔ Role"** di bawah.

---

## Ringkasan & Prioritas

| # | Judul singkat | Area | Role terdampak | Kesulitan | Migrasi DB? |
|---|---------------|------|----------------|-----------|-------------|
| N1 | Disable tombol "Tambah Peserta" saat slot penuh | Admin / Peserta | **Admin** | 🟢 Ringan | Tidak |
| N2 | Integrasikan Undian (Draw) ke halaman Bagan Klasemen | Admin / Standings | **Admin** | 🟡 Sedang | Tidak |
| N3 | Token auto-generate & langsung tampil (jangan reset) | Admin / Peserta | **Admin** (token utk Official) | 🟡 Sedang | Tidak |
| N4 | Role Manager bisa lihat bracket | Official portal | **Official** | 🟡 Sedang | Tidak |
| N5 | Tombol "Edit" → khusus isi/ubah Skor | Admin / Jadwal | **Admin** | 🟡 Sedang | Tidak |
| N6 | Tombol "Jadwal" terpisah + gating Live Logger | Admin / Jadwal | **Admin** | 🟡 Sedang | Tidak |
| N7 | Riwayat Official → scoreboard tim bertanding | Official portal | **Official** | 🟡 Sedang | Tidak |
| N8 | Bracket responsif / scroll rapi saat tim banyak | Admin / Bracket | **Admin** (+Official via N4) | 🟡 Sedang | Tidak |
| N9 | Sinkronisasi data Official/Manager ke panel Admin | Admin + Official | **Admin** ← **Official** | 🟡 Sedang | Mungkin |
| N10 | Setting Peringkat 3 tidak berefek di bracket gugur | Admin / Bracket | **Admin** | 🟡 Sedang | Tidak |
| N11 | Tab Jadwal Internal vs Jadwal Turnamen (Manager) | Official portal | **Official** | 🟢 Ringan | Tidak |
| N12 | Halaman Statistik lengkap (Manajemen Pemain Admin) | Admin (baru) | **Admin** | 🔴 Berat | **Ya** (assist) |
| N13 | Statistik view-only untuk Manager & Tamu/Visitor | Official + Public | **Official + Public** | 🔴 Berat | Tidak (reuse N12) |
| N14 | Bagan knockout model dua sisi kiri-kanan (mirror) | Bracket (semua view) | **Admin + Official + Public** | 🔴 Berat | Tidak |

**Urutan kerja yang disarankan:** N1 → N3 → N5/N6 (sepaket) → N10 → N8 → N2 → N4 → N11 → N7 → N9 → N14 → N12 → N13.
N12 & N13 paling berat dan saling bergantung; kerjakan terakhir sebagai satu paket statistik.
N14 sebaiknya setelah N8 (kerapian bracket) & N4 (bracket official), karena menyentuh semua view bracket.

---

## N1 — Disable tombol "Tambah Peserta" saat kuota penuh

**Revisi:** Pada Manajemen Peserta, jika kuota (slot) pendaftaran sudah penuh, tombol
"Tambah Peserta" otomatis dinonaktifkan (disabled) dan tidak dapat diklik.

### Kondisi saat ini
- View: `resources/views/admin/tournaments/participants/index.blade.php`.
  - Banner kapasitas grup sudah dihitung di view: `$capacity = group_count × teams_per_group`,
    `$current = $participants->count()` (hanya saat `$usesGroups`).
  - Tombol "Tambah Peserta" (header-actions, ~baris 10) dan tombol "Tambah Peserta Sekarang"
    (state kosong, ~baris 50) **selalu aktif** — tidak ada cek kuota.
- Backend sudah memproteksi (defense in depth): `TournamentParticipantController::store()`
  menolak via `DB::transaction` + `lockForUpdate` dengan pesan `GROUP_FULL` / `PLAN_LIMIT`
  (baris 94–140). Jadi kuota penuh sudah aman di server; revisi ini murni **UX di depan**.

### Analisis
- Dua sumber "penuh" yang berbeda:
  1. **Kapasitas grup** (`group_count × teams_per_group`) — hanya berlaku saat `$usesGroups`
     (kompetisi non-`tournament` dengan grup).
  2. **Limit paket** (`Auth::user()->teamLimit()`, null = unlimited) — berlaku di semua tipe.
- Tombol harus disabled bila **salah satu** batas tercapai. Untuk tipe `tournament` (gugur murni)
  tanpa grup, hanya limit paket yang relevan.

### Rencana implementasi
1. Hitung flag `$isFull` di controller `index()` (lebih bersih daripada di view) dan kirim ke view:
   - `$capacity` (grup) bila `$usesGroups`, plus `$teamLimit`.
   - `$isFull = (capacity > 0 && current >= capacity) || (teamLimit !== null && current >= teamLimit)`.
   - Sertakan `$fullReason` ("Kapasitas grup penuh" / "Batas paket tercapai") untuk tooltip.
2. Di view, render tombol kondisional:
   - Jika `$isFull`: ganti `<a>` jadi `<button disabled>` dengan styling `opacity-50 cursor-not-allowed`
     dan `title="{{ $fullReason }}"`. Terapkan ke **kedua** tombol (header & empty-state — walau
     empty-state praktis tak pernah penuh).
   - Tambah hint kecil di banner: "Slot penuh — tombol Tambah Peserta dinonaktifkan."

### File terdampak
- `app/Http/Controllers/TournamentParticipantController.php` (method `index`)
- `resources/views/admin/tournaments/participants/index.blade.php`

### Kesulitan: 🟢 Ringan • **Tanpa migrasi**

### Risiko/catatan
- Pastikan logika `$usesGroups` di view & controller konsisten (sudah ada di `index()`).
- Jangan hilangkan proteksi backend — biarkan sebagai jaring pengaman.

---

## N2 — Integrasikan Sistem Undian (Draw/Lottery) ke halaman Bagan Klasemen

**Revisi:** Pindahkan fitur/menu Sistem Undian (Lottery/Draw) agar terintegrasi langsung ke
dalam halaman Bagan Klasemen.

### Kondisi saat ini
- Fitur undian sudah ada (R16): route `tournaments.groupDraw` (GET) & `tournaments.performGroupDraw` (POST),
  controller `TournamentController::groupDraw()` / `performGroupDraw()`, view
  `resources/views/admin/tournaments/settings/group-draw.blade.php` (animasi spin 🎲).
- **Tidak ada link/menu menuju halaman undian di sidebar maupun di tempat lain** —
  `grep` hanya menemukan referensi di file `group-draw.blade.php` sendiri. Praktis halaman ini
  "yatim" (hanya bisa diakses jika URL diketik manual).
- Halaman Bagan Klasemen: `resources/views/admin/tournaments/standings.blade.php`
  (`@section('page-title','Bagan Klasemen Grup')`), menampilkan grid grup + klasemen.
  Menu sidebar: "Bagan Klasemen" → `route('tournaments.standings')`.

### Analisis
- "Integrasi langsung" bisa diartikan dua cara; rekomendasi: **embed panel undian di atas/di samping
  grid grup pada halaman standings**, sehingga admin mengundi lalu langsung melihat hasil pengelompokan
  di halaman yang sama. Alternatif lebih ringan: tambahkan tombol/section "Undian Grup" yang
  meng-embed view spin via partial.
- Undian hanya relevan untuk kompetisi **bergrup** (`$usesGroups`). Pada tipe `tournament` (gugur murni
  tanpa grup), panel undian tidak ditampilkan.
- `performGroupDraw` saat ini redirect kembali ke halaman undian; perlu disesuaikan agar redirect
  ke `tournaments.standings` (atau tetap di standings) dengan flash hasil.

### Rencana implementasi
1. Ekstrak isi `group-draw.blade.php` jadi partial, mis. `settings/partials/group-draw.blade.php`
   (atau partial baru di `standings/`), agar bisa di-`@include` dari standings.
2. Di `standings.blade.php`, tambahkan section "Sistem Undian Grup" (collapsible) sebelum grid grup,
   hanya saat kompetisi bergrup. Form `performGroupDraw` tetap dipakai.
3. Pastikan `standings()` controller mengirim data yang dibutuhkan panel undian (daftar tim, grup labels).
   Saat ini `groupDraw()` menyiapkan datanya — pindahkan/duplikasi penyiapan data itu ke `standings()`.
4. Ubah redirect `performGroupDraw` → `tournaments.standings` (jaga query/flash).
5. Hapus/redirect route `groupDraw` lama (opsional) atau biarkan sebagai deep-link; minimal pastikan
   tidak ada menu ganda.

### File terdampak
- `app/Http/Controllers/TournamentController.php` (`standings`, `performGroupDraw`, mungkin `groupDraw`)
- `resources/views/admin/tournaments/standings.blade.php`
- `resources/views/admin/tournaments/settings/group-draw.blade.php` → jadi partial / di-include
- `routes/web.php` (opsional cleanup)

### Kesulitan: 🟡 Sedang • **Tanpa migrasi**

### Risiko/catatan
- Halaman standings akan lebih berat; jaga agar panel undian collapsible/tersembunyi default.
- Hasil undian menimpa penempatan grup manual (sudah ada warning di view) — pertahankan peringatan itu.

---

## N3 — Token auto-generate & langsung ditampilkan (jangan harus reset)

**Revisi:** Saat admin selesai input awal data peserta, Token harus langsung di-generate otomatis
dan langsung ditampilkan di layar. JANGAN harus di-reset dulu.

### Kondisi saat ini
- **Token TIDAK di-generate saat peserta dibuat lewat Manajemen Peserta.**
  `TournamentParticipantController::store()` memanggil `Team::create([...])` (baris 108–115)
  **tanpa** field `manager_token`. Jadi kolom `manager_token` tim peserta baru = `null`.
- Generator token ada tapi hanya dipakai di `TeamController`:
  - `store()` (baris 35) dan `resetToken()` (baris 74) memakai `generateUniqueManagerToken()`.
- Di view index peserta, kolom "Manager Token" menampilkan `$participant->team->manager_token ?? 'N/A'`
  → untuk peserta yang ditambah via Manajemen Peserta akan tampil **N/A**, sehingga admin
  terpaksa klik **"Reset Token"** untuk memunculkannya. **Inilah keluhan revisi ini.**

### Analisis
- Akar masalah: `TournamentParticipantController::store` tidak mengisi `manager_token`.
- `generateUniqueManagerToken()` adalah method privat di `TeamController` — perlu dipindah agar
  reusable (mis. ke model `Team` sebagai static, atau ke trait/helper) supaya tidak duplikasi.
- "Langsung ditampilkan di layar": setelah simpan, redirect ke index sudah menampilkan kolom token.
  Untuk penekanan, tampilkan token di flash success ("Token manager: XXXX — bagikan ke manajer tim")
  dan/atau modal. Pastikan kolom token tidak lagi "N/A".

### Rencana implementasi
1. Pindahkan logika generator token ke `Team` model (mis. `Team::generateUniqueManagerToken(string $name)`)
   atau ke trait. Refactor `TeamController::store/resetToken` agar memakai sumber yang sama.
2. Di `TournamentParticipantController::store`, set `'manager_token' => Team::generateUniqueManagerToken($validated['name'])`
   pada `Team::create([...])`.
3. Flash success diperkaya menampilkan token (atau lewat session khusus untuk modal "salin token").
4. (Opsional) Backfill peserta lama yang `manager_token = null` lewat command/tinker satu kali.

### File terdampak
- `app/Http/Controllers/TournamentParticipantController.php` (`store`)
- `app/Models/Team.php` (method generator baru) atau trait baru
- `app/Http/Controllers/TeamController.php` (refactor agar pakai sumber sama)
- `resources/views/admin/tournaments/participants/index.blade.php` (flash/badge token)

### Kesulitan: 🟡 Sedang • **Tanpa migrasi** (kolom `manager_token` sudah ada)

### Risiko/catatan
- Jaga keunikan token (loop `while exists` sudah ada di `generateUniqueManagerToken`).
- Peserta lama dengan token null tetap bisa di-"Reset Token" (fitur lama tetap ada).

---

## N4 — Role Manager dapat melihat view bagan/bracket

**Revisi:** Pastikan pengguna dengan peran Manager dapat melihat view bagan/bracket pertandingan.

### Kondisi saat ini
- "Manager" di sistem ini = pengguna **portal Official** (login via `manager_token`,
  `OfficialAuth` middleware). Bukan role auth admin. (`TournamentTeamOfficial.role` juga punya
  nilai 'Manager'/'Coach', tapi konteks revisi = akses portal.)
- Di layout official `resources/views/official/layouts/app.blade.php` (nav bawah, baris 79):
  menu **"Bracket"** dirender sebagai `<span ... opacity-60>` **tanpa link / dinonaktifkan**
  ("Segera"). Jadi Manager **tidak punya akses** ke bracket sama sekali.
- Admin punya bracket di `tournaments.bracketAdmin` (`bracket/manage.blade.php`), tapi route itu
  ada di group `['auth','owns']` → tidak bisa dipakai portal official.

### Analisis
- Perlu route + controller + view **read-only** bracket untuk portal official:
  - Route baru di group `OfficialAuth`, mis. `GET /official/bracket`.
  - Controller (mis. `OfficialAuthController::bracket` atau controller baru) menyiapkan struktur
    bracket per turnamen yang diikuti tim (mirip data di `bracketAdmin`, tapi tanpa form simpan).
  - View read-only — bisa reuse komputasi `MatchGenerator::computeBracketCardTops()` dan markup
    kartu dari `bracket/manage.blade.php` (versi tanpa tombol edit/assign).
- Satu tim bisa ikut >1 turnamen → tampilkan per-turnamen (atau pilih turnamen).
- Hanya relevan untuk kompetisi yang punya bracket (`tournament` / `league_playoff`); untuk `league`
  murni, tampilkan pesan "tidak ada bracket".

### Rencana implementasi
1. Tambah route official `bracket` (group `OfficialAuth`).
2. Buat method controller yang memuat turnamen tim + struktur bracket (gunakan helper yang sama
   dengan admin agar konsisten).
3. Buat view `resources/views/official/bracket.blade.php` (read-only, responsif — selaras N8).
4. Ubah `official/layouts/app.blade.php`: ganti `<span>Bracket` jadi `<a href="{{ route('official.bracket') }}">`.

### File terdampak
- `routes/web.php`
- `app/Http/Controllers/OfficialAuthController.php` (atau controller baru)
- `resources/views/official/bracket.blade.php` (baru)
- `resources/views/official/layouts/app.blade.php`

### Kesulitan: 🟡 Sedang • **Tanpa migrasi**

### Risiko/catatan
- Read-only: jangan ekspos endpoint simpan/assign.
- Sebaiknya kerjakan setelah/bersama N8 agar markup bracket sudah responsif sebelum di-reuse.

---

## N5 — Tombol "Edit" khusus untuk isi/ubah Skor saja

**Revisi:** Di Kelola Jadwal & Skor, tombol "Edit" diubah fungsinya khusus untuk mengisi/mengubah
Skor saja.

### Kondisi saat ini
- View baris match: `resources/views/admin/tournaments/schedule/partials/match-table.blade.php`.
  - Tombol **"Edit Match"** (baris 162) membuka panel yang berisi **tanggal, waktu, status, skor home,
    skor away** sekaligus (baris 198–221). Submit ke `tournaments.matches.update` (`updateMatch`).
  - Ada catatan di UI (baris 224): "Edit hanya tanggal, waktu, dan status… Skor disimpan otomatis
    melalui Live Match Logger." — artinya saat ini field skor **ada tapi narasinya membingungkan**.
- Backend `updateMatch` (PATCH) menerima `match_date`, `match_time`, `match_status`, `home_score`,
  `away_score` (perlu cek validasinya di `TournamentController::updateMatch`).

### Analisis
- Revisi N5 + N6 saling terkait: pisahkan tanggung jawab tombol.
  - **"Edit" → hanya Skor** (N5).
  - **"Jadwal" → hanya tanggal/waktu** (N6).
- Maka panel "Edit Match" yang sekarang campur harus dipecah jadi dua panel/tombol berbeda.
- Backend `updateMatch` perlu mendukung partial update (hanya skor, atau hanya jadwal) — atau dipecah
  jadi dua endpoint (`updateScore`, `updateSchedule`) agar validasi & otorisasi bersih.

### Rencana implementasi
1. Ubah tombol "Edit Match" → **"Edit Skor"**; panelnya hanya menampilkan field Skor Home / Skor Away
   (+ mungkin status full_time otomatis saat skor diisi).
2. Sesuaikan `updateMatch` (atau buat `updateScore`) agar menerima hanya skor; jangan menimpa
   `match_date` saat hanya skor diubah.
3. Selaraskan teks bantu (hapus narasi lama yang menyebut skor lewat logger bila kini skor bisa via Edit).

### File terdampak
- `resources/views/admin/tournaments/schedule/partials/match-table.blade.php`
- `app/Http/Controllers/TournamentController.php` (`updateMatch` / endpoint skor baru)
- `routes/web.php` (bila pisah endpoint)

### Kesulitan: 🟡 Sedang • **Tanpa migrasi**

### Risiko/catatan
- **Kerjakan bersama N6** (satu paket UI jadwal/skor) agar tidak konflik edit di file yang sama.
- Hati-hati interaksi dengan Live Match Logger & tie 2-leg (skor leg, agregat, penalti) — jangan
  rusak idempotensi yang sudah ada. Untuk match knockout/tie, skor mungkin tetap harus lewat logger;
  konfirmasi cakupan "Edit Skor" (semua match vs hanya league/group).

---

## N6 — Tombol "Jadwal" terpisah + gating Live Match Logger

**Revisi:** Sediakan tombol terpisah "Jadwal" khusus mengisi/mengatur waktu pertandingan.
Logika: jika jadwal belum diisi, fitur Live Match Logger otomatis tidak bisa diaktifkan.

### Kondisi saat ini
- Di `match-table.blade.php`, "Edit Match" mencakup tanggal+waktu (lihat N5). Belum ada tombol
  "Jadwal" terpisah.
- Tombol **"Live Match Event Logger"** (baris 163–166) submit ke `tournaments.matches.liveLogger`.
  - Saat ini disabled hanya jika `$matchLocked` (kondisi lock lain), **belum** dikaitkan dengan
    "jadwal belum diisi".
  - `$matchReady` / `Menunggu...` badge sudah ada (baris 153–155) — perlu cek apa basisnya.

### Analisis
- Tambah tombol **"Jadwal"** yang membuka panel tanggal/waktu (+status) → submit ke endpoint jadwal.
- Gating logger: tombol "Live Match Event Logger" harus **disabled** bila `match_date` kosong (TBD).
  - Kondisi: `disabled` jika `empty($match['datetime'])` / `match_date == null`.
  - Tambah guard server di `openLiveMatchLogger` (tolak bila `match_date` null) sebagai jaring pengaman.

### Rencana implementasi
1. Tambah tombol "Jadwal" di area aksi match (sebelah "Edit Skor").
2. Panel "Jadwal" berisi tanggal + waktu (+ status laga); submit ke `updateSchedule` (atau `updateMatch`
   mode jadwal). Jangan menimpa skor.
3. Tombol Live Logger: tambahkan kondisi disabled berbasis `match_date` null + tooltip
   "Isi jadwal dulu sebelum memulai Live Match".
4. Guard server di `TournamentController::openLiveMatchLogger`: abort/redirect jika `match_date` null.

### File terdampak
- `resources/views/admin/tournaments/schedule/partials/match-table.blade.php`
- `app/Http/Controllers/TournamentController.php` (`openLiveMatchLogger`, endpoint jadwal)
- `routes/web.php` (bila pisah endpoint)

### Kesulitan: 🟡 Sedang • **Tanpa migrasi**

### Risiko/catatan
- **Satu paket dengan N5.**
- Untuk tie 2-leg, "jadwal terisi" perlu diperiksa per-leg; pastikan logger leg-2 tetap mengikuti
  aturan lama (menunggu leg-1) ditambah aturan baru (jadwal terisi).

---

## N7 — Riwayat Pertandingan (Official) → scoreboard tim yang sedang bertanding

**Revisi:** Halaman Riwayat Pertandingan pada akses Official diganti menjadi menampilkan
scoreboard tim yang sedang bertanding.

### Kondisi saat ini
- Tidak ada menu eksplisit bernama "Riwayat" di nav official (`official/layouts/app.blade.php`
  hanya: Beranda, Pemain, Official, Jadwal, Klasemen, Bracket[disabled], Profil[disabled]).
- Konsep "riwayat/pertandingan" terdekat: dashboard official menampilkan **"Pertandingan Berikutnya"**
  (`$nextMatch`, `official/dashboard.blade.php` ~baris 112), dan halaman **Jadwal** menampilkan daftar
  match (termasuk selesai/upcoming/TBD via filter).
- Belum ada tampilan **scoreboard live** (skor berjalan tim yang sedang `live_match`).

### Analisis
- Perlu klarifikasi lokasi persis "Riwayat Pertandingan" yang dimaksud user (kemungkinan section di
  dashboard atau tab di Jadwal). Asumsi kerja: **ganti/ubah** bagian yang menampilkan daftar match
  selesai menjadi **scoreboard pertandingan yang sedang berlangsung** (status `live_match` /
  `penalty_shootout`) milik tim tersebut.
- Data: query `TournamentMatch` tim dengan `status IN (live_match, penalty_shootout)` + skor terkini
  (`home_score`/`away_score`, penalty). Tampilkan kartu scoreboard (nama tim, logo, skor, status LIVE).
- Bila tidak ada laga live, tampilkan fallback ("Tidak ada pertandingan berlangsung") atau scoreboard
  pertandingan terdekat.

### Rencana implementasi
1. Tentukan titik tampil (rekomendasi: section "Sedang Berlangsung" di dashboard official + opsi tab
   di Jadwal). Konfirmasi ke user bila perlu.
2. Di controller terkait (`dashboard`/`schedule`), tambahkan query match `live_match` tim → kirim ke view.
3. Buat partial scoreboard live (selaras visual dengan logger admin) read-only.
4. Bila ada menu/section bernama "Riwayat", arahkan ke scoreboard ini.

### File terdampak
- `app/Http/Controllers/OfficialAuthController.php` (`dashboard` dan/atau `schedule`)
- `resources/views/official/dashboard.blade.php` dan/atau `resources/views/official/schedule.blade.php`
- partial scoreboard baru (mis. `official/partials/live-scoreboard.blade.php`)

### Kesulitan: 🟡 Sedang • **Tanpa migrasi**

### Risiko/catatan
- ⚠️ **Lokasi "Riwayat Pertandingan" belum pasti** di kode — sebelum implementasi, konfirmasi ke user
  halaman/menu mana yang dia maksud (agar tidak salah sasaran).
- Scoreboard "live" idealnya auto-refresh (polling sederhana) — sepakati apakah perlu realtime.

---

## N8 — Bracket responsif / bisa di-scroll rapi saat tim banyak

**Revisi:** Rapikan tampilan visual bagan (bracket). Card pertandingan harus responsif (menyesuaikan
layar / bisa di-scroll rapi) saat jumlah tim sangat banyak & melebar kiri-kanan.

### Kondisi saat ini
- View bracket admin: `resources/views/admin/tournaments/bracket/manage.blade.php`.
  - Layout pakai `@section('wrapper-class','h-screen overflow-hidden')` + `main-class overflow-y-auto`.
  - Kontainer bracket: `<div class="... overflow-x-auto">` (baris 189) membungkus
    `#bracketConnectorLayout` `min-w-max` (baris 190), kolom `flex gap-12` (baris 193), kartu
    lebar tetap `w-[200px]` (baris 195/308), tinggi via `computeBracketCardTops()`.
  - Konektor digambar SVG berbasis posisi kartu (script di bawah).
- Jadi **scroll horizontal sudah ada**, tapi: ukuran kartu/tinggi kanvas dihitung statis
  (`$cardHeight=120`, `$cardGap=120`), dan saat tim sangat banyak konektor SVG + tinggi kanvas
  bisa kurang rapi / overflow vertikal.

### Analisis
- Yang perlu diperbaiki: **kerapian saat skala besar**, bukan menambah scroll dari nol.
  - Pastikan kontainer scroll punya indikator/scrollbar yang jelas + padding agar kartu tepi tidak terpotong.
  - Pertimbangkan kontrol zoom / fit-to-width, atau kurangi `gap`/lebar kartu saat ronde banyak.
  - Pastikan konektor SVG dihitung ulang saat resize (cek apakah ada listener `resize`).
  - Responsif di mobile: kontainer scroll-x dengan momentum, jangan memecah layout.

### Rencana implementasi
1. Audit script konektor (`querySelectorAll('.bracket-card')`, recompute) — tambahkan recompute on
   `window.resize` bila belum ada.
2. Tambah pembungkus scroll yang konsisten (scrollbar tipis, `scroll-smooth`, padding) + hint UI
   "geser untuk melihat seluruh bagan".
3. Opsional: kontrol zoom (CSS `transform: scale`) atau toggle kompak (kurangi `gap-12`→`gap-8`,
   `w-[200px]`→`w-[170px]`) saat jumlah kolom > N.
4. Terapkan pola yang sama pada partial bracket lain (`bracket-section`, `bracket-settings-panel`)
   dan ke view bracket official (N4) agar konsisten.

### File terdampak
- `resources/views/admin/tournaments/bracket/manage.blade.php`
- `resources/views/admin/tournaments/settings/partials/bracket-section.blade.php`
- `resources/views/admin/tournaments/settings/partials/bracket-settings-panel.blade.php`
- (jika sudah ada) `resources/views/official/bracket.blade.php`

### Kesulitan: 🟡 Sedang • **Tanpa migrasi**

### Risiko/catatan
- Posisi kartu & konektor saling bergantung pada `computeBracketCardTops()`; uji ulang dengan
  jumlah tim besar (mis. 16/32) agar konektor tidak melenceng.
- Jangan ubah algoritma posisi tanpa uji regresi visual.

---

## N9 — Sinkronisasi: data Official/Manager muncul di panel Admin

**Revisi:** Data input nama Official/Manager belum masuk/tidak muncul di panel Admin. Hubungkan agar
tersimpan & terbaca oleh Admin.

### Kondisi saat ini
- Manager (portal official) menginput Official via `OfficialTeamOfficialController` → tersimpan di
  `tournament_team_officials` (model `TournamentTeamOfficial`, kolom `role`, dll.) — terikat ke
  `tournament_team_id`.
- Di sisi Admin: **tidak ditemukan view yang menampilkan daftar Official/Manager** suatu tim.
  - Manajemen Peserta (`participants/index.blade.php`) menampilkan jumlah **Pemain** (expandable),
    Manager Token, status verifikasi — **tetapi tidak menampilkan Ofisial/Manager tim**.
  - Halaman Verifikasi Berkas juga berfokus dokumen tim, bukan daftar ofisial.
- Jadi data ofisial **tersimpan** (kemungkinan besar) tetapi **tidak ditampilkan** di Admin → inilah
  "tidak muncul".

### Analisis
- Perlu konfirmasi apakah masalahnya (a) data tidak tersimpan, atau (b) tersimpan tapi tak ditampilkan.
  Bukti kode menunjukkan **(b) lebih mungkin** (controller official menyimpan ke tabel; admin tak punya
  view-nya). Verifikasi via tinker: cek `TournamentTeamOfficial::count()` & relasi.
- Solusi utama: **tampilkan daftar Official/Manager per tim di panel Admin** (mis. di Manajemen Peserta,
  expandable seperti daftar pemain, atau di halaman detail peserta).
- Pastikan relasi model lengkap: `TournamentTeam` → `officials()` (hasMany `TournamentTeamOfficial`),
  dan `Team`/`Tournament` bisa menjangkau ofisial. Cek apakah relasi sudah ada.

### Rencana implementasi
1. Verifikasi data tersimpan (tinker) & lengkapi relasi Eloquent bila ada yang hilang
   (`TournamentTeam::officials`, eager-load di `participants index`).
2. Tampilkan ofisial di Manajemen Peserta: tambah panel expandable "Ofisial/Manager" per peserta
   (mirip blok pemain), tampilkan nama + role.
3. (Opsional) Tampilkan juga di halaman verifikasi/detail tim.
4. Jika ternyata data **tidak** tersimpan (skenario a), perbaiki `store()` di
   `OfficialTeamOfficialController` (mapping `tournament_team_id`, validasi, lock saat approved).

### File terdampak
- `app/Http/Controllers/TournamentParticipantController.php` (eager-load officials di `index`)
- `app/Models/TournamentTeam.php` (relasi `officials` bila belum ada)
- `resources/views/admin/tournaments/participants/index.blade.php` (panel ofisial)
- (kondisional) `app/Http/Controllers/OfficialTeamOfficialController.php`

### Kesulitan: 🟡 Sedang • **Migrasi: kemungkinan tidak** (hanya jika kolom relasi kurang)

### Risiko/catatan
- ⚠️ Diagnosa dulu (tersimpan vs tampil) sebelum menulis kode — hemat usaha.
- Hormati lock R18 (tim approved → data terkunci) saat menampilkan.

---

## N10 — Setting "Peringkat 3" tidak boleh berefek di Bracket Gugur

**Revisi:** Setting Peringkat 3 dibuat tidak ada efek saat di bracket gugur.

### Kondisi saat ini
- Opsi `third_place` tersedia di pengaturan bracket
  (`bracket-settings-panel.blade.php` baris 131: checkbox `name="third_place"`), dan **diterapkan**
  ke semua tipe yang punya bracket — termasuk **tournament (gugur murni)**:
  - `TournamentController` memakai `third_place` di banyak tempat (baris 1092–1130, 1661–1675, dst.)
    untuk membuat babak "Third Place".
  - Narasi UI bahkan menyebut untuk tipe gugur: "babak Third Place akan muncul... diisi runner-up
    semifinal" (`bracket-settings-panel.blade.php` baris 207, 209).
  - Render kartu "Third Place" ada di `bracket/manage.blade.php` (baris 317), `bracket-section`,
    dan `standings`.

### Analisis
- ⚠️ **Ambiguitas penting.** "Tidak ada efek saat di bracket gugur" bisa berarti:
  - **(A)** Untuk kompetisi **tournament (gugur murni)**, opsi Peringkat 3 harus dimatikan/diabaikan
    (tidak membuat babak Third Place). — interpretasi paling literal.
  - **(B)** Babak perebutan juara 3 tidak boleh mempengaruhi jalannya bracket utama (mis. tidak
    memengaruhi seeding/advancement) — tapi ini sudah terpisah (third place = runner-up SF).
- Interpretasi (A) paling masuk akal dengan kalimat revisi. Maka: pada `competition_type === 'tournament'`,
  paksa `third_place = false` (sembunyikan checkbox + abaikan di generator + jangan render kartu).

### Rencana implementasi (asumsi interpretasi A — konfirmasi ke user)
1. Di `updateBracketSettings`/normalisasi setting: bila `competition_type === 'tournament'`,
   set `third_place = false` (server-side, abaikan input).
2. Di `bracket-settings-panel.blade.php`: sembunyikan/disable checkbox Peringkat 3 saat tipe `tournament`;
   hapus narasi yang menjanjikan Third Place untuk tipe gugur.
3. Di generator (`MatchGenerator` / method bracket di controller): jangan buat match `is_third_place`
   untuk tipe `tournament`.
4. Di view bracket (`manage`, `bracket-section`, `standings`): jangan render kartu Third Place untuk
   tipe `tournament`.
5. Migrasi data: turnamen `tournament` lama yang sudah punya match `is_third_place=true` → bersihkan
   saat regenerate (atau command satu kali).

### File terdampak
- `app/Http/Controllers/TournamentController.php` (normalisasi setting + generator bracket)
- `app/Services/MatchGenerator.php` (bila pembuatan third place ada di sini)
- `resources/views/admin/tournaments/settings/partials/bracket-settings-panel.blade.php`
- `resources/views/admin/tournaments/bracket/manage.blade.php`
- `resources/views/admin/tournaments/settings/partials/bracket-section.blade.php`
- `resources/views/admin/tournaments/standings.blade.php`

### Kesulitan: 🟡 Sedang • **Tanpa migrasi struktur** (mungkin command pembersih data)

### Risiko/catatan
- ⚠️ **Konfirmasi interpretasi (A) vs (B) sebelum coding.** Salah tafsir = rework besar.
- Regenerate bracket menghapus skor lama (sudah jadi sifat sistem) — peringatkan user.

---

## N11 — Tab "Jadwal Internal" vs "Jadwal Turnamen" (sisi Manager)

**Revisi:** Di halaman Jadwal sisi Manager, sediakan dua tab/filter: **Jadwal Internal**
(hanya laga tim manager) dan **Jadwal Turnamen** (seluruh laga semua tim).

### Kondisi saat ini
- `OfficialAuthController::schedule()` saat ini **hanya** mengambil match milik tim
  (`whereIn home_team_id/away_team_id` dengan `tournamentTeamIds` tim sendiri, baris 103–109).
  Ada filter waktu (`all/upcoming/finished/tbd`) tapi **tidak ada** mode "semua tim turnamen".
- View `official/schedule.blade.php` sudah punya filter (`$filter`), tinggal ditambah dimensi baru.
- `teamTournamentTeamIds` sudah dikirim ke view (untuk highlight laga sendiri) — berguna untuk mode turnamen.

### Analisis
- Tambah parameter `scope` (`internal` | `tournament`):
  - `internal` (default): perilaku sekarang (laga tim sendiri).
  - `tournament`: ambil **semua match dari semua turnamen yang diikuti tim** (bukan hanya match tim),
    yaitu `whereIn('tournament_id', $tournamentIds)`.
- Tetap pertahankan filter waktu yang ada (kombinasikan scope × filter).
- Highlight laga tim sendiri di mode turnamen (pakai `teamTournamentTeamIds`).

### Rencana implementasi
1. `schedule()`: baca `request('scope','internal')`. Jika `tournament`, query berbasis `tournament_id`
   (semua match turnamen yang diikuti); jika `internal`, query lama.
2. Kirim `scope` ke view. Tambah dua tab "Jadwal Internal" / "Jadwal Turnamen" (link `?scope=...`),
   pertahankan filter waktu.
3. Di mode turnamen, tandai baris laga tim sendiri (badge "Tim Anda").

### File terdampak
- `app/Http/Controllers/OfficialAuthController.php` (`schedule`)
- `resources/views/official/schedule.blade.php`

### Kesulitan: 🟢 Ringan • **Tanpa migrasi**

### Risiko/catatan
- Mode "tournament" bisa banyak baris → pertimbangkan pagination/grup per tanggal.
- Pastikan data lawan (homeTeam/awayTeam) tetap eager-loaded.

---

## N12 — Halaman Statistik lengkap (Manajemen Pemain — sisi Admin)

**Revisi:** Tambahkan menu yang hilang "Manajemen Pemain" (sisi Admin) yang menampilkan statistik
lengkap: Top Skor, Top Assist, akumulasi Kartu Kuning terbanyak, akumulasi Kartu Merah terbanyak,
Tim Paling Produktif (gol terbanyak), Tim Paling Banyak Kebobolan, Tim Paling Fairplay
(akumulasi kartu kuning+merah paling sedikit).

### Kondisi saat ini
- **Tidak ada menu/halaman "Manajemen Pemain" di Admin** (sidebar admin hanya: Ikhtisar, Verifikasi,
  Pengaturan, Kelola Jadwal & Skor, Bagan Klasemen, Bracket Gugur, Manajemen Peserta).
- Statistik per-pemain **sebagian sudah dihitung** di `TournamentParticipantController::index()`
  (R19): agregasi `goals`, `yellow_cards`, `red_cards` dari `match_events` per `player_id`
  (baris 22–31). Tapi ini hanya tampil sebagai badge di kartu pemain, **bukan halaman statistik ranking**.
- ⚠️ **"Top Assist" BELUM didukung:** event_type yang divalidasi di `storeMatchEvent`
  (`TournamentController` baris 2594) = `goal,own_goal,yellow_card,red_card,foul,timeout,halftime,
  full_time,penalty_goal,penalty_miss` — **tidak ada `assist`**. Maka "Top Assist" perlu fitur baru:
  pencatatan assist saat live logger.

### Analisis
- Statistik yang bisa langsung dihitung dari data sekarang:
  - **Top Skor** ✅ (`goal` + mungkin `penalty_goal`; tentukan apakah penalti dihitung).
  - **Kartu Kuning/Merah terbanyak (pemain)** ✅.
  - **Tim Paling Produktif** ✅ (sum gol per tim dari `matches.home_score/away_score` atau dari events).
  - **Tim Paling Kebobolan** ✅ (sum kebobolan per tim).
  - **Tim Paling Fairplay** ✅ (sum kartu kuning+merah per tim, paling sedikit).
- **Top Assist** ❌ butuh:
  1. Tambah `assist` ke daftar event_type (validasi + UI live logger untuk mencatat assist + `player_id`).
  2. Migrasi tidak wajib untuk kolom (sudah ada `player_id` di `match_events`), tapi **alur input** baru.
  3. Tanpa input assist, "Top Assist" akan kosong. → **Ini bagian terberat.**
- Komputasi agregat sebaiknya diekstrak ke service/query reusable (dipakai ulang oleh N13).

### Rencana implementasi
1. **Backend statistik:** buat method/service yang menghitung semua metrik per turnamen
   (top skor, top assist, kartu kuning, kartu merah, tim produktif, tim kebobolan, tim fairplay).
   Gunakan join `match_events` ↔ `tournament_team_players` ↔ `tournament_teams`/`teams`.
2. **Top Assist (fitur baru):**
   - Tambah `assist` ke `event_type` validasi di `storeMatchEvent`.
   - Tambah tombol "Assist" di Live Match Logger (pilih pemain pemberi assist), simpan event `assist`
     dengan `player_id` + `team_side`.
   - (Opsional) kaitkan assist ke gol tertentu — untuk MVP cukup hitung total assist per pemain.
3. **Menu & halaman Admin:** tambah item sidebar "Manajemen Pemain"/"Statistik Pemain",
   route + controller method + view ranking (tabel Top Skor, Top Assist, dst. + statistik tim).
4. Tentukan kebijakan: apakah `penalty_goal` dihitung sebagai gol; apakah `own_goal` dihitung ke tim lawan.

### File terdampak
- `app/Http/Controllers/TournamentController.php` (atau controller statistik baru) + route
- `app/Services/` (service statistik baru, reusable untuk N13)
- `resources/views/admin/tournaments/partials/sidebar.blade.php` (menu baru)
- `resources/views/admin/tournaments/statistics.blade.php` (baru)
- `app/Http/Controllers/TournamentController.php` `storeMatchEvent` + Live Logger view
  (`schedule/manage.blade.php`) untuk fitur assist
- (opsional) migrasi tidak wajib; `match_events.player_id` sudah ada

### Kesulitan: 🔴 Berat • **Migrasi: tidak wajib** (tapi butuh fitur input assist baru)

### Risiko/catatan
- ⚠️ **Top Assist = fitur baru**, bukan sekadar tampilan. Tanpa input assist, kolomnya kosong.
  Konfirmasi ke user: cukup sediakan slot Top Assist (kosong sampai assist dicatat), atau wajib
  bangun input assist sekarang.
- Tentukan scope statistik: per-turnamen (disarankan) vs global.
- Reuse service ini untuk N13 (jangan duplikasi query).

---

## N13 — Statistik view-only untuk Manager & Tamu/Visitor

**Revisi:** Seluruh statistik pada N12 juga harus bisa diakses & dilihat (view-only) melalui halaman
pengguna Manager dan Tamu/Visitor. (Catatan: revisi menyebut "Poin 7" — konteks merujuk paket
statistik N12.)

### Kondisi saat ini
- **Manager (portal official):** punya menu Klasemen, Jadwal — belum ada menu Statistik.
- **Tamu/Visitor (public):** praktis **belum ada area fungsional**. Yang ada hanya
  `public/portal.blade.php` (landing), `public/welcome.blade.php`, dan
  `public/auth/login-placeholder.blade.php` (route `public.login` → placeholder).
  Tidak ada halaman publik untuk melihat statistik turnamen.

### Analisis
- Tergantung penuh pada N12 (service statistik). Setelah service ada, buat **dua view-only**:
  1. **Manager:** menu "Statistik" di nav official → halaman statistik turnamen yang diikuti tim.
  2. **Tamu/Visitor:** halaman publik (tanpa login) untuk melihat statistik turnamen.
     - Perlu keputusan akses: turnamen mana yang publik? (semua, atau yang ditandai publik?)
     - Perlu route publik baru + view publik (bangun area public yang selama ini stub).
- Keduanya **read-only**: tidak ada tombol input/ubah.

### Rencana implementasi
1. **Prasyarat:** selesaikan service statistik N12.
2. **Manager:** tambah route `official.statistics` (group `OfficialAuth`) + method controller +
   menu di `official/layouts/app.blade.php` + view `official/statistics.blade.php` (reuse partial tabel N12).
3. **Tamu/Visitor:** tambah route publik (mis. `/public/tournaments/{tournament}/statistics`) +
   controller publik + view `public/statistics.blade.php`. Tentukan kebijakan turnamen yang boleh
   diakses publik (mis. yang sudah berjalan / di-publish).
4. Ekstrak tabel statistik jadi partial bersama agar Admin (N12) / Manager / Public memakai markup sama.

### File terdampak
- `routes/web.php` (route official + public)
- `app/Http/Controllers/OfficialAuthController.php` atau controller statistik official baru
- controller publik baru (mis. `PublicStatisticsController`)
- `resources/views/official/layouts/app.blade.php` (menu)
- `resources/views/official/statistics.blade.php` (baru)
- `resources/views/public/statistics.blade.php` (baru)
- partial tabel statistik bersama (reuse N12)

### Kesulitan: 🔴 Berat • **Tanpa migrasi** (asumsi reuse service N12)

### Risiko/catatan
- ⚠️ **Area publik/Tamu nyaris belum ada** — perlu membangunnya, bukan sekadar menambah halaman.
- ⚠️ Keamanan: pastikan halaman publik hanya mengekspos data statistik yang memang boleh publik
  (jangan bocorkan token manager, data pribadi pemain, dsb.).
- Kerjakan **setelah N12** dan reuse service/partial-nya.

---

## N14 — Bagan Knockout model dua sisi kiri-kanan (mirror bracket)

**Revisi:** Pada bagan Knockout, dibuat model **dua sisi kiri-kanan** (mirror) seperti format
Piala Dunia (referensi gambar "KO STAGE"): separuh tim mengisi sisi **kiri** (mengerucut ke kanan),
separuh lagi mengisi sisi **kanan** (mengerucut ke kiri), bertemu di **Final** yang berada di tengah.

### Kondisi saat ini
- Semua view bracket memakai layout **satu arah** (kiri → kanan):
  - `resources/views/admin/tournaments/bracket/manage.blade.php`: `#bracketConnectorLayout`
    (`min-w-max`), kolom `<div class="relative flex gap-12 ...">` (baris 193) ditata berurutan
    dari ronde awal di kiri sampai **Final di kolom paling kanan**
    (`data-final-column` di kolom terakhir, baris 195).
  - Posisi vertikal kartu dihitung `MatchGenerator::computeBracketCardTops($bracketColumns, $rowUnit)`
    (baris 176) — graf feeder→next, kartu di tengah pengumpannya.
  - Konektor digambar SVG (`#bracketConnectorSvg`) berbasis `data-match-id` / `data-next-match-id`
    (script di bawah view) — **mengasumsikan aliran satu arah kiri→kanan**.
  - Pola serupa ada di `settings/partials/bracket-section.blade.php` dan
    `settings/partials/bracket-settings-panel.blade.php`.
- Jadi saat ini **tidak ada konsep "dua sisi"**; Final selalu di ujung kanan.

### Analisis
- Ini perubahan **struktur layout & konektor** yang cukup dalam, menyentuh:
  1. **Pembagian sisi**: ronde-ronde harus dipecah jadi sisi kiri (top half seeding) & sisi kanan
     (bottom half), bertemu di Final tengah. Butuh logika "belah bracket di tengah".
  2. **Rendering**: dua kolom-set yang saling cermin — sisi kanan diurutkan terbalik (Final → ronde awal
     dari tengah ke tepi kanan), termasuk posisi skor & arah panel kartu.
  3. **Konektor SVG**: harus menggambar siku-siku ke **dua arah** (sisi kiri menuju kanan-tengah,
     sisi kanan menuju kiri-tengah). Script konektor sekarang satu arah → perlu mode mirror.
  4. **`computeBracketCardTops`**: perhitungan posisi vertikal masih relevan, tapi pemetaan kolom→sisi
     dan koordinat-x perlu disesuaikan (kolom sisi kanan dibalik).
- Berlaku untuk **semua tipe yang punya bracket** (`tournament`, `league_playoff`) dan **semua view**
  bracket: admin (manage + settings panel + section), Official (N4 read-only), Public (N13 bila ada
  tampilan bracket publik). Konsistensi visual lintas role penting.
- **Catatan kelayakan:** mirror paling natural bila jumlah peserta bracket = pangkat 2 (8/16/32 → seimbang
  kiri-kanan). Untuk jumlah ganjil / banyak bye, pembelahan harus tetap menempatkan Final di tengah dan
  membagi feeder seimbang — perlu aturan pembelahan yang robust (mis. belah berdasar subtree Final).

### Rencana implementasi
1. **Logika pembelahan sisi** (PHP, idealnya di `MatchGenerator` agar reusable): dari struktur bracket,
   tentukan match Final, lalu telusuri dua subtree pengumpannya → subtree kiri = sisi kiri,
   subtree kanan = sisi kanan. Hasilkan dua set kolom (kiri normal, kanan ter-reverse) + Final tengah.
2. **Helper posisi**: perluas/duplikasi `computeBracketCardTops` agar bisa menghitung tops per-sisi;
   koordinat-x sisi kanan dihitung dari kanan ke kiri.
3. **Refactor view bracket** jadi layout 3 bagian: `[kolom sisi kiri] [Final + trofi/tengah] [kolom sisi kanan]`.
   Pertimbangkan **ekstrak partial bracket bersama** supaya admin/official/public memakai markup sama
   (sekalian mendukung N4 & N8 & N13).
4. **Rewrite konektor SVG** untuk mode mirror (dua arah). Recompute on resize (sinkron dengan N8).
5. **Fallback**: bila jumlah/struktur tak memungkinkan mirror rapi, fallback ke layout satu arah lama
   (atau tetap mirror dengan satu sisi lebih berat) — tetapkan aturannya.
6. Uji visual dengan 4/8/16/32 tim + kasus bye.

### File terdampak
- `app/Services/MatchGenerator.php` (logika pembelahan sisi + helper posisi)
- `resources/views/admin/tournaments/bracket/manage.blade.php` (+ script konektor)
- `resources/views/admin/tournaments/settings/partials/bracket-section.blade.php`
- `resources/views/admin/tournaments/settings/partials/bracket-settings-panel.blade.php`
- `resources/views/admin/tournaments/standings.blade.php` (bracket playoff bila ditampilkan)
- `resources/views/official/bracket.blade.php` (N4) + bracket publik (N13) bila ada
- partial bracket bersama (baru, sangat disarankan)

### Kesulitan: 🔴 Berat • **Tanpa migrasi** (murni layout/render + helper posisi)

### Risiko/catatan
- ⚠️ **Perubahan terbesar pada lapisan bracket.** Konektor SVG & perhitungan posisi rapuh — wajib uji
  regresi visual menyeluruh (jangan rusak bracket yang sudah jalan).
- ⚠️ **Sinergikan dengan N8 (responsif) & N4 (bracket official)**: kerjakan **setelah** keduanya, dan
  manfaatkan momentum untuk mengekstrak **satu partial bracket** yang dipakai semua role.
- Tetapkan aturan pembelahan untuk jumlah tim non-pangkat-2 / banyak bye sebelum coding.
- Final di tengah (dengan elemen trofi seperti referensi) bersifat opsional/estetis — inti revisi =
  arah dua sisi.

---

## Pemetaan Revisi ↔ Role (4 Level)

Sistem memiliki **4 level role**:

| Role | Definisi teknis | Akses |
|------|-----------------|-------|
| **Public** (Tamu/Visitor) | Tanpa login. Area `public/*` (saat ini hampir kosong: portal + welcome + login placeholder). | View-only publik. |
| **Official** (Official / Manager / Peserta) | Login **portal token** (`manager_token`, middleware `OfficialAuth`, session `official_team_id`). | Kelola pemain/ofisial tim sendiri, lihat jadwal/klasemen/bracket. |
| **Admin** (Pemilik Turnamen) | Login email/Google (`users`), pemilik turnamen via `created_by`. Middleware `auth` + `owns`. | Kelola penuh turnamen miliknya. |
| **Root** (super-admin) | `users.is_root = true`. Middleware `root` (`/root/*`). | Tinjau & ACC upgrade paket langganan. |

### Tabel pemetaan

| # | Revisi | Public | Official | Admin | Root |
|---|--------|:------:|:--------:|:-----:|:----:|
| N1 | Disable tombol Tambah Peserta saat slot penuh | — | — | ✅ | — |
| N2 | Undian (Draw) → halaman Bagan Klasemen | — | — | ✅ | — |
| N3 | Token auto-generate & langsung tampil | — | 🔸 (token dipakai login Official) | ✅ | — |
| N4 | Manager bisa lihat bracket | — | ✅ | — | — |
| N5 | Tombol Edit khusus Skor | — | — | ✅ | — |
| N6 | Tombol Jadwal + gating Live Logger | — | — | ✅ | — |
| N7 | Riwayat Official → scoreboard live | — | ✅ | — | — |
| N8 | Bracket responsif / scroll rapi | 🔸 (jika bracket publik N13) | 🔸 (via N4) | ✅ | — |
| N9 | Sinkron data Official/Manager ke Admin | — | 🔸 (sumber data) | ✅ (konsumen) | — |
| N10 | Peringkat 3 tidak berefek di gugur | — | — | ✅ | — |
| N11 | Tab Jadwal Internal vs Turnamen | — | ✅ | — | — |
| N12 | Statistik lengkap (Manajemen Pemain) | — | — | ✅ | — |
| N13 | Statistik view-only Manager & Tamu | ✅ | ✅ | 🔸 (sumber service N12) | — |
| N14 | Bracket dua sisi kiri-kanan (mirror) | 🔸 (jika bracket publik) | ✅ (via N4) | ✅ | — |

**Keterangan:** ✅ = role utama yang terdampak/jadi sasaran revisi • 🔸 = terdampak tidak langsung
(menjadi sumber data, prasyarat, atau hanya jika fitur publik diaktifkan) • — = tidak relevan.

### Catatan pemetaan
- **Root tidak tersentuh** oleh gelombang revisi ini (semua di luar area langganan `/root/*`).
- **Admin** = role paling banyak terdampak (N1, N2, N5, N6, N9, N10, N12 + sebagian N3, N8, N13, N14).
- **Official** terdampak N4, N7, N11 + sebagian N3 (token), N8/N14 (bracket), N9 (sumber), N13.
- **Public** hanya benar-benar muncul di **N13** (statistik view-only) dan **opsional** N8/N14 bila
  bracket dibuat dapat diakses publik. Karena area Public masih stub, mengaktifkan fitur Public =
  membangun area baru.
- **N3** menyentuh dua role secara berurutan: Admin **menggenerate** token, Official **memakainya** login.
- **N9** adalah jembatan: data diinput Official, harus terbaca Admin.

---

## Catatan Lintas-Revisi (perlu konfirmasi user sebelum mulai)

1. **N5 + N6 satu paket** (file `match-table.blade.php` & `updateMatch` sama) — kerjakan bersamaan.
2. **N12 + N13 satu paket** (service statistik bersama) — kerjakan terakhir.
3. **N4 + N8 + N14 saling terkait** (semua lapisan bracket): disarankan **ekstrak satu partial bracket
   bersama** dan kerjakan berurutan N8 (responsif) → N4 (bracket official) → N14 (mirror) agar markup
   tidak ditulis ulang berkali-kali.
4. **Top Assist (N12)** = fitur input baru (`assist` belum ada di `event_type`). Tanyakan: bangun
   input assist sekarang atau sediakan slot kosong dulu?
5. **N10 (Peringkat 3)** ada ambiguitas interpretasi (A: matikan untuk tipe gugur murni / B: lainnya).
   Konfirmasi sebelum coding.
6. **N7 (Riwayat Official)** lokasi "Riwayat Pertandingan" belum pasti di kode — konfirmasi halaman/menu
   yang user maksud.
7. **N13 (Tamu/Visitor)** kebijakan akses publik (turnamen mana yang boleh dilihat publik) perlu ditetapkan.
8. **N14 (mirror bracket)** tetapkan aturan pembelahan sisi untuk jumlah tim non-pangkat-2 / bye; putuskan
   apakah elemen trofi tengah perlu.
9. Banyak perubahan kemungkinan **belum di-commit** sesuai pola kerja sebelumnya — gunakan branch &
   commit bertahap per revisi.

---

### Lampiran: Pemetaan istilah peran (detail teknis)

- **Public (Tamu/Visitor)** = pengunjung tanpa login. Area `public/*` (`portal.blade.php`,
  `welcome.blade.php`, `auth/login-placeholder.blade.php`) — saat ini hampir kosong / stub.
- **Official (Official / Manager / Peserta)** = pengguna **portal official** (login via `manager_token`,
  middleware `OfficialAuth`, session `official_team_id`). Di revisi, sebutan "Manager" = pengguna portal
  official ini. `TournamentTeamOfficial.role` ('Manager'/'Coach'/dll.) = jabatan ofisial dalam tim (data),
  **bukan** peran autentikasi.
- **Admin (Pemilik Turnamen)** = pengguna login email/Google (`users`), pemilik turnamen (`created_by`),
  middleware `auth` + `owns`.
- **Root (super-admin)** = `users.is_root = true`, middleware `root` (prefix `/root/*`), khusus ACC
  upgrade paket langganan (R22).
- **Tamu/Visitor** = pengunjung publik tanpa login (area `public/*`, saat ini hampir kosong).
- `TournamentTeamOfficial.role` ('Manager'/'Coach'/dll.) = jabatan ofisial dalam tim (data), bukan
  peran autentikasi.
