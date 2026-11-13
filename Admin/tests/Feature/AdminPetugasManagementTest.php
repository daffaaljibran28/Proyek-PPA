<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPetugasManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_only_petugas_accounts(): void
    {
        $admin = $this->createUser('management-admin@example.test', 'admin');
        $petugas = $this->createUser('listed-petugas@example.test', 'petugas');
        $pengguna = $this->createUser('hidden-pengguna@example.test');

        $this->actingAs($admin)
            ->get(route('admin.petugas.index'))
            ->assertOk()
            ->assertSee($petugas->name)
            ->assertSee($petugas->email)
            ->assertSee($petugas->no_id_card)
            ->assertDontSee($pengguna->email);
    }

    public function test_admin_can_create_an_active_petugas_with_a_hashed_password(): void
    {
        $admin = $this->createUser('create-admin@example.test', 'admin');
        $password = 'PetugasPassword123!';

        $this->actingAs($admin)
            ->post(route('admin.petugas.store'), [
                'name' => 'Petugas Baru',
                'email' => 'new-petugas@example.test',
                'no_id_card' => 'CARD-NEW-001',
                'password' => $password,
                'password_confirmation' => $password,
                'role' => 'admin',
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.petugas.index'))
            ->assertSessionHas('success');

        $petugas = User::where('email', 'new-petugas@example.test')->firstOrFail();
        $this->assertSame('petugas', $petugas->role);
        $this->assertTrue($petugas->is_active);
        $this->assertSame('CARD-NEW-001', $petugas->no_id_card);
        $this->assertTrue(Hash::check($password, $petugas->password));
        $this->assertNotSame($password, $petugas->password);
    }

    public function test_no_id_card_is_required_and_unique_when_creating_petugas(): void
    {
        $admin = $this->createUser('create-validation-admin@example.test', 'admin');
        $existing = $this->createUser('existing-card@example.test', 'petugas');
        $originalCount = User::count();

        foreach ([
            [
                'name' => 'Tanpa Kartu',
                'email' => 'missing-card@example.test',
                'no_id_card' => '',
            ],
            [
                'name' => 'Kartu Duplikat',
                'email' => 'duplicate-card@example.test',
                'no_id_card' => $existing->no_id_card,
            ],
        ] as $case) {
            $response = $this->actingAs($admin)
                ->from(route('admin.petugas.create'))
                ->post(route('admin.petugas.store'), $case + [
                    'password' => 'PetugasPassword123!',
                    'password_confirmation' => 'PetugasPassword123!',
                ]);

            $response->assertRedirect(route('admin.petugas.create'))
                ->assertSessionHasErrors('no_id_card');
        }

        $this->assertSame($originalCount, User::count());
        $this->assertDatabaseMissing('user', ['email' => 'missing-card@example.test']);
        $this->assertDatabaseMissing('user', ['email' => 'duplicate-card@example.test']);
    }

    public function test_admin_can_edit_petugas_using_its_own_card_without_changing_password_or_role(): void
    {
        $admin = $this->createUser('edit-admin@example.test', 'admin');
        $petugas = $this->createUser('edit-petugas@example.test', 'petugas');
        $originalPassword = $petugas->password;

        $this->actingAs($admin)
            ->put(route('admin.petugas.update', $petugas->id), [
                'name' => 'Nama Diperbarui',
                'email' => 'edit-petugas-updated@example.test',
                'no_id_card' => $petugas->no_id_card,
                'password' => '',
                'password_confirmation' => '',
                'role' => 'admin',
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.petugas.index'))
            ->assertSessionHas('success');

        $petugas->refresh();
        $this->assertSame('Nama Diperbarui', $petugas->name);
        $this->assertSame('edit-petugas-updated@example.test', $petugas->email);
        $this->assertSame('petugas', $petugas->role);
        $this->assertTrue($petugas->is_active);
        $this->assertSame($originalPassword, $petugas->password);
    }

    public function test_admin_can_update_petugas_password_and_it_is_hashed(): void
    {
        $admin = $this->createUser('password-admin@example.test', 'admin');
        $petugas = $this->createUser('password-petugas@example.test', 'petugas');
        $newPassword = 'NewPetugasPassword123!';

        $this->actingAs($admin)
            ->put(route('admin.petugas.update', $petugas->id), [
                'name' => $petugas->name,
                'email' => $petugas->email,
                'no_id_card' => $petugas->no_id_card,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertRedirect(route('admin.petugas.index'))
            ->assertSessionHas('success');

        $petugas->refresh();
        $this->assertTrue(Hash::check($newPassword, $petugas->password));
        $this->assertNotSame($newPassword, $petugas->password);
        $this->assertSame('petugas', $petugas->role);
    }

    public function test_invalid_petugas_update_does_not_change_account_data(): void
    {
        $admin = $this->createUser('invalid-update-admin@example.test', 'admin');
        $petugas = $this->createUser('invalid-update-petugas@example.test', 'petugas');
        $otherPetugas = $this->createUser('other-update-petugas@example.test', 'petugas');
        $original = $petugas->only(['name', 'email', 'no_id_card', 'password', 'role', 'is_active']);

        foreach (['', $otherPetugas->no_id_card] as $noIdCard) {
            $this->actingAs($admin)
                ->from(route('admin.petugas.edit', $petugas->id))
                ->put(route('admin.petugas.update', $petugas->id), [
                    'name' => 'Nama Tidak Boleh Tersimpan',
                    'email' => 'invalid-update-petugas@example.test',
                    'no_id_card' => $noIdCard,
                    'password' => 'AnotherPassword123!',
                    'password_confirmation' => 'AnotherPassword123!',
                    'role' => 'admin',
                    'is_active' => false,
                ])
                ->assertRedirect(route('admin.petugas.edit', $petugas->id))
                ->assertSessionHasErrors('no_id_card');

            $this->assertSame($original, $petugas->fresh()->only([
                'name', 'email', 'no_id_card', 'password', 'role', 'is_active',
            ]));
        }
    }

    public function test_pengguna_and_petugas_cannot_access_petugas_management_pages(): void
    {
        $pengguna = $this->createUser('blocked-pengguna@example.test');
        $petugas = $this->createUser('blocked-petugas@example.test', 'petugas');

        $this->actingAs($pengguna)
            ->get(route('admin.petugas.index'))
            ->assertRedirect(route('pelapor.home'));
        $this->actingAs($pengguna)
            ->get(route('admin.petugas.create'))
            ->assertRedirect(route('pelapor.home'));

        $this->actingAs($petugas)
            ->get(route('admin.petugas.index'))
            ->assertForbidden();
        $this->actingAs($petugas)
            ->get(route('admin.petugas.create'))
            ->assertForbidden();
    }

    private function createUser(string $email, string $role = 'pengguna'): User
    {
        return User::create([
            'name' => 'Akun Tes',
            'email' => $email,
            'no_id_card' => $role === 'petugas' ? 'CARD-'.$email : null,
            'role' => $role,
            'is_active' => true,
            'password' => Hash::make('Password123!'),
        ]);
    }
}
