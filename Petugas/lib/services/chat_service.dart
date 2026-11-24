import 'dart:convert';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:http/http.dart' as http;
import '../app_theme.dart';
import '../models/chat_message.dart';
import '../models/petugas_user.dart';

class ChatSessionData {
  const ChatSessionData({
    required this.chatId,
    required this.uid,
    required this.role,
    required this.customToken,
    required this.firebaseConfig,
    required this.participants,
  });

  final String chatId;
  final String uid;
  final String role;
  final String customToken;
  final Map<String, dynamic> firebaseConfig;
  final List<String> participants;
}

class ChatService {
  FirebaseApp? _app;

  /// Meminta custom token dan konfigurasi sesi Firebase dari API Laravel
  Future<ChatSessionData> requestChatSession(int reportId, PetugasUser user) async {
    final url = Uri.parse('${AppTheme.apiBaseUrl}/chat/laporan/$reportId/sesi');
    final response = await http.post(
      url,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ${user.token}',
      },
      body: '{}',
    );

    if (response.statusCode != 200) {
      final payload = jsonDecode(response.body);
      final message = payload['message'] ?? 'Gagal membuat sesi percakapan.';
      throw StateError(message);
    }

    final payload = jsonDecode(response.body);
    final data = payload['data'] as Map<String, dynamic>;

    return ChatSessionData(
      chatId: data['chat_id'].toString(),
      uid: data['uid'] as String,
      role: data['role'] as String,
      customToken: data['custom_token'] as String,
      firebaseConfig: Map<String, dynamic>.from(data['firebase'] ?? {}),
      participants: List<String>.from(data['participants'] ?? []),
    );
  }

  /// Inisialisasi Firebase & Autentikasi Custom Token
  Future<void> connectFirebase(ChatSessionData session) async {
    if (Firebase.apps.isEmpty) {
      final config = session.firebaseConfig;
      final options = FirebaseOptions(
        apiKey: config['apiKey'] ?? '',
        appId: config['appId'] ?? '',
        messagingSenderId: config['messagingSenderId'] ?? '',
        projectId: config['projectId'] ?? '',
        authDomain: config['authDomain'],
        storageBucket: config['storageBucket'],
      );
      _app = await Firebase.initializeApp(options: options);
    } else {
      _app = Firebase.app();
    }

    final auth = FirebaseAuth.instanceFor(app: _app!);
    if (auth.currentUser?.uid != session.uid) {
      await auth.signInWithCustomToken(session.customToken);
    }
  }

  /// Stream percakapan pesan realtime terurut waktu
  Stream<List<ChatMessage>> getMessagesStream(int reportId) {
    final db = FirebaseFirestore.instanceFor(app: _app ?? Firebase.app());
    return db
        .collection('chats')
        .doc(reportId.toString())
        .collection('messages')
        .orderBy('created_at', descending: true)
        .snapshots()
        .map((snapshot) =>
            snapshot.docs.map((doc) => ChatMessage.fromFirestore(doc)).toList());
  }

  /// Kirim pesan baru ke subcollection messages dan update metadata chat induk
  Future<void> sendMessage({
    required int reportId,
    required String message,
    required String currentUid,
    required String senderName,
    required List<String> participants,
  }) async {
    final trimmed = message.trim();
    if (trimmed.isEmpty) return;

    final db = FirebaseFirestore.instanceFor(app: _app ?? Firebase.app());
    final chatDoc = db.collection('chats').doc(reportId.toString());
    final messageDoc = chatDoc.collection('messages').doc();

    final batch = db.batch();
    batch.set(messageDoc, {
      'sender_id': currentUid,
      'sender_role': 'petugas',
      'sender_name': senderName,
      'message': trimmed,
      'created_at': FieldValue.serverTimestamp(),
      'read_at': null,
      'participants': participants,
    });

    batch.update(chatDoc, {
      'last_message': trimmed,
      'last_message_at': FieldValue.serverTimestamp(),
      'last_sender_id': currentUid,
      'updated_at': FieldValue.serverTimestamp(),
    });

    await batch.commit();
  }

  /// Menandai pesan lawan bicara sebagai telah dibaca
  Future<void> markMessagesAsRead({
    required int reportId,
    required List<ChatMessage> messages,
    required String currentUid,
  }) async {
    final unread = messages
        .where((m) => m.senderId != currentUid && m.readAt == null)
        .take(25)
        .toList();

    if (unread.isEmpty) return;

    final db = FirebaseFirestore.instanceFor(app: _app ?? Firebase.app());
    final batch = db.batch();

    for (final item in unread) {
      final docRef = db
          .collection('chats')
          .doc(reportId.toString())
          .collection('messages')
          .doc(item.id);
      batch.update(docRef, {'read_at': FieldValue.serverTimestamp()});
    }

    try {
      await batch.commit();
    } catch (_) {
      // Abaikan jika batch gagal karena network offline sementara
    }
  }
}
