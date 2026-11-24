import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' show ClientException;

import 'forgot_password_page.dart';
import 'forgot_password_service.dart';
import 'reset_password_screen.dart';

const _primaryPink = Color(0xFFE0245E);

class VerifyOtpScreen extends StatefulWidget {
  const VerifyOtpScreen({
    required this.email,
    required this.service,
    required this.loginBuilder,
    super.key,
  });

  final String email;
  final ForgotPasswordService service;
  final WidgetBuilder loginBuilder;

  @override
  State<VerifyOtpScreen> createState() => _VerifyOtpScreenState();
}

class _VerifyOtpScreenState extends State<VerifyOtpScreen> {
  final _controllers = List.generate(6, (_) => TextEditingController());
  final _focusNodes = List.generate(6, (_) => FocusNode());
  Timer? _timer;
  int _secondsRemaining = 60;
  bool _isVerifying = false;
  bool _isResending = false;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    for (final controller in _controllers) {
      controller.dispose();
    }
    for (final focusNode in _focusNodes) {
      focusNode.dispose();
    }
    super.dispose();
  }

  void _startCountdown() {
    _timer?.cancel();
    setState(() => _secondsRemaining = 60);
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_secondsRemaining <= 1) {
        timer.cancel();
        setState(() => _secondsRemaining = 0);
      } else {
        setState(() => _secondsRemaining--);
      }
    });
  }

  Future<void> _verify() async {
    if (_isVerifying) return;
    final otp = _controllers.map((controller) => controller.text).join();
    if (!RegExp(r'^\d{6}$').hasMatch(otp)) {
      _showMessage('Masukkan 6 digit kode OTP.');
      return;
    }

    setState(() => _isVerifying = true);
    try {
      await widget.service.verifyOtp(email: widget.email, otp: otp);
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => ResetPasswordScreen(
            email: widget.email,
            otp: otp,
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
      if (mounted) setState(() => _isVerifying = false);
    }
  }

  Future<void> _resend() async {
    if (_isResending || _secondsRemaining > 0) return;
    setState(() => _isResending = true);
    try {
      await widget.service.sendOtp(widget.email);
      if (!mounted) return;
      for (final controller in _controllers) {
        controller.clear();
      }
      _focusNodes.first.requestFocus();
      _startCountdown();
      _showMessage('Kode OTP baru telah dikirim.');
    } on ForgotPasswordException catch (error) {
      _showMessage(error.message);
    } on TimeoutException {
      _showMessage('Server terlalu lama merespons. Coba lagi.');
    } on ClientException {
      _showMessage('Tidak dapat terhubung ke server. Periksa koneksi Anda.');
    } on FormatException {
      _showMessage('Respons server tidak valid. Coba lagi nanti.');
    } finally {
      if (mounted) setState(() => _isResending = false);
    }
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: message.startsWith('Kode OTP baru')
              ? Colors.green
              : Colors.redAccent,
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final otpBoxWidth = ((MediaQuery.sizeOf(context).width - 92) / 6).clamp(
      34.0,
      50.0,
    );

    return ForgotPasswordPage(
      title: 'Verifikasi Kode OTP',
      subtitle: 'Masukkan kode 6 digit yang dikirim ke ${widget.email}.',
      icon: Icons.mark_email_read_outlined,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: List.generate(6, (index) {
              return SizedBox(
                width: otpBoxWidth,
                child: TextField(
                  controller: _controllers[index],
                  focusNode: _focusNodes[index],
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  maxLength: 1,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: InputDecoration(
                    counterText: '',
                    filled: true,
                    fillColor: Colors.white,
                    contentPadding: const EdgeInsets.symmetric(vertical: 13),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: const BorderSide(color: Color(0xFFFFD2DC)),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: const BorderSide(
                        color: _primaryPink,
                        width: 1.5,
                      ),
                    ),
                  ),
                  onChanged: (value) {
                    if (value.isNotEmpty && index < 5) {
                      _focusNodes[index + 1].requestFocus();
                    } else if (value.isEmpty && index > 0) {
                      _focusNodes[index - 1].requestFocus();
                    } else if (value.isNotEmpty) {
                      _focusNodes[index].unfocus();
                    }
                  },
                ),
              );
            }),
          ),
          const SizedBox(height: 8),
          TextButton(
            onPressed: _secondsRemaining == 0 && !_isResending ? _resend : null,
            child: _isResending
                ? const Text('Mengirim ulang...')
                : Text(
                    _secondsRemaining > 0
                        ? 'Kirim ulang OTP dalam $_secondsRemaining detik'
                        : 'Kirim Ulang OTP',
                  ),
          ),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: _isVerifying ? null : _verify,
            style: FilledButton.styleFrom(
              backgroundColor: _primaryPink,
              padding: const EdgeInsets.symmetric(vertical: 15),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: _isVerifying
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('Verifikasi'),
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
            child: const Text('Kembali'),
          ),
        ],
      ),
    );
  }
}
