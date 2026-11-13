<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLaporanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_verification_assigns_active_petugas_and_appends_status_history(): void
    {
        $admin = $this->createUser('workflow-admin@example.test', 'admin');
        $pelapor = $this->createUser('workflow-owner@example.test');
        $petugas = $this->createUser('workflow-petugas@example.test', 'petugas');
        $history = $this->initialHistory();
        $report = $this->createReport($pelapor, [
            'riwayat_status' => $history,
            'catatan_admin' => 'Catatan lama.',
        ]);

        $response = $this->actingAs($admin)->put(route('laporan.update-status', $report->id_laporan), [
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => $petugas->id,
            'pesan_pelapor' => 'Laporan telah diverifikasi.',
        ]);

        $response->assertRedirect('/laporan')
            ->assertSessionHas('success');
        $updated = $report->fresh();
        $this->assertSame('Sedang Diproses', $updated->status_penanganan);
        $this->assertSame($petugas->id, $updated->assigned_petugas_id);
        $this->assertSame('Catatan lama.', $updated->catatan_admin);
        $this->assertCount(2, $updated->riwayat_status);
        $this->assertSame($history[0], $updated->riwayat_status[0]);
        $this->assertSame([
            'status' => 'Sedang Diproses',
            'pesan' => 'Laporan telah diverifikasi.',
            'oleh' => $admin->name,
        ], array_intersect_key($updated->riwayat_status[1], array_flip(['status', 'pesan', 'oleh'])));
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $report->id_laporan,
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => $petugas->id,
            'catatan_admin' => 'Catatan lama.',
            'pesan_pelapor' => 'Laporan telah diverifikasi.',
        ]);
    }

    public function test_verification_requires_an_active_petugas_and_leaves_report_unchanged_on_failure(): void
    {
        $admin = $this->createUser('workflow-validation-admin@example.test', 'admin');
        $inactivePetugas = $this->createUser('workflow-inactive-petugas@example.test', 'petugas', false);
        $nonPetugas = $this->createUser('workflow-non-petugas@example.test');

        foreach ([
            ['email' => null, 'id' => null],
            ['email' => $inactivePetugas->email, 'id' => $inactivePetugas->id],
            ['email' => $nonPetugas->email, 'id' => $nonPetugas->id],
        ] as $case) {
            $pelapor = $this->createUser('workflow-failed-owner-'.uniqid().'@example.test');
            $history = $this->initialHistory();
            $report = $this->createReport($pelapor, ['riwayat_status' => $history]);
            $payload = ['status_penanganan' => 'Sedang Diproses'];
            if ($case['id'] !== null) {
                $payload['assigned_petugas_id'] = $case['id'];
            }

            $response = $this->actingAs($admin)
                ->from(route('laporan.show', $report->id_laporan))
                ->put(route('laporan.update-status', $report->id_laporan), $payload);

            $response->assertRedirect(route('laporan.show', $report->id_laporan))
                ->assertSessionHasErrors('assigned_petugas_id');
            $this->assertDatabaseHas('laporan', [
                'id_laporan' => $report->id_laporan,
                'status_penanganan' => 'Menunggu Verifikasi',
                'assigned_petugas_id' => null,
            ]);
            $this->assertSame($history, $report->fresh()->riwayat_status);
        }
    }

    public function test_rejection_does_not_require_petugas_or_remove_existing_assignment(): void
    {
        $admin = $this->createUser('workflow-rejection-admin@example.test', 'admin');
        $pelapor = $this->createUser('workflow-rejection-owner@example.test');
        $petugas = $this->createUser('workflow-rejection-petugas@example.test', 'petugas');
        $history = $this->initialHistory();
        $report = $this->createReport($pelapor, [
            'assigned_petugas_id' => $petugas->id,
            'riwayat_status' => $history,
        ]);

        $response = $this->actingAs($admin)->put(route('laporan.update-status', $report->id_laporan), [
            'status_penanganan' => 'Ditolak',
            'pesan_pelapor' => 'Laporan tidak dapat diverifikasi.',
        ]);

        $response->assertRedirect('/laporan')
            ->assertSessionHas('success');
        $updated = $report->fresh();
        $this->assertSame('Ditolak', $updated->status_penanganan);
        $this->assertSame($petugas->id, $updated->assigned_petugas_id);
        $this->assertCount(2, $updated->riwayat_status);
        $this->assertSame($history[0], $updated->riwayat_status[0]);
        $this->assertSame('Ditolak', $updated->riwayat_status[1]['status']);
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $report->id_laporan,
            'status_penanganan' => 'Ditolak',
            'assigned_petugas_id' => $petugas->id,
            'pesan_pelapor' => 'Laporan tidak dapat diverifikasi.',
        ]);
    }

    public function test_admin_can_assign_an_unassigned_legacy_report_without_changing_status_or_history(): void
    {
        $admin = $this->createUser('workflow-legacy-admin@example.test', 'admin');
        $pelapor = $this->createUser('workflow-legacy-owner@example.test');
        $petugas = $this->createUser('workflow-legacy-petugas@example.test', 'petugas');
        $history = $this->initialHistory();
        $report = $this->createReport($pelapor, [
            'status_penanganan' => 'Sedang Diproses',
            'riwayat_status' => $history,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('laporan.assign-petugas', $report->id_laporan),
            ['assigned_petugas_id' => $petugas->id]
        );

        $response->assertRedirect(route('laporan.show', $report->id_laporan))
            ->assertSessionHas('success');
        $updated = $report->fresh();
        $this->assertSame('Sedang Diproses', $updated->status_penanganan);
        $this->assertSame($petugas->id, $updated->assigned_petugas_id);
        $this->assertSame($history, $updated->riwayat_status);
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $report->id_laporan,
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => $petugas->id,
        ]);
        $this->get(route('laporan.show', $report->id_laporan))
            ->assertOk()
            ->assertSee($petugas->name);
        $this->get(route('laporan.index'))
            ->assertOk()
            ->assertSee($petugas->name);
    }

    public function test_legacy_assignment_rejects_wrong_status_existing_assignment_and_inactive_petugas(): void
    {
        $admin = $this->createUser('workflow-legacy-reject-admin@example.test', 'admin');
        $pelapor = $this->createUser('workflow-legacy-reject-owner@example.test');
        $petugas = $this->createUser('workflow-legacy-reject-petugas@example.test', 'petugas');
        $inactivePetugas = $this->createUser('workflow-legacy-reject-inactive@example.test', 'petugas', false);
        $waiting = $this->createReport($pelapor);
        $alreadyAssigned = $this->createReport($pelapor, [
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => $petugas->id,
        ]);
        $unassigned = $this->createReport($pelapor, ['status_penanganan' => 'Sedang Diproses']);
        $historyWaiting = $waiting->riwayat_status;
        $historyAssigned = $alreadyAssigned->riwayat_status;
        $historyUnassigned = $unassigned->riwayat_status;

        foreach ([
            [$waiting, $petugas->id, 'error'],
            [$alreadyAssigned, $inactivePetugas->id, 'validation'],
            [$alreadyAssigned, $petugas->id, 'error'],
        ] as [$report, $petugasId, $failureType]) {
            $response = $this->actingAs($admin)
                ->from(route('laporan.show', $report->id_laporan))
                ->patch(route('laporan.assign-petugas', $report->id_laporan), [
                    'assigned_petugas_id' => $petugasId,
                ]);
            $response->assertRedirect(route('laporan.show', $report->id_laporan));
            if ($failureType === 'validation') {
                $response->assertSessionHasErrors('assigned_petugas_id');
            } else {
                $response->assertSessionHas('error');
            }
        }

        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $waiting->id_laporan,
            'status_penanganan' => 'Menunggu Verifikasi',
            'assigned_petugas_id' => null,
        ]);
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $alreadyAssigned->id_laporan,
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => $petugas->id,
        ]);
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $unassigned->id_laporan,
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => null,
        ]);
        $this->assertSame($historyWaiting, $waiting->fresh()->riwayat_status);
        $this->assertSame($historyAssigned, $alreadyAssigned->fresh()->riwayat_status);
        $this->assertSame($historyUnassigned, $unassigned->fresh()->riwayat_status);
    }

    public function test_admin_cannot_assign_legacy_report_to_inactive_or_non_petugas_accounts(): void
    {
        $admin = $this->createUser('workflow-invalid-target-admin@example.test', 'admin');
        $pelapor = $this->createUser('workflow-invalid-target-owner@example.test');
        $inactivePetugas = $this->createUser('workflow-invalid-target-inactive@example.test', 'petugas', false);
        $pengguna = $this->createUser('workflow-invalid-target-user@example.test');
        $otherAdmin = $this->createUser('workflow-invalid-target-other-admin@example.test', 'admin');
        $targets = [$inactivePetugas, $pengguna, $otherAdmin];

        foreach ($targets as $target) {
            $history = $this->initialHistory();
            $report = $this->createReport($pelapor, [
                'status_penanganan' => 'Sedang Diproses',
                'riwayat_status' => $history,
            ]);

            $this->actingAs($admin)
                ->from(route('laporan.show', $report->id_laporan))
                ->patch(route('laporan.assign-petugas', $report->id_laporan), [
                    'assigned_petugas_id' => $target->id,
                    'status_penanganan' => 'Selesai',
                    'id_pengguna' => $target->id,
                ])
                ->assertRedirect(route('laporan.show', $report->id_laporan))
                ->assertSessionHasErrors('assigned_petugas_id');

            $this->assertDatabaseHas('laporan', [
                'id_laporan' => $report->id_laporan,
                'status_penanganan' => 'Sedang Diproses',
                'assigned_petugas_id' => null,
                'id_pengguna' => $pelapor->id,
            ]);
            $this->assertSame($history, $report->fresh()->riwayat_status);
        }
    }

    public function test_admin_cannot_set_petugas_owned_statuses_and_report_data_remains_unchanged(): void
    {
        $admin = $this->createUser('workflow-transition-admin@example.test', 'admin');
        $pelapor = $this->createUser('workflow-transition-owner@example.test');
        $petugas = $this->createUser('workflow-transition-petugas@example.test', 'petugas');

        foreach (['Diproses', 'Selesai'] as $status) {
            $history = $this->initialHistory();
            $report = $this->createReport($pelapor, [
                'assigned_petugas_id' => $petugas->id,
                'riwayat_status' => $history,
            ]);
            $response = $this->actingAs($admin)
                ->from(route('laporan.show', $report->id_laporan))
                ->put(route('laporan.update-status', $report->id_laporan), [
                    'status_penanganan' => $status,
                    'pesan_pelapor' => 'Pesan tidak boleh ikut tersimpan.',
                ]);

            $response->assertRedirect(route('laporan.show', $report->id_laporan))
                ->assertSessionHasErrors('status_penanganan');
            $this->assertDatabaseHas('laporan', [
                'id_laporan' => $report->id_laporan,
                'status_penanganan' => 'Menunggu Verifikasi',
                'assigned_petugas_id' => $petugas->id,
                'pesan_pelapor' => null,
            ]);
            $this->assertSame($history, $report->fresh()->riwayat_status);
        }
    }

    public function test_non_admin_cannot_assign_legacy_report(): void
    {
        $pengguna = $this->createUser('workflow-unauthorized-owner@example.test');
        $petugas = $this->createUser('workflow-unauthorized-petugas@example.test', 'petugas');
        $report = $this->createReport($pengguna, ['status_penanganan' => 'Sedang Diproses']);
        $history = $report->riwayat_status;

        $response = $this->actingAs($pengguna)->patch(
            route('laporan.assign-petugas', $report->id_laporan),
            ['assigned_petugas_id' => $petugas->id]
        );

        $response->assertRedirect(route('pelapor.home'));
        $this->assertDatabaseHas('laporan', [
            'id_laporan' => $report->id_laporan,
            'status_penanganan' => 'Sedang Diproses',
            'assigned_petugas_id' => null,
        ]);
        $this->assertSame($history, $report->fresh()->riwayat_status);
    }

    private function createUser(string $email, string $role = 'pengguna', bool $active = true): User
    {
        return User::create([
            'name' => 'Akun Workflow',
            'email' => $email,
            'no_id_card' => $role === 'petugas' ? 'ID-'.$email : null,
            'role' => $role,
            'is_active' => $active,
            'password' => Hash::make('Password123!'),
        ]);
    }

    private function createReport(User $pelapor, array $attributes = []): Laporan
    {
        return Laporan::create($attributes + [
            'nomor_laporan' => 'LAP-'.uniqid(),
            'id_pengguna' => $pelapor->id,
            'kategori' => 'Kekerasan',
            'status_penanganan' => 'Menunggu Verifikasi',
        ]);
    }

    private function initialHistory(): array
    {
        return [[
            'status' => 'Menunggu Verifikasi',
            'pesan' => 'Laporan diterima.',
            'pada' => now()->subMinute()->toIso8601String(),
            'oleh' => 'Sistem',
            'legacy_field' => 'Tetap dipertahankan.',
        ]];
    }
}
