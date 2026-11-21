<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_pelapor_forgot_password_page_uses_the_otp_flow(): void
    {
        $this->get('/pelapor/lupa-password')
            ->assertOk()
            ->assertSee('Atur Ulang Kata Sandi')
            ->assertSee('Kirim Kode OTP')
            ->assertSee('Verifikasi Kode OTP')
            ->assertSee('password_confirmation')
            ->assertSee('rounded-2xl')
            ->assertSee('send-otp')
            ->assertSee('verify-otp')
            ->assertSee('reset');
    }

    public function test_web_password_cannot_be_changed_without_a_valid_otp(): void
    {
        $this->post('/pelapor/lupa-password', [
            'email' => 'user@example.test',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertStatus(405);
    }
}
