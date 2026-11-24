<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Project
    |--------------------------------------------------------------------------
    |
    | Chat realtime SAPA memakai Cloud Firestore. Laravel tetap menjadi
    | sumber kebenaran untuk laporan, pengguna, petugas, dan hak akses.
    | Nilai di bawah diisi melalui .env dan tidak boleh berisi private key
    | di dalam file yang di-commit.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID'),

    // Path absolut/relatif terhadap base_path() menuju service account JSON,
    // atau isi JSON penuh melalui FIREBASE_CREDENTIALS_JSON.
    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH'),
    'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Web Client Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi publik (bukan rahasia) yang dikirim ke aplikasi Web/Flutter
    | agar dapat terhubung ke project Firebase yang sama.
    |
    */

    'web' => [
        'api_key' => env('FIREBASE_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'app_id' => env('FIREBASE_APP_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Emulator (opsional, untuk pengembangan)
    |--------------------------------------------------------------------------
    */

    'emulator' => [
        'auth_host' => env('FIREBASE_AUTH_EMULATOR_HOST'),
        'firestore_host' => env('FIRESTORE_EMULATOR_HOST'),
    ],

    'collection' => env('FIREBASE_CHAT_COLLECTION', 'chats'),

    'timeout' => (int) env('FIREBASE_HTTP_TIMEOUT', 10),

];
