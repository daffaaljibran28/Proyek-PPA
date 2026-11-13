# sapa_petugas

Fondasi aplikasi Flutter untuk Petugas SAPA.

## Konfigurasi API

Android emulator menggunakan `http://10.0.2.2:8000/api` secara default.
Web menggunakan `http://localhost:8000/api`.

Untuk perangkat Android fisik, jalankan aplikasi dengan alamat IP komputer
yang dapat dijangkau perangkat:

```shell
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api
```

Ganti alamat contoh dengan alamat host backend yang sesuai. Nilai
`API_BASE_URL` harus berakhir dengan `/api`.

Build debug Android mengizinkan HTTP untuk pengembangan lokal. Gunakan HTTPS
untuk server produksi.
