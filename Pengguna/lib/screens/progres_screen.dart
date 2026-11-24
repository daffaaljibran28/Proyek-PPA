import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme.dart';
import '../widgets/header.dart';
import '../chat_page.dart';
import 'detail_screen.dart';

class ProgresPage extends StatelessWidget {
  final Map<String, dynamic> laporan;
  const ProgresPage({super.key, required this.laporan});

  @override
  Widget build(BuildContext context) {
    return BaseLayout(
      showBackButton: true,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('LAP-${laporan['id_laporan']}', style: GoogleFonts.plusJakartaSans(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.primaryPink)),
            const SizedBox(height: 16),
            _step(Icons.check_circle, 'Laporan Dikirim', true),
            _step(Icons.hourglass_top, 'Menunggu Verifikasi', true),
            _step(Icons.refresh, 'Diproses', false),
            _step(Icons.done_all, 'Selesai', false),
            const Spacer(),
            SizedBox(
              width: double.infinity,
              height: 44,
              child: ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primaryPink,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                icon: const Icon(Icons.chat_bubble_outline_rounded, size: 18),
                label: Text(
                  'Chat Petugas',
                  style: GoogleFonts.plusJakartaSans(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => ChatPage(laporan: laporan)),
                ),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              height: 44,
              child: OutlinedButton(
                style: OutlinedButton.styleFrom(side: const BorderSide(color: AppTheme.primaryPink)),
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DetailPage(laporan: laporan))),
                child: Text('Lihat Detail', style: GoogleFonts.plusJakartaSans(color: AppTheme.primaryPink, fontWeight: FontWeight.bold)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _step(IconData icon, String title, bool active) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Icon(icon, color: active ? AppTheme.primaryPink : Colors.grey.shade400, size: 24),
          const SizedBox(width: 12),
          Text(title, style: GoogleFonts.plusJakartaSans(fontWeight: active ? FontWeight.bold : FontWeight.normal)),
        ],
      ),
    );
  }
}