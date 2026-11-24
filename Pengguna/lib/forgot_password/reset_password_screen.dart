import 'dart:async';

import 'package:flutter/material.dart';
import 'package:http/http.dart' show ClientException;

import 'forgot_password_page.dart';
import 'forgot_password_service.dart';

const _primaryPink = Color(0xFFE0245E);

class ResetPasswordScreen extends StatefulWidget {
  const ResetPasswordScreen({
    required this.email,
    required this.otp,
    required this.service,
    required this.loginBuilder,
    super.key,
  });

  final String email;
  final String otp;
  final ForgotPasswordService service;
  final WidgetBuilder loginBuilder;

  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _passwordController = TextEditingController();
  final _confirmationController = TextEditingController();
  bool _obscurePassword = true;
  bool _obscureConfirmation = true;
  bool _isSaving = false;

  @override
  void dispose() {
    _passwordController.dispose();
    _confirmationController.dispose();
    super.dispose();
  }

  Future<void> _savePassword() async {
    if (_isSaving || !_formKey.currentState!.validate()) return;
    setState(() => _isSaving = true);
    try {
      await widget.service.resetPassword(
        email: widget.email,
        otp: widget.otp,
        password: _passwordController.text,
        passwordConfirmation: _confirmationController.text,
      );
      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      Navigator.of(context).pushAndRemoveUntil<void>(
        MaterialPageRoute<void>(builder: widget.loginBuilder),
        (_) => false,
      );
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(
          content: Text('Password berhasil diperbarui. Silakan login.'),
          backgroundColor: Colors.green,
        ));
    } on ForgotPasswordException catch (error) {
      _showMessage(error.message);
    } on TimeoutException {
      _showMessage('Server terlalu lama merespons. Coba lagi.');
    } on ClientException {
      _showMessage('Tidak dapat terhubung ke server. Periksa koneksi Anda.');
    } on FormatException {
      _showMessage('Respons server tidak valid. Coba lagi nanti.');
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  String? _validatePassword(String? value) {
    if (value == null || value.length < 6) {
      return 'Password minimal 6 karakter.';
    }
    return null;
  }

  String? _validateConfirmation(String? value) {
    if (value == null || value.isEmpty) {
      return 'Konfirmasi password wajib diisi.';
    }
    if (value != _passwordController.text) return 'Password tidak cocok.';
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
      subtitle: 'Buat kata sandi baru untuk akun ${widget.email}.',
      icon: Icons.lock_reset,
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _passwordController,
              obscureText: _obscurePassword,
              validator: _validatePassword,
              decoration: InputDecoration(
                labelText: 'Kata Sandi Baru',
                hintText: 'Minimal 6 karakter',
                suffixIcon: IconButton(
                  tooltip: _obscurePassword
                      ? 'Tampilkan kata sandi'
                      : 'Sembunyikan kata sandi',
                  onPressed: () =>
                      setState(() => _obscurePassword = !_obscurePassword),
                  icon: Icon(
                    _obscurePassword
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                  ),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(28),
                  borderSide: const BorderSide(color: Color(0xFFFFD2DC)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(28),
                  borderSide: const BorderSide(color: _primaryPink, width: 1.5),
                ),
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _confirmationController,
              obscureText: _obscureConfirmation,
              validator: _validateConfirmation,
              decoration: InputDecoration(
                labelText: 'Ulangi Kata Sandi Baru',
                hintText: 'Ulangi kata sandi baru',
                suffixIcon: IconButton(
                  tooltip: _obscureConfirmation
                      ? 'Tampilkan kata sandi'
                      : 'Sembunyikan kata sandi',
                  onPressed: () => setState(
                    () => _obscureConfirmation = !_obscureConfirmation,
                  ),
                  icon: Icon(
                    _obscureConfirmation
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                  ),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(28),
                  borderSide: const BorderSide(color: Color(0xFFFFD2DC)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(28),
                  borderSide: const BorderSide(color: _primaryPink, width: 1.5),
                ),
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _isSaving ? null : _savePassword,
              style: FilledButton.styleFrom(
                backgroundColor: _primaryPink,
                padding: const EdgeInsets.symmetric(vertical: 15),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: _isSaving
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Simpan Kata Sandi Baru'),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () => Navigator.of(context).pushAndRemoveUntil<void>(
                MaterialPageRoute<void>(builder: widget.loginBuilder),
                (_) => false,
              ),
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
