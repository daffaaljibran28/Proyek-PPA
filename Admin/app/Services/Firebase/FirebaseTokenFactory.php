<?php

namespace App\Services\Firebase;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pembuat token Firebase tanpa dependency tambahan.
 *
 * - customToken(): Firebase Custom Auth token (RS256) agar klien mendapat
 *   identitas Firebase yang sama dengan akun Laravel.
 * - accessToken(): OAuth2 access token untuk Firestore REST API (server-side).
 */
class FirebaseTokenFactory
{
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private const FIRESTORE_SCOPE = 'https://www.googleapis.com/auth/datastore';

    private const CUSTOM_TOKEN_AUDIENCE = 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit';

    public function __construct(private readonly FirebaseConfig $config)
    {
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function customToken(string $uid, array $claims = []): string
    {
        if ($uid === '') {
            throw new FirebaseException('Identitas Firebase tidak valid.');
        }

        $issuedAt = time();

        $payload = [
            'iss' => '',
            'sub' => '',
            'aud' => self::CUSTOM_TOKEN_AUDIENCE,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
            'uid' => $uid,
            'claims' => (object) $claims,
        ];

        $credentials = $this->config->credentials();

        if ($credentials === null) {
            if (! $this->config->usesEmulator()) {
                throw new FirebaseException('Kredensial Firebase belum dikonfigurasi pada server.');
            }

            $payload['iss'] = 'sapa-emulator';
            $payload['sub'] = 'sapa-emulator';

            return $this->encode(['alg' => 'none', 'typ' => 'JWT'], $payload, null, '');
        }

        $payload['iss'] = $credentials['client_email'];
        $payload['sub'] = $credentials['client_email'];

        return $this->encode(['alg' => 'RS256', 'typ' => 'JWT'], $payload, $credentials);
    }

    public function accessToken(): string
    {
        if ($this->config->usesEmulator()) {
            return 'owner';
        }

        return Cache::remember(
            'sapa:firebase:access-token:'.($this->config->projectId() ?? 'default'),
            3000,
            fn (): string => $this->requestAccessToken()
        );
    }

    private function requestAccessToken(): string
    {
        $credentials = $this->config->credentials();

        if ($credentials === null) {
            throw new FirebaseException('Kredensial Firebase belum dikonfigurasi pada server.');
        }

        $issuedAt = time();
        $assertion = $this->encode(
            ['alg' => 'RS256', 'typ' => 'JWT'],
            [
                'iss' => $credentials['client_email'],
                'scope' => self::FIRESTORE_SCOPE,
                'aud' => self::TOKEN_URI,
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ],
            $credentials
        );

        $response = Http::asForm()
            ->timeout((int) config('firebase.timeout', 10))
            ->post(self::TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            Log::error('Gagal mengambil access token Firebase.', [
                'status' => $response->status(),
                'body' => $response->json('error_description') ?? $response->body(),
            ]);

            throw new FirebaseException('Layanan chat tidak dapat menghubungi Firebase.');
        }

        $token = (string) $response->json('access_token', '');

        if ($token === '') {
            throw new FirebaseException('Firebase tidak mengembalikan access token.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $header, array $payload, ?array $credentials = null, string $signature = ''): string
    {
        $segments = $this->encodeSegment($header).'.'.$this->encodeSegment($payload);

        if ($credentials !== null) {
            $signature = $this->sign($segments, $credentials);
        }

        return $segments.'.'.$signature;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeSegment(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new FirebaseException('Gagal membentuk token Firebase.');
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @param  array{private_key: string}  $credentials
     */
    private function sign(string $segments, array $credentials): string
    {
        $key = openssl_pkey_get_private($credentials['private_key']);

        if ($key === false) {
            throw new FirebaseException('Private key Firebase tidak valid.');
        }

        if (! openssl_sign($segments, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new FirebaseException('Gagal menandatangani token Firebase.');
        }

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
