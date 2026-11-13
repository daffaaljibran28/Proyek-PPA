import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' show ClientException;

import '../app_theme.dart';
import '../models/laporan.dart';
import '../models/petugas_user.dart';
import '../petugas_api.dart';
import '../petugas_session.dart';
import 'account_screen.dart';
import 'report_detail_screen.dart';
import 'login_screen.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key, required this.user});

  final PetugasUser user;

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  static const _filters = ['Semua', 'Menunggu', 'Diproses', 'Selesai'];
  static const _apiStatuses = {
    'Menunggu': 'Sedang Diproses',
    'Diproses': 'Diproses',
    'Selesai': 'Selesai',
  };

  final PetugasApi _api = PetugasApi();
  List<Laporan> _reports = const [];
  String _selectedFilter = 'Semua';
  String? _errorMessage;
  bool _isLoading = true;
  bool _sessionExpired = false;
  int _requestVersion = 0;

  @override
  void initState() {
    super.initState();
    _loadReports();
  }

  @override
  void dispose() {
    _requestVersion++;
    _api.close();
    super.dispose();
  }

  Future<void> _selectFilter(String filter) async {
    if (_selectedFilter == filter) return;
    setState(() => _selectedFilter = filter);
    await _loadReports();
  }

  Future<void> _loadReports() async {
    if (_sessionExpired) return;
    final requestVersion = ++_requestVersion;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final apiStatus = _apiStatuses[_selectedFilter];
      final response = await _api
          .request(
            method: 'GET',
            path: '/petugas/laporan',
            token: widget.user.token,
            queryParameters: apiStatus == null ? null : {'status': apiStatus},
          )
          .timeout(const Duration(seconds: 20));

      if (requestVersion != _requestVersion || !mounted) return;
      if (response.statusCode == 401) {
        await _handleExpiredSession();
        return;
      }

      final payload = _decodeResponse(response.body);
      if (response.statusCode != 200) {
        throw _ReportsException(
          _messageForStatus(response.statusCode, payload),
        );
      }

      final data = payload['data'];
      if (payload['status'] != 'success' || data is! List) {
        throw const FormatException('Respons daftar laporan tidak valid.');
      }

      final reports = data
          .map((entry) {
            if (entry is! Map) {
              throw const FormatException(
                'Data laporan dari server tidak valid.',
              );
            }
            return Laporan.fromJson(Map<String, dynamic>.from(entry));
          })
          .toList(growable: false);

      if (requestVersion != _requestVersion || !mounted) return;
      setState(() {
        _reports = reports;
        _isLoading = false;
      });
    } on TimeoutException {
      _setError(
        requestVersion,
        'Server tidak dapat dihubungi. Periksa koneksi, lalu coba lagi.',
      );
    } on ClientException {
      _setError(
        requestVersion,
        'Server tidak dapat dihubungi. Periksa alamat API dan koneksi.',
      );
    } on _ReportsException catch (error) {
      _setError(requestVersion, error.message);
    } on FormatException {
      _setError(
        requestVersion,
        'Respons daftar laporan dari server tidak valid.',
      );
    } catch (_) {
      _setError(
        requestVersion,
        'Daftar laporan gagal dimuat. Silakan coba lagi.',
      );
    }
  }

  Map<String, dynamic> _decodeResponse(String body) {
    final decoded = jsonDecode(body);
    if (decoded is! Map) {
      throw const FormatException('Respons API bukan objek JSON.');
    }
    return Map<String, dynamic>.from(decoded);
  }

  String _messageForStatus(int statusCode, Map<String, dynamic> payload) {
    if (statusCode == 403) {
      return _serverMessage(payload) ??
          'Akses ditolak atau akun Petugas tidak aktif.';
    }
    if (statusCode >= 500) {
      return 'Terjadi gangguan pada server. Silakan coba lagi nanti.';
    }
    return _serverMessage(payload) ?? 'Daftar laporan gagal dimuat.';
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

  void _setError(int requestVersion, String message) {
    if (!mounted || requestVersion != _requestVersion) return;
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
            const _ReportsHeader(),
            _FilterBar(
              filters: _filters,
              selectedFilter: _selectedFilter,
              onSelected: _selectFilter,
            ),
            Expanded(
              child: RefreshIndicator(
                color: AppTheme.primaryPink,
                onRefresh: _loadReports,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(18, 8, 18, 24),
                  children: [
                    if (_isLoading)
                      const _ListState(
                        child: CircularProgressIndicator(
                          color: AppTheme.primaryPink,
                        ),
                      )
                    else if (_errorMessage != null)
                      _ErrorState(
                        message: _errorMessage!,
                        onRetry: _loadReports,
                      )
                    else if (_reports.isEmpty)
                      const _EmptyState()
                    else
                      ..._reports.map(
                        (report) => _ReportCard(
                          report: report,
                          onTap: () => _openReport(report.idLaporan),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _ReportsNavigation(
        onDashboard: () => Navigator.of(context).pop(),
        onAccount: _openAccount,
      ),
    );
  }

  void _openAccount() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => AccountScreen(
          user: widget.user,
          onDashboard: () =>
              Navigator.of(context).popUntil((route) => route.isFirst),
          onReports: () => Navigator.of(context).pop(),
        ),
      ),
    );
  }

  void _openReport(int reportId) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        settings: const RouteSettings(name: '/petugas/laporan/detail'),
        builder: (_) =>
            ReportDetailScreen(user: widget.user, reportId: reportId),
      ),
    );
  }
}

class _ReportsHeader extends StatelessWidget {
  const _ReportsHeader();

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

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.filters,
    required this.selectedFilter,
    required this.onSelected,
  });

  final List<String> filters;
  final String selectedFilter;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.fromLTRB(14, 18, 14, 12),
      child: Row(
        children: filters
            .map((filter) {
              final selected = filter == selectedFilter;
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: ChoiceChip(
                  label: Text(filter),
                  selected: selected,
                  onSelected: (_) => onSelected(filter),
                  showCheckmark: false,
                  labelStyle: Theme.of(context).textTheme.labelLarge?.copyWith(
                    color: selected ? Colors.white : AppTheme.textGrey,
                    fontWeight: FontWeight.w700,
                  ),
                  selectedColor: AppTheme.primaryPink,
                  backgroundColor: Colors.white,
                  side: BorderSide(
                    color: selected
                        ? AppTheme.primaryPink
                        : AppTheme.borderPink,
                  ),
                ),
              );
            })
            .toList(growable: false),
      ),
    );
  }
}

class _ReportCard extends StatelessWidget {
  const _ReportCard({required this.report, required this.onTap});

  final Laporan report;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final reportNumber = report.nomorLaporan?.trim();
    final date = _formatDate(report.createdAt ?? report.tanggalKejadian);
    final (backgroundColor, foregroundColor) = _statusColors(
      report.statusPenanganan,
    );

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: const BorderSide(color: AppTheme.borderPink),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      reportNumber == null || reportNumber.isEmpty
                          ? 'Laporan #${report.idLaporan}'
                          : reportNumber,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: AppTheme.textDark,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: backgroundColor,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      _statusLabel(report.statusPenanganan),
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: foregroundColor,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                report.kategori,
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                  color: AppTheme.primaryPink,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (date != null) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Icon(
                      Icons.calendar_today_outlined,
                      size: 15,
                      color: AppTheme.textGrey,
                    ),
                    const SizedBox(width: 7),
                    Text(
                      'Tanggal laporan: $date',
                      style: Theme.of(context).textTheme.bodySmall
                          ?.copyWith(color: AppTheme.textGrey),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  static String _statusLabel(String status) {
    return status == 'Sedang Diproses' ? 'Menunggu' : status;
  }

  static (Color, Color) _statusColors(String status) {
    return switch (status) {
      'Sedang Diproses' => (
        AppTheme.badgeYellowBackground,
        AppTheme.badgeYellowText,
      ),
      'Diproses' => (AppTheme.badgeBlueBackground, AppTheme.badgeBlueText),
      'Selesai' => (AppTheme.badgeGreenBackground, AppTheme.badgeGreenText),
      _ => (AppTheme.background, AppTheme.textGrey),
    };
  }

  static String? _formatDate(String? value) {
    if (value == null || value.isEmpty) return null;
    final parsed = DateTime.tryParse(value);
    if (parsed == null) return value;
    final day = parsed.day.toString().padLeft(2, '0');
    final month = parsed.month.toString().padLeft(2, '0');
    return '$day/$month/${parsed.year}';
  }
}

class _ListState extends StatelessWidget {
  const _ListState({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return SizedBox(height: 300, child: Center(child: child));
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    return const _ListState(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.inbox_outlined, color: AppTheme.textGrey, size: 50),
          SizedBox(height: 12),
          Text('Belum ada laporan pada filter ini.'),
        ],
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return _ListState(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.cloud_off_outlined,
              color: AppTheme.textGrey,
              size: 48,
            ),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
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

class _ReportsNavigation extends StatelessWidget {
  const _ReportsNavigation({
    required this.onDashboard,
    required this.onAccount,
  });

  final VoidCallback onDashboard;
  final VoidCallback onAccount;

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
          const _NavigationItem(
            icon: Icons.description_outlined,
            label: 'Laporan',
            selected: true,
          ),
          _NavigationItem(
            icon: Icons.person_outline,
            label: 'Akun',
            onTap: onAccount,
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

class _ReportsException implements Exception {
  const _ReportsException(this.message);

  final String message;
}
