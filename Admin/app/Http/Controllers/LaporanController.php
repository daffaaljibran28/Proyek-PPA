<?php

namespace App\Http\Controllers;

use App\Models\Laporan;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Firebase\FirebaseException;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LaporanController extends Controller
{
    /**
     * Menyimpan Laporan Baru dengan Pencegahan Spam & Redundansi
     */
    public function store(Request $request)
    {
        $tanggal = $request->tanggal_kejadian;
        try {
            if ($tanggal && str_contains($tanggal, '-')) {
                $parts = explode('-', $tanggal);
                if (strlen($parts[0]) == 2) {
                    $tanggal = Carbon::createFromFormat('d-m-Y', $request->tanggal_kejadian)->format('Y-m-d');
                }
            }
        } catch (\Exception $e) {
            $tanggal = date('Y-m-d');
        }

        // ========================================================
        // PENCEGAHAN REDUNDANSI & SPAM (Maksimal 10 menit terakhir)
        // ========================================================
        $idPengguna = $request->id_pengguna ?? 1;
        $isSpam = Laporan::where('id_pengguna', $idPengguna)
            ->where('created_at', '>=', Carbon::now()->subMinutes(10))
            ->where(function ($query) use ($request) {
                $query->where('kronologi', $request->kronologi)
                      ->orWhere(function ($q) use ($request) {
                          $q->where('kategori', $request->kategori)
                            ->where('lokasi_kejadian', $request->lokasi_kejadian);
                      });
            })
            ->exists();

        if ($isSpam) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Laporan serupa sudah pernah dikirimkan baru-baru ini. Mohon tunggu beberapa saat untuk mencegah spam.',
            ], 422);
        }
        // ========================================================

        $nomorLaporan = 'LAP-' . date('Ymd') . '-' . rand(100, 999);

        $laporan = Laporan::create([
            'nomor_laporan'     => $nomorLaporan,
            'id_pengguna'       => $idPengguna,
            'nama_pelapor'      => $request->nama_pelapor ?? 'Revitaaa',
            'kategori'          => $request->kategori,
            'sebagai'           => $request->sebagai,
            'lokasi_kejadian'   => $request->lokasi_kejadian,
            'tanggal_kejadian'  => $tanggal,
            'kronologi'         => $request->kronologi,
            'bukti_lampiran'    => $request->bukti_lampiran,
            'status_penanganan' => 'Menunggu Verifikasi',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Laporan berhasil dibuat',
            'data'    => $laporan,
        ], 201);
    }

    /**
     * Dashboard Admin dengan Filter Harian / Bulanan & Sinkronisasi Status
     */
    public function dashboard(Request $request)
    {
        $validated = $request->validate([
            'filter' => ['sometimes', 'required', Rule::in(['hari', 'bulan'])],
            'tanggal' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'bulan' => ['sometimes', 'nullable', 'date_format:Y-m'],
        ]);

        $filter  = $validated['filter'] ?? 'bulan';
        $tanggal = $validated['tanggal'] ?? now()->format('Y-m-d');
        $bulan   = $validated['bulan'] ?? now()->format('Y-m');

        $query = Laporan::query();

        if ($filter === 'hari') {
            $mulaiPeriode = Carbon::parse($tanggal)->startOfDay();
            $akhirPeriode = $mulaiPeriode->copy()->addDay();
        } else {
            $mulaiPeriode = Carbon::createFromFormat('!Y-m', $bulan);
            $akhirPeriode = $mulaiPeriode->copy()->addMonth();
        }
        $query->where('created_at', '>=', $mulaiPeriode)
            ->where('created_at', '<', $akhirPeriode);

        // Total and status counts share the same created_at period filter.
        $totalLaporan       = (clone $query)->count();
        $menungguVerifikasi = (clone $query)->where('status_penanganan', 'Menunggu Verifikasi')->count();
        $terverifikasi      = (clone $query)->where('status_penanganan', 'Sedang Diproses')->count();
        $diproses           = (clone $query)->where('status_penanganan', 'Diproses')->count();
        $selesai            = (clone $query)->where('status_penanganan', 'Selesai')->count();
        $ditolak            = (clone $query)->where('status_penanganan', 'Ditolak')->count();

        // Ambil data laporan terbaru sesuai filter periode
        $laporanTerbaru     = (clone $query)->latest()->take(5)->get();

        return view('dashboard', compact(
            'totalLaporan',
            'menungguVerifikasi',
            'terverifikasi',
            'diproses',
            'selesai',
            'ditolak',
            'laporanTerbaru',
            'filter',
            'tanggal',
            'bulan'
        ));
    }

    /**
     * WEB: Daftar Seluruh Laporan
     */
    public function index(Request $request)
    {
        $query = Laporan::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_laporan', 'like', "%{$search}%")
                  ->orWhere('nama_pelapor', 'like', "%{$search}%")
                  ->orWhere('lokasi_kejadian', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kategori')) {
            $kategori = trim($request->kategori);
            $query->where(function ($q) use ($kategori) {
                $q->where('kategori', 'like', "%{$kategori}%");
            });
        }

        if ($request->filled('status')) {
            $status = trim($request->status);
            if ($status === 'Sedang Diproses') {
                $query->whereIn('status_penanganan', ['Sedang Diproses', 'Diproses']);
            } else {
                $query->where('status_penanganan', $status);
            }
        }

        $laporans = $query->with('petugas:id,name')->latest()->paginate(10)->withQueryString();

        return view('laporan.index', compact('laporans'));
    }

    /**
     * Detail Satu Laporan
     */
    public function show($id)
    {
        $laporan = Laporan::where('id_laporan', $id)->firstOrFail();
        $laporan->load('petugas:id,name');
        $petugasAktif = User::query()
            ->where('role', 'petugas')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'no_id_card']);

        return view('laporan.show', compact('laporan', 'petugasAktif'));
    }

    /**
     * Berkas Bukti Lampiran
     */
    public function evidence(Request $request, $id, int $index)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $laporan = Laporan::where('id_laporan', $id)->firstOrFail();
        $files = array_values(array_filter(array_map('trim', explode(',', (string) $laporan->bukti_lampiran))));
        $path = $files[$index] ?? abort(404);

        abort_unless(str_starts_with($path, 'laporan/' . $laporan->id_laporan . '/') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path), ['Cache-Control' => 'private, no-store'], 'inline');
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status_penanganan' => ['sometimes', 'required', Rule::in(['Menunggu Verifikasi', 'Sedang Diproses', 'Ditolak'])],
            'status' => ['sometimes', 'required', Rule::in(['Menunggu Verifikasi', 'Sedang Diproses', 'Ditolak'])],
            'assigned_petugas_id' => [
                'required_if:status_penanganan,Sedang Diproses',
                'required_if:status,Sedang Diproses',
                'nullable',
                'integer',
                Rule::exists('user', 'id')->where(fn ($query) => $query
                    ->where('role', 'petugas')
                    ->where('is_active', true)),
            ],
            'catatan_admin' => ['nullable', 'string', 'max:5000'],
            'pesan_pelapor' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($validated['status_penanganan'], $validated['status'])
            && $validated['status_penanganan'] !== $validated['status']) {
            return back()
                ->withErrors(['status_penanganan' => 'Status yang dikirim tidak konsisten.'])
                ->withInput();
        }

        $statusBaru = $validated['status_penanganan'] ?? $validated['status'] ?? null;
        $pesanPelapor = trim($validated['pesan_pelapor'] ?? '');

        $hasil = DB::transaction(function () use ($id, $validated, $statusBaru, $pesanPelapor): string {
            $laporan = Laporan::where('id_laporan', $id)->lockForUpdate()->firstOrFail();

            if ($laporan->status_penanganan === 'Selesai') {
                return 'completed';
            }

            if ($statusBaru !== null && $statusBaru !== $laporan->status_penanganan) {
                if ($laporan->status_penanganan !== 'Menunggu Verifikasi'
                    || ! in_array($statusBaru, ['Sedang Diproses', 'Ditolak'], true)) {
                    return 'invalid_transition';
                }

                if ($statusBaru === 'Sedang Diproses') {
                    $petugas = User::query()
                        ->where('role', 'petugas')
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->find($validated['assigned_petugas_id']);

                    if (! $petugas) {
                        return 'inactive_petugas';
                    }

                    if ($laporan->assigned_petugas_id !== null
                        && (int) $laporan->assigned_petugas_id !== (int) $petugas->id) {
                        return 'assignment_exists';
                    }

                    $laporan->assigned_petugas_id = $petugas->id;
                }

                $laporan->status_penanganan = $statusBaru;
            }

            $riwayat = $laporan->riwayat_status ?? [];
            $statusBerubah = $statusBaru !== null && $statusBaru !== $laporan->getOriginal('status_penanganan');

            if ($statusBerubah || $pesanPelapor !== '') {
                $riwayat[] = [
                    'status' => $laporan->status_penanganan,
                    'pesan' => $pesanPelapor !== '' ? $pesanPelapor : null,
                    'pada' => now()->toIso8601String(),
                    'oleh' => auth()->user()->name,
                ];
                $laporan->riwayat_status = $riwayat;
                $laporan->notifikasi_dihapus = false;
            }

            if (array_key_exists('catatan_admin', $validated)) {
                $laporan->catatan_admin = $validated['catatan_admin'];
            }

            if ($pesanPelapor !== '') {
                $laporan->pesan_pelapor = $pesanPelapor;
            }

            $laporan->save();

            return 'updated';
        });

        if ($hasil !== 'updated') {
            $message = match ($hasil) {
                'completed' => 'Laporan yang sudah selesai tidak dapat diubah.',
                'inactive_petugas' => 'Petugas yang dipilih sudah tidak aktif. Silakan pilih Petugas aktif.',
                'assignment_exists' => 'Laporan ini sudah memiliki Petugas. Assignment yang ada tidak diubah.',
                default => 'Admin hanya dapat memverifikasi atau menolak laporan yang masih menunggu verifikasi.',
            };

            return redirect()
                ->route('laporan.show', $id)
                ->with('error', $message);
        }

        return redirect()->route('laporan.index')
            ->with('success', 'Status laporan berhasil diperbarui!');
    }

    public function assignPetugas(Request $request, int $id)
    {
        $validated = $request->validate([
            'assigned_petugas_id' => [
                'required',
                'integer',
                Rule::exists('user', 'id')->where(fn ($query) => $query
                    ->where('role', 'petugas')
                    ->where('is_active', true)),
            ],
        ]);

        $hasil = DB::transaction(function () use ($id, $validated): string {
            $laporan = Laporan::where('id_laporan', $id)->lockForUpdate()->firstOrFail();

            if ($laporan->status_penanganan !== 'Sedang Diproses') {
                return 'invalid_status';
            }

            if ($laporan->assigned_petugas_id !== null) {
                return 'already_assigned';
            }

            $petugas = User::query()
                ->where('role', 'petugas')
                ->where('is_active', true)
                ->lockForUpdate()
                ->find($validated['assigned_petugas_id']);

            if (! $petugas) {
                return 'inactive_petugas';
            }

            $laporan->assigned_petugas_id = $petugas->id;
            $laporan->save();

            return 'assigned';
        });

        if ($hasil !== 'assigned') {
            return back()->with('error', match ($hasil) {
                'invalid_status' => 'Petugas hanya dapat ditetapkan pada laporan berstatus Sedang Diproses.',
                'already_assigned' => 'Laporan ini sudah memiliki Petugas; assignment tidak diubah.',
                default => 'Petugas yang dipilih sudah tidak aktif. Silakan pilih Petugas aktif.',
            });
        }

        return redirect()
            ->route('laporan.show', $id)
            ->with('success', 'Petugas berhasil ditetapkan tanpa mengubah status atau riwayat laporan.');
    }

    /**
     * Halaman chat laporan untuk Petugas / Admin (Web).
     */
    public function chat(Request $request, int $id, ChatService $chat)
    {
        $laporan = Laporan::findOrFail($id);

        return view('laporan.chat', [
            'laporan' => $laporan,
            'chatContext' => $chat->context($request->user(), $laporan->id_laporan),
        ]);
    }

    /**
     * Sesi Firebase untuk halaman chat Petugas / Admin (Web).
     */
    public function chatSession(Request $request, int $id, ChatService $chat)
    {
        $laporan = Laporan::findOrFail($id);

        try {
            $data = $chat->session($request->user(), $laporan->id_laporan);
        } catch (FirebaseException $exception) {
            return response()
                ->json(['status' => 'error', 'message' => $exception->getMessage()], $exception->status)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()
            ->json(['status' => 'success', 'data' => $data])
            ->header('Cache-Control', 'private, no-store');
    }
}
