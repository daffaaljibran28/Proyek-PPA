<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPetugasController extends Controller
{
    public function index(): View
    {
        $petugas = User::query()
            ->where('role', 'petugas')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'no_id_card', 'is_active']);

        return view('petugas.index', compact('petugas'));
    }

    public function create(): View
    {
        return view('petugas.form', ['petugas' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:user,email'],
            'no_id_card' => ['required', 'string', 'max:100', 'unique:user,no_id_card'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'no_id_card' => $validated['no_id_card'],
            'password' => Hash::make($validated['password']),
            'role' => 'petugas',
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.petugas.index')
            ->with('success', 'Akun Petugas berhasil ditambahkan.');
    }

    public function edit(int $id): View
    {
        $petugas = User::query()
            ->where('role', 'petugas')
            ->findOrFail($id);

        return view('petugas.form', compact('petugas'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $petugas = User::query()
            ->where('role', 'petugas')
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('user', 'email')->ignore($petugas->id)],
            'no_id_card' => ['required', 'string', 'max:100', Rule::unique('user', 'no_id_card')->ignore($petugas->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $petugas->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'no_id_card' => $validated['no_id_card'],
        ]);

        if (! empty($validated['password'])) {
            $petugas->password = Hash::make($validated['password']);
        }

        $petugas->save();

        return redirect()
            ->route('admin.petugas.index')
            ->with('success', 'Akun Petugas berhasil diperbarui.');
    }

    public function toggleStatus(int $id): RedirectResponse
    {
        $statusDiubah = DB::transaction(function () use ($id): ?string {
            $petugas = User::query()
                ->where('role', 'petugas')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($petugas->is_active) {
                $laporanAktif = $petugas->assignedReports()
                    ->whereIn('status_penanganan', ['Sedang Diproses', 'Diproses'])
                    ->lockForUpdate()
                    ->get(['id_laporan']);

                if ($laporanAktif->isNotEmpty()) {
                    return null;
                }

                $petugas->is_active = false;
                $petugas->save();
                $petugas->tokens()->delete();

                return 'deactivated';
            }

            $petugas->is_active = true;
            $petugas->save();

            return 'activated';
        });

        if ($statusDiubah === null) {
            return back()->with(
                'error',
                'Akun Petugas tidak dapat dinonaktifkan karena masih memiliki laporan aktif. Alihkan laporan kepada Petugas aktif lain atau tunggu hingga laporan selesai.'
            );
        }

        return back()->with(
            'success',
            $statusDiubah === 'activated'
                ? 'Akun Petugas berhasil diaktifkan.'
                : 'Akun Petugas berhasil dinonaktifkan dan token aksesnya dicabut.'
        );
    }
}
