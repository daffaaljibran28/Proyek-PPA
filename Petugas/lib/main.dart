import 'package:flutter/material.dart';

import 'app_theme.dart';
import 'models/petugas_user.dart';
import 'petugas_session.dart';
import 'screens/dashboard_screen.dart';
import 'screens/login_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const PetugasApp());
}

class PetugasApp extends StatelessWidget {
  const PetugasApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SAPA Petugas',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      home: const _SessionGate(),
    );
  }
}

class _SessionGate extends StatefulWidget {
  const _SessionGate();

  @override
  State<_SessionGate> createState() => _SessionGateState();
}

class _SessionGateState extends State<_SessionGate> {
  late final Future<PetugasUser?> _session = PetugasSession.restore();

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<PetugasUser?>(
      future: _session,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return const LoginScreen();
        }
        if (snapshot.connectionState != ConnectionState.done) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }

        final user = snapshot.data;
        if (user == null || user.role.trim().toLowerCase() != 'petugas') {
          return const LoginScreen();
        }

        return DashboardScreen(user: user);
      },
    );
  }
}
