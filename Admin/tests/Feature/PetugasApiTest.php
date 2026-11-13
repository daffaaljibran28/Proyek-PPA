<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PetugasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_petugas_can_use_the_petugas_login(): void
    {
        $petugas = $this->createUser('petugas@example.test', 'petugas');
        $inactivePetugas = $this->createUser('inactive@example.test', 'petugas', false);
        $pengguna = $this->createUser('pengguna@example.test');
        $admin = $this->createUser('admin@example.test', 'admin');

        $loginResponse = $this->postJson('/api/petugas/login', [
            'email' => $petugas->email,
            'password' => 'Password123!',
        ])->assertOk()
            ->assertJsonPath('data.role', 'petugas')
            ->assertJsonPath('data.id', $petugas->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'token']])
            ->assertJsonMissingPath('data.nik')
            ->assertJsonMissingPath('data.alamat')
            ->assertJsonMissingPath('data.tanggal_lahir')
            ->assertJsonMissingPath('data.no_id_card')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
        $this->assertSame(
            ['id', 'name', 'email', 'role', 'token'],
            array_keys($loginResponse->json('data'))
        );

        foreach ([$inactivePetugas, $pengguna, $admin] as $account) {
            $this->postJson('/api/petugas/login', [
                'email' => $account->email,
                'password' => 'Password123!',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => $petugas->email,
            'password' => 'Password123!',
        ])->assertUnauthorized();

        $this->postJson('/api/register', [
            'name' => 'Pendaftar Uji',
            'nik' => '3201010101010001',
            'no_hp' => '081234567890',
            'email' => 'public-registration@example.test',
            'password' => 'Password123!',
            'role' => 'petugas',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'pengguna');
    }

    public function test_petugas_can_login_on_multiple_devices_without_revoking_previous_tokens(): void
    {
        $petugas = $this->createUser('multiple-devices@example.test', 'petugas');

        $firstLogin = $this->postJson('/api/petugas/login', [
            'email' => $petugas->email,
            'password' => 'Password123!',
        ])->assertOk();
        $firstToken = $firstLogin->json('data.token');

        $secondLogin = $this->postJson('/api/petugas/login', [
            'email' => $petugas->email,
            'password' => 'Password123!',
        ])->assertOk();
        $secondToken = $secondLogin->json('data.token');

        $this->assertNotSame($firstToken, $secondToken);

        $this->resetAuthGuard();
        $this->withToken($firstToken)->getJson('/api/petugas/laporan')->assertOk();
        $this->resetAuthGuard();
        $this->withToken($secondToken)->getJson('/api/petugas/laporan')->assertOk();
    }

    public function test_mobile_and_petugas_api_routes_are_limited_to_their_roles(): void
    {
        $petugas = $this->createUser('role-petugas@example.test', 'petugas');
        $pengguna = $this->createUser('role-pengguna@example.test');
        $admin = $this->createUser('role-admin@example.test', 'admin');

        $petugasToken = $petugas->createToken('flutter-petugas')->plainTextToken;
        $penggunaToken = $pengguna->createToken('flutter-app')->plainTextToken;
        $adminToken = $admin->createToken('admin-test')->plainTextToken;

        $this->resetAuthGuard();
        $this->withToken($petugasToken)->getJson('/api/petugas/laporan')->assertOk();
        $this->resetAuthGuard();
        $this->withToken($petugasToken)->getJson('/api/riwayat')->assertForbidden();
        $this->resetAuthGuard();
        $this->withToken($penggunaToken)->getJson('/api/petugas/laporan')->assertForbidden();
        $this->resetAuthGuard();
        $this->withToken($penggunaToken)->getJson('/api/riwayat')->assertOk();
        $this->resetAuthGuard();
        $this->withToken($adminToken)->getJson('/api/petugas/laporan')->assertForbidden();
        $this->resetAuthGuard();
        $this->withToken($adminToken)->getJson('/api/riwayat')->assertForbidden();
        $this->resetAuthGuard();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/petugas/laporan')->assertUnauthorized();

        $inactivePetugas = $this->createUser('inactive-route@example.test', 'petugas', false);
        $inactiveToken = $inactivePetugas->createToken('flutter-petugas')->plainTextToken;
        $this->resetAuthGuard();
        $this->withToken($inactiveToken)->getJson('/api/petugas/laporan')->assertForbidden();
    }

    public function test_petugas_only_sees_assigned_reports_and_internal_notes(): void
    {
        $petugas = $this->createUser('assigned@example.test', 'petugas');
        $otherPetugas = $this->createUser('other-assigned@example.test', 'petugas');
        $token = $petugas->createToken('flutter-petugas')->plainTextToken;

        $assigned = $this->createReport($petugas, [
            'status_penanganan' => 'Sedang Diproses',
            'catatan_admin' => 'Catatan internal Admin.',
            'catatan_petugas' => 'Catatan internal Petugas.',
        ]);
        $otherReport = $this->createReport($otherPetugas, [
            'status_penanganan' => 'Diproses',
            'catatan_petugas' => 'Catatan milik Petugas lain.',
        ]);
        $rejectedReport = $this->createReport($petugas, [
            'status_penanganan' => 'Ditolak',
            'catatan_petugas' => 'Catatan untuk laporan yang ditolak.',
        ]);

        $this->resetAuthGuard();
        $this->withToken($token)->getJson('/api/petugas/laporan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_laporan', $assigned->id_laporan);

        $this->resetAuthGuard();
        $this->withToken($token)->getJson("/api/petugas/laporan/{$assigned->id_laporan}")
            ->assertOk()
            ->assertJsonPath('data.catatan_petugas', 'Catatan internal Petugas.')
            ->assertJsonMissingPath('data.catatan_admin')
            ->assertJsonMissingPath('data.id_pengguna')
            ->assertJsonMissingPath('data.assigned_petugas_id')
            ->assertDontSee('Catatan internal Admin.');

        $this->resetAuthGuard();
        $this->withToken($token)->getJson("/api/petugas/laporan/{$otherReport->id_laporan}")
            ->assertNotFound()
            ->assertDontSee('Catatan milik Petugas lain.');

        $this->resetAuthGuard();
        $this->withToken($token)->getJson("/api/petugas/laporan/{$rejectedReport->id_laporan}")
            ->assertNotFound()
            ->assertDontSee('Catatan untuk laporan yang ditolak.');
    }

    public function test_petugas_must_be_assigned_to_open_report_evidence(): void
    {
        Storage::fake('local');
        $petugas = $this->createUser('evidence-owner@example.test', 'petugas');
        $otherPetugas = $this->createUser('evidence-other@example.test', 'petugas');
        $token = $petugas->createToken('flutter-petugas')->plainTextToken;

        $assigned = $this->createReport($petugas);
        $otherAssigned = $this->createReport($otherPetugas);
        $assigned->bukti_lampiran = implode(',', [
            'laporan/'.$assigned->id_laporan.'/bukti.jpg',
            'laporan/'.$otherAssigned->id_laporan.'/bukti.jpg',
            'private/bukti.jpg',
            'laporan/'.$assigned->id_laporan.'/../'.$otherAssigned->id_laporan.'/bukti.jpg',
        ]);
        $assigned->save();
        Storage::disk('local')->put('laporan/'.$assigned->id_laporan.'/bukti.jpg', 'bukti privat');
        Storage::disk('local')->put('laporan/'.$otherAssigned->id_laporan.'/bukti.jpg', 'bukti petugas lain');
        Storage::disk('local')->put('private/bukti.jpg', 'file di luar direktori laporan');

        $this->resetAuthGuard();
        $response = $this->withToken($token)
            ->get("/api/petugas/laporan/{$assigned->id_laporan}/bukti/0")
            ->assertOk();
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->resetAuthGuard();
        $this->withToken($token)
            ->getJson("/api/petugas/laporan/{$otherAssigned->id_laporan}/bukti/0")
            ->assertNotFound();

        $this->resetAuthGuard();
        $this->withToken($token)
            ->getJson("/api/petugas/laporan/{$assigned->id_laporan}/bukti/1")
            ->assertNotFound();

        $this->resetAuthGuard();
        $this->withToken($token)
            ->getJson("/api/petugas/laporan/{$assigned->id_laporan}/bukti/2")
            ->assertNotFound();

        $this->resetAuthGuard();
        $this->withToken($token)
            ->getJson("/api/petugas/laporan/{$assigned->id_laporan}/bukti/3")
            ->assertNotFound();

        $this->resetAuthGuard();
        $this->withToken($token)
            ->getJson("/api/petugas/laporan/{$assigned->id_laporan}/bukti/-1")
            ->assertNotFound();
    }

    public function test_petugas_appends_internal_notes_and_records_only_public_history(): void
    {
        $petugas = $this->createUser('notes-petugas@example.test', 'petugas');
        $pelapor = $this->createUser('notes-pelapor@example.test');
        $attacker = $this->createUser('notes-attacker@example.test', 'petugas');
        $token = $petugas->createToken('flutter-petugas')->plainTextToken;
        $report = $this->createReport($petugas, [
            'id_pengguna' => $pelapor->id,
            'status_penanganan' => 'Sedang Diproses',
            'catatan_admin' => 'Catatan Admin tetap internal.',
            'catatan_petugas' => 'Catatan lama yang harus tetap ada.',
            'riwayat_status' => [[
                'status' => 'Sedang Diproses',
                'pesan' => null,
                'pada' => now()->toIso8601String(),
                'oleh' => 'Admin',
                'catatan_internal' => 'Field ini tidak boleh ditampilkan ke pengguna.',
            ]],
        ]);

        $this->resetAuthGuard();
        $this->withToken($token)->patchJson("/api/petugas/laporan/{$report->id_laporan}/penanganan", [
            'status_penanganan' => 'Diproses',
            'catatan_petugas' => 'Catatan lanjutan internal.',
            'pesan_pelapor' => 'Petugas mulai menangani laporan Anda.',
            'assigned_petugas_id' => $attacker->id,
            'id_pengguna' => $attacker->id,
            'catatan_admin' => 'Catatan Admin palsu dari Petugas.',
            'nomor_laporan' => 'NOMOR-MANIPULASI',
        ])->assertOk()
            ->assertJsonPath('data.status_penanganan', 'Diproses')
            ->assertJsonMissingPath('data.catatan_admin')
            ->assertJsonMissingPath('data.assigned_petugas_id');

        $report->refresh();
        $this->assertStringContainsString('Catatan lama yang harus tetap ada.', $report->catatan_petugas);
        $this->assertStringContainsString('Catatan lanjutan internal.', $report->catatan_petugas);
        $this->assertSame($petugas->id, $report->assigned_petugas_id);
        $this->assertSame($pelapor->id, $report->id_pengguna);
        $this->assertSame('Catatan Admin tetap internal.', $report->catatan_admin);
        $this->assertNotSame('NOMOR-MANIPULASI', $report->nomor_laporan);
        $this->assertSame('Petugas mulai menangani laporan Anda.', $report->pesan_pelapor);
        $this->assertSame('Diproses', $report->riwayat_status[1]['status']);
        $this->assertSame('Petugas mulai menangani laporan Anda.', $report->riwayat_status[1]['pesan']);
        $this->assertArrayNotHasKey('catatan_petugas', $report->riwayat_status[1]);

        $this->resetAuthGuard();
        $response = $this->withToken($pelapor->createToken('flutter-app')->plainTextToken)
            ->getJson('/api/riwayat')
            ->assertOk()
            ->assertJsonPath('data.0.pesan_pelapor', 'Petugas mulai menangani laporan Anda.')
            ->assertJsonMissingPath('data.0.catatan_petugas')
            ->assertJsonMissingPath('data.0.catatan_admin')
            ->assertJsonMissingPath('data.0.assigned_petugas_id')
            ->assertJsonMissingPath('data.0.riwayat_status.0.catatan_internal');
        $response->assertDontSee('Catatan lama yang harus tetap ada.');
        $response->assertDontSee('Catatan lanjutan internal.');
    }

    public function test_petugas_can_complete_an_assigned_report_but_cannot_skip_status_steps(): void
    {
        $petugas = $this->createUser('transition-petugas@example.test', 'petugas');
        $otherPetugas = $this->createUser('transition-other-petugas@example.test', 'petugas');
        $token = $petugas->createToken('flutter-petugas')->plainTextToken;
        $waiting = $this->createReport($petugas, ['status_penanganan' => 'Sedang Diproses']);
        $processing = $this->createReport($petugas, ['status_penanganan' => 'Diproses']);
        $otherReport = $this->createReport($otherPetugas, [
            'status_penanganan' => 'Sedang Diproses',
            'catatan_petugas' => 'Catatan Petugas lain.',
        ]);

        $this->resetAuthGuard();
        $this->withToken($token)->patchJson("/api/petugas/laporan/{$waiting->id_laporan}/penanganan", [
            'status_penanganan' => 'Selesai',
        ])->assertUnprocessable();
        $this->resetAuthGuard();
        $this->withToken($token)->patchJson("/api/petugas/laporan/{$waiting->id_laporan}/penanganan", [
            'status_penanganan' => 'Ditolak',
        ])->assertUnprocessable();

        $this->resetAuthGuard();
        $this->withToken($token)->patchJson("/api/petugas/laporan/{$processing->id_laporan}/penanganan", [
            'status_penanganan' => 'Selesai',
        ])->assertOk()
            ->assertJsonPath('data.status_penanganan', 'Selesai');

        $this->resetAuthGuard();
        $this->withToken($token)->patchJson("/api/petugas/laporan/{$processing->id_laporan}/penanganan", [
            'catatan_petugas' => 'Tidak boleh menambah catatan setelah selesai.',
        ])->assertUnprocessable();

        $this->resetAuthGuard();
        $this->withToken($token)->patchJson("/api/petugas/laporan/{$otherReport->id_laporan}/penanganan", [
            'status_penanganan' => 'Diproses',
            'catatan_petugas' => 'Pengambilalihan tidak diizinkan.',
            'assigned_petugas_id' => $petugas->id,
        ])->assertNotFound();

        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $otherReport->id_laporan,
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => $otherPetugas->id,
            'catatan_petugas' => 'Catatan Petugas lain.',
        ]);
    }

    private function createUser(string $email, string $role = 'pengguna', bool $active = true): User
    {
        return User::create([
            'name' => 'Akun Uji',
            'email' => $email,
            'no_id_card' => $role === 'petugas' ? 'ID-'.$email : null,
            'role' => $role,
            'is_active' => $active,
            'password' => Hash::make('Password123!'),
        ]);
    }

    private function createReport(User $petugas, array $attributes = []): Laporan
    {
        $idPengguna = $attributes['id_pengguna']
            ?? $this->createUser('report-owner-'.uniqid().'@example.test')->id;

        return Laporan::create($attributes + [
            'nomor_laporan' => 'LAP-'.uniqid(),
            'id_pengguna' => $idPengguna,
            'kategori' => 'Kekerasan',
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => $petugas->id,
        ]);
    }

    private function resetAuthGuard(): void
    {
        Auth::forgetGuards();
    }
}
