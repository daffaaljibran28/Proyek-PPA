import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import 'models/petugas_user.dart';

class PetugasSession {
  static const String _sessionKey = 'sapa_petugas_session_v1';

  static Future<void> save(PetugasUser user) async {
    final preferences = await SharedPreferences.getInstance();
    await preferences.setString(_sessionKey, jsonEncode(user.toJson()));
  }

  static Future<PetugasUser?> restore() async {
    final preferences = await SharedPreferences.getInstance();
    final storedSession = preferences.getString(_sessionKey);
    if (storedSession == null) return null;

    final decoded = jsonDecode(storedSession);
    if (decoded is! Map<String, dynamic>) {
      throw const FormatException('Data sesi Petugas tersimpan tidak valid.');
    }

    return PetugasUser.fromJson(decoded);
  }

  static Future<void> clear() async {
    final preferences = await SharedPreferences.getInstance();
    final removed = await preferences.remove(_sessionKey);
    if (!removed) {
      throw StateError('Sesi Petugas tidak berhasil dihapus.');
    }
  }
}
