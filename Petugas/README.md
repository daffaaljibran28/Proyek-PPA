# SAPA PPA - Aplikasi Petugas

Aplikasi Flutter khusus petugas PPA. Aplikasi ini terhubung ke REST API Laravel untuk melihat laporan yang ditugaskan, membuka detail dan bukti, memulai penanganan, mencatat penanganan, serta menyelesaikan atau menolak laporan.

Lihat [README utama](../README.md) untuk dokumentasi seluruh proyek.

## Menjalankan

```powershell
flutter pub get
flutter run
```

URL API bawaan adalah `http://10.0.2.2:8000/api` untuk Android Emulator dan `http://localhost:8000/api` untuk web.

Untuk host custom:

```powershell
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api
```

## Pengujian

```powershell
flutter test
flutter analyze
```
