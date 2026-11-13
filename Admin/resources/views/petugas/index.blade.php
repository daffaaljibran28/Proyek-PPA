<x-app-layout>
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-rose-600">Manajemen Petugas</h2>
            <p class="mt-1 text-xs text-gray-500">Daftar akun Petugas SAPA.</p>
        </div>
        <a href="{{ route('admin.petugas.create') }}" class="rounded-xl bg-rose-500 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-rose-600">
            Tambah Petugas
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-rose-100 bg-white p-6 shadow-sm">
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-rose-100 bg-rose-50/50 text-xs font-bold uppercase text-gray-500">
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">No. ID Card</th>
                    <th class="px-4 py-3 text-center">Status Akun</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-xs">
                @forelse ($petugas as $akun)
                    <tr>
                        <td class="px-4 py-4 font-semibold text-gray-800">{{ $akun->name }}</td>
                        <td class="px-4 py-4 text-gray-600">{{ $akun->email }}</td>
                        <td class="px-4 py-4 text-gray-600">{{ $akun->no_id_card }}</td>
                        <td class="px-4 py-4 text-center">
                            <span class="inline-flex rounded-full px-3 py-1 font-bold {{ $akun->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $akun->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center gap-3">
                                <a href="{{ route('admin.petugas.edit', $akun->id) }}" class="font-bold text-rose-600 hover:underline">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('admin.petugas.toggle-status', $akun->id) }}" onsubmit="return confirm('{{ $akun->is_active ? 'Nonaktifkan akun Petugas ini?' : 'Aktifkan kembali akun Petugas ini?' }}')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="font-bold hover:underline {{ $akun->is_active ? 'text-amber-600' : 'text-emerald-600' }}">
                                        {{ $akun->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-gray-400">Belum ada akun Petugas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
