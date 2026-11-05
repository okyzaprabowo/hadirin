# Hadirin

**Smart Guest Management & Barcode Check-in System**

Hadirin mengelola undangan dan kehadiran tamu untuk acara, seminar, pertemuan,
konferensi, dan kegiatan organisasi, menggunakan konsep:

> **1 Event → Banyak Grup Undangan → Banyak Tamu**, dengan **1 Barcode = 1 Grup Undangan**

Dibangun dengan PHP Native 8.2+, MySQL/MariaDB (PDO + prepared statements),
Bootstrap 5, dan `picqer/php-barcode-generator` (barcode Code128 — bukan QR).
Input barcode di sisi petugas memakai barcode scanner genggam (USB/Bluetooth)
atau input manual — lihat bagian 7. Tidak menggunakan framework backend atau
frontend apa pun, dan tidak membutuhkan Node.js untuk berjalan.

## 1. Kebutuhan Sistem

- PHP 8.2 atau lebih baru, dengan ekstensi: `pdo_mysql`, `gd`, `mbstring`, `json`.
- MySQL 8.x atau MariaDB 10.4+.
- Composer.
- Apache dengan `mod_rewrite` dan `mod_headers` (umumnya tersedia di XAMPP dan shared hosting cPanel).
- Barcode scanner USB/Bluetooth genggam (mode keyboard-wedge/HID) untuk digunakan
  di lapangan — lihat bagian 7.

## 2. Instalasi di Localhost (XAMPP / Laragon)

1. Salin folder project ke `htdocs/hadirin` (XAMPP) atau `www/hadirin` (Laragon).
2. Buat database kosong bernama `hadirin_db` (atau biarkan `database.sql`
   membuatkannya otomatis — lihat langkah 3).
3. Import skema database:
   ```bash
   mysql -u root -p < database.sql
   ```
   atau lewat phpMyAdmin: menu **Import** → pilih `database.sql`.

   > **Sudah punya database lama?** Jika `hadirin_db` sudah berisi data dari
   > versi sebelumnya (belum ada tabel `events`), jangan import ulang.
   > Cukup jalankan migrasi sekali:
   > ```bash
   > mysql -u root -p hadirin_db < database/migration_events.sql
   > ```
   > Migrasi akan membuat tabel `events`, menambah kolom `guest_groups.event_id`,
   > dan menampung semua grup lama ke dalam satu event default.
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
agar fitur CRUD, barcode, dan scanner dapat langsung diuji setelah akun admin dibuat.

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
   cPanel) agar session cookie dan kredensial login terenkripsi saat dikirim.
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
(`index.php`, `login.php`, `logout.php`, `dashboard.php`, dan folder
`groups/ guests/ scanner/ monitoring/ reports/ users/ account/`, plus `assets/`),
lalu sesuaikan path `require` di masing-masing file. Dokumentasi ini sengaja
tidak mengasumsikan struktur tersebut secara default karena kebanyakan shared
hosting murah tidak mengizinkan document root dipindah.

## 4. Konsep & Alur Penggunaan

Alur data berjenjang: **Event → Grup Undangan → Tamu**
(1 Event punya banyak Grup, 1 Grup punya banyak Tamu, 1 Barcode per Grup).

1. **Admin** membuat **Event** terlebih dahulu (nama event, tanggal, lokasi).
2. **Admin** membuat **Grup Undangan** di dalam event tersebut (nama grup,
   kuota, penanggung jawab). Sistem otomatis men-generate satu Barcode
   (Code128) unik untuk grup tersebut.
3. **Admin** menambahkan **Tamu** ke dalam grup (satu per satu, atau dari
   halaman detail grup). Jumlah tamu tidak boleh melebihi kuota grup.
4. Barcode dicetak/dibagikan ke grup tersebut (barcode **tidak** berisi nama
   atau data pribadi tamu — hanya token acak).
5. **Petugas** memilih event aktif lalu membuka halaman **Scan Barcode**,
   kemudian menembakkan barcode scanner genggam (atau mengetik kodenya secara
   manual) ke kolom input. Sistem menampilkan seluruh nama tamu dalam grup
   tersebut.
6. Petugas mencentang nama tamu yang benar-benar hadir, lalu menekan tombol
   **KONFIRMASI CHECK-IN**. Tamu yang sudah check-in tidak bisa dipilih lagi
   pada scan berikutnya, tetapi barcode yang sama tetap bisa dipakai untuk
   men-check-in anggota grup lainnya yang belum hadir.
7. **Admin** memonitor kehadiran secara real-time melalui menu **Monitoring**
   dan **Laporan**, serta dapat mengekspor data ke CSV.

## 5. Struktur Folder

```
hadirin/
├── index.php, login.php, logout.php, dashboard.php
├── composer.json, database.sql, README.md
├── .htaccess
├── config/          (database, auth, csrf, bootstrap — diblokir dari akses web)
├── includes/        (header, sidebar, footer, functions, barcode_helper — diblokir dari akses web)
├── events/          (CRUD event — level teratas)
├── groups/          (CRUD grup undangan + barcode)
├── guests/          (CRUD tamu)
├── scanner/         (scanner barcode, validasi, check-in, riwayat)
├── monitoring/       (monitoring kehadiran real-time)
├── reports/         (laporan + export CSV)
├── users/           (manajemen akun petugas)
├── account/         (pengaturan akun sendiri)
├── database/        (seeder admin CLI — diblokir dari akses web)
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
- **Role-based access control** (admin vs petugas) di setiap endpoint.
- **Check-in ganda dicegah** dengan transaksi database + `SELECT ... FOR UPDATE`
  pada baris tamu yang dipilih, sehingga aman dari race condition saat dua
  petugas melakukan scan bersamaan.
- Barcode hanya berisi token acak (`random_bytes(10)`, Code128), tidak pernah
  berisi nama atau data pribadi tamu.
- **Audit log** untuk aksi penting (login, logout, CRUD grup/tamu, rotasi
  barcode, manajemen petugas, export laporan).
- Perlindungan **CSV formula injection** pada export laporan (UTF-8 BOM + escaping).
- Header keamanan (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`).
- Endpoint AJAX mengembalikan HTTP status code yang sesuai (401 tidak
  terautentikasi, 403 tidak berizin/grup nonaktif, 404 token tidak ditemukan,
  409 konflik check-in ganda, 422 input tidak valid).

## 7. Input Barcode: Scanner Genggam (bukan kamera)

Halaman **Scan Barcode** saat ini **tidak menggunakan kamera sama sekali** —
dukungan kamera (`html5-qrcode`) sengaja dinonaktifkan untuk saat ini. Satu-
satunya cara memasukkan kode adalah lewat kolom input teks, yang dirancang
untuk dipakai bersama **barcode scanner USB/Bluetooth genggam**: perangkat
semacam ini umumnya bertindak sebagai keyboard (keyboard-wedge/HID) — begitu
barcode ditembak, scanner "mengetikkan" kodenya lalu menekan Enter secara
otomatis. Kolom input pada halaman scanner sudah otomatis fokus dan menerima
Enter sebagai submit, sehingga alurnya: pilih event aktif → tembak barcode →
hasil langsung muncul, tanpa perlu klik apa pun. Kode juga bisa diketik
manual bila diperlukan.

Barcode yang digenerate adalah **Code128**, format yang didukung hampir
semua barcode scanner genggam di pasaran.

## 8. Pengujian yang Disarankan

Skenario pengujian end-to-end (lihat juga daftar lengkap pada dokumen
spesifikasi): login admin & petugas, membuat grup + tamu, download barcode,
pilih event lalu input barcode (lewat scanner genggam atau ketik manual),
check-in sebagian tamu, scan ulang barcode yang sama untuk men-check-in sisa
tamu, uji barcode palsu/nonaktif/milik event lain, uji akses tanpa login/role
salah, uji CSRF invalid, uji check-in bersamaan (dua tab/dua device), cek
statistik dashboard (termasuk filter per event di halaman Monitoring), filter
laporan, export CSV, rotasi barcode token, serta validasi kuota tidak bisa
lebih kecil dari jumlah tamu terdaftar.

Jalankan pemeriksaan sintaks PHP dengan:
```bash
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```
