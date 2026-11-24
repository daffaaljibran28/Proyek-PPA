import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../chat_page.dart';
import '../theme.dart';
import '../widgets/header.dart';

class DetailPage extends StatelessWidget {
  final Map<String, dynamic> laporan;

  const DetailPage({super.key, required this.laporan});

  String _formatTanggal(dynamic raw) {
    if (raw == null) return '-';
    final text = raw.toString().trim();
    if (text.isEmpty) return '-';

    final parsed = DateTime.tryParse(text);
    if (parsed != null) {
      final local = parsed.toLocal();
      final d = local.day.toString().padLeft(2, '0');
      final m = local.month.toString().padLeft(2, '0');
      final y = local.year.toString();
      return '$d-$m-$y';
    }

    return text.contains('T') ? text.split('T').first : text;
  }

  String? _resolveBuktiUrl(dynamic raw, dynamic idLaporan) {
    if (raw == null) return null;
    final path = raw.toString().replaceAll(r'\/', '/').trim();
    if (path.isEmpty || path.toLowerCase() == 'null') return null;

    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }

    final firstPath =
        path.split(',').first.trim().replaceFirst(RegExp(r'^/+'), '');
    if (firstPath.startsWith('laporan/') && idLaporan != null) {
      return '${AppTheme.apiUrl}/laporan/$idLaporan/bukti/0';
    }

    final baseHost = AppTheme.apiUrl.replaceFirst(RegExp(r'/api/?$'), '');
    final cleanPath = firstPath;

    if (cleanPath.startsWith('storage/')) {
      return '$baseHost/$cleanPath';
    }
    return '$baseHost/storage/$cleanPath';
  }

  @override
  Widget build(BuildContext context) {
    final tanggalFormatted = _formatTanggal(laporan['tanggal_kejadian']);

    final nomorLaporan = (laporan['nomor_laporan'] ?? '').toString().trim();
    final idLaporan = laporan['id_laporan'] ?? laporan['id'] ?? '';
    final teksNomor = nomorLaporan.isNotEmpty ? nomorLaporan : 'LAP-$idLaporan';

    String statusText =
        (laporan['status_penanganan'] ?? 'Menunggu Verifikasi').toString();
    Color bgColor = const Color(0xFFFEF3C7); // Kuning
    Color textColor = const Color(0xFFD97706);

    if (statusText == 'Sedang Diproses' || statusText == 'Diproses') {
      bgColor = const Color(0xFFDBEAFE); // Biru
      textColor = const Color(0xFF1D4ED8);
    } else if (statusText == 'Selesai') {
      bgColor = const Color(0xFFD1FAE5); // Hijau
      textColor = const Color(0xFF059669);
    }

    final buktiRaw = laporan['bukti_lampiran'] ??
        laporan['bukti_foto'] ??
        laporan['foto'] ??
        laporan['lampiran'] ??
        laporan['bukti'] ??
        laporan['bukti_kejadian'];
    final buktiUrl = _resolveBuktiUrl(buktiRaw, idLaporan);

    return BaseLayout(
      showBackButton: true,
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.03),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Nomor Laporan & Status
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    teksNomor,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.primaryPink,
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: bgColor,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      statusText,
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: textColor,
                      ),
                    ),
                  ),
                ],
              ),
              const Divider(height: 24, thickness: 1),

              // Detail Informasi
              _buildInfoItem(
                  'Kategori Kejadian', (laporan['kategori'] ?? '-').toString()),
              const SizedBox(height: 12),
              _buildInfoItem('Sebagai', (laporan['sebagai'] ?? '-').toString()),
              const SizedBox(height: 12),
              _buildInfoItem('Lokasi Kejadian',
                  (laporan['lokasi_kejadian'] ?? '-').toString()),
              const SizedBox(height: 12),
              _buildInfoItem('Tanggal Kejadian', tanggalFormatted),
              const SizedBox(height: 16),

              Text(
                'Kronologi:',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                  color: AppTheme.textDark,
                ),
              ),
              const SizedBox(height: 6),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8F9FA),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFF0F0F2)),
                ),
                child: Text(
                  (laporan['kronologi'] ?? 'Tidak ada kronologi.').toString(),
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 13,
                    color: AppTheme.textDark,
                    height: 1.4,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                height: 46,
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryPink,
                    foregroundColor: Colors.white,
                    elevation: 1,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  icon: const Icon(Icons.chat_bubble_outline_rounded, size: 20),
                  label: Text(
                    'Chat Petugas',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ChatPage(laporan: laporan),
                    ),
                  ),
                ),
              ),

              if (buktiUrl != null) ...[
                const SizedBox(height: 16),
                Text(
                  'Bukti Foto:',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textDark,
                  ),
                ),
                const SizedBox(height: 8),
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    buktiUrl,
                    headers: UserSession.authHeaders(),
                    width: double.infinity,
                    height: 220,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(
                      width: double.infinity,
                      height: 120,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8F9FA),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppTheme.borderPink),
                      ),
                      child: Text(
                        'Gambar bukti tidak dapat dimuat.',
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 12,
                          color: AppTheme.textGrey,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildInfoItem(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 11,
            color: AppTheme.textGrey,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: AppTheme.textDark,
          ),
        ),
      ],
    );
  }
}
