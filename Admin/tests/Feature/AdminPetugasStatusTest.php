<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPetugasStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_deactivate_petugas_with_active_assigned_reports(): void
    {
        $admin = $this->createUser('status-admin@example.test', 'admin');
        $petugas = $this->createUser('active-petugas@example.test', 'petugas');
        $token = $petugas->createToken('flutter-petugas');
        $reportSedangDiproses = $this->createReport($petugas, 'Sedang Diproses');
        $reportDiproses = $this->createReport($petugas, 'Diproses');
        $historySedangDiproses = $reportSedangDiproses->riwayat_status;
        $historyDiproses = $reportDiproses->riwayat_status;

        $response = $this->actingAs($admin)
            ->patch(route('admin.petugas.toggle-status', $petugas->id));

        $response->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('user', ['id' => $petugas->id, 'is_active' => true]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $reportSedangDiproses->id_laporan,
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => $petugas->id,
        ]);
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $reportDiproses->id_laporan,
            'status_penanganan' => 'Diproses',
            'assigned_petugas_id' => $petugas->id,
        ]);
        $this->assertSame($historySedangDiproses, $reportSedangDiproses->fresh()->riwayat_status);
        $this->assertSame($historyDiproses, $reportDiproses->fresh()->riwayat_status);
    }

    public function test_admin_can_deactivate_petugas_without_active_reports_and_only_revoke_their_tokens(): void
    {
        $admin = $this->createUser('deactivation-admin@example.test', 'admin');
        $pengguna = $this->createUser('deactivation-pengguna@example.test');
        $petugas = $this->createUser('deactivation-petugas@example.test', 'petugas');
        $adminToken = $admin->createToken('admin-test');
        $penggunaToken = $pengguna->createToken('flutter-app');
        $petugasToken = $petugas->createToken('flutter-petugas');
        $completed = $this->createReport($petugas, 'Selesai');
        $historyBefore = $completed->riwayat_status;

        $this->actingAs($admin)
            ->patch(route('admin.petugas.toggle-status', $petugas->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user', ['id' => $petugas->id, 'is_active' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $petugasToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $adminToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $penggunaToken->accessToken->id]);
        Auth::forgetGuards();
        $this->withToken($petugasToken->plainTextToken)
            ->getJson('/api/petugas/laporan')
            ->assertUnauthorized();
        $this->assertSame($historyBefore, $completed->fresh()->riwayat_status);
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $completed->id_laporan,
            'status_penanganan' => 'Selesai',
            'assigned_petugas_id' => $petugas->id,
        ]);
    }

    public function test_admin_can_reactivate_only_petugas_without_changing_assignments(): void
    {
        $admin = $this->createUser('reactivation-admin@example.test', 'admin');
        $petugas = $this->createUser('inactive-petugas@example.test', 'petugas', false);
        $token = $petugas->createToken('flutter-petugas');
        $report = $this->createReport($petugas, 'Diproses');

        $this->actingAs($admin)
            ->patch(route('admin.petugas.toggle-status', $petugas->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user', ['id' => $petugas->id, 'is_active' => true]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $report->id_laporan,
            'status_penanganan' => 'Diproses',
            'assigned_petugas_id' => $petugas->id,
        ]);
    }

    public function test_status_route_cannot_change_non_petugas_accounts(): void
    {
        $admin = $this->createUser('scope-admin@example.test', 'admin');
        $pengguna = $this->createUser('scope-pengguna@example.test');
        $token = $pengguna->createToken('flutter-app');

        $this->actingAs($admin)
            ->patch(route('admin.petugas.toggle-status', $pengguna->id))
            ->assertNotFound();

        $this->assertDatabaseHas('user', ['id' => $pengguna->id, 'is_active' => true]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_only_admin_can_access_petugas_status_route(): void
    {
        $pengguna = $this->createUser('unauthorized-pengguna@example.test');
        $petugas = $this->createUser('unauthorized-petugas@example.test', 'petugas');
        $petugasToken = $petugas->createToken('flutter-petugas');

        $this->actingAs($pengguna)
            ->patch(route('admin.petugas.toggle-status', $petugas->id))
            ->assertRedirect(route('pelapor.home'));

        Auth::forgetGuards();
        $this->actingAs($petugas)
            ->patch(route('admin.petugas.toggle-status', $petugas->id))
            ->assertForbidden();

        $this->assertDatabaseHas('user', ['id' => $petugas->id, 'is_active' => true]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $petugasToken->accessToken->id]);
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

    private function createReport(User $petugas, string $status): Laporan
    {
        $pelapor = $this->createUser('report-owner-'.uniqid().'@example.test');

        return Laporan::create([
            'nomor_laporan' => 'LAP-'.uniqid(),
            'id_pengguna' => $pelapor->id,
            'kategori' => 'Kekerasan',
            'status_penanganan' => $status,
            'assigned_petugas_id' => $petugas->id,
            'riwayat_status' => [[
                'status' => $status,
                'pesan' => 'Pesan yang tetap dipertahankan.',
                'pada' => now()->toIso8601String(),
                'oleh' => 'Admin',
            ]],
        ]);
    }
}
