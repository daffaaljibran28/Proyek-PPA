# SAPA PPA - Backend dan Web

Direktori ini berisi backend Laravel 12 untuk SAPA PPA. Backend menyediakan portal web pelapor, panel admin, REST API untuk aplikasi Flutter, autentikasi Sanctum, serta penyimpanan lampiran dan informasi.

Lihat [README utama](../README.md) untuk dokumentasi keseluruhan repository.

## Teknologi

- PHP 8.2+ dan Laravel 12
- Laravel Sanctum
- Blade, Tailwind CSS, dan Vite
- MySQL
- PHPUnit

## Menjalankan Lokal

```powershell
copy .env.example .env
composer install
npm install
php artisan key:generate
```

Atur database dan akun admin pada `.env`, lalu jalankan:

```powershell
php artisan migrate
php artisan db:seed
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Untuk mode pengembangan aset:

```powershell
npm run dev
```

Pastikan `.env` berisi `ADMIN_EMAIL` dan `ADMIN_PASSWORD` yang valid. Kata sandi admin minimal 12 karakter.

## Area Utama

- Admin: login, laporan, penugasan petugas, pengguna, informasi, dan pengaturan akun
- Pelapor: registrasi, laporan, bukti, riwayat, notifikasi, profil, darurat, dan edukasi
- API: autentikasi, OTP reset password, pelapor, informasi, serta operasi petugas

## Video Informasi

Jalankan `php artisan storage:link` agar file video dapat diakses. Unggah maksimal 50 MB pada file MP4 atau WebM, atau gunakan tautan YouTube/Vimeo.

## Pengujian

```powershell
php artisan test
```
