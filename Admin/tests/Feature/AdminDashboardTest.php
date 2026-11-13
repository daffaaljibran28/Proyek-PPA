<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_statuses_separately_within_the_created_at_date_filter(): void
    {
        $admin = $this->createUser('dashboard-admin@example.test', 'admin');
        $owner = $this->createUser('dashboard-owner@example.test');
        $date = now()->toDateString();
        $this->createReport($owner, 'Menunggu Verifikasi', Carbon::parse($date)->startOfDay());
        $this->createReport($owner, 'Sedang Diproses', Carbon::parse($date)->setTime(12, 0));
        $this->createReport($owner, 'Diproses', Carbon::parse($date)->endOfDay());
        $this->createReport($owner, 'Selesai', Carbon::parse($date)->setTime(15, 0));
        $this->createReport($owner, 'Ditolak', Carbon::parse($date)->setTime(18, 0));
        $this->createReport($owner, 'Sedang Diproses', Carbon::parse($date)->subSecond());
        $this->createReport($owner, 'Diproses', Carbon::parse($date)->addDay());

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'filter' => 'hari',
            'tanggal' => $date,
        ]));

        $response->assertOk()
            ->assertViewHas('totalLaporan', 5)
            ->assertViewHas('menungguVerifikasi', 1)
            ->assertViewHas('terverifikasi', 1)
            ->assertViewHas('diproses', 1)
            ->assertViewHas('selesai', 1)
            ->assertViewHas('ditolak', 1)
            ->assertViewHas('laporanTerbaru', function ($laporanTerbaru) {
                return $laporanTerbaru->count() === 5;
            })
            ->assertSee('Terverifikasi')
            ->assertSee('Status database: Sedang Diproses')
            ->assertSee('Diproses');
    }

    public function test_dashboard_month_filter_counts_only_reports_created_in_that_month(): void
    {
        $admin = $this->createUser('dashboard-month-admin@example.test', 'admin');
        $owner = $this->createUser('dashboard-month-owner@example.test');
        $month = now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('!Y-m', $month);
        $monthEnd = $monthStart->copy()->addMonth()->subMicrosecond();
        $this->createReport($owner, 'Menunggu Verifikasi', $monthStart);
        $this->createReport($owner, 'Sedang Diproses', $monthEnd);
        $this->createReport($owner, 'Selesai', $monthStart->copy()->subSecond());
        $this->createReport($owner, 'Ditolak', $monthStart->copy()->addMonth());

        $this->actingAs($admin)
            ->get(route('dashboard', [
                'filter' => 'bulan',
                'bulan' => $month,
            ]))
            ->assertOk()
            ->assertViewHas('totalLaporan', 2)
            ->assertViewHas('menungguVerifikasi', 1)
            ->assertViewHas('terverifikasi', 1)
            ->assertViewHas('diproses', 0)
            ->assertViewHas('selesai', 0)
            ->assertViewHas('ditolak', 0)
            ->assertViewHas('laporanTerbaru', function ($laporanTerbaru) {
                return $laporanTerbaru->count() === 2;
            });
    }

    public function test_dashboard_rejects_invalid_date_and_month_filters(): void
    {
        $admin = $this->createUser('dashboard-invalid-admin@example.test', 'admin');
        $this->actingAs($admin)
            ->get(route('dashboard', ['filter' => 'hari', 'tanggal' => '2026-02-30']))
            ->assertSessionHasErrors('tanggal');

        $this->actingAs($admin)
            ->get(route('dashboard', ['filter' => 'bulan', 'bulan' => '2026-13']))
            ->assertSessionHasErrors('bulan');

        $this->actingAs($admin)
            ->get(route('dashboard', ['filter' => 'bulan', 'bulan' => '2026-00']))
            ->assertSessionHasErrors('bulan');

        $this->actingAs($admin)
            ->get(route('dashboard', ['filter' => 'bulan', 'bulan' => '2026-1']))
            ->assertSessionHasErrors('bulan');

        $this->actingAs($admin)
            ->get(route('dashboard', ['filter' => 'bulan', 'bulan' => 'November-2026']))
            ->assertSessionHasErrors('bulan');

        $this->actingAs($admin)
            ->get(route('dashboard', ['filter' => 'minggu']))
            ->assertSessionHasErrors('filter');
    }

    private function createUser(string $email, string $role = 'pengguna'): User
    {
        return User::create([
            'name' => 'Akun Dashboard',
            'email' => $email,
            'role' => $role,
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);
    }

    private function createReport(User $owner, string $status, Carbon $createdAt): Laporan
    {
        return Laporan::create([
            'nomor_laporan' => 'DASH-'.uniqid(),
            'id_pengguna' => $owner->id,
            'kategori' => 'Kekerasan',
            'status_penanganan' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
