import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' show ClientException;

import '../app_theme.dart';
import '../models/petugas_user.dart';
import '../petugas_api.dart';
import '../petugas_session.dart';
import 'dashboard_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _api = PetugasApi();

  bool _isLoading = false;
  bool _obscurePassword = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _api.close();
    super.dispose();
  }

  Future<void> _login() async {
    if (_isLoading || !_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);
    try {
      final response = await _api
          .request(
            method: 'POST',
            path: '/petugas/login',
            body: {
              'email': _emailController.text.trim(),
              'password': _passwordController.text,
            },
          )
          .timeout(const Duration(seconds: 20));

      final payload = _decodeResponse(response.body);
      if (response.statusCode == 200) {
        final data = payload['data'];
        if (data is! Map) {
          _showMessage('Respons login dari server tidak valid.', isError: true);
          return;
        }

        final user = PetugasUser.fromJson(Map<String, dynamic>.from(data));
        if (user.role.trim().toLowerCase() != 'petugas') {
          _showMessage('Akun ini tidak memiliki akses Petugas.', isError: true);
          return;
        }

        await PetugasSession.save(user);
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(builder: (_) => DashboardScreen(user: user)),
        );
        return;
      }

      _showMessage(_errorMessage(response.statusCode, payload), isError: true);
    } on TimeoutException {
      _showMessage('Server tidak dapat dihubungi. Coba lagi.', isError: true);
    } on FormatException {
      _showMessage('Respons login dari server tidak valid.', isError: true);
    } on ClientException {
      _showMessage(
        'Server tidak dapat dihubungi. Periksa koneksi Anda.',
        isError: true,
      );
    } catch (_) {
      _showMessage('Server tidak dapat dihubungi. Coba lagi.', isError: true);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Map<String, dynamic> _decodeResponse(String body) {
    final decoded = jsonDecode(body);
    if (decoded is! Map) {
      throw const FormatException('Respons API bukan objek JSON.');
    }
    return Map<String, dynamic>.from(decoded);
  }

  String _errorMessage(int statusCode, Map<String, dynamic> payload) {
    if (statusCode == 401) return 'Email atau password salah.';
    if (statusCode == 403) {
      return _serverMessage(payload) ??
          'Akses ditolak atau akun Petugas tidak aktif.';
    }
    if (statusCode == 422) {
      final errors = payload['errors'];
      if (errors is Map) {
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty && value.first is String) {
            return value.first as String;
          }
        }
      }
      return _serverMessage(payload) ?? 'Periksa kembali data login Anda.';
    }
    if (statusCode >= 500) {
      return 'Terjadi gangguan pada server. Coba lagi nanti.';
    }
    return _serverMessage(payload) ?? 'Login gagal. Silakan coba lagi.';
  }

  String? _serverMessage(Map<String, dynamic> payload) {
    final message = payload['message'];
    if (message is String && message.trim().isNotEmpty) return message.trim();
    return null;
  }

  void _showMessage(String message, {bool isError = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: isError ? Colors.redAccent : AppTheme.successGreen,
        ),
      );
  }

  String? _validateEmail(String? value) {
    final email = value?.trim() ?? '';
    if (email.isEmpty) return 'Email wajib diisi.';
    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      return 'Masukkan format email yang valid.';
    }
    return null;
  }

  String? _validatePassword(String? value) {
    if (value == null || value.isEmpty) return 'Password wajib diisi.';
    return null;
  }

  InputDecoration _inputDecoration({
    required String label,
    required IconData icon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      labelText: label,
      prefixIcon: Icon(icon, color: AppTheme.primaryPink),
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppTheme.borderPink),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppTheme.borderPink),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppTheme.primaryPink, width: 1.5),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Column(
                children: [
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(24, 36, 24, 30),
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        colors: [Color(0xFFFF719B), Color(0xFFFF3F78)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.all(Radius.circular(28)),
                    ),
                    child: Column(
                      children: [
                        Container(
                          width: 76,
                          height: 76,
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.volunteer_activism,
                            color: AppTheme.primaryPink,
                            size: 40,
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'SAPA',
                          style: Theme.of(context).textTheme.headlineSmall
                              ?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Sistem Pelaporan Aman · Petugas',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: Colors.white.withAlpha(220)),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 28),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Masuk ke Akun Petugas',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: AppTheme.primaryPink,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Gunakan akun Petugas yang diberikan Admin.',
                      style: Theme.of(context).textTheme.bodySmall
                          ?.copyWith(color: AppTheme.textGrey),
                    ),
                  ),
                  const SizedBox(height: 22),
                  Form(
                    key: _formKey,
                    child: Column(
                      children: [
                        TextFormField(
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                          autocorrect: false,
                          autofillHints: const [AutofillHints.email],
                          validator: _validateEmail,
                          decoration: _inputDecoration(
                            label: 'Email',
                            icon: Icons.email_outlined,
                          ),
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _passwordController,
                          obscureText: _obscurePassword,
                          textInputAction: TextInputAction.done,
                          autofillHints: const [AutofillHints.password],
                          validator: _validatePassword,
                          onFieldSubmitted: (_) => _login(),
                          decoration: _inputDecoration(
                            label: 'Password',
                            icon: Icons.lock_outline,
                            suffixIcon: IconButton(
                              tooltip: _obscurePassword
                                  ? 'Tampilkan password'
                                  : 'Sembunyikan password',
                              onPressed: () => setState(
                                () => _obscurePassword = !_obscurePassword,
                              ),
                              icon: Icon(
                                _obscurePassword
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                                color: AppTheme.headerPink,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 24),
                        SizedBox(
                          width: double.infinity,
                          height: 52,
                          child: FilledButton(
                            onPressed: _isLoading ? null : _login,
                            style: FilledButton.styleFrom(
                              backgroundColor: AppTheme.primaryPink,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                            ),
                            child: _isLoading
                                ? const SizedBox(
                                    width: 22,
                                    height: 22,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2.5,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text('Masuk'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
