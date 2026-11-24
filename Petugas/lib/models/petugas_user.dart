class PetugasUser {
  const PetugasUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.token,
  });

  final int id;
  final String name;
  final String email;
  final String role;
  final String token;

  factory PetugasUser.fromJson(Map<String, dynamic> json) {
    return PetugasUser(
      id: _requiredInt(json['id'], 'id'),
      name: _requiredString(json['name'], 'name'),
      email: _requiredString(json['email'], 'email'),
      role: _requiredString(json['role'], 'role'),
      token: _requiredString(json['token'], 'token'),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'email': email,
    'role': role,
    'token': token,
  };

  static int _requiredInt(Object? value, String field) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) {
      final parsed = int.tryParse(value);
      if (parsed != null) return parsed;
    }

    throw FormatException('Field "$field" tidak berisi ID yang valid.');
  }

  static String _requiredString(Object? value, String field) {
    if (value is String && value.isNotEmpty) return value;
    throw FormatException('Field "$field" tidak berisi teks yang valid.');
  }
}
