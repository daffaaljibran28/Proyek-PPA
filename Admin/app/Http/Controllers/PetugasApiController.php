<?php

namespace App\Http\Controllers;

use App\Models\Laporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PetugasApiController extends Controller
{
    private const ACTIVE_STATUSES = ['Sedang Diproses', 'Diproses'];

    private const VISIBLE_STATUSES = ['Sedang Diproses', 'Diproses', 'Selesai'];

    public function dashboard(Request $request)
    {
        $jumlahTugas = Laporan::where('assigned_petugas_id', $request->user()->id)
            ->whereIn('status_penanganan', self::ACTIVE_STATUSES)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'name' => $request->user()->name,
                'jumlah_tugas' => $jumlahTugas,
            ],
        ]);
    }

    public function reports(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'required', Rule::in(self::VISIBLE_STATUSES)],
        ]);

        $query = Laporan::where('assigned_petugas_id', $request->user()->id)
            ->whereIn('status_penanganan', self::VISIBLE_STATUSES)
            ->latest();

        if (isset($validated['status'])) {
            $query->where('status_penanganan', $validated['status']);
        }

        $reports = $query->get([
            'id_laporan',
            'nomor_laporan',
            'kategori',
            'tanggal_kejadian',
            'lokasi_kejadian',
            'status_penanganan',
            'created_at',
        ]);

        return response()->json(['status' => 'success', 'data' => $reports]);
    }

    public function showReport(Request $request, int $id)
    {
        $report = $this->assignedReport($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $this->petugasReport($report),
        ]);
    }

    public function evidence(Request $request, int $id, int $index)
    {
        $report = $this->assignedReport($request, $id);
        $files = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $report->bukti_lampiran)
        )));
        $path = $files[$index] ?? abort(404);
        $segments = explode('/', $path);

        abort_unless(
            ! str_contains($path, '\\')
                && count($segments) >= 3
                && $segments[0] === 'laporan'
                && $segments[1] === (string) $report->id_laporan
                && ! in_array('', $segments, true)
                && ! in_array('.', $segments, true)
                && ! in_array('..', $segments, true),
            404
        );

        $disk = Storage::disk('local');
        $diskRoot = realpath($disk->path(''));
        $reportsRoot = realpath($disk->path('laporan'));
        $reportRoot = realpath($disk->path('laporan/'.$report->id_laporan));
        $filePath = realpath($disk->path($path));
        $isWithin = static fn (string $path, string $directory): bool => str_starts_with(
            $path,
            rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR
        );

        abort_unless(
            $diskRoot !== false
                && $reportsRoot !== false
                && $reportRoot !== false
                && $filePath !== false
                && $isWithin($reportsRoot, $diskRoot)
                && $isWithin($reportRoot, $reportsRoot)
                && $isWithin($filePath, $reportRoot)
                && is_file($filePath),
            404
        );

        return $disk->response(
            $path,
            basename($path),
            ['Cache-Control' => 'private, no-store'],
            'inline'
        );
    }

    public function updateHandling(Request $request, int $id)
    {
        $validated = $request->validate([
            'status_penanganan' => ['sometimes', 'required', Rule::in(['Diproses', 'Selesai'])],
            'catatan_petugas' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'pesan_pelapor' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $catatan = trim((string) ($validated['catatan_petugas'] ?? ''));
        $pesanPelapor = trim((string) ($validated['pesan_pelapor'] ?? ''));
        $ubahStatus = array_key_exists('status_penanganan', $validated);

        if (! $ubahStatus && $catatan === '' && $pesanPelapor === '') {
            abort(422, 'Isi perubahan status, catatan internal, atau pesan untuk pelapor.');
        }

        $report = DB::transaction(function () use (
            $request,
            $id,
            $validated,
            $catatan,
            $pesanPelapor,
            $ubahStatus
        ): Laporan {
            $report = $this->assignedReport($request, $id, lockForUpdate: true);

            if (! in_array($report->status_penanganan, self::ACTIVE_STATUSES, true)) {
                abort(422, 'Laporan ini tidak lagi dapat diperbarui.');
            }

            if ($ubahStatus) {
                $statusTujuan = $validated['status_penanganan'];
                $transisiDiizinkan = match ($report->status_penanganan) {
                    'Sedang Diproses' => $statusTujuan === 'Diproses',
                    'Diproses' => $statusTujuan === 'Selesai',
                    default => false,
                };

                abort_unless($transisiDiizinkan, 422, 'Transisi status laporan tidak diizinkan.');
                $report->status_penanganan = $statusTujuan;
            }

            if ($catatan !== '') {
                $entriCatatan = '['.now()->toIso8601String().'] '
                    .$request->user()->name.': '.$catatan;
                $report->catatan_petugas = trim((string) $report->catatan_petugas) === ''
                    ? $entriCatatan
                    : rtrim((string) $report->catatan_petugas).PHP_EOL.PHP_EOL.$entriCatatan;
            }

            if ($ubahStatus || $pesanPelapor !== '') {
                $riwayat = $report->riwayat_status ?? [];
                $riwayat[] = [
                    'status' => $report->status_penanganan,
                    'pesan' => $pesanPelapor !== '' ? $pesanPelapor : null,
                    'pada' => now()->toIso8601String(),
                    'oleh' => 'Petugas',
                ];
                $report->riwayat_status = $riwayat;
                $report->notifikasi_dihapus = false;
            }

            if ($pesanPelapor !== '') {
                $report->pesan_pelapor = $pesanPelapor;
            }

            $report->save();

            return $report->fresh();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Penanganan laporan berhasil diperbarui.',
            'data' => $this->petugasReport($report),
        ]);
    }

    private function assignedReport(Request $request, int $id, bool $lockForUpdate = false): Laporan
    {
        $query = Laporan::where('assigned_petugas_id', $request->user()->id)
            ->whereIn('status_penanganan', self::VISIBLE_STATUSES);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($id);
    }

    private function petugasReport(Laporan $report): array
    {
        $report->setAttribute(
            'riwayat_status',
            collect($report->riwayat_status ?? [])
                ->filter(fn ($entry): bool => is_array($entry))
                ->map(fn (array $entry): array => array_intersect_key(
                    $entry,
                    array_flip(['status', 'pesan', 'pada', 'oleh'])
                ))
                ->values()
                ->all()
        );

        return $report->makeHidden([
            'id_pengguna',
            'catatan_admin',
            'assigned_petugas_id',
            'notification_read_events',
        ])->toArray();
    }
}
