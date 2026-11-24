<?php

namespace App\Services\Chat;

use App\Models\Laporan;
use App\Models\User;
use App\Services\Firebase\FirebaseConfig;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\FirebaseTokenFactory;
use App\Services\Firebase\FirestoreClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Menjembatani laporan Laravel dengan percakapan Firestore.
 *
 * Laravel menentukan siapa peserta chat (pemilik laporan dan Petugas yang
 * ditugaskan). Firestore hanya menyimpan pesan dan metadata percakapan.
 */
class ChatService
{
    public const ROLE_PENGGUNA = 'pengguna';

    public const ROLE_PETUGAS = 'petugas';

    private const VISIBLE_STATUSES = ['Sedang Diproses', 'Diproses', 'Selesai'];

    public function __construct(
        private readonly FirebaseConfig $firebase,
        private readonly FirestoreClient $firestore,
        private readonly FirebaseTokenFactory $tokens
    ) {
    }

    public static function uid(User $user): string
    {
        return 'sapa_'.strtolower((string) $user->role).'_'.$user->id;
    }

    /**
     * Konteks percakapan (tanpa menyentuh Firebase) untuk header chat.
     *
     * @return array<string, mixed>
     */
    public function context(User $viewer, int $reportId): array
    {
        $report = $this->reportFor($viewer, $reportId);
        $pengguna = $this->penggunaFor($report);
        $petugas = $this->petugasFor($report);
        $viewerRole = strtolower((string) $viewer->role);
        $counterpart = in_array($viewerRole, [self::ROLE_PETUGAS, 'admin'], true) ? $pengguna : $petugas;

        return [
            'chat_id' => (string) $report->id_laporan,
            'collection' => (string) config('firebase.collection', 'chats'),
            'report' => [
                'id_laporan' => (int) $report->id_laporan,
                'nomor_laporan' => $report->nomor_laporan,
                'kategori' => $report->kategori,
                'status_penanganan' => $report->status_penanganan,
            ],
            'viewer' => [
                'id' => (int) $viewer->id,
                'name' => $viewer->name,
                'role' => $viewerRole,
                'uid' => self::uid($viewer),
            ],
            'counterpart' => $counterpart === null ? null : [
                'id' => (int) $counterpart->id,
                'name' => $counterpart->name,
                'role' => strtolower((string) $counterpart->role),
                'uid' => self::uid($counterpart),
            ],
            'availability' => $this->availability(),
        ];
    }

    /**
     * Sesi Firebase untuk klien: memastikan dokumen chat ada, lalu
     * menerbitkan custom token sesuai identitas Laravel.
     *
     * @return array<string, mixed>
     */
    public function session(User $viewer, int $reportId): array
    {
        $report = $this->reportFor($viewer, $reportId);

        $missing = $this->firebase->missingConfiguration();

        if ($missing !== []) {
            Log::warning('Permintaan sesi chat ditolak karena konfigurasi Firebase belum lengkap.', [
                'missing' => $missing,
                'user_id' => $viewer->id,
                'report_id' => $reportId,
            ]);

            throw new FirebaseException(
                'Fitur chat belum aktif. Hubungi administrator untuk melengkapi konfigurasi Firebase.'
            );
        }

        $document = $this->ensureChat($report, $viewer);
        $role = strtolower((string) $viewer->role);
        $uid = self::uid($viewer);

        return [
            'chat_id' => (string) $report->id_laporan,
            'uid' => $uid,
            'role' => $role,
            'custom_token' => $this->tokens->customToken($uid, [
                'role' => $role,
                'sapa_user_id' => (string) $viewer->id,
                'report_id' => (string) $report->id_laporan,
            ]),
            'firebase' => $this->firebase->webConfig(),
            'participants' => $document['participants'] ?? [],
        ];
    }

    /**
     * Menyelaraskan metadata chat tanpa mengubah pesan terakhir.
     *
     * @return array<string, mixed>
     */
    public function ensureChat(Laporan $report, ?User $viewer = null): array
    {
        $path = $this->documentPath($report);
        $existing = $this->firestore->get($path);
        $metadata = $this->metadata($report, $viewer);
        $now = Carbon::now('UTC');

        if ($existing === null) {
            return $this->firestore->put($path, $metadata + [
                'last_message' => null,
                'last_message_at' => null,
                'last_sender_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $changed = [];

        foreach ($metadata as $key => $value) {
            if (json_encode($existing[$key] ?? null) !== json_encode($value)) {
                $changed[$key] = $value;
            }
        }

        $changed['updated_at'] = $now;

        return $this->firestore->put($path, $changed, array_keys($changed));
    }

    public function documentPath(Laporan $report): string
    {
        return ((string) config('firebase.collection', 'chats')).'/'.$report->id_laporan;
    }

    /**
     * Mengambil laporan yang boleh diakses penonton. Laporan milik orang lain
     * menghasilkan 404 agar ID laporan tidak dapat ditebak.
     */
    public function reportFor(User $viewer, int $reportId): Laporan
    {
        $role = strtolower((string) $viewer->role);

        $report = match ($role) {
            self::ROLE_PENGGUNA => Laporan::query()
                ->where('id_pengguna', $viewer->id)
                ->find($reportId),
            self::ROLE_PETUGAS => Laporan::query()
                ->where('assigned_petugas_id', $viewer->id)
                ->whereIn('status_penanganan', self::VISIBLE_STATUSES)
                ->find($reportId),
            'admin' => Laporan::query()->find($reportId),
            default => null,
        };

        abort_if($report === null, 404, 'Laporan tidak ditemukan.');

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    public function availability(): array
    {
        $missing = $this->firebase->missingConfiguration();

        return [
            'ready' => $missing === [],
            'message' => $missing === []
                ? null
                : 'Fitur chat belum aktif. Administrator perlu melengkapi konfigurasi Firebase.',
        ];
    }

    private function penggunaFor(Laporan $report): ?User
    {
        return User::query()->find($report->id_pengguna);
    }

    private function petugasFor(Laporan $report): ?User
    {
        if ($report->assigned_petugas_id === null) {
            return null;
        }

        return User::query()->find($report->assigned_petugas_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(Laporan $report, ?User $viewer = null): array
    {
        $pengguna = $this->penggunaFor($report);
        $petugas = $this->petugasFor($report);

        $participants = [];

        if ($pengguna !== null) {
            $participants[] = self::uid($pengguna);
        } elseif ($report->id_pengguna !== null) {
            // Akun pelapor sudah tidak ada; pertahankan identitas laporan agar
            // percakapan lama tetap terbaca oleh Petugas.
            $participants[] = 'sapa_pengguna_'.$report->id_pengguna;
        }

        if ($petugas !== null) {
            $participants[] = self::uid($petugas);
        }

        if ($viewer !== null && $viewer->isAdmin()) {
            $participants[] = self::uid($viewer);
        }

        return [
            'report_id' => (int) $report->id_laporan,
            'report_number' => (string) $report->nomor_laporan,
            'report_category' => (string) $report->kategori,
            'report_status' => (string) $report->status_penanganan,
            'user_id' => $participants[0] ?? null,
            'user_name' => $pengguna->name ?? $report->nama_pelapor,
            'officer_id' => $petugas === null ? null : self::uid($petugas),
            'officer_name' => $petugas?->name,
            'participants' => array_values(array_unique($participants)),
        ];
    }

    /**
     * Mengambil riwayat pesan percakapan laporan.
     *
     * @return list<array<string, mixed>>
     */
    public function messages(User $viewer, int $reportId): array
    {
        $report = $this->reportFor($viewer, $reportId);
        $missing = $this->firebase->missingConfiguration();

        if ($missing !== []) {
            throw new FirebaseException(
                'Fitur chat belum aktif. Hubungi administrator untuk melengkapi konfigurasi Firebase.'
            );
        }

        $collectionPath = $this->documentPath($report).'/messages';
        $items = $this->firestore->list($collectionPath, 200);

        usort($items, function (array $a, array $b): int {
            $timeA = $a['created_at'] ?? '';
            $timeB = $b['created_at'] ?? '';
            return strcmp((string) $timeA, (string) $timeB);
        });

        return array_values($items);
    }

    /**
     * Mengirim pesan baru ke percakapan laporan.
     *
     * @return array<string, mixed>
     */
    public function sendMessage(User $viewer, int $reportId, string $message): array
    {
        $report = $this->reportFor($viewer, $reportId);
        $cleanMessage = trim($message);

        if ($cleanMessage === '') {
            throw new FirebaseException('Pesan tidak boleh kosong.', 422);
        }

        if (mb_strlen($cleanMessage) > 2000) {
            throw new FirebaseException('Pesan tidak boleh melebihi 2000 karakter.', 422);
        }

        $missing = $this->firebase->missingConfiguration();
        if ($missing !== []) {
            throw new FirebaseException(
                'Fitur chat belum aktif. Hubungi administrator untuk melengkapi konfigurasi Firebase.'
            );
        }

        $this->ensureChat($report, $viewer);
        $now = Carbon::now('UTC');
        $uid = self::uid($viewer);
        $role = strtolower((string) $viewer->role);
        $meta = $this->metadata($report, $viewer);
        $participants = $meta['participants'] ?? [];

        $messageData = [
            'sender_id' => $uid,
            'sender_role' => $role,
            'sender_name' => $viewer->name,
            'message' => $cleanMessage,
            'created_at' => $now,
            'read_at' => null,
            'participants' => $participants,
        ];

        $saved = $this->firestore->post($this->documentPath($report).'/messages', $messageData);

        $this->firestore->put($this->documentPath($report), [
            'last_message' => $cleanMessage,
            'last_message_at' => $now,
            'last_sender_id' => $uid,
            'updated_at' => $now,
        ], ['last_message', 'last_message_at', 'last_sender_id', 'updated_at']);

        return $saved;
    }
}
