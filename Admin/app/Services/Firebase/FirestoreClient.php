<?php

namespace App\Services\Firebase;

use DateTimeInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien tipis Cloud Firestore REST API (tanpa dependency tambahan).
 *
 * Hanya dipakai di server untuk membuat/menyinkronkan dokumen metadata chat.
 * Pesan chat ditulis oleh klien melalui Firebase SDK dan dilindungi
 * Firestore Security Rules.
 */
class FirestoreClient
{
    public function __construct(
        private readonly FirebaseConfig $config,
        private readonly FirebaseTokenFactory $tokens
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $path): ?array
    {
        $response = $this->request()->get($this->documentUrl($path));

        if ($response->status() === 404) {
            return null;
        }

        $this->ensureSuccessful($response, 'membaca');

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new FirebaseException('Respons Firestore tidak valid.');
        }

        return $this->decodeDocument($payload);
    }

    /**
     * Membuat atau memperbarui dokumen. Bila $updateMask diisi, hanya field
     * tersebut yang diubah sehingga last_message tidak ikut tertimpa.
     *
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $updateMask
     * @return array<string, mixed>
     */
    public function put(string $path, array $fields, array $updateMask = []): array
    {
        $url = $this->documentUrl($path);

        if ($updateMask !== []) {
            $query = implode('&', array_map(
                static fn (string $field): string => 'updateMask.fieldPaths='.rawurlencode($field),
                array_values($updateMask)
            ));
            $url .= '?'.$query;
        }

        $response = $this->request()
            ->withBody(json_encode(['fields' => $this->encodeFields($fields)], JSON_UNESCAPED_SLASHES), 'application/json')
            ->patch($url);

        $this->ensureSuccessful($response, 'menyimpan');

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new FirebaseException('Respons Firestore tidak valid.');
        }

        return $this->decodeDocument($payload);
    }

    public function delete(string $path): void
    {
        $response = $this->request()->delete($this->documentUrl($path));

        if ($response->status() === 404) {
            return;
        }

        $this->ensureSuccessful($response, 'menghapus');
    }

    /**
     * Mengambil daftar dokumen dalam sebuah koleksi atau subkoleksi.
     *
     * @return list<array<string, mixed>>
     */
    public function list(string $collectionPath, int $pageSize = 100): array
    {
        $url = $this->documentUrl($collectionPath).'?pageSize='.$pageSize;
        $response = $this->request()->get($url);

        if ($response->status() === 404) {
            return [];
        }

        $this->ensureSuccessful($response, 'mengambil daftar');

        $payload = $response->json();
        $documents = $payload['documents'] ?? [];

        if (! is_array($documents)) {
            return [];
        }

        $results = [];
        foreach ($documents as $doc) {
            if (! is_array($doc)) {
                continue;
            }
            $item = $this->decodeDocument($doc);
            $name = (string) ($doc['name'] ?? '');
            $parts = explode('/', $name);
            $item['id'] = end($parts);
            $results[] = $item;
        }

        return $results;
    }

    /**
     * Menambahkan dokumen baru ke dalam koleksi dengan ID otomatis dari Firestore.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function post(string $collectionPath, array $fields): array
    {
        $url = $this->documentUrl($collectionPath);
        $response = $this->request()
            ->withBody(json_encode(['fields' => $this->encodeFields($fields)], JSON_UNESCAPED_SLASHES), 'application/json')
            ->post($url);

        $this->ensureSuccessful($response, 'menyimpan');

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new FirebaseException('Respons Firestore tidak valid.');
        }

        $result = $this->decodeDocument($payload);
        $name = (string) ($payload['name'] ?? '');
        $parts = explode('/', $name);
        $result['id'] = end($parts);

        return $result;
    }

    private function request()
    {
        return Http::withToken($this->tokens->accessToken())
            ->acceptJson()
            ->timeout((int) config('firebase.timeout', 10));
    }

    private function documentUrl(string $path): string
    {
        $projectId = $this->config->projectId();

        if ($projectId === null) {
            throw new FirebaseException('Project Firebase belum dikonfigurasi.');
        }

        $path = ltrim($path, '/');

        if ($this->config->usesEmulator()) {
            $base = 'http://'.$this->config->firestoreEmulatorHost()
                .'/v1/projects/'.rawurlencode($projectId).'/databases/(default)/documents';
        } else {
            $base = 'https://firestore.googleapis.com/v1/projects/'
                .rawurlencode($projectId).'/databases/(default)/documents';
        }

        return $base.'/'.$path;
    }

    private function ensureSuccessful(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        Log::error('Firestore gagal '.$action.' dokumen chat.', [
            'status' => $response->status(),
            'body' => $response->json('error.message') ?? substr($response->body(), 0, 500),
        ]);

        throw new FirebaseException('Layanan chat sedang tidak dapat diakses. Coba lagi.');
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, array<string, mixed>>
     */
    public function encodeFields(array $fields): array
    {
        $encoded = [];

        foreach ($fields as $key => $value) {
            $encoded[$key] = $this->encodeValue($value);
        }

        return $encoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeValue(mixed $value): array
    {
        if ($value === null) {
            return ['nullValue' => null];
        }

        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }

        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }

        if (is_float($value)) {
            return ['doubleValue' => $value];
        }

        if ($value instanceof DateTimeInterface) {
            return ['timestampValue' => $value->format('Y-m-d\TH:i:s.u\Z')];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return ['arrayValue' => ['values' => array_map(
                    fn (mixed $item): array => $this->encodeValue($item),
                    $value
                )]];
            }

            return ['mapValue' => ['fields' => $this->encodeFields($value)]];
        }

        return ['stringValue' => (string) $value];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function decodeDocument(array $document): array
    {
        $decoded = [];

        foreach ((array) ($document['fields'] ?? []) as $key => $value) {
            $decoded[$key] = $this->decodeValue($value);
        }

        return $decoded;
    }

    private function decodeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        if (array_key_exists('nullValue', $value)) {
            return null;
        }

        if (array_key_exists('stringValue', $value)) {
            return (string) $value['stringValue'];
        }

        if (array_key_exists('booleanValue', $value)) {
            return (bool) $value['booleanValue'];
        }

        if (array_key_exists('integerValue', $value)) {
            return (int) $value['integerValue'];
        }

        if (array_key_exists('doubleValue', $value)) {
            return (float) $value['doubleValue'];
        }

        if (array_key_exists('timestampValue', $value)) {
            return $value['timestampValue'];
        }

        if (array_key_exists('arrayValue', $value)) {
            return array_map(
                fn (mixed $item): mixed => $this->decodeValue($item),
                (array) ($value['arrayValue']['values'] ?? [])
            );
        }

        if (array_key_exists('mapValue', $value)) {
            return $this->decodeDocument((array) $value['mapValue']);
        }

        return null;
    }
}
