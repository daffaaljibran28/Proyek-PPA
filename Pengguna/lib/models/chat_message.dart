import 'package:cloud_firestore/cloud_firestore.dart';

class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.senderId,
    required this.senderRole,
    required this.senderName,
    required this.message,
    required this.createdAt,
    this.readAt,
    this.participants = const [],
  });

  final String id;
  final String senderId;
  final String senderRole;
  final String senderName;
  final String message;
  final DateTime? createdAt;
  final DateTime? readAt;
  final List<String> participants;

  bool isMine(String currentUid) => senderId == currentUid;

  factory ChatMessage.fromFirestore(DocumentSnapshot<Map<String, dynamic>> doc) {
    final data = doc.data() ?? {};

    DateTime? parseTimestamp(dynamic value) {
      if (value is Timestamp) return value.toDate();
      if (value is String) return DateTime.tryParse(value);
      return null;
    }

    final rawParticipants = data['participants'];
    final participantsList = rawParticipants is List
        ? rawParticipants.map((e) => e.toString()).toList()
        : <String>[];

    return ChatMessage(
      id: doc.id,
      senderId: data['sender_id'] as String? ?? '',
      senderRole: data['sender_role'] as String? ?? 'pengguna',
      senderName: data['sender_name'] as String? ?? 'Pengguna',
      message: data['message'] as String? ?? '',
      createdAt: parseTimestamp(data['created_at']),
      readAt: parseTimestamp(data['read_at']),
      participants: participantsList,
    );
  }

  Map<String, dynamic> toMap() => {
    'sender_id': senderId,
    'sender_role': senderRole,
    'sender_name': senderName,
    'message': message,
    'created_at': createdAt != null ? Timestamp.fromDate(createdAt!) : FieldValue.serverTimestamp(),
    'read_at': readAt != null ? Timestamp.fromDate(readAt!) : null,
    'participants': participants,
  };
}
