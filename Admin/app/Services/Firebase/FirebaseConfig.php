<?php

namespace App\Services\Firebase;

use Illuminate\Support\Facades\Log;

/**
 * Membaca konfigurasi Firebase dari config/firebase.php.
 *
 * Service account hanya dibaca di server. Private key tidak pernah
 * dikirim ke browser, Flutter, atau response API.
 */
class FirebaseConfig
{
    private ?array $credentials = null;

    private bool $credentialsLoaded = false;

    public function projectId(): ?string
    {
        $projectId = trim((string) config('firebase.project_id', ''));

        if ($projectId !== '') {
            return $projectId;
        }

        return $this->credentials()['project_id'] ?? null;
    }

    /**
     * @return array{client_email: string, private_key: string, project_id?: string}|null
     */
    public function credentials(): ?array
    {
        if ($this->credentialsLoaded) {
            return $this->credentials;
        }

        $this->credentialsLoaded = true;

        $json = trim((string) config('firebase.credentials_json', ''));

        if ($json === '') {
            $json = $this->readCredentialsFile();
        }

        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)
            || ! is_string($decoded['client_email'] ?? null)
            || ! is_string($decoded['private_key'] ?? null)) {
            Log::warning('Konfigurasi service account Firebase tidak valid.');

            return null;
        }

        $decoded['private_key'] = str_replace('\n', "\n", $decoded['private_key']);

        return $this->credentials = $decoded;
    }

    public function usesEmulator(): bool
    {
        return $this->firestoreEmulatorHost() !== null;
    }

    public function firestoreEmulatorHost(): ?string
    {
        $host = trim((string) config('firebase.emulator.firestore_host', ''));

        return $host === '' ? null : $host;
    }

    public function authEmulatorHost(): ?string
    {
        $host = trim((string) config('firebase.emulator.auth_host', ''));

        return $host === '' ? null : $host;
    }

    /**
     * Konfigurasi publik untuk Firebase JS/Flutter SDK.
     *
     * @return array<string, string>
     */
    public function webConfig(): array
    {
        $projectId = $this->projectId();

        $config = [
            'apiKey' => trim((string) config('firebase.web.api_key', '')),
            'authDomain' => trim((string) config('firebase.web.auth_domain', '')),
            'projectId' => (string) ($projectId ?? ''),
            'storageBucket' => trim((string) config('firebase.web.storage_bucket', '')),
            'messagingSenderId' => trim((string) config('firebase.web.messaging_sender_id', '')),
            'appId' => trim((string) config('firebase.web.app_id', '')),
        ];

        if ($config['authDomain'] === '' && $projectId !== null) {
            $config['authDomain'] = $projectId.'.firebaseapp.com';
        }

        if ($config['storageBucket'] === '' && $projectId !== null) {
            $config['storageBucket'] = $projectId.'.appspot.com';
        }

        return array_filter($config, static fn (string $value): bool => $value !== '');
    }

    public function isConfigured(): bool
    {
        return $this->missingConfiguration() === [];
    }

    /**
     * Daftar konfigurasi yang belum diisi (nama kunci saja, tanpa nilai rahasia).
     *
     * @return list<string>
     */
    public function missingConfiguration(): array
    {
        $missing = [];

        if ($this->projectId() === null) {
            $missing[] = 'FIREBASE_PROJECT_ID';
        }

        if (! $this->usesEmulator() && $this->credentials() === null) {
            $missing[] = 'FIREBASE_CREDENTIALS_PATH / FIREBASE_CREDENTIALS_JSON';
        }

        $web = $this->webConfig();

        if (($web['apiKey'] ?? '') === '') {
            $missing[] = 'FIREBASE_API_KEY';
        }

        if (($web['appId'] ?? '') === '') {
            $missing[] = 'FIREBASE_APP_ID';
        }

        return $missing;
    }

    private function readCredentialsFile(): string
    {
        $path = trim((string) config('firebase.credentials_path', ''));

        if ($path === '') {
            return '';
        }

        $isAbsolute = str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;

        $resolved = $isAbsolute ? $path : base_path($path);

        if (! is_file($resolved) || ! is_readable($resolved)) {
            Log::warning('File service account Firebase tidak dapat dibaca.', [
                'path' => $path,
            ]);

            return '';
        }

        return (string) file_get_contents($resolved);
    }
}
