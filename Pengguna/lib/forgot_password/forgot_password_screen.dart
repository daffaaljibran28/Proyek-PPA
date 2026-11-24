import 'dart:async';

import 'package:flutter/material.dart';
import 'package:http/http.dart' show ClientException;

import 'forgot_password_page.dart';
import 'forgot_password_service.dart';
import 'verify_otp_screen.dart';

const _primaryPink = Color(0xFFE0245E);
const _borderPink = Color(0xFFFFD2DC);

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({
    required this.service,
    required this.loginBuilder,
    super.key,
  });

  final ForgotPasswordService service;
  final WidgetBuilder loginBuilder;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  bool _isLoading = false;

  @override
  void dispose() {
    _emailController.dispose();
    widget.service.close();
    super.dispose();
  }

  Future<void> _sendCode() async {
    if (_isLoading || !_formKey.currentState!.validate()) return;
    setState(() => _isLoading = true);
    try {
      final email = _emailController.text.trim();
      await widget.service.sendOtp(email);
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => VerifyOtpScreen(
            email: email,
            service: widget.service,
            loginBuilder: widget.loginBuilder,
          ),
        ),
      );
    } on ForgotPasswordException catch (error) {
      _showMessage(error.message);
    } on TimeoutException {
      _showMessage('Server terlalu lama merespons. Coba lagi.');
    } on ClientException {
      _showMessage('Tidak dapat terhubung ke server. Periksa koneksi Anda.');
    } on FormatException {
      _showMessage('Respons server tidak valid. Coba lagi nanti.');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String? _validateEmail(String? value) {
    final email = value?.trim() ?? '';
    if (email.isEmpty) return 'Email wajib diisi.';
    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      return 'Masukkan format email yang valid.';
    }
    return null;
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        content: Text(message),
        backgroundColor: Colors.redAccent,
      ));
  }

  @override
  Widget build(BuildContext context) {
    return ForgotPasswordPage(
      title: 'Atur Ulang Kata Sandi',
      subtitle: 'Masukkan email akun Anda untuk menerima kode OTP.',
      icon: Icons.lock_reset,
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.done,
              autocorrect: false,
              validator: _validateEmail,
              onFieldSubmitted: (_) => _sendCode(),
              decoration: InputDecoration(
                labelText: 'Email',
                hintText: 'contoh@email.com',
                prefixIcon: const Icon(Icons.email_outlined),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(28),
                  borderSide: const BorderSide(color: _borderPink),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(28),
                  borderSide: const BorderSide(color: _borderPink),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(28),
                  borderSide: const BorderSide(color: _primaryPink, width: 1.5),
                ),
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _isLoading ? null : _sendCode,
              style: FilledButton.styleFrom(
                backgroundColor: _primaryPink,
                padding: const EdgeInsets.symmetric(vertical: 15),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: _isLoading
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Kirim Kode OTP'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () => Navigator.of(context).pop(),
              style: OutlinedButton.styleFrom(
                foregroundColor: _primaryPink,
                side: const BorderSide(color: _primaryPink),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text('Kembali ke Masuk'),
            ),
          ],
        ),
      ),
    );
  }
}
