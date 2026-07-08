# Panduan `tournament:simulate` — Simulator Turnamen untuk Pengujian

Command artisan untuk **menguji sistem dalam skala besar tanpa input manual**.

Simulator akan:

1. **Generate tim dummy** lengkap — nama tim, kota, token manager, pemain
   (posisi, nomor punggung, kapten), dan official (Manager/Coach) — langsung
   berstatus *approved*.
2. **Menjalankan undian grup** memakai fungsi undian asli sistem
   (`performGroupDraw`).
3. **Memainkan seluruh pertandingan** — skor, pencetak gol, assist, kartu
   kuning/merah, sampai adu penalti — lewat jalur yang **sama persis dengan
   input manual admin** (Live Match Event Logger + End Match). Klasemen,
   pengisian bracket, dan playoff dihitung oleh kode produksi sungguhan,
   bukan duplikat logika.
4. **Memvalidasi hasilnya otomatis** dan memberi laporan PASS/FAIL.

> ⚠️ **Penting:** jalankan hanya di **turnamen tes**, jangan di turnamen yang
> berisi data asli. Simulator bisa meregenerasi jadwal (menghapus hasil lama)
> saat menambah tim.

---

## Langkah pemakaian dari nol

### Langkah 1 — Siapkan turnamen di UI seperti biasa

Buat turnamen baru lalu atur pengaturannya lewat menu admin:

| Mode yang mau diuji | Yang harus diatur di UI |
|---|---|
| **Grup → Gugur** | Tipe kompetisi, jumlah grup × tim per grup, peringkat lolos (mis. 1 & 2), single/home-away, juara 3 |
| **Liga** | Tipe kompetisi liga, putaran (single/double), pengaturan poin |
| **Gugur murni** | Tipe kompetisi turnamen (bracket), juara 3 kalau mau |

**Jangan tambah peserta manual** — biar simulator yang mengisi. Catat ID
turnamennya (terlihat di URL, mis. `/tournaments/12/...`).

### Langkah 2 — Jalankan simulasi

```bash
php artisan tournament:simulate 12
```

(`php83 artisan ...` juga bisa.) Tanpa opsi apa pun, simulator akan:

- mengisi tim sampai **kapasitas grup penuh** (mis. 4 grup × 4 tim = 16 tim),
  atau **8 tim** untuk mode tanpa grup;
- 10 pemain + 2 official per tim;
- memainkan **semua** laga sampai juara ditentukan.

Kalau ID tidak ditulis, muncul menu pilih turnamen.

### Langkah 3 — Baca laporannya

Output terdiri dari 4 bagian:

**a. Ringkasan** — jumlah laga selesai, total event (gol, bunuh diri, assist,
kartu), jumlah adu penalti.

**b. Klasemen per grup** — kolom: `Ma` main, `M` menang, `S` seri, `K` kalah,
`GM` gol memasukkan, `GK` gol kemasukan, `SG` selisih gol, `Poin`. Dihitung
fungsi klasemen produksi, bukan hitungan simulator sendiri.

**c. Juara & Top Skor** — juara dari Final bracket / puncak klasemen liga;
top skor dari event gol ber-`player_id` (data yang sama dengan halaman
Statistik).

**d. Validasi konsistensi** — bagian terpenting:

| Validasi | Artinya kalau FAIL |
|---|---|
| Seluruh laga non-bye full_time | Ada laga yang tak pernah bisa dimainkan (mis. slot tak terisi) → bug alur |
| Semua slot bracket/playoff terisi | Ada slot bracket kosong padahal semua laga selesai → bug advancement |
| Skor akhir = jumlah event gol | Skor dan event tidak sinkron → bug pencatatan skor |
| Jumlah main sesuai round robin | Jadwal grup/liga kurang/kelebihan laga → bug generator jadwal |
| Juara berhasil ditentukan | Sistem gagal menyimpulkan juara → bug resolusi juara |

Kalau semua hijau: `✔ Semua validasi lolos`. Kalau ada FAIL, kolom kanannya
memuat detail (termasuk **ID laga** bermasalah) — petunjuk langsung ke bugnya.
*(Contoh nyata: run perdana simulator menangkap laga juara-3 yang menggantung
karena slot "Runner-up M#" tidak pernah diisi — bug asli yang kemudian
diperbaiki lewat `routeLoserToRunnerUpSlots()`.)*

### Langkah 4 — Cek visual di UI

Buka turnamen tes di browser: **Klasemen**, **Bracket Gugur**, **Jadwal**
(Live Logger bisa dibuka read-only per laga untuk melihat kronologi gol/kartu),
**Statistik**, dan **Manajemen Peserta** (roster pemain & official ikut
ter-generate). Ini cara cepat memeriksa tampilan dengan data ramai.

### Langkah 5 — Ulangi skenario lain

```bash
# ubah pengaturan turnamen di UI dulu (mis. jadi 8 grup × 4, home-away), lalu:
php artisan tournament:simulate 12 --fresh
```

`--fresh` menghapus semua tim buatan simulator + meregenerasi jadwal, jadi
tiap run mulai bersih. **Tim asli (bukan buatan simulator) tidak disentuh** —
tim simulator ditandai khusus di kolom `notes`
(`auto-generated:tournament-simulator`).

---

## Semua opsi

| Opsi | Default | Fungsi |
|---|---|---|
| `--teams=N` | kapasitas grup / 8 | **Target total** tim (bukan tambahan). Kalau sudah ada 10 tim dan `--teams=16`, hanya 6 yang dibuat. Melebihi kapasitas grup → otomatis dipangkas. |
| `--players=N` | 10 | Pemain per tim (2–15; 2 kiper kalau ≥8 pemain) |
| `--officials=N` | 2 | Official per tim (1–3: Manager, Coach, Assistant Coach) |
| `--seed=N` | acak | Skor & pencetak gol bisa direproduksi — berguna saat men-debug kasus tertentu. *Catatan: hasil undian grup tetap acak (pengacak sistem tidak bisa di-seed).* |
| `--fresh` | — | Bersihkan tim simulasi lama + reset jadwal sebelum mulai |
| `-v` | — | Tampilkan hasil per laga (`[group] Tim A 3 - 2 Tim B`), bukan cuma progress bar |
| `-n` | — | Non-interaktif: lewati semua pertanyaan konfirmasi |

---

## Resep skenario siap pakai

```bash
# Grup→Gugur standar (ikuti kapasitas grup di pengaturan)
php artisan tournament:simulate 12

# Uji skala besar: atur 8 grup × 4 di UI dulu, lalu
php artisan tournament:simulate 12 --fresh

# Liga 20 tim putaran ganda (atur grup=1, tim per grup ≥20, putaran double di UI)
php artisan tournament:simulate 12 --teams=20 --fresh

# Gugur murni jumlah ganjil — menguji bye
php artisan tournament:simulate 12 --teams=13 --fresh

# Reproduksi bug: seed sama = skor sama
php artisan tournament:simulate 12 --seed=42 --fresh -v

# Hanya melanjutkan laga yang belum selesai (tanpa tambah tim, tanpa reset)
php artisan tournament:simulate 12

# Bersih-bersih setelah selesai menguji (hapus tim simulasi saja)
php artisan tournament:simulate 12 --fresh --teams=0
#   → pembersihan jalan, lalu berhenti dengan pesan "Minimal 2 tim" — normal,
#     abaikan. Turnamen tesnya sendiri dihapus manual lewat UI bila sudah
#     tidak dipakai.
```

---

## Perilaku yang perlu diketahui

- **Menambah tim = regenerasi jadwal** (perilaku sistem yang sama dengan
  menambah peserta di UI). Kalau turnamen sudah punya hasil pertandingan dan
  simulator perlu menambah tim, dia akan **minta konfirmasi** dulu. Dengan
  `-n` tanpa `--fresh`, jawabannya otomatis "tidak" → dibatalkan (aman).
- **Exit code**: `0` semua validasi lolos, `1` ada FAIL — bisa dirangkai di
  script:

  ```bash
  php artisan tournament:simulate 12 --fresh -n && echo "AMAN" || echo "ADA MASALAH"
  ```

- **Kecepatan**: ±5–6 detik untuk 32 tim (fase grup + gugur 2 leg). Liga 90
  laga juga hitungan detik.
- Tim dummy **tidak punya logo** → di UI tampil placeholder. Kosmetik saja.
- Peringatan `Event ditolak sistem pada laga #...` di tengah run = simulator
  mengirim event yang ditolak validasi produksi. Satu-dua kali tidak masalah
  (skor tetap konsisten — dicek validasi #3), tapi kalau banyak, itu sinyal
  ada aturan validasi yang berubah.
- Limit paket admin di-bypass (khusus pengujian) — hanya muncul peringatan.

## Arti pesan error umum

| Pesan | Penyebab & solusi |
|---|---|
| `Turnamen ini belum punya pengaturan grup` | Pengaturan grup belum disimpan di UI → atur dulu (Langkah 1) |
| `Undian grup gagal: jumlah tim melebihi kapasitas...` | Tim terdaftar > grup × tim-per-grup → besarkan pengaturan grup atau kecilkan `--teams` |
| `Minimal 2 tim untuk menjalankan simulasi` | `--teams` terlalu kecil (wajar kalau sedang pakai trik bersih-bersih `--teams=0`) |
| `Tidak ada pertandingan yang bisa disimulasikan` | Jadwal tidak ter-generate → cek tipe kompetisi & verifikasi tim di UI |
| `Laga #X tidak bisa dituntaskan` | Laga macet di status tertentu → ini **temuan bug**, cek laga tersebut |

---

## Cara kerja singkat (untuk developer)

Sumber: `app/Console/Commands/SimulateTournament.php`.

- Tim/pemain/official dibuat langsung ke model, lalu **undian & pertandingan
  memakai endpoint produksi** — `performGroupDraw`, `storeMatchEvent`, dan
  `endMatch` di `TournamentController` — dipanggil dari console dengan
  `Request::create()` + session, sehingga seluruh validasi & efek samping
  (update klasemen, isi bracket, buka adu penalti) dieksekusi kode asli.
- Skor disampel dari **distribusi Poisson** berbobot "kekuatan tim" acak
  (rata-rata khas futsal); pencetak gol berbobot posisi (Pivot > Flank >
  Anchor ≫ GK) plus variasi skill per pemain.
- Laga dimainkan berurutan per ID; tiap laga selesai, daftar "laga siap main"
  diambil ulang — slot bracket/playoff yang baru terisi otomatis ikut
  dimainkan pada iterasi berikutnya.
- Laporan klasemen & juara memakai `buildStandingsGroups()` dan
  `resolveChampion()` milik controller (via reflection) supaya angka yang
  dilaporkan identik dengan UI.
