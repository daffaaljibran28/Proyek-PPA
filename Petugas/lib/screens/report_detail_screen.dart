import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:http/http.dart' show ClientException;

import '../app_theme.dart';
import '../models/laporan.dart';
import '../models/petugas_user.dart';
import '../petugas_api.dart';
import '../petugas_session.dart';
import 'account_screen.dart';
import 'login_screen.dart';

class ReportDetailScreen extends StatefulWidget {
  const ReportDetailScreen({
    super.key,
    required this.user,
    required this.reportId,
  });

  final PetugasUser user;
  final int reportId;

  @override
  State<ReportDetailScreen> createState() => _ReportDetailScreenState();
}

class _ReportDetailScreenState extends State<ReportDetailScreen> {
  final PetugasApi _api = PetugasApi();
  final TextEditingController _internalNoteController = TextEditingController();
  final TextEditingController _reporterMessageController =
      TextEditingController();

  Laporan? _report;
  String? _errorMessage;
  bool _isLoading = true;
  bool _isSaving = false;
  bool _sessionExpired = false;
  int _loadVersion = 0;

  @override
  void initState() {
    super.initState();
    _loadDetail();
  }

  @override
  void dispose() {
    _loadVersion++;
    _internalNoteController.dispose();
    _reporterMessageController.dispose();
    _api.close();
    super.dispose();
  }

  Future<void> _loadDetail({bool showLoading = true}) async {
    if (_sessionExpired) return;
    final loadVersion = ++_loadVersion;
    if (showLoading && mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final response = await _api
          .request(
            method: 'GET',
            path: '/petugas/laporan/${widget.reportId}',
            token: widget.user.token,
          )
          .timeout(const Duration(seconds: 20));

      if (!mounted || loadVersion != _loadVersion) return;
      if (response.statusCode == 401) {
        await _handleUnauthorized();
        return;
      }

      final payload = _decodeResponse(response.body);
      if (response.statusCode != 200) {
        throw _DetailException(_responseMessage(response.statusCode, payload));
      }

      final data = payload['data'];
      if (payload['status'] != 'success' || data is! Map) {
        throw const FormatException('Respons detail laporan tidak valid.');
      }

      final report = Laporan.fromJson(Map<String, dynamic>.from(data));
      if (!mounted || loadVersion != _loadVersion) return;
      setState(() {
        _report = report;
        _isLoading = false;
        _errorMessage = null;
      });
    } on TimeoutException {
      _setLoadError(
        loadVersion,
        'Server tidak dapat dihubungi. Periksa koneksi, lalu coba lagi.',
      );
    } on ClientException {
      _setLoadError(
        loadVersion,
        'Server tidak dapat dihubungi. Periksa alamat API dan koneksi.',
      );
    } on _DetailException catch (error) {
      _setLoadError(loadVersion, error.message);
    } on FormatException {
      _setLoadError(loadVersion, 'Respons detail laporan tidak valid.');
    } catch (_) {
      _setLoadError(loadVersion, 'Detail laporan gagal dimuat. Coba lagi.');
    }
  }

  Future<Uint8List?> _loadEvidence(int index) async {
    final response = await _api
        .request(
          method: 'GET',
          path: '/petugas/laporan/${widget.reportId}/bukti/$index',
          token: widget.user.token,
          accept: 'image/*',
        )
        .timeout(const Duration(seconds: 20));

    if (response.statusCode == 401) {
      await _handleUnauthorized();
      return null;
    }
    if (response.statusCode != 200) {
      throw _DetailException(
        _responseMessage(response.statusCode, _decodeResponse(response.body)),
      );
    }
    if (response.bodyBytes.isEmpty) {
      throw const _DetailException('Bukti laporan tidak tersedia.');
    }

    return response.bodyBytes;
  }

  Future<void> _saveHandling({String? targetStatus}) async {
    if (_isSaving || _sessionExpired) return;

    final internalNote = _internalNoteController.text.trim();
    final reporterMessage = _reporterMessageController.text.trim();
    if (targetStatus == null &&
        internalNote.isEmpty &&
        reporterMessage.isEmpty) {
      _showMessage('Isi catatan internal atau pesan untuk Pelapor.');
      return;
    }

    final body = <String, String>{};
    if (targetStatus != null) {
      body['status_penanganan'] = targetStatus;
    }
    if (internalNote.isNotEmpty) {
      body['catatan_petugas'] = internalNote;
    }
    if (reporterMessage.isNotEmpty) {
      body['pesan_pelapor'] = reporterMessage;
    }

    setState(() => _isSaving = true);
    try {
      final response = await _api
          .request(
            method: 'PATCH',
            path: '/petugas/laporan/${widget.reportId}/penanganan',
            token: widget.user.token,
            body: body,
          )
          .timeout(const Duration(seconds: 20));

      if (response.statusCode == 401) {
        await _handleUnauthorized();
        return;
      }
      if (response.statusCode != 200) {
        final payload = _decodeResponse(response.body);
        throw _DetailException(_responseMessage(response.statusCode, payload));
      }

      _internalNoteController.clear();
      _reporterMessageController.clear();
      if (!mounted) return;
      _showMessage('Perubahan penanganan berhasil disimpan.');
      await _loadDetail();
    } on TimeoutException {
      _showMessage('Server tidak dapat dihubungi. Coba kirim kembali.');
    } on ClientException {
      _showMessage('Server tidak dapat dihubungi. Periksa koneksi Anda.');
    } on _DetailException catch (error) {
      _showMessage(error.message);
    } on FormatException {
      _showMessage('Respons server tidak valid. Perubahan belum dikonfirmasi.');
    } catch (_) {
      _showMessage('Perubahan gagal disimpan. Silakan coba lagi.');
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  Future<void> _confirmCompletion() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Selesaikan laporan?'),
        content: const Text(
          'Status laporan akan diubah menjadi Selesai. Tindakan ini '
          'menandai penanganan sebagai selesai.',
        ),
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
            child: const Text('Selesaikan'),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      await _saveHandling(targetStatus: 'Selesai');
    }
  }

  Future<void> _handleUnauthorized() async {
    if (_sessionExpired) return;
    _sessionExpired = true;
    await PetugasSession.clear();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  void _setLoadError(int loadVersion, String message) {
    if (!mounted || loadVersion != _loadVersion) return;
    setState(() {
      _isLoading = false;
      _errorMessage = message;
    });
  }

  Map<String, dynamic> _decodeResponse(String body) {
    final decoded = jsonDecode(body);
    if (decoded is! Map) {
      throw const FormatException('Respons API bukan objek JSON.');
    }
    return Map<String, dynamic>.from(decoded);
  }

  String _responseMessage(int statusCode, Map<String, dynamic> payload) {
    final serverMessage = _serverMessage(payload);
    return switch (statusCode) {
      403 => serverMessage ?? 'Akses ditolak atau akun Petugas tidak aktif.',
      404 => 'Laporan atau bukti tidak ditemukan, atau bukan tugas Anda.',
      422 =>
        _validationMessage(payload) ??
            serverMessage ??
            'Periksa kembali data penanganan laporan.',
      >= 500 => 'Terjadi gangguan pada server. Silakan coba lagi nanti.',
      _ => serverMessage ?? 'Permintaan gagal. Silakan coba lagi.',
    };
  }

  String? _serverMessage(Map<String, dynamic> payload) {
    final message = payload['message'];
    if (message is String && message.trim().isNotEmpty) {
      return message.trim();
    }
    return null;
  }

  String? _validationMessage(Map<String, dynamic> payload) {
    final errors = payload['errors'];
    if (errors is! Map) return null;
    for (final value in errors.values) {
      if (value is List && value.isNotEmpty && value.first is String) {
        return value.first as String;
      }
    }
    return null;
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final report = _report;
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: SafeArea(
        child: Column(
          children: [
            _DetailHeader(onBack: () => Navigator.of(context).maybePop()),
            Expanded(
              child: _isLoading && report == null
                  ? const Center(
                      child: CircularProgressIndicator(
                        color: AppTheme.primaryPink,
                      ),
                    )
                  : _errorMessage != null && report == null
                  ? _DetailError(message: _errorMessage!, onRetry: _loadDetail)
                  : report == null
                  ? const SizedBox.shrink()
                  : RefreshIndicator(
                      color: AppTheme.primaryPink,
                      onRefresh: _loadDetail,
                      child: ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(18, 20, 18, 28),
                        children: [
                          if (_errorMessage != null)
                            _InlineError(message: _errorMessage!),
                          _ReportInformation(report: report),
                          const SizedBox(height: 14),
                          _EvidenceSection(
                            key: ValueKey(
                              'evidence-${widget.reportId}-${report.buktiLampiran}',
                            ),
                            report: report,
                            loadEvidence: _loadEvidence,
                          ),
                          const SizedBox(height: 14),
                          _HistorySection(history: report.riwayatStatus),
                          const SizedBox(height: 14),
                          _NoteCard(
                            title: 'Catatan internal Petugas',
                            icon: Icons.lock_outline,
                            content: report.catatanPetugas,
                            emptyText: 'Belum ada catatan internal.',
                          ),
                          const SizedBox(height: 14),
                          _NoteCard(
                            title: 'Pesan untuk Pelapor',
                            icon: Icons.mark_chat_unread_outlined,
                            content: report.pesanPelapor,
                            emptyText: 'Belum ada pesan untuk Pelapor.',
                          ),
                          if (report.statusPenanganan != 'Selesai') ...[
                            const SizedBox(height: 14),
                            _HandlingForm(
                              internalNoteController: _internalNoteController,
                              reporterMessageController:
                                  _reporterMessageController,
                              isSaving: _isSaving,
                              status: report.statusPenanganan,
                              onSave: () => _saveHandling(),
                              onStart: () =>
                                  _saveHandling(targetStatus: 'Diproses'),
                              onComplete: _confirmCompletion,
                            ),
                          ],
                        ],
                      ),
                    ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _DetailNavigation(
        onDashboard: () =>
            Navigator.of(context).popUntil((route) => route.isFirst),
        onReports: () => Navigator.of(context).maybePop(),
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
          onReports: () => Navigator.of(context).popUntil(
            (route) =>
                route.settings.name == '/petugas/laporan' || route.isFirst,
          ),
        ),
      ),
    );
  }
}

class _DetailHeader extends StatelessWidget {
  const _DetailHeader({required this.onBack});

  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(10, 14, 22, 20),
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
          IconButton(
            onPressed: onBack,
            icon: const Icon(Icons.arrow_back, color: Colors.white),
            tooltip: 'Kembali',
          ),
          const SizedBox(width: 4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'SAPA · PETUGAS',
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: Colors.white.withAlpha(225),
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.6,
                  ),
                ),
                Text(
                  'Detail Laporan',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
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

class _ReportInformation extends StatelessWidget {
  const _ReportInformation({required this.report});

  final Laporan report;

  @override
  Widget build(BuildContext context) {
    final reportNumber = report.nomorLaporan?.trim();
    return _SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  reportNumber == null || reportNumber.isEmpty
                      ? 'Laporan #${report.idLaporan}'
                      : reportNumber,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: AppTheme.textDark,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              _StatusBadge(status: report.statusPenanganan),
            ],
          ),
          const SizedBox(height: 14),
          _DetailField(label: 'Kategori', value: report.kategori),
          _DetailField(
            label: 'Tanggal kejadian',
            value: _formatDateTime(report.tanggalKejadian),
          ),
          _DetailField(label: 'Lokasi kejadian', value: report.lokasiKejadian),
          _DetailField(label: 'Pelapor sebagai', value: report.sebagai),
          _DetailField(label: 'Kronologi', value: report.kronologi),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final (backgroundColor, foregroundColor) = switch (status) {
      'Sedang Diproses' => (
        AppTheme.badgeYellowBackground,
        AppTheme.badgeYellowText,
      ),
      'Diproses' => (AppTheme.badgeBlueBackground, AppTheme.badgeBlueText),
      'Selesai' => (AppTheme.badgeGreenBackground, AppTheme.badgeGreenText),
      _ => (AppTheme.background, AppTheme.textGrey),
    };
    final label = status == 'Sedang Diproses' ? 'Menunggu' : status;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall
            ?.copyWith(color: foregroundColor, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _DetailField extends StatelessWidget {
  const _DetailField({required this.label, required this.value});

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    if (value == null || value!.trim().isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: AppTheme.textGrey,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value!,
            style: Theme.of(context).textTheme.bodyMedium
                ?.copyWith(color: AppTheme.textDark, height: 1.5),
          ),
        ],
      ),
    );
  }
}

class _EvidenceSection extends StatelessWidget {
  const _EvidenceSection({
    super.key,
    required this.report,
    required this.loadEvidence,
  });

  final Laporan report;
  final Future<Uint8List?> Function(int index) loadEvidence;

  @override
  Widget build(BuildContext context) {
    final paths = (report.buktiLampiran ?? '')
        .split(',')
        .map((path) => path.trim())
        .where((path) => path.isNotEmpty)
        .toList(growable: false);

    return _SectionCard(
      title: 'Bukti laporan',
      child: paths.isEmpty
          ? Text(
              'Tidak ada bukti laporan.',
              style: Theme.of(context).textTheme.bodyMedium
                  ?.copyWith(color: AppTheme.textGrey),
            )
          : Column(
              children: List.generate(
                paths.length,
                (index) => Padding(
                  padding: EdgeInsets.only(top: index == 0 ? 0 : 14),
                  child: _EvidenceItem(
                    index: index,
                    loadEvidence: loadEvidence,
                  ),
                ),
              ),
            ),
    );
  }
}

class _EvidenceItem extends StatefulWidget {
  const _EvidenceItem({required this.index, required this.loadEvidence});

  final int index;
  final Future<Uint8List?> Function(int index) loadEvidence;

  @override
  State<_EvidenceItem> createState() => _EvidenceItemState();
}

class _EvidenceItemState extends State<_EvidenceItem> {
  late Future<Uint8List?> _evidence;

  @override
  void initState() {
    super.initState();
    _evidence = widget.loadEvidence(widget.index);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Uint8List?>(
      future: _evidence,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const _EvidenceMessage(
            icon: Icons.hourglass_top,
            message: 'Memuat bukti...',
          );
        }
        if (snapshot.hasError || snapshot.data == null) {
          final message = snapshot.error is _DetailException
              ? (snapshot.error! as _DetailException).message
              : 'Bukti tidak dapat dimuat.';
          return _EvidenceMessage(
            icon: Icons.broken_image_outlined,
            message: message,
          );
        }

        return ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: Image.memory(
            snapshot.data!,
            width: double.infinity,
            fit: BoxFit.contain,
            errorBuilder: (_, _, _) => const _EvidenceMessage(
              icon: Icons.broken_image_outlined,
              message: 'Format bukti tidak dapat ditampilkan.',
            ),
          ),
        );
      },
    );
  }
}

class _EvidenceMessage extends StatelessWidget {
  const _EvidenceMessage({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppTheme.background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Icon(icon, color: AppTheme.textGrey, size: 30),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall
                ?.copyWith(color: AppTheme.textGrey),
          ),
        ],
      ),
    );
  }
}

class _HistorySection extends StatelessWidget {
  const _HistorySection({required this.history});

  final List<Map<String, dynamic>> history;

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      title: 'Riwayat status',
      child: history.isEmpty
          ? Text(
              'Belum ada riwayat status.',
              style: Theme.of(context).textTheme.bodyMedium
                  ?.copyWith(color: AppTheme.textGrey),
            )
          : Column(
              children: history.indexed
                  .map((entry) {
                    final index = entry.$1;
                    final item = entry.$2;
                    final status = item['status'];
                    final message = item['pesan'];
                    final date = item['pada'];
                    final actor = item['oleh'];

                    return Padding(
                      padding: EdgeInsets.only(top: index == 0 ? 0 : 16),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 10,
                            height: 10,
                            margin: const EdgeInsets.only(top: 5, right: 12),
                            decoration: const BoxDecoration(
                              color: AppTheme.primaryPink,
                              shape: BoxShape.circle,
                            ),
                          ),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _displayValue(status, 'Status diperbarui'),
                                  style: Theme.of(context).textTheme.bodyMedium
                                      ?.copyWith(
                                        color: AppTheme.textDark,
                                        fontWeight: FontWeight.w700,
                                      ),
                                ),
                                if (actor is String && actor.isNotEmpty)
                                  Text(
                                    'Oleh $actor',
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(color: AppTheme.textGrey),
                                  ),
                                if (date is String && date.isNotEmpty)
                                  Text(
                                    _formatDateTime(date) ?? date,
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(color: AppTheme.textGrey),
                                  ),
                                if (message is String &&
                                    message.trim().isNotEmpty) ...[
                                  const SizedBox(height: 5),
                                  Text(
                                    message,
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: AppTheme.textDark,
                                          height: 1.4,
                                        ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                    );
                  })
                  .toList(growable: false),
            ),
    );
  }
}

class _NoteCard extends StatelessWidget {
  const _NoteCard({
    required this.title,
    required this.icon,
    required this.content,
    required this.emptyText,
  });

  final String title;
  final IconData icon;
  final String? content;
  final String emptyText;

  @override
  Widget build(BuildContext context) {
    final text = content?.trim();
    return _SectionCard(
      title: title,
      leading: Icon(icon, size: 19, color: AppTheme.primaryPink),
      child: Text(
        text == null || text.isEmpty ? emptyText : text,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          color: text == null || text.isEmpty
              ? AppTheme.textGrey
              : AppTheme.textDark,
          height: 1.5,
        ),
      ),
    );
  }
}

class _HandlingForm extends StatelessWidget {
  const _HandlingForm({
    required this.internalNoteController,
    required this.reporterMessageController,
    required this.isSaving,
    required this.status,
    required this.onSave,
    required this.onStart,
    required this.onComplete,
  });

  final TextEditingController internalNoteController;
  final TextEditingController reporterMessageController;
  final bool isSaving;
  final String status;
  final VoidCallback onSave;
  final VoidCallback onStart;
  final VoidCallback onComplete;

  @override
  Widget build(BuildContext context) {
    final canStart = status == 'Sedang Diproses';
    final canComplete = status == 'Diproses';

    return _SectionCard(
      title: 'Penanganan laporan',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Catatan internal Petugas',
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppTheme.textDark,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 7),
          TextField(
            controller: internalNoteController,
            enabled: !isSaving,
            maxLength: 5000,
            maxLines: 4,
            decoration: _textDecoration(
              hintText: 'Catatan ini hanya terlihat oleh Petugas.',
            ),
          ),
          const SizedBox(height: 10),
          Text(
            'Pesan untuk Pelapor',
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: AppTheme.textDark,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 7),
          TextField(
            controller: reporterMessageController,
            enabled: !isSaving,
            maxLength: 2000,
            maxLines: 3,
            decoration: _textDecoration(
              hintText: 'Pesan ini akan disampaikan kepada Pelapor.',
            ),
          ),
          const SizedBox(height: 4),
          OutlinedButton.icon(
            onPressed: isSaving ? null : onSave,
            icon: isSaving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.save_outlined),
            label: Text(isSaving ? 'Menyimpan...' : 'Simpan catatan/pesan'),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppTheme.primaryPink,
              side: const BorderSide(color: AppTheme.primaryPink),
              padding: const EdgeInsets.symmetric(vertical: 13),
            ),
          ),
          if (canStart || canComplete) ...[
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: isSaving ? null : (canStart ? onStart : onComplete),
              icon: isSaving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : Icon(
                      canStart ? Icons.play_arrow_rounded : Icons.check_rounded,
                    ),
              label: Text(
                isSaving
                    ? 'Memproses...'
                    : canStart
                    ? 'Mulai penanganan'
                    : 'Selesaikan laporan',
              ),
              style: FilledButton.styleFrom(
                backgroundColor: AppTheme.primaryPink,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ],
        ],
      ),
    );
  }

  static InputDecoration _textDecoration({required String hintText}) {
    return InputDecoration(
      hintText: hintText,
      filled: true,
      fillColor: Colors.white,
      alignLabelWithHint: true,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppTheme.borderPink),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppTheme.borderPink),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppTheme.primaryPink, width: 1.5),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.child, this.title, this.leading});

  final Widget child;
  final String? title;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(17),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppTheme.borderPink),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (title != null) ...[
            Row(
              children: [
                if (leading != null) ...[leading!, const SizedBox(width: 8)],
                Expanded(
                  child: Text(
                    title!,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: AppTheme.textDark,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
          ],
          child,
        ],
      ),
    );
  }
}

class _DetailError extends StatelessWidget {
  const _DetailError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(26),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.cloud_off_outlined,
              color: AppTheme.textGrey,
              size: 50,
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
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Text(
        message,
        style: Theme.of(context).textTheme.bodySmall
            ?.copyWith(color: Colors.redAccent),
      ),
    );
  }
}

class _DetailNavigation extends StatelessWidget {
  const _DetailNavigation({
    required this.onDashboard,
    required this.onReports,
    required this.onAccount,
  });

  final VoidCallback onDashboard;
  final VoidCallback onReports;
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
          _NavigationItem(
            icon: Icons.description_outlined,
            label: 'Laporan',
            selected: true,
            onTap: onReports,
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

class _DetailException implements Exception {
  const _DetailException(this.message);

  final String message;
}

String? _formatDateTime(String? value) {
  if (value == null || value.isEmpty) return null;
  final parsed = DateTime.tryParse(value);
  if (parsed == null) return value;
  final day = parsed.day.toString().padLeft(2, '0');
  final month = parsed.month.toString().padLeft(2, '0');
  final hour = parsed.hour.toString().padLeft(2, '0');
  final minute = parsed.minute.toString().padLeft(2, '0');
  return '$day/$month/${parsed.year} $hour:$minute';
}

String _displayValue(Object? value, String fallback) {
  if (value is String && value.trim().isNotEmpty) return value;
  return fallback;
}
