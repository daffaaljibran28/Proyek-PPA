import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' show ClientException;

import '../app_theme.dart';
import '../models/petugas_user.dart';
import '../petugas_api.dart';
import '../petugas_session.dart';
import 'account_screen.dart';
import 'login_screen.dart';
import 'reports_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.user});

  final PetugasUser user;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final PetugasApi _api = PetugasApi();

  String? _name;
  int? _jumlahTugas;
  String? _errorMessage;
  bool _isLoading = true;
  bool _sessionExpired = false;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
  }

  @override
  void dispose() {
    _api.close();
    super.dispose();
  }

  Future<void> _loadDashboard() async {
    if (_isLoading && !_sessionExpired && _name != null) return;
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final response = await _api
          .request(
            method: 'GET',
            path: '/petugas/dashboard',
            token: widget.user.token,
          )
          .timeout(const Duration(seconds: 20));

      if (response.statusCode == 401) {
        await _handleExpiredSession();
        return;
      }

      final payload = _decodeResponse(response.body);
      if (response.statusCode != 200) {
        throw _DashboardException(
          _messageForStatus(response.statusCode, payload),
        );
      }

      final data = payload['data'];
      if (payload['status'] != 'success' || data is! Map) {
        throw const FormatException('Respons dashboard tidak valid.');
      }

      final name = data['name'];
      final count = _parseTaskCount(data['jumlah_tugas']);
      if (name is! String || name.trim().isEmpty || count == null) {
        throw const FormatException('Data dashboard tidak valid.');
      }

      if (!mounted) return;
      setState(() {
        _name = name.trim();
        _jumlahTugas = count;
        _isLoading = false;
      });
    } on TimeoutException {
      _setError(
        'Server tidak dapat dihubungi. Periksa koneksi, lalu coba lagi.',
      );
    } on ClientException {
      _setError(
        'Server tidak dapat dihubungi. Periksa alamat API dan koneksi.',
      );
    } on _DashboardException catch (error) {
      _setError(error.message);
    } on FormatException {
      _setError('Respons dashboard dari server tidak valid.');
    } catch (_) {
      _setError('Dashboard gagal dimuat. Silakan coba lagi.');
    }
  }

  Map<String, dynamic> _decodeResponse(String body) {
    final decoded = jsonDecode(body);
    if (decoded is! Map) {
      throw const FormatException('Respons API bukan objek JSON.');
    }
    return Map<String, dynamic>.from(decoded);
  }

  int? _parseTaskCount(Object? value) {
    if (value is int && value >= 0) return value;
    if (value is num && value >= 0 && value == value.toInt()) {
      return value.toInt();
    }
    if (value is String) {
      final parsed = int.tryParse(value);
      if (parsed != null && parsed >= 0) return parsed;
    }
    return null;
  }

  String _messageForStatus(int statusCode, Map<String, dynamic> payload) {
    if (statusCode == 403) {
      return _serverMessage(payload) ??
          'Akses ditolak atau akun Petugas tidak aktif.';
    }
    if (statusCode >= 500) {
      return 'Terjadi gangguan pada server. Silakan coba lagi nanti.';
    }
    return _serverMessage(payload) ?? 'Dashboard gagal dimuat.';
  }

  String? _serverMessage(Map<String, dynamic> payload) {
    final message = payload['message'];
    if (message is String && message.trim().isNotEmpty) return message.trim();
    return null;
  }

  Future<void> _handleExpiredSession() async {
    _sessionExpired = true;
    await PetugasSession.clear();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  void _setError(String message) {
    if (!mounted) return;
    setState(() {
      _isLoading = false;
      _errorMessage = message;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: SafeArea(
        child: Column(
          children: [
            const _DashboardHeader(),
            Expanded(
              child: _isLoading
                  ? const Center(
                      child: CircularProgressIndicator(
                        color: AppTheme.primaryPink,
                      ),
                    )
                  : _errorMessage != null
                  ? _DashboardError(
                      message: _errorMessage!,
                      onRetry: _loadDashboard,
                    )
                  : _DashboardContent(
                      name: _name!,
                      jumlahTugas: _jumlahTugas!,
                      onRefresh: _loadDashboard,
                    ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _DashboardNavigation(
        onSelect: _onNavigationSelected,
      ),
    );
  }

  void _onNavigationSelected(String label) {
    if (label == 'Laporan') {
      Navigator.of(context).push(
        MaterialPageRoute<void>(
          settings: const RouteSettings(name: '/petugas/laporan'),
          builder: (_) => ReportsScreen(user: widget.user),
        ),
      );
      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => AccountScreen(
          user: widget.user,
          onDashboard: () =>
              Navigator.of(context).popUntil((route) => route.isFirst),
          onReports: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              settings: const RouteSettings(name: '/petugas/laporan'),
              builder: (_) => ReportsScreen(user: widget.user),
            ),
          ),
        ),
      ),
    );
  }
}

class _DashboardHeader extends StatelessWidget {
  const _DashboardHeader();
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

class _DashboardContent extends StatelessWidget {
  const _DashboardContent({
    required this.name,
    required this.jumlahTugas,
    required this.onRefresh,
  });

  final String name;
  final int jumlahTugas;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      color: AppTheme.primaryPink,
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 26, 20, 28),
        children: [
          Text(
            'Halo, $name',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              color: AppTheme.textDark,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Berikut ringkasan tugas yang sedang Anda tangani.',
            style: Theme.of(context).textTheme.bodyMedium
                ?.copyWith(color: AppTheme.textGrey),
          ),
          const SizedBox(height: 24),
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: AppTheme.borderPink),
              boxShadow: [
                BoxShadow(
                  color: AppTheme.primaryPink.withAlpha(18),
                  blurRadius: 18,
                  offset: const Offset(0, 7),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    color: AppTheme.badgeBlueBackground,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Icon(
                    Icons.assignment_outlined,
                    color: AppTheme.badgeBlueText,
                    size: 30,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Tugas Aktif',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: AppTheme.textGrey,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '$jumlahTugas',
                        style: Theme.of(context).textTheme.headlineMedium
                            ?.copyWith(
                              color: AppTheme.primaryPink,
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          Text(
            'Jumlah tugas aktif berasal dari laporan berstatus Sedang Diproses atau Diproses.',
            style: Theme.of(context).textTheme.bodySmall
                ?.copyWith(color: AppTheme.textGrey, height: 1.5),
          ),
        ],
      ),
    );
  }
}

class _DashboardError extends StatelessWidget {
  const _DashboardError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.cloud_off_outlined,
              color: AppTheme.textGrey,
              size: 52,
            ),
            const SizedBox(height: 14),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium
                  ?.copyWith(color: AppTheme.textDark),
            ),
            const SizedBox(height: 18),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Coba lagi'),
              style: FilledButton.styleFrom(
                backgroundColor: AppTheme.primaryPink,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DashboardNavigation extends StatelessWidget {
  const _DashboardNavigation({required this.onSelect});

  final void Function(String label) onSelect;

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
          const _NavigationItem(
            icon: Icons.home_rounded,
            label: 'Dashboard',
            selected: true,
          ),
          _NavigationItem(
            icon: Icons.description_outlined,
            label: 'Laporan',
            onTap: () => onSelect('Laporan'),
          ),
          _NavigationItem(
            icon: Icons.person_outline,
            label: 'Akun',
            onTap: () => onSelect('Akun'),
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

class _DashboardException implements Exception {
  const _DashboardException(this.message);

  final String message;
}
