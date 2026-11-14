# Hadirin

**Smart Guest Management & QR Self Check-in System**

Hadirin mengelola undangan dan kehadiran tamu untuk acara, seminar, pertemuan,
konferensi, dan kegiatan organisasi (saat ini dipakai untuk wisuda), dengan
konsep pendaftaran mandiri + presensi multi-sesi:

> **1 Event → Banyak Grup → Banyak Tamu**, dengan presensi per **sesi/area
> acara** (Absen Masuk, Snack, Makan Siang, atau sesi lain sesuai kebutuhan)

Dibangun dengan PHP Native 8.2+, MySQL/MariaDB (PDO + prepared statements),
Bootstrap 5, dan `endroid/qr-code` untuk QR presensi yang **berganti tiap 5
detik** (HMAC time-based). Tidak menggunakan framework backend atau frontend
apa pun, dan tidak membutuhkan Node.js untuk berjalan.

## 1. Kebutuhan Sistem

- PHP 8.2 atau lebih baru, dengan ekstensi: `pdo_mysql`, `gd`, `mbstring`, `json`.
- MySQL 8.x atau MariaDB 10.4+.
- Composer.
- Apache dengan `mod_rewrite` dan `mod_headers` (umumnya tersedia di XAMPP dan shared hosting cPanel).
- Kamera di HP peserta (untuk scan QR presensi) dan di laptop/HP panitia tidak
  diperlukan — panitia hanya perlu membuka layar QR di browser mana pun.

## 2. Instalasi di Localhost (XAMPP / Laragon)

1. Salin folder project ke `htdocs/hadirin` (XAMPP) atau `www/hadirin` (Laragon).
2. Buat database kosong bernama `hadirin_db` (atau biarkan `database.sql`
   membuatkannya otomatis — lihat langkah 3).
3. Import skema database:
   ```bash
   mysql -u root -p < database.sql
   ```
   atau lewat phpMyAdmin: menu **Import** → pilih `database.sql`.

   > **Upgrade dari versi lama?** Jalankan migrasi yang relevan secara
   > berurutan (lihat isi masing-masing file untuk detail):
   > ```bash
   > mysql -u root -p hadirin_db < database/migration_events.sql
   > mysql -u root -p hadirin_db < database/migration_mahasiswa_presensi.sql
   > mysql -u root -p hadirin_db < database/migration_presensi_sessions_crud.sql
   > mysql -u root -p hadirin_db < database/migration_remove_barcode.sql
   > ```
   > Semuanya idempotent (aman dijalankan ulang).
4. Sesuaikan kredensial koneksi database di [config/database.php](config/database.php)
   (default: host `localhost`, user `root`, password kosong) — atau setel
   environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`.
5. Install dependency PHP:
   ```bash
   composer install
   ```
6. Buat akun admin pertama melalui CLI (tidak ada password default yang disimpan di repo):
   ```bash
   php database/seed_admin.php
   ```
   Ikuti prompt untuk mengisi username, nama lengkap, dan password.
7. Akses aplikasi di `http://localhost/hadirin`.

Data contoh (3 grup undangan beserta tamunya) sudah disertakan di `database.sql`
agar fitur CRUD dan presensi dapat langsung diuji setelah akun admin dibuat.
Untuk memulai acara sungguhan dari nol, kosongkan data contoh dengan:
```bash
mysql -u root -p hadirin_db < database/reset_wisudawan_data.sql
```
Lihat peringatan di dalam file tersebut sebelum menjalankannya — operasinya
permanen (hapus semua grup/tamu/presensi/akun mahasiswa, tapi tetap
mempertahankan akun admin/petugas, event, dan konfigurasi sesi presensi).

## 3. Instalasi di Shared Hosting / cPanel

1. Upload seluruh isi folder project ke `public_html/` (atau subfolder, misalnya
   `public_html/hadirin/`) menggunakan File Manager atau FTP/SFTP.
2. Buat database MySQL baru dan user database melalui **MySQL Databases** di cPanel,
   lalu catat nama database, username, dan password yang diberikan hosting.
3. Import `database.sql` melalui **phpMyAdmin** (menu Import).
4. Atur kredensial database. Cara paling aman di shared hosting adalah melalui
   **MultiPHP INI Editor** / `php.ini` tambahan atau file `.env`-like melalui
   `putenv()` di sebuah file yang di-include sebelum `config/bootstrap.php`
   dan diletakkan di luar `public_html` jika panel hosting mengizinkannya.
   Jika tidak memungkinkan, edit langsung nilai default di
   [config/database.php](config/database.php) di server (jangan commit nilai
   produksi ke repository publik).

   Dengan cara yang sama, **set juga `SESSION_QR_SECRET`** (dipakai untuk
   menandatangani QR presensi di [config/bootstrap.php](config/bootstrap.php))
   ke nilai acak Anda sendiri di server produksi — nilai default di repo
   hanya untuk pengembangan lokal.
5. Pastikan Composer tersedia di hosting (banyak cPanel menyediakan **Setup PHP App**
   atau akses SSH). Jalankan:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
   Jika hosting tidak menyediakan SSH/Composer, jalankan `composer install` di
   komputer lokal lalu upload folder `vendor/` beserta `composer.lock` ke server.
6. Jalankan seeder admin melalui SSH:
   ```bash
   php database/seed_admin.php
   ```
   Jika SSH tidak tersedia, jalankan seeder ini secara lokal terhadap database
   produksi (dengan kredensial yang sama) lalu putuskan koneksi, atau minta
   penyedia hosting mengaktifkan akses SSH sementara.
7. Aktifkan **HTTPS** (AutoSSL / Let's Encrypt tersedia gratis di hampir semua
   cPanel) agar session cookie dan kredensial login terenkripsi saat dikirim —
   ini juga disyaratkan browser agar kamera HP peserta bisa dipakai untuk scan
   QR presensi (kecuali di `localhost`).
8. Login menggunakan akun admin yang sudah dibuat di langkah 6.

### Keamanan file sensitif di shared hosting

Banyak paket shared hosting hanya mengekspos folder `public_html` sebagai document
root, sehingga seluruh project (termasuk `config/`, `database/`, `vendor/`) ikut
berada di dalam document root. Untuk mengatasi ini, setiap folder sensitif sudah
dilengkapi `.htaccess` berisi `Require all denied`:

- `config/.htaccess`
- `database/.htaccess`
- `includes/.htaccess`
- `vendor/.htaccess`
- `storage/.htaccess`

serta `.htaccess` di root yang memblokir akses langsung ke `composer.json`,
`composer.lock`, `database.sql`, dan `README.md`.

**Jika hosting Anda mengizinkan document root diarahkan ke sub-folder** (misalnya
`public/`), ini lebih aman lagi: pindahkan seluruh isi project ke luar document
root kecuali file-file PHP yang memang harus diakses langsung lewat URL
(`index.php`, `login.php`, `logout.php`, `dashboard.php`, `daftar-wisuda.php`,
dan folder `groups/ guests/ events/ presensi/ monitoring/ reports/ users/
account/`, plus `assets/`), lalu sesuaikan path `require` di masing-masing
file. Dokumentasi ini sengaja tidak mengasumsikan struktur tersebut secara
default karena kebanyakan shared hosting murah tidak mengizinkan document
root dipindah.

## 4. Konsep & Alur Penggunaan

Alur data berjenjang: **Event → Grup Undangan → Tamu**, dengan presensi per
**sesi** yang dikelola bebas oleh admin (bukan 3 nama tetap).

### Setup oleh admin (sekali per event)

1. **Admin** membuat **Event** (nama, tanggal, lokasi). Begitu event dibuat,
   3 sesi presensi default langsung dibuatkan otomatis: *Absen Masuk*,
   *Absen Snack*, *Absen Makan Siang*.
2. **Admin** mengelola sesi presensi lewat menu **Layar Presensi → Kelola
   Sesi Presensi** (`presensi/sessions/index.php`): CRUD penuh per event —
   nama sesi bebas (mis. "Pengambilan Toga", "Sesi Foto"), urutan tampil,
   status aktif/nonaktif. Menambah sesi baru otomatis memberi semua grup
   yang sudah ada di event itu baris presensi untuk sesi tersebut.

### Pendaftaran & presensi oleh peserta (mandiri, tanpa dientri admin)

3. Peserta membuka **`/daftar-wisuda`** (halaman publik, tanpa login) dan
   mengisi NIM, nama, dan nomor HP (opsional). Sistem otomatis membuat:
   - 1 grup berkuota 3 (peserta, "Ayah {nama}", "Ibu {nama}"),
   - 1 akun login dengan **role `mahasiswa`** — username & password
     sama-sama NIM,
   - baris presensi kosong untuk setiap sesi **aktif** di event tersebut.
4. Peserta login dan hanya melihat 2 menu: **Dashboard** (data diri + ortu +
   status tiap sesi presensi) dan **Scan QR**.
5. **Admin/Panitia** membuka salah satu layar sesi dari menu **Layar
   Presensi** (`presensi/display.php?session_id=...`) di laptop/proyektor,
   atau cukup **print** QR-nya (tombol "Cetak halaman ini", atau akses
   langsung `presensi/qr.php?session_id=...`) dan tempel di lokasi masing-
   masing sesi (pintu masuk, meja snack, dst). QR ini **statis** — tidak
   berganti selama sesi presensi tersebut masih ada (HMAC per sesi+event,
   lihat `includes/session_qr.php`) — bukan kode unik per tamu, jadi aman
   dicetak dan dipakai berulang oleh semua tamu di sesi itu.
6. Peserta, dari menu **Scan QR** di akunnya sendiri, memotret QR di layar
   tersebut dengan kamera HP-nya. Karena ia sudah login, sistem tahu ini
   grup siapa; presensi dicatat **per grup per sesi** (mewakili peserta +
   kedua orang tuanya sekaligus) ke tabel `session_attendance`.
7. Untuk grup yang didaftarkan manual oleh admin (tanpa akun mahasiswa),
   admin bisa menandai presensi tiap sesi secara manual lewat tombol
   "Tandai Hadir" di halaman detail grup.
8. **Admin** memonitor kehadiran secara real-time melalui dashboard, menu
   **Monitoring**, dan **Laporan** (export CSV).

## 5. Struktur Folder

```
hadirin/
├── index.php, login.php, logout.php, dashboard.php
├── daftar-wisuda.php (pendaftaran mandiri peserta, halaman publik)
├── composer.json, database.sql, README.md
├── .htaccess
├── config/          (database, auth, csrf, bootstrap — diblokir dari akses web)
├── includes/        (header, sidebar, footer, functions, session_qr — diblokir dari akses web)
├── events/          (CRUD event — level teratas)
├── groups/          (CRUD grup undangan + presensi manual per sesi)
├── guests/          (CRUD tamu)
├── presensi/        (CRUD sesi presensi, layar QR rotasi, scan mandiri peserta)
├── monitoring/      (monitoring presensi real-time)
├── reports/         (laporan + export CSV)
├── users/           (manajemen akun petugas)
├── account/         (pengaturan akun sendiri)
├── database/        (seeder admin CLI, migrasi, reset data — diblokir dari akses web)
├── assets/css, assets/js, assets/img
└── vendor/          (dependency Composer — diblokir dari akses web)
```

## 6. Keamanan yang Diimplementasikan

- Password di-hash dengan `password_hash()` (bcrypt) dan diverifikasi dengan `password_verify()`.
- Seluruh query database menggunakan **prepared statements** (PDO).
- **CSRF token** pada seluruh form POST dan endpoint AJAX.
- **Session** dengan cookie `HttpOnly`, `SameSite=Lax`, dan `Secure` otomatis
  saat diakses via HTTPS; `session_regenerate_id()` setiap login untuk
  mencegah session fixation; timeout otomatis 30 menit tidak aktif.
- **Rate limiting** percobaan login (maksimal 5 kali gagal per 15 menit,
  berdasarkan kombinasi username dan IP).
- **Role-based access control** (admin / petugas / mahasiswa) di setiap endpoint.
- **Presensi ganda dicegah** dengan transaksi database + `SELECT ... FOR UPDATE`
  pada baris presensi yang dipilih, sehingga aman dari race condition.
- QR presensi **berganti tiap 5 detik** (HMAC time-based) dan tidak pernah
  berisi nama atau data pribadi tamu — hanya ID sesi + token waktu.
- **Audit log** untuk aksi penting (login, logout, CRUD grup/tamu/event/sesi
  presensi, pendaftaran mandiri, manajemen petugas, export laporan).
- Perlindungan **CSV formula injection** pada export laporan (UTF-8 BOM + escaping).
- Header keamanan (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`).
- Endpoint AJAX mengembalikan HTTP status code yang sesuai (401 tidak
  terautentikasi, 403 tidak berizin/sesi nonaktif, 404 data tidak ditemukan,
  409 konflik presensi ganda, 422 input tidak valid).

## 7. Pengujian yang Disarankan

Skenario pengujian end-to-end: login admin, buat event (cek 3 sesi default
otomatis terbuat), kelola sesi presensi (tambah/edit/nonaktifkan/hapus),
daftar sebagai peserta lewat `/daftar-wisuda`, login sebagai peserta, buka
layar QR di satu sesi sebagai admin/petugas lalu scan dari akun peserta
(cek presensi tercatat, cek tidak bisa presensi dua kali di sesi yang sama),
uji QR kedaluwarsa (lebih dari ~10 detik) dan QR acak/salah event, uji akses
tanpa login/role salah, uji CSRF invalid, uji presensi bersamaan (dua
tab/dua device), cek statistik dashboard & filter per event di Monitoring,
filter Laporan, export CSV, serta validasi kuota tidak bisa lebih kecil dari
jumlah tamu terdaftar.

Jalankan pemeriksaan sintaks PHP dengan:
```bash
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```
