<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Firebase\FirebaseConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;
    private User $otherPengguna;
    private User $petugas;
    private User $otherPetugas;
    private User $admin;
    private Laporan $laporan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin SAPA',
            'email' => 'admin@example.test',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->pengguna = User::create([
            'name' => 'Pelapor Utama',
            'email' => 'pelapor@example.test',
            'password' => Hash::make('Password123!'),
            'role' => 'pengguna',
            'is_active' => true,
        ]);

        $this->otherPengguna = User::create([
            'name' => 'Pelapor Lain',
            'email' => 'other-pelapor@example.test',
            'password' => Hash::make('Password123!'),
            'role' => 'pengguna',
            'is_active' => true,
        ]);

        $this->petugas = User::create([
            'name' => 'Petugas Khusus',
            'email' => 'petugas@example.test',
            'password' => Hash::make('Password123!'),
            'role' => 'petugas',
            'no_id_card' => 'PETUGAS-001',
            'is_active' => true,
        ]);

        $this->otherPetugas = User::create([
            'name' => 'Petugas Lain',
            'email' => 'other-petugas@example.test',
            'password' => Hash::make('Password123!'),
            'role' => 'petugas',
            'no_id_card' => 'PETUGAS-002',
            'is_active' => true,
        ]);

        $this->laporan = Laporan::create([
            'nomor_laporan' => 'LAP-20261120-001',
            'id_pengguna' => $this->pengguna->id,
            'nama_pelapor' => $this->pengguna->name,
            'kategori' => 'Kekerasan terhadap Perempuan',
            'sebagai' => 'Korban',
            'lokasi_kejadian' => 'Jl. Kebon Jeruk No. 10',
            'tanggal_kejadian' => '2026-11-20',
            'kronologi' => 'Kronologi kejadian laporan uji coba chat.',
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => $this->petugas->id,
        ]);
    }

    public function test_owner_can_get_chat_context(): void
    {
        $token = $this->pengguna->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/chat/laporan/{$this->laporan->id_laporan}")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.chat_id', (string) $this->laporan->id_laporan)
            ->assertJsonPath('data.report.nomor_laporan', 'LAP-20261120-001')
            ->assertJsonPath('data.viewer.id', $this->pengguna->id)
            ->assertJsonPath('data.viewer.role', 'pengguna')
            ->assertJsonPath('data.viewer.uid', 'sapa_pengguna_'.$this->pengguna->id)
            ->assertJsonPath('data.counterpart.id', $this->petugas->id)
            ->assertJsonPath('data.counterpart.role', 'petugas')
            ->assertJsonPath('data.counterpart.uid', 'sapa_petugas_'.$this->petugas->id);
    }

    public function test_other_user_cannot_access_chat_context_or_session(): void
    {
        $otherToken = $this->otherPengguna->createToken('other-token')->plainTextToken;

        // Harus 404 (bukan 403) untuk mencegah enumerasi ID laporan
        $this->withToken($otherToken)
            ->getJson("/api/chat/laporan/{$this->laporan->id_laporan}")
            ->assertNotFound();

        $this->withToken($otherToken)
            ->postJson("/api/chat/laporan/{$this->laporan->id_laporan}/sesi")
            ->assertNotFound();
    }

    public function test_assigned_petugas_can_access_chat_context(): void
    {
        $petugasToken = $this->petugas->createToken('petugas-token')->plainTextToken;

        $response = $this->withToken($petugasToken)
            ->getJson("/api/chat/laporan/{$this->laporan->id_laporan}")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.viewer.id', $this->petugas->id)
            ->assertJsonPath('data.viewer.role', 'petugas')
            ->assertJsonPath('data.viewer.uid', 'sapa_petugas_'.$this->petugas->id)
            ->assertJsonPath('data.counterpart.id', $this->pengguna->id)
            ->assertJsonPath('data.counterpart.role', 'pengguna')
            ->assertJsonPath('data.counterpart.uid', 'sapa_pengguna_'.$this->pengguna->id);
    }

    public function test_unassigned_petugas_cannot_access_chat(): void
    {
        $otherPetugasToken = $this->otherPetugas->createToken('other-petugas-token')->plainTextToken;

        $this->withToken($otherPetugasToken)
            ->getJson("/api/chat/laporan/{$this->laporan->id_laporan}")
            ->assertNotFound();

        $this->withToken($otherPetugasToken)
            ->postJson("/api/chat/laporan/{$this->laporan->id_laporan}/sesi")
            ->assertNotFound();
    }

    public function test_non_existent_report_returns_404(): void
    {
        $token = $this->pengguna->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/chat/laporan/999999')
            ->assertNotFound();
    }

    public function test_inactive_user_cannot_access_chat(): void
    {
        $this->pengguna->update(['is_active' => false]);
        $token = $this->pengguna->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/chat/laporan/{$this->laporan->id_laporan}")
            ->assertForbidden();
    }

    public function test_chat_session_returns_helpful_error_when_firebase_unconfigured(): void
    {
        Config::set('firebase.project_id', null);
        Config::set('firebase.credentials_path', null);
        Config::set('firebase.credentials_json', null);

        $token = $this->pengguna->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson("/api/chat/laporan/{$this->laporan->id_laporan}/sesi")
            ->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Fitur chat belum aktif. Hubungi administrator untuk melengkapi konfigurasi Firebase.');
    }

    public function test_chat_session_generates_custom_token_in_emulator_mode(): void
    {
        Config::set('firebase.project_id', 'sapa-test-project');
        Config::set('firebase.emulator.firestore_host', '127.0.0.1:8080');
        Config::set('firebase.emulator.auth_host', '127.0.0.1:9099');
        Config::set('firebase.web.api_key', 'AIzaSyFakeApiKeyForTesting');
        Config::set('firebase.web.app_id', '1:123456789:web:abcdef');

        // Fake Firestore call to ensureChat
        Http::fake([
            'http://127.0.0.1:8080/v1/projects/sapa-test-project/databases/(default)/documents/chats/*' => Http::response([
                'name' => 'projects/sapa-test-project/databases/(default)/documents/chats/'.$this->laporan->id_laporan,
                'fields' => [
                    'report_id' => ['integerValue' => (string) $this->laporan->id_laporan],
                    'participants' => ['arrayValue' => ['values' => [
                        ['stringValue' => 'sapa_pengguna_'.$this->pengguna->id],
                        ['stringValue' => 'sapa_petugas_'.$this->petugas->id],
                    ]]],
                ],
            ], 200),
        ]);

        $token = $this->pengguna->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson("/api/chat/laporan/{$this->laporan->id_laporan}/sesi")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.chat_id', (string) $this->laporan->id_laporan)
            ->assertJsonPath('data.uid', 'sapa_pengguna_'.$this->pengguna->id)
            ->assertJsonPath('data.role', 'pengguna')
            ->assertJsonStructure([
                'data' => [
                    'chat_id',
                    'uid',
                    'role',
                    'custom_token',
                    'firebase' => ['apiKey', 'projectId', 'appId'],
                    'participants',
                ],
            ]);

        $customToken = $response->json('data.custom_token');
        $this->assertNotEmpty($customToken);
        $this->assertCount(3, explode('.', $customToken));
    }

    public function test_send_message_validates_message_cannot_be_empty(): void
    {
        $token = $this->pengguna->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/chat/laporan/{$this->laporan->id_laporan}/pesan", [
                'message' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->withToken($token)
            ->postJson("/api/chat/laporan/{$this->laporan->id_laporan}/pesan", [
                'message' => '   ',
            ])
            ->assertStatus(422);
    }

    public function test_send_and_retrieve_messages_via_backend(): void
    {
        Config::set('firebase.project_id', 'sapa-test-project');
        Config::set('firebase.emulator.firestore_host', '127.0.0.1:8080');
        Config::set('firebase.emulator.auth_host', '127.0.0.1:9099');
        Config::set('firebase.web.api_key', 'AIzaSyFakeApiKeyForTesting');
        Config::set('firebase.web.app_id', '1:123456789:web:abcdef');

        Http::fake([
            // Mock messages subcollection (put specific route first)
            'http://127.0.0.1:8080/v1/projects/sapa-test-project/databases/(default)/documents/chats/'.$this->laporan->id_laporan.'/messages*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response([
                        'name' => 'projects/sapa-test-project/databases/(default)/documents/chats/'.$this->laporan->id_laporan.'/messages/msg-test-123',
                        'fields' => [
                            'sender_id' => ['stringValue' => 'sapa_pengguna_'.$this->pengguna->id],
                            'sender_role' => ['stringValue' => 'pengguna'],
                            'sender_name' => ['stringValue' => $this->pengguna->name],
                            'message' => ['stringValue' => 'Halo Petugas, ini pesan uji.'],
                            'created_at' => ['timestampValue' => '2026-11-20T10:00:00.000000Z'],
                        ],
                    ], 200);
                }
                // GET list messages
                return Http::response([
                    'documents' => [
                        [
                            'name' => 'projects/sapa-test-project/databases/(default)/documents/chats/'.$this->laporan->id_laporan.'/messages/msg-test-123',
                            'fields' => [
                                'sender_id' => ['stringValue' => 'sapa_pengguna_'.$this->pengguna->id],
                                'sender_role' => ['stringValue' => 'pengguna'],
                                'sender_name' => ['stringValue' => $this->pengguna->name],
                                'message' => ['stringValue' => 'Halo Petugas, ini pesan uji.'],
                                'created_at' => ['timestampValue' => '2026-11-20T10:00:00.000000Z'],
                            ],
                        ],
                    ],
                ], 200);
            },
            // Mock parent chat document
            'http://127.0.0.1:8080/v1/projects/sapa-test-project/databases/(default)/documents/chats/'.$this->laporan->id_laporan.'*' => function ($request) {
                if ($request->method() === 'PATCH') {
                    return Http::response(['fields' => []], 200);
                }
                return Http::response([
                    'fields' => [
                        'report_id' => ['integerValue' => (string) $this->laporan->id_laporan],
                        'participants' => ['arrayValue' => ['values' => [
                            ['stringValue' => 'sapa_pengguna_'.$this->pengguna->id],
                            ['stringValue' => 'sapa_petugas_'.$this->petugas->id],
                        ]]],
                    ],
                ], 200);
            },
        ]);

        $token = $this->pengguna->createToken('test-token')->plainTextToken;

        // 1. Kirim pesan
        $sendResponse = $this->withToken($token)
            ->postJson("/api/chat/laporan/{$this->laporan->id_laporan}/pesan", [
                'message' => 'Halo Petugas, ini pesan uji.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.message', 'Halo Petugas, ini pesan uji.')
            ->assertJsonPath('data.id', 'msg-test-123');

        // 2. Ambil riwayat pesan
        $messagesResponse = $this->withToken($token)
            ->getJson("/api/chat/laporan/{$this->laporan->id_laporan}/pesan")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'msg-test-123')
            ->assertJsonPath('data.0.message', 'Halo Petugas, ini pesan uji.');
    }

    public function test_petugas_cannot_access_chat_if_report_is_still_waiting_verification(): void
    {
        $waitingReport = Laporan::create([
            'nomor_laporan' => 'LAP-20261120-WAITING',
            'id_pengguna' => $this->pengguna->id,
            'kategori' => 'Kekerasan',
            'status_penanganan' => 'Menunggu Verifikasi',
            'assigned_petugas_id' => $this->petugas->id,
        ]);

        $petugasToken = $this->petugas->createToken('petugas-token')->plainTextToken;

        // Petugas hanya boleh mengakses status: 'Sedang Diproses', 'Diproses', 'Selesai'
        $this->withToken($petugasToken)
            ->getJson("/api/chat/laporan/{$waitingReport->id_laporan}")
            ->assertNotFound();
    }

    public function test_web_pelapor_can_access_chat_endpoints(): void
    {
        // 1. Mengakses halaman chat web pelapor
        $this->actingAs($this->pengguna)
            ->get("/pelapor/pengaduan/{$this->laporan->id_laporan}/chat")
            ->assertOk()
            ->assertSee('Chat Petugas')
            ->assertSee($this->laporan->nomor_laporan);

        // 2. Pengguna lain tidak bisa mengakses halaman chat web pelapor ini
        $this->actingAs($this->otherPengguna)
            ->get("/pelapor/pengaduan/{$this->laporan->id_laporan}/chat")
            ->assertNotFound();
    }

    public function test_admin_detail_laporan_contains_chat_button_above_penanganan_section(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/laporan/{$this->laporan->id_laporan}")
            ->assertOk()
            ->assertSee('Buka Chat Petugas')
            ->assertSee('Ruang Chat Laporan')
            ->assertSee(route('laporan.chat', $this->laporan->id_laporan));

        // Pastikan tombol chat berada sebelum section "Tindak Lanjut Laporan"
        $content = $response->getContent();
        $chatPos = strpos($content, 'Buka Chat Petugas');
        $tindakLanjutPos = strpos($content, 'Tindak Lanjut Laporan');

        $this->assertNotFalse($chatPos);
        $this->assertNotFalse($tindakLanjutPos);
        $this->assertLessThan($tindakLanjutPos, $chatPos);
    }

    public function test_pelapor_detail_laporan_contains_chat_button(): void
    {
        $this->actingAs($this->pengguna)
            ->get("/pelapor/pengaduan/{$this->laporan->id_laporan}")
            ->assertOk()
            ->assertSee('Chat Petugas')
            ->assertSee(route('pelapor.laporan.chat', $this->laporan->id_laporan));
    }

    public function test_admin_can_access_report_chat_page(): void
    {
        $this->actingAs($this->admin)
            ->get("/laporan/{$this->laporan->id_laporan}/chat")
            ->assertOk()
            ->assertSee('Chat Pelapor')
            ->assertSee($this->laporan->nomor_laporan)
            ->assertSee($this->pengguna->name);
    }

    public function test_admin_can_get_chat_session_in_emulator_mode(): void
    {
        Config::set('firebase.project_id', 'sapa-test-project');
        Config::set('firebase.emulator.firestore_host', '127.0.0.1:8080');
        Config::set('firebase.emulator.auth_host', '127.0.0.1:9099');
        Config::set('firebase.web.api_key', 'AIzaSyFakeApiKeyForTesting');
        Config::set('firebase.web.app_id', '1:123456789:web:abcdef');

        Http::fake([
            'http://127.0.0.1:8080/v1/projects/sapa-test-project/databases/(default)/documents/chats/*' => Http::response([
                'fields' => [
                    'report_id' => ['integerValue' => (string) $this->laporan->id_laporan],
                    'participants' => ['arrayValue' => ['values' => [
                        ['stringValue' => 'sapa_admin_'.$this->admin->id],
                        ['stringValue' => 'sapa_pengguna_'.$this->pengguna->id],
                    ]]],
                ],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson("/laporan/{$this->laporan->id_laporan}/chat/sesi")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.chat_id', (string) $this->laporan->id_laporan)
            ->assertJsonPath('data.uid', 'sapa_admin_'.$this->admin->id)
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_non_admin_cannot_access_admin_chat_page(): void
    {
        // Pengguna biasa mencoba membuka rute admin chat
        $this->actingAs($this->pengguna)
            ->get("/laporan/{$this->laporan->id_laporan}/chat")
            ->assertRedirect(route('pelapor.home'));
    }
}
