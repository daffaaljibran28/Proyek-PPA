# SAPA PPA - Aplikasi Pelapor

Aplikasi Flutter untuk pelapor PPA. Aplikasi ini terhubung ke backend Laravel dan menyediakan autentikasi, registrasi, pelaporan, riwayat, notifikasi, edukasi, reset password OTP, profil, dan akses darurat.

Lihat [README utama](../README.md) untuk dokumentasi seluruh proyek.

## Menjalankan

```powershell
flutter pub get
flutter run
```

URL API bawaan dipilih berdasarkan platform. Untuk alamat custom:

```powershell
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api
```

## Pengujian

```powershell
flutter test
flutter analyze
```
