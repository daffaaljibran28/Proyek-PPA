import 'dart:convert';

import 'package:http/http.dart' as http;

import 'app_theme.dart';

class PetugasApi {
  PetugasApi({http.Client? client, String? baseUrl})
    : _client = client ?? http.Client(),
      _baseUrl = (baseUrl ?? AppTheme.apiBaseUrl).replaceFirst(
        RegExp(r'/+$'),
        '',
      );

  final http.Client _client;
  final String _baseUrl;

  Future<http.Response> request({
    required String method,
    required String path,
    String? token,
    String accept = 'application/json',
    Map<String, String>? queryParameters,
    Object? body,
  }) async {
    final normalizedPath = path.replaceFirst(RegExp(r'^/+'), '');
    final uri = Uri.parse('$_baseUrl/$normalizedPath')
        .replace(queryParameters: queryParameters);
    final request = http.Request(method, uri)..headers['Accept'] = accept;

    if (token != null && token.isNotEmpty) {
      request.headers['Authorization'] = 'Bearer $token';
    }

    if (body != null) {
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode(body);
    }

    return http.Response.fromStream(await _client.send(request));
  }

  void close() {
    _client.close();
  }
}
