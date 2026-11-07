# Buku Tamu (CRUD) | Skenario 2 (Tersulit) — Flat-file JSON

Aplikasi sederhana untuk Ujian Kompetensi JWD.
Backend: **PHP native** (tanpa framework). Frontend: **HTML + CSS + JavaScript (ES6)**, tanpa library/framework. Penyimpanan data: **flat-file JSON** (bukan database).

## Struktur Folder

```
buku-tamu/
├── index.html              <- halaman utama (form + daftar tamu)
├── assets/
│   ├── css/style.css
│   ├── js/app.js           <- ES6 module, fetch() ke API
│   └── img/                <- (kosong, untuk aset gambar bila diperlukan)
├── api/
│   ├── buku_tamu.php       <- satu endpoint untuk semua aksi CRUD
│   └── lib/
│       └── json_store.php  <- baca/tulis JSON dengan file locking
└── data/
    ├── tamu.json           <- penyimpanan data (mulai dari [])
    └── .htaccess           <- memblokir akses langsung ke folder data/
```

## Cara Menjalankan (Laragon)

1. Salin folder `buku-tamu/` ke dalam `www/` (Laragon) atau `htdocs/` (XAMPP).
2. Pastikan modul `mod_rewrite`/`mod_authz_core` Apache aktif agar `.htaccess` di `data/` berfungsi (default sudah aktif di Laragon).
3. Pastikan folder `data/` memiliki izin tulis (write permission) untuk PHP.
4. Buka `http://localhost/buku-tamu/` di browser.

## Cara Kerja Singkat

- **Create** — isi form, submit → `POST api/buku_tamu.php?aksi=tambah` → `tanggal` di-generate otomatis oleh server (`date('Y-m-d H:i:s')`, bukan dari input pengguna).
- **Read** — saat halaman dimuat, `GET api/buku_tamu.php?aksi=list` → data diurutkan terbaru di atas.
- **Update** — klik "Edit" pada baris → form terisi otomatis → submit → `POST ...?aksi=edit`. Tanggal asli tidak berubah.
- **Delete** — klik "Hapus" → konfirmasi → `POST ...?aksi=hapus`.

## UI/UX Responsif

Tabel ramah Mobile (Mobile-Friendly)

## Catatan Keamanan (poin penilaian)

1. `data/.htaccess` (`Require all denied`) — mencegah `tamu.json` diakses langsung lewat URL browser.
2. Setiap operasi tulis memakai `flock()` (file locking) via `bukaJsonTerkunci()` / `tutupJsonTerkunci()` di `json_store.php`, mencegah data korup saat dua request menulis bersamaan.
3. Validasi input dilakukan di **server** (`validasiInput()`), bukan hanya `required` di HTML, ini PENTING ya TIM, karena kita sudah men-demokan (kerentanan) validasi yang HANYA dilakukan di sisi Client (FRONTEND).
4. Input yang disimpan di-escape dengan `htmlspecialchars()` sebelum disimpan, sebagai pertahanan terhadap XSS karena data ini ditampilkan kembali ke publik.
5. Setelah `array_filter()` (operasi hapus), `array_values()` dipanggil untuk merapikan ulang key array — tanpa ini, `json_encode()` bisa salah mengubah array `[]` menjadi objek `{}` saat ada celah index.

## Pengujian Manual (karena tidak ada server PHP live saat disusun)

- Kode sudah diverifikasi oleh Instruktur secara manual: pengecekan keseimbangan kurung/kurawal pada tiap blok PHP/JS, review logika tiap fungsi CRUD, dan validasi struktur JSON.
- Disarankan tetap menjalankan uji fungsional penuh (tambah/edit/hapus + uji akses langsung ke `data/tamu.json` yang harus menghasilkan 403) di lingkungan Laragon/XAMPP sebelum dipakai sebagai kunci jawaban ujian.
