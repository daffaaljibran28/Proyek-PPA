class Laporan {
  const Laporan({
    required this.idLaporan,
    required this.kategori,
    required this.statusPenanganan,
    this.nomorLaporan,
    this.tanggalKejadian,
    this.lokasiKejadian,
    this.createdAt,
    this.sebagai,
    this.kronologi,
    this.buktiLampiran,
    this.riwayatStatus = const [],
    this.catatanPetugas,
    this.pesanPelapor,
    this.namaPelapor,
  });

  final int idLaporan;
  final String? nomorLaporan;
  final String kategori;
  final String? tanggalKejadian;
  final String? lokasiKejadian;
  final String statusPenanganan;
  final String? createdAt;
  final String? sebagai;
  final String? kronologi;
  final String? buktiLampiran;
  final List<Map<String, dynamic>> riwayatStatus;
  final String? catatanPetugas;
  final String? pesanPelapor;
  final String? namaPelapor;

  factory Laporan.fromJson(Map<String, dynamic> json) {
    return Laporan(
      idLaporan: _requiredInt(json['id_laporan'], 'id_laporan'),
      nomorLaporan: _optionalString(json['nomor_laporan'], 'nomor_laporan'),
      kategori: _requiredString(json['kategori'], 'kategori'),
      tanggalKejadian: _optionalString(
        json['tanggal_kejadian'],
        'tanggal_kejadian',
      ),
      lokasiKejadian: _optionalString(
        json['lokasi_kejadian'],
        'lokasi_kejadian',
      ),
      statusPenanganan: _requiredString(
        json['status_penanganan'],
        'status_penanganan',
      ),
      createdAt: _optionalString(json['created_at'], 'created_at'),
      sebagai: _optionalString(json['sebagai'], 'sebagai'),
      kronologi: _optionalString(json['kronologi'], 'kronologi'),
      buktiLampiran: _optionalString(json['bukti_lampiran'], 'bukti_lampiran'),
      riwayatStatus: _history(json['riwayat_status']),
      catatanPetugas: _optionalString(
        json['catatan_petugas'],
        'catatan_petugas',
      ),
      pesanPelapor: _optionalString(json['pesan_pelapor'], 'pesan_pelapor'),
      namaPelapor: _optionalString(
        json['nama_pelapor'] ??
            (json['pelapor'] is Map ? (json['pelapor'] as Map)['name'] : null) ??
            (json['user'] is Map ? (json['user'] as Map)['name'] : null),
        'nama_pelapor',
      ),
    );
  }

  static int _requiredInt(Object? value, String field) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) {
      final parsed = int.tryParse(value);
      if (parsed != null) return parsed;
    }

    throw FormatException('Field "$field" tidak berisi ID yang valid.');
  }

  static String _requiredString(Object? value, String field) {
    if (value is String) return value;
    throw FormatException('Field "$field" tidak berisi teks yang valid.');
  }

  static String? _optionalString(Object? value, String field) {
    if (value == null) return null;
    if (value is String) return value;
    throw FormatException('Field "$field" tidak berisi teks yang valid.');
  }

  static List<Map<String, dynamic>> _history(Object? value) {
    if (value == null) return const [];
    if (value is! List) {
      throw const FormatException('Riwayat status laporan tidak valid.');
    }

    return value
        .map((entry) {
          if (entry is! Map) {
            throw const FormatException('Entri riwayat status tidak valid.');
          }
          return Map<String, dynamic>.from(entry);
        })
        .toList(growable: false);
  }
}
