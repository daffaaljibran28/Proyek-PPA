import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ppa_flutter/login.dart';
import 'package:ppa_flutter/main.dart';
import 'package:ppa_flutter/theme.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('app opens on the login screen without a session',
      (tester) async {
    UserSession.currentUser = null;
    await tester.pumpWidget(const MyApp());

    expect(find.byType(Login), findsOneWidget);
  });

  testWidgets('forgot password link opens the OTP screen', (tester) async {
    UserSession.currentUser = null;
    await tester.pumpWidget(const MyApp());

    await tester.tap(find.text('Lupa Password?'));
    await tester.pumpAndSettle();

    expect(find.text('Atur Ulang Kata Sandi'), findsOneWidget);
    expect(find.text('Kirim Kode OTP'), findsOneWidget);
    final emailField = tester.widget<TextField>(find.byType(TextField));
    final emailBorder = emailField.decoration!.border! as OutlineInputBorder;
    expect(emailBorder.borderRadius, BorderRadius.circular(28));
  });

  test('saved authentication session can be restored', () async {
    SharedPreferences.setMockInitialValues({});
    await UserSession.save(
        {'id': 42, 'name': 'Pelapor', 'token': 'test-token'});

    UserSession.currentUser = null;
    await UserSession.restore();

    expect(UserSession.id, 42);
    expect(UserSession.authHeaders()['Authorization'], 'Bearer test-token');
    await UserSession.clear();
  });
}
