import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sapa_petugas/app_theme.dart';
import 'package:sapa_petugas/screens/login_screen.dart';

void main() {
  testWidgets('login button is spaced below the password field', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(theme: AppTheme.lightTheme, home: const LoginScreen()),
    );

    final passwordField = tester.getRect(find.byType(TextFormField).last);
    final loginButton = tester.getRect(
      find.widgetWithText(FilledButton, 'Masuk'),
    );

    expect(loginButton.top - passwordField.bottom, greaterThanOrEqualTo(16));
  });

  testWidgets('login page uses the pale pink background', (tester) async {
    await tester.pumpWidget(
      MaterialApp(theme: AppTheme.lightTheme, home: const LoginScreen()),
    );

    final scaffold = tester.widget<Scaffold>(find.byType(Scaffold));
    expect(scaffold.backgroundColor, const Color(0xFFFFF1F4));
  });

  testWidgets('forgot password pill appears above the login button', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(theme: AppTheme.lightTheme, home: const LoginScreen()),
    );

    final forgotPasswordButton = tester.getRect(
      find.ancestor(
        of: find.text('Lupa Password?'),
        matching: find.byType(TextButton),
      ),
    );
    final loginButton = tester.getRect(
      find.widgetWithText(FilledButton, 'Masuk'),
    );

    expect(forgotPasswordButton.bottom, lessThan(loginButton.top));
  });

  testWidgets('forgot password link opens the OTP screen', (tester) async {
    await tester.pumpWidget(
      MaterialApp(theme: AppTheme.lightTheme, home: const LoginScreen()),
    );

    final forgotPasswordLink = find.text('Lupa Password?');
    await tester.ensureVisible(forgotPasswordLink);
    await tester.pumpAndSettle();
    await tester.tap(forgotPasswordLink);
    await tester.pumpAndSettle();

    expect(find.text('Atur Ulang Kata Sandi'), findsOneWidget);
    expect(find.text('Kirim Kode OTP'), findsOneWidget);
    final emailField = tester.widget<TextField>(find.byType(TextField));
    final emailBorder = emailField.decoration!.border! as OutlineInputBorder;
    expect(emailBorder.borderRadius, BorderRadius.circular(28));
  });
}
