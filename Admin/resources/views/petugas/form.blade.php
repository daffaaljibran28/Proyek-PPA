<x-app-layout>
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('admin.petugas.index') }}" aria-label="Kembali ke daftar Petugas" class="grid h-10 w-10 place-items-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7 7-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-rose-600">{{ $petugas->exists ? 'Edit Petugas' : 'Tambah Petugas' }}</h2>
            <p class="mt-1 text-xs text-gray-500">Isi data akun Petugas.</p>
        </div>
    </div>

    <div class="max-w-2xl rounded-2xl border border-rose-100 bg-white p-6 shadow-sm">
        <form
            action="{{ $petugas->exists ? route('admin.petugas.update', $petugas->id) : route('admin.petugas.store') }}"
            method="POST"
            class="space-y-5"
        >
            @csrf
            @if ($petugas->exists)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="mb-1 block text-xs font-bold text-gray-700">Nama</label>
                <input id="name" name="name" type="text" value="{{ old('name', $petugas->name) }}" required maxlength="255" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100">
                @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="mb-1 block text-xs font-bold text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $petugas->email) }}" required maxlength="255" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100">
                @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="no_id_card" class="mb-1 block text-xs font-bold text-gray-700">No. ID Card</label>
                <input id="no_id_card" name="no_id_card" type="text" value="{{ old('no_id_card', $petugas->no_id_card) }}" required maxlength="100" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100">
                @error('no_id_card')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="mb-1 block text-xs font-bold text-gray-700">
                    Password{{ $petugas->exists ? ' baru (opsional)' : '' }}
                </label>
                <input id="password" name="password" type="password" {{ $petugas->exists ? '' : 'required' }} minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100">
                @if ($petugas->exists)
                    <p class="mt-1 text-xs text-gray-500">Kosongkan jika password tidak ingin diubah.</p>
                @endif
                @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-xs font-bold text-gray-700">Konfirmasi Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" {{ $petugas->exists ? '' : 'required' }} minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100">
            </div>

            @if ($petugas->exists)
                <p class="text-xs text-gray-500">
                    Status akun:
                    <span class="font-bold {{ $petugas->is_active ? 'text-emerald-700' : 'text-gray-600' }}">
                        {{ $petugas->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                    (tidak dapat diubah dari formulir ini)
                </p>
            @endif

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.petugas.index') }}" class="rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-bold text-gray-600 transition hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit" class="rounded-xl bg-rose-500 px-5 py-2.5 text-xs font-bold text-white transition hover:bg-rose-600">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
