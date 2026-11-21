import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

class ForgotPasswordException implements Exception {
  const ForgotPasswordException(this.message);

  final String message;

  @override
  String toString() => message;
}

class ForgotPasswordService {
  ForgotPasswordService({required String baseUrl, http.Client? client})
    : _baseUrl = baseUrl.replaceFirst(RegExp(r'/+$'), ''),
      _client = client ?? http.Client();

  final String _baseUrl;
  final http.Client _client;

  Future<void> sendOtp(String email) async {
    await _post('send-otp', {'email': email});
  }

  Future<void> verifyOtp({required String email, required String otp}) async {
    await _post('verify-otp', {'email': email, 'otp': otp});
  }

  Future<void> resetPassword({
    required String email,
    required String otp,
    required String password,
    required String passwordConfirmation,
  }) async {
    await _post('reset', {
      'email': email,
      'otp': otp,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
  }

  Future<void> _post(String endpoint, Map<String, String> body) async {
    final response = await _client
        .post(
          Uri.parse('$_baseUrl/forgot-password/$endpoint'),
          headers: const {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: jsonEncode(body),
        )
        .timeout(const Duration(seconds: 20));

    final decoded = jsonDecode(response.body);
    if (decoded is! Map) {
      throw const FormatException('Respons API bukan objek JSON.');
    }

    final payload = Map<String, dynamic>.from(decoded);
    if (response.statusCode >= 200 && response.statusCode < 300) return;

    final message = payload['message'];
    if (response.statusCode == 429) {
      throw const ForgotPasswordException(
        'Terlalu banyak permintaan. Coba lagi dalam satu menit.',
      );
    }
    if (message is String && message.trim().isNotEmpty) {
      throw ForgotPasswordException(_validationMessage(payload) ?? message);
    }
    throw const ForgotPasswordException('Permintaan gagal. Silakan coba lagi.');
  }

  String? _validationMessage(Map<String, dynamic> payload) {
    final data = payload['data'];
    if (data is! Map || data['errors'] is! Map) return null;
    for (final value in (data['errors'] as Map).values) {
      if (value is List && value.isNotEmpty && value.first is String) {
        return value.first as String;
      }
    }
    return null;
  }

  void close() {
    _client.close();
  }
}
