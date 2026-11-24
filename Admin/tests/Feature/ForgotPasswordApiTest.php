<?php

namespace Tests\Feature;

use App\Mail\SendOtpMail;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_pengguna_and_petugas_can_request_an_otp_but_admin_and_inactive_accounts_cannot(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        $pengguna = $this->createUser('pengguna-otp@example.test');
        $petugas = $this->createUser('petugas-otp@example.test', 'petugas');
        $admin = $this->createUser('admin-otp@example.test', 'admin');
        $inactive = $this->createUser('inactive-otp@example.test', 'pengguna', false);

        foreach ([$pengguna, $petugas] as $user) {
            $this->postJson('/api/forgot-password/send-otp', ['email' => $user->email])
                ->assertOk()
                ->assertJsonStructure(['status', 'message', 'data']);
            $this->assertDatabaseHas('password_otps', ['email' => $user->email]);
            Mail::assertSent(SendOtpMail::class);
        }

        foreach ([$admin, $inactive] as $user) {
            $this->postJson('/api/forgot-password/send-otp', ['email' => $user->email])
                ->assertForbidden()
                ->assertJsonStructure(['status', 'message', 'data']);
            $this->assertDatabaseMissing('password_otps', ['email' => $user->email]);
        }

        $this->postJson('/api/forgot-password/send-otp', ['email' => 'unknown@example.test'])
            ->assertNotFound()
            ->assertJsonPath('status', 'error');
    }

    public function test_otp_is_replaced_verified_reset_and_removed_after_use(): void
    {
        Mail::fake();
        $user = $this->createUser('reset-otp@example.test');

        $this->postJson('/api/forgot-password/send-otp', ['email' => $user->email])->assertOk();
        $firstOtp = PasswordOtp::where('email', $user->email)->firstOrFail();
        $this->postJson('/api/forgot-password/send-otp', ['email' => $user->email])->assertOk();
        $secondOtp = PasswordOtp::where('email', $user->email)->firstOrFail();
        $this->assertNotSame($firstOtp->otp, $secondOtp->otp);
        $this->assertSame(1, PasswordOtp::where('email', $user->email)->count());

        $this->postJson('/api/forgot-password/verify-otp', [
            'email' => $user->email,
            'otp' => $secondOtp->otp,
        ])->assertOk();

        $this->postJson('/api/forgot-password/reset', [
            'email' => $user->email,
            'otp' => $secondOtp->otp,
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk()
            ->assertJsonStructure(['status', 'message', 'data']);

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_otps', ['email' => $user->email]);
    }

    public function test_invalid_or_expired_otp_and_unconfirmed_password_are_rejected(): void
    {
        $user = $this->createUser('expired-otp@example.test');
        PasswordOtp::create([
            'email' => $user->email,
            'otp' => '123456',
            'expires_at' => now()->subSecond(),
        ]);

        $this->postJson('/api/forgot-password/verify-otp', [
            'email' => $user->email,
            'otp' => '123456',
        ])->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/forgot-password/reset', [
            'email' => $user->email,
            'otp' => '123456',
            'password' => 'newpass123',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonPath('status', 'error');
    }

    public function test_new_password_must_be_different_from_the_current_password(): void
    {
        $user = $this->createUser('same-password@example.test');
        PasswordOtp::create([
            'email' => $user->email,
            'otp' => '654321',
            'expires_at' => now()->addMinutes(10),
        ]);
        $oldPasswordHash = $user->password;

        $this->postJson('/api/forgot-password/reset', [
            'email' => $user->email,
            'otp' => '654321',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Password baru tidak boleh sama dengan password lama.'
            );

        $this->assertSame($oldPasswordHash, $user->fresh()->password);
        $this->assertDatabaseHas('password_otps', [
            'email' => $user->email,
            'otp' => '654321',
        ]);
    }

    private function createUser(string $email, string $role = 'pengguna', bool $active = true): User
    {
        return User::create([
            'name' => 'Pengguna Uji',
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'role' => $role,
            'is_active' => $active,
        ]);
    }
}
