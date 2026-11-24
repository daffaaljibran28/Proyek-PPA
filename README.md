# SAPA PPA

SAPA PPA adalah aplikasi pelaporan dan penanganan kekerasan berbasis Perlindungan Perempuan dan Anak (PPA). Repository ini terdiri atas satu backend Laravel, dua aplikasi Flutter, dan antarmuka web Laravel untuk pelapor serta admin.

## Komponen Proyek

| Komponen | Teknologi | Peran |
|---|---|---|
| `Admin/` | Laravel 12, Blade, Sanctum, Tailwind/Vite | Panel admin, portal pelapor web, dan REST API |
| `Pengguna/` | Flutter 3/Dart | Aplikasi pelapor: registrasi, laporan, riwayat, edukasi, profil, dan notifikasi |
| `Petugas/` | Flutter 3/Dart | Aplikasi petugas: daftar tugas, detail laporan, penanganan, dan penyelesaian laporan |

Database MySQL, akun, laporan, lampiran, informasi, dan sesi API digunakan bersama oleh semua komponen.

## Fitur Utama

### Pelapor

- Registrasi dan login dengan email atau nomor HP
- Buat laporan dengan kategori, kronologi, lokasi, tanggal kejadian, dan bukti lampiran
- Pilih lokasi kejadian melalui peta pada aplikasi Flutter
- Melihat riwayat, detail, dan perkembangan status laporan
- Menerima notifikasi status serta mengelola riwayat notifikasi yang dibaca/dihapus
- Melihat informasi dan edukasi PPA, termasuk gambar dan video
- Mengubah profil dan kata sandi
- Reset kata sandi melalui OTP email
- Mengakses halaman panggilan darurat

### Admin

- Login admin dan pengaturan profil/kata sandi
- Dashboard ringkasan laporan
- Verifikasi atau penolakan laporan
- Penugasan laporan kepada petugas aktif
- Memperbarui status dan meninjau bukti laporan
- Mengelola akun pengguna dan status aktif/nonaktif pengguna
- Membuat, mengubah, menonaktifkan, atau menghapus akun petugas
- Membuat dan mengelola konten informasi/edukasi beserta gambar dan video

### Petugas

- Login khusus akun berperan `petugas`
- Melihat daftar laporan yang ditugaskan
- Melihat dashboard dan ringkasan laporan
- Membuka detail serta bukti laporan
- Memulai penanganan, menambah catatan penanganan internal, dan menyelesaikan/menolak laporan

### Status Laporan

Alur status laporan yang digunakan oleh aplikasi dan web:

1. `Menunggu Verifikasi`
2. `Sedang Diproses` setelah admin memverifikasi atau menugaskan petugas
3. `Diproses` saat petugas mulai menangani laporan
4. `Selesai` atau `Ditolak`

Setiap perubahan status dicatat dalam riwayat status untuk ditampilkan kepada pelapor.

## Teknologi

- PHP 8.2+ dan Laravel 12
- Laravel Sanctum untuk autentikasi API Flutter
- MySQL
- Blade dan Tailwind CSS melalui Vite
- Flutter/Dart
- HTTP API, Shared Preferences, Image Picker, flutter_map, dan Google Fonts
- PHPUnit untuk pengujian backend

## Persyaratan

Pastikan perangkat pengembangan memiliki:

- PHP 8.2 atau lebih baru
- Composer
- Node.js dan npm
- MySQL
- Flutter SDK versi 3.x dan Dart SDK yang kompatibel
- Android Studio/SDK atau perangkat lain yang didukung Flutter

## Instalasi Backend dan Web

```powershell
cd Admin

copy .env.example .env
composer install
npm install

php artisan key:generate
```

Buat database MySQL, misalnya `db_sapa`, lalu ubah bagian berikut pada `Admin/.env`:

```dotenv
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_sapa
DB_USERNAME=root
DB_PASSWORD=

ADMIN_NAME="Administrator SAPA"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=kata-sandi-minimal-12-karakter
```

Email admin harus valid dan kata sandi minimal 12 karakter. Kemudian jalankan migrasi dan seeder:

```powershell
php artisan migrate
php artisan db:seed
npm run build
```

Saat pengembangan, jalankan server Laravel dan aset Vite dalam dua terminal:

```powershell
# Terminal 1
cd Admin
php artisan serve --host=0.0.0.0 --port=8000
```

```powershell
# Terminal 2
cd Admin
npm run dev
```

Alternatif untuk semua proses Laravel beserta Vite:

```powershell
cd Admin
composer run dev
```

## Alamat Web

Setelah backend berjalan:

- `http://localhost:8000/login` — login admin
- `http://localhost:8000/dashboard-admin` — dashboard admin
- `http://localhost:8000/pelapor/login` — login pelapor web
- `http://localhost:8000/pelapor/register` — registrasi pelapor web
- `http://localhost:8000/pelapor/lupa-password` — lupa kata sandi web

## Konfigurasi Video Informasi

Informasi dapat menggunakan tautan YouTube/Vimeo atau mengunggah MP4/WebM hingga 50 MB.

```powershell
cd Admin
php artisan storage:link
```

Pastikan konfigurasi PHP mengizinkan unggahan dan permintaan minimal 50 MB:

```ini
upload_max_filesize=50M
post_max_size=55M
```

## Konfigurasi Reset Password OTP

Backend menyediakan endpoint OTP dan mengirim pesan menggunakan konfigurasi `MAIL_*` pada `Admin/.env`.

Untuk pengujian tanpa SMTP, gunakan `MAIL_MAILER=log`. Kode OTP akan ditulis ke log Laravel. Untuk penggunaan nyata, isi SMTP, pengguna, kata sandi, alamat pengirim, dan alamat dari.

Endpoint yang tersedia:

- `POST /api/forgot-password/send-otp`
- `POST /api/forgot-password/verify-otp`
- `POST /api/forgot-password/reset`

## Menjalankan Aplikasi Pelapor

```powershell
cd Pengguna
flutter pub get
flutter run
```

URL API bawaan:

- Android Emulator: `http://10.0.2.2:8000/api`
- iOS Simulator: `http://127.0.0.1:8000/api`
- Web: `http://<host-browser>:8000/api`
- Desktop: `http://127.0.0.1:8000/api`

Untuk emulator perangkat fisik atau server yang berbeda, tentukan alamat API secara eksplisit:

```powershell
cd Pengguna
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api
```

Ganti alamat dengan IP yang dapat dijangkau dari perangkat. Pastikan aplikasi Flutter dan server Laravel berada pada jaringan yang sama.

## Menjalankan Aplikasi Petugas

```powershell
cd Petugas
flutter pub get
flutter run
```

Nilai API bawaan adalah `http://10.0.2.2:8000/api` untuk Android Emulator dan `http://localhost:8000/api` untuk web.

Untuk perangkat fisik atau host khusus:

```powershell
cd Petugas
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api
```

HTTP hanya dimaksudkan untuk pengembangan lokal. Gunakan HTTPS pada deployment produksi.

## REST API

Semua endpoint API berada di bawah `/api`.

### Autentikasi

- `POST /api/login`
- `POST /api/register`
- `POST /api/petugas/login`
- `POST /api/logout`
- `POST /api/forgot-password/send-otp`
- `POST /api/forgot-password/verify-otp`
- `POST /api/forgot-password/reset`

### Pelapor

- `GET /api/user`
- `GET /api/informasi`
- `GET /api/laporan`
- `GET /api/riwayat`
- `GET /api/laporan-terakhir`
- `POST /api/lapor`
- `PUT /api/laporan/{id}`
- `DELETE /api/laporan/{id}`
- `GET /api/notifikasi/{userId}`
- `DELETE /api/notifikasi/{userId}/{reportId}`
- `PUT /api/pengguna/{userId}/profil`
- `PUT /api/pengguna/{userId}/password`

### Petugas

- `GET /api/petugas/dashboard`
- `GET /api/petugas/laporan`
- `GET /api/petugas/laporan/{id}`
- `GET /api/petugas/laporan/{id}/bukti/{index}`
- `PATCH /api/petugas/laporan/{id}/penanganan`

Endpoint laporan dan profil membutuhkan token Bearer Laravel Sanctum.

## Pengujian

### Backend

```powershell
cd Admin
php artisan test
```

Test feature mencakup autentikasi, manajemen akun, alur laporan, API pelapor, API petugas, lupa kata sandi, informasi, dan pengaturan admin.

### Aplikasi Flutter

```powershell
cd Pengguna
flutter test
```

```powershell
cd Petugas
flutter test
```

Gunakan `flutter analyze` pada masing-masing aplikasi Flutter untuk memeriksa kode statis.

## Struktur Direktori

```text
.
├── Admin/
│   ├── app/                 # Controller, model, middleware, service, dan mail
│   ├── database/            # Migrasi dan seeder
│   ├── resources/views/     # Tampilan Blade admin dan pelapor
│   ├── routes/              # Route web dan REST API
│   └── tests/               # Pengujian Laravel
├── Pengguna/
│   ├── lib/auth/            # Login dan registrasi pelapor
│   ├── lib/forms/           # Form laporan bertahap
│   ├── lib/screens/         # Beranda, riwayat, progres, edukasi, profil
│   ├── lib/forgot_password/ # Layar dan service OTP
│   └── lib/widgets/         # Navigasi, header, dan peta
└── Petugas/
    ├── lib/screens/         # Login, dashboard, daftar, detail, dan akun
    ├── lib/models/          # Model laporan dan petugas
    └── lib/forgot_password/ # Layar dan service OTP
```

## Keamanan Operasional

- Jangan menyimpan `.env`, kredensial database, atau kunci `APP_KEY` dalam source control.
- Jangan menyertakan kata sandi, foto identitas, atau data pelapor di contoh konfigurasi.
- Jalankan backup database sebelum migrasi pada lingkungan produksi.
- Validasi `ADMIN_PASSWORD` dan lakukan pergantian kunci serta kredensial secara berkala.
- Gunakan HTTPS dan domain yang aman untuk deployment.

## Lisensi

Project ini dikembangkan untuk kebutuhan implementasi SAPA PPA. Gunakan sesuai kebutuhan dan kebijakan masing-masing organisasi.
