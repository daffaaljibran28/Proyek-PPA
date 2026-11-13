import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  static const Color primaryPink = Color(0xFFE0245E);
  static const Color headerPink = Color(0xFFFF4B72);
  static const Color background = Color(0xFFFBFBFD);
  static const Color textDark = Color(0xFF2D2D2D);
  static const Color textGrey = Color(0xFF8A8A8E);
  static const Color borderPink = Color(0xFFFFD2DC);
  static const Color successGreen = Color(0xFF10B981);
  static const Color badgeYellowBackground = Color(0xFFFFF8E6);
  static const Color badgeYellowText = Color(0xFFF5A623);
  static const Color badgeBlueBackground = Color(0xFFEFF6FF);
  static const Color badgeBlueText = Color(0xFF2563EB);
  static const Color badgeGreenBackground = Color(0xFFECFDF5);
  static const Color badgeGreenText = Color(0xFF059669);

  static String get apiBaseUrl {
    const configuredUrl = String.fromEnvironment('API_BASE_URL');
    if (configuredUrl.isNotEmpty) {
      return configuredUrl.replaceFirst(RegExp(r'/+$'), '');
    }

    if (kIsWeb) {
      return 'http://localhost:8000/api';
    }

    return 'http://10.0.2.2:8000/api';
  }

  static ThemeData get lightTheme {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: primaryPink,
      primary: primaryPink,
      surface: Colors.white,
    );

    return ThemeData(
      colorScheme: colorScheme,
      scaffoldBackgroundColor: background,
      textTheme: GoogleFonts.plusJakartaSansTextTheme(),
      appBarTheme: const AppBarTheme(
        backgroundColor: primaryPink,
        foregroundColor: Colors.white,
        centerTitle: false,
      ),
      useMaterial3: true,
    );
  }
}
