import 'dart:async';

import 'package:flutter/material.dart';
import 'package:http/http.dart' show ClientException;

import '../app_theme.dart';
import '../models/petugas_user.dart';
import '../petugas_api.dart';
import '../petugas_session.dart';
import 'login_screen.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({
    super.key,
    required this.user,
    required this.onDashboard,
    required this.onReports,
  });

  final PetugasUser user;
  final VoidCallback onDashboard;
  final VoidCallback onReports;

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  final PetugasApi _api = PetugasApi();
  bool _isLoggingOut = false;

  @override
  void dispose() {
    _api.close();
    super.dispose();
  }

  Future<void> _confirmLogout() async {
    if (_isLoggingOut) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Keluar dari akun?'),
        content: const Text('Anda perlu login kembali untuk menggunakan SAPA.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: AppTheme.primaryPink,
            ),
            child: const Text('Logout'),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      await _logout();
    }
  }

  Future<void> _logout() async {
    if (_isLoggingOut) return;
    setState(() => _isLoggingOut = true);

    String? warning;
    try {
      final response = await _api
          .request(method: 'POST', path: '/logout', token: widget.user.token)
          .timeout(const Duration(seconds: 20));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        warning =
            'Sesi lokal dihapus, tetapi pencabutan token di server '
            'belum dapat dikonfirmasi.';
      }
    } on TimeoutException {
      warning =
          'Sesi lokal dihapus, tetapi server tidak merespons untuk '
          'mengonfirmasi pencabutan token.';
    } on ClientException {
      warning =
          'Sesi lokal dihapus, tetapi server tidak dapat dihubungi untuk '
          'mengonfirmasi pencabutan token.';
    } catch (_) {
      warning =
          'Sesi lokal dihapus, tetapi pencabutan token di server '
          'belum dapat dikonfirmasi.';
    }

    try {
      await PetugasSession.clear();
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoggingOut = false);
      _showMessage(
        'Sesi lokal gagal dihapus. Anda tetap di halaman Akun; '
        'silakan coba logout kembali.',
      );
      return;
    }

    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
      (route) => false,
    );

    if (warning != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!messenger.mounted) return;
        messenger
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(warning!)));
      });
    }
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: !_isLoggingOut,
      child: Scaffold(
        backgroundColor: AppTheme.background,
        body: SafeArea(
          child: Column(
            children: [
              const _AccountHeader(),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 26, 20, 28),
                  children: [
                    Container(
                      padding: const EdgeInsets.all(22),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppTheme.borderPink),
                        boxShadow: [
                          BoxShadow(
                            color: AppTheme.primaryPink.withAlpha(16),
                            blurRadius: 16,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      child: Column(
                        children: [
                          Container(
                            width: 76,
                            height: 76,
                            decoration: const BoxDecoration(
                              color: AppTheme.badgeYellowBackground,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.person_outline,
                              color: AppTheme.primaryPink,
                              size: 42,
                            ),
                          ),
                          const SizedBox(height: 14),
                          Text(
                            widget.user.name,
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(
                                  color: AppTheme.textDark,
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            widget.user.email,
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(color: AppTheme.textGrey),
                          ),
                          const SizedBox(height: 18),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 14,
                              vertical: 8,
                            ),
                            decoration: BoxDecoration(
                              color: AppTheme.badgeYellowBackground,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              'Akun Petugas SAPA',
                              style: Theme.of(context).textTheme.labelLarge
                                  ?.copyWith(
                                    color: AppTheme.primaryPink,
                                    fontWeight: FontWeight.w700,
                                  ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),
                    OutlinedButton.icon(
                      onPressed: _isLoggingOut ? null : _confirmLogout,
                      icon: _isLoggingOut
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.logout),
                      label: Text(
                        _isLoggingOut ? 'Memproses logout...' : 'Logout',
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppTheme.primaryPink,
                        side: const BorderSide(color: AppTheme.primaryPink),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        textStyle: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        bottomNavigationBar: _AccountNavigation(
          onDashboard: widget.onDashboard,
          onReports: widget.onReports,
        ),
      ),
    );
  }
}

class _AccountHeader extends StatelessWidget {
  const _AccountHeader();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(22, 18, 22, 24),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFFF719B), Color(0xFFFF3F78)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(26)),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: const BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.volunteer_activism,
              color: AppTheme.primaryPink,
              size: 27,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'SAPA',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                  ),
                ),
                Text(
                  'Sistem Pelaporan Aman · Petugas',
                  style: Theme.of(context).textTheme.bodySmall
                      ?.copyWith(color: Colors.white.withAlpha(225)),
                ),
              ],
            ),
          ),
          const Icon(
            Icons.account_circle_outlined,
            color: Colors.white,
            size: 30,
          ),
        ],
      ),
    );
  }
}

class _AccountNavigation extends StatelessWidget {
  const _AccountNavigation({
    required this.onDashboard,
    required this.onReports,
  });

  final VoidCallback onDashboard;
  final VoidCallback onReports;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 76,
      decoration: const BoxDecoration(
        color: AppTheme.headerPink,
        borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _NavigationItem(
            icon: Icons.home_rounded,
            label: 'Dashboard',
            onTap: onDashboard,
          ),
          _NavigationItem(
            icon: Icons.description_outlined,
            label: 'Laporan',
            onTap: onReports,
          ),
          const _NavigationItem(
            icon: Icons.person_outline,
            label: 'Akun',
            selected: true,
          ),
        ],
      ),
    );
  }
}

class _NavigationItem extends StatelessWidget {
  const _NavigationItem({
    required this.icon,
    required this.label,
    this.selected = false,
    this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? Colors.white : Colors.white.withAlpha(175);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: SizedBox(
        width: 96,
        height: 68,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 25),
            const SizedBox(height: 3),
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: color,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
