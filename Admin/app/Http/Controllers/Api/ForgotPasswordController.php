<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SendOtpMail;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->error('Periksa kembali alamat email Anda.', 422, [
                'errors' => $validator->errors(),
            ]);
        }

        $email = $validator->validated()['email'];
        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->error('Email tidak ditemukan.', 404);
        }

        if (! $user->is_active) {
            return $this->error('Akun Anda tidak aktif. Hubungi administrator.', 403);
        }

        if (! in_array(strtolower((string) $user->role), ['pengguna', 'petugas'], true)) {
            return $this->error('Reset password tidak tersedia untuk akun ini.', 403);
        }

        $otp = (string) random_int(100000, 999999);

        DB::transaction(function () use ($email, $otp): void {
            PasswordOtp::where('email', $email)->delete();
            PasswordOtp::create([
                'email' => $email,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        Mail::to($email)->send(new SendOtpMail($otp));

        return response()->json([
            'status' => 'success',
            'message' => 'Kode OTP telah dikirim ke email Anda.',
            'data' => null,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return $this->error('Email atau format OTP tidak valid.', 422, [
                'errors' => $validator->errors(),
            ]);
        }

        $validated = $validator->validated();
        $user = User::where('email', $validated['email'])->first();
        if (! $this->canResetPassword($user)) {
            return $this->error('Akun tidak aktif atau tidak diizinkan reset password.', 403);
        }

        if (! $this->hasValidOtp($validated['email'], $validated['otp'])) {
            return $this->error('Kode OTP salah atau sudah kedaluwarsa.', 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Kode OTP berhasil diverifikasi.',
            'data' => null,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 6 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->error('Periksa kembali data reset password Anda.', 422, [
                'errors' => $validator->errors(),
            ]);
        }

        $validated = $validator->validated();
        $result = DB::transaction(function () use ($validated): string {
            $user = User::where('email', $validated['email'])->lockForUpdate()->first();
            if (! $this->canResetPassword($user)) {
                return 'invalid';
            }

            $otp = PasswordOtp::where('email', $validated['email'])
                ->where('otp', $validated['otp'])
                ->lockForUpdate()
                ->first();

            if (! $otp || ! $otp->expires_at->isFuture()) {
                return 'invalid';
            }

            if (Hash::check($validated['password'], $user->password)) {
                return 'same_password';
            }

            $user->password = Hash::make($validated['password']);
            $user->save();
            PasswordOtp::where('email', $validated['email'])->delete();

            return 'success';
        });

        if ($result === 'same_password') {
            return $this->error('Password baru tidak boleh sama dengan password lama.', 422);
        }

        if ($result !== 'success') {
            return $this->error('Kode OTP salah atau sudah kedaluwarsa.', 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diperbarui.',
            'data' => null,
        ]);
    }

    private function canResetPassword(?User $user): bool
    {
        return $user !== null
            && $user->is_active
            && in_array(strtolower((string) $user->role), ['pengguna', 'petugas'], true);
    }

    private function hasValidOtp(string $email, string $code): bool
    {
        $otp = PasswordOtp::where('email', $email)->where('otp', $code)->first();

        return $otp !== null && $otp->expires_at->isFuture();
    }

    private function error(string $message, int $statusCode, ?array $data = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}
