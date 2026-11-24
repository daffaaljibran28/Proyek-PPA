<x-app-layout>
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('laporan.index') }}" aria-label="Kembali ke daftar laporan" title="Kembali ke daftar laporan" class="grid h-10 w-10 place-items-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:-translate-x-0.5 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7 7-7-7 7-7"/></svg>
            </a>
            <h2 class="text-xl font-bold text-rose-600">Detail Laporan {{ $laporan->nomor_laporan }}</h2>
        </div>
        <span class="bg-amber-50 text-amber-600 border border-amber-200 px-4 py-1.5 rounded-full text-xs font-bold">
            {{ $laporan->status_penanganan }}
        </span>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-xl font-semibold">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-xl font-semibold">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid min-w-0 grid-cols-1 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <!-- Kolom Data Utama -->
        <div class="min-w-0 space-y-6">
            <div class="min-w-0 rounded-2xl border border-rose-100 bg-white p-6 shadow-sm">
                <h3 class="font-bold text-rose-600 mb-4 border-b pb-2">Data Utama Laporan</h3>
                <div class="grid grid-cols-2 gap-y-4 text-sm">
                    <div>
                        <p class="text-gray-400 text-xs">Nomor Laporan</p>
                        <p class="font-bold text-rose-600">{{ $laporan->nomor_laporan }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Kategori Kejadian</p>
                        <p class="font-semibold text-gray-800">{{ $laporan->kategori }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Nama Pelapor</p>
                        <p class="font-semibold text-gray-800">{{ $laporan->nama_pelapor ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Melapor Sebagai</p>
                        <p class="font-semibold text-gray-800">{{ $laporan->sebagai }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Lokasi Kejadian</p>
                        <p class="font-semibold text-gray-800">{{ $laporan->lokasi_kejadian ?: '-' }}</p>
                        @if($laporan->lokasi_kejadian)
                            <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($laporan->lokasi_kejadian) }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                Buka di Google Maps
                            </a>
                        @endif
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs">Tanggal Kejadian</p>
                        <p class="font-semibold text-gray-800">
                            {{ optional($laporan->tanggal_kejadian)->format('d M Y') ?? '-' }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 min-w-0 border-t border-gray-100 pt-4">
                    <p class="font-bold text-gray-800 text-sm mb-2">Penjelasan Kronologi Kejadian</p>
                    <p class="min-w-0 max-w-full whitespace-pre-wrap break-words [overflow-wrap:anywhere] rounded-xl bg-gray-50 p-4 text-sm leading-relaxed text-gray-600">
                        {{ $laporan->kronologi }}
                    </p>
                </div>
            </div>

            <!-- Bukti Lampiran -->
            <div class="bg-white p-6 rounded-2xl border border-rose-100 shadow-sm">
                <h3 class="font-bold text-rose-600 mb-4">Bukti Lampiran</h3>
                @if($laporan->bukti_lampiran)
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach(array_values(array_filter(array_map('trim', explode(',', $laporan->bukti_lampiran)))) as $index => $path)
                            <a href="{{ str_starts_with($path, 'laporan/' . $laporan->id_laporan . '/') ? route('laporan.evidence', [$laporan->id_laporan, $index]) : asset('storage/' . $path) }}" target="_blank" rel="noopener" class="block">
                                @if(str_starts_with($path, 'laporan/' . $laporan->id_laporan . '/'))
                                    <img src="{{ route('laporan.evidence', [$laporan->id_laporan, $index]) }}" alt="Bukti foto pengaduan" class="aspect-square w-full rounded-lg border border-gray-200 object-cover">
                                @else
                                    <span class="block break-all rounded-lg border border-gray-200 p-3 text-sm text-gray-700">{{ $path }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-400 text-xs italic">Tidak ada bukti lampiran yang disertakan.</p>
                @endif
            </div>
        </div>

        <!-- Kolom Update Status -->
        <div class="min-w-0 space-y-6">
            <!-- Tombol Chat Petugas -->
            <div class="rounded-2xl border border-rose-100 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-gray-800">Ruang Chat Laporan</h3>
                        <p class="truncate text-xs text-gray-500">Pelapor: <strong class="text-gray-700">{{ $laporan->nama_pelapor ?? 'Pelapor' }}</strong></p>
                    </div>
                </div>
                <p class="text-xs leading-relaxed text-gray-500 mb-4">
                    Komunikasi langsung secara realtime dengan Pelapor mengenai tindak lanjut dan klarifikasi laporan ini.
                </p>
                <a href="{{ route('laporan.chat', $laporan->id_laporan) }}" class="flex w-full items-center justify-center gap-2 rounded-xl bg-rose-500 py-2.5 text-xs font-bold text-white shadow-md shadow-rose-200 transition hover:bg-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-200">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z"/></svg>
                    Buka Chat Petugas
                </a>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-rose-100 shadow-sm">
                <h3 class="font-bold text-rose-600 mb-4">Tindak Lanjut Laporan</h3>
                @if ($laporan->status_penanganan === 'Selesai')
                    <p class="rounded-xl bg-emerald-50 p-4 text-sm leading-6 text-emerald-800">Laporan sudah selesai. Data dan status dikunci dan hanya dapat dilihat.</p>
                @elseif ($laporan->status_penanganan === 'Menunggu Verifikasi')
                    <form action="{{ route('laporan.update-status', $laporan->id_laporan) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="status_penanganan" class="mb-1 block text-xs font-bold text-gray-700">Keputusan Admin</label>
                            <select id="status_penanganan" name="status_penanganan" required class="w-full rounded-xl border-gray-200 p-3 text-xs focus:border-rose-500 focus:ring-rose-500">
                                <option value="">Pilih keputusan</option>
                                <option value="Sedang Diproses" @selected(old('status_penanganan') === 'Sedang Diproses')>Verifikasi dan tugaskan Petugas</option>
                                <option value="Ditolak" @selected(old('status_penanganan') === 'Ditolak')>Tolak laporan</option>
                            </select>
                            @error('status_penanganan')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="assigned_petugas_id" class="mb-1 block text-xs font-bold text-gray-700">Petugas aktif</label>
                            <select id="assigned_petugas_id" name="assigned_petugas_id" class="w-full rounded-xl border-gray-200 p-3 text-xs focus:border-rose-500 focus:ring-rose-500">
                                <option value="">Pilih Petugas (wajib untuk verifikasi)</option>
                                @foreach ($petugasAktif as $petugas)
                                    <option value="{{ $petugas->id }}" @selected((string) old('assigned_petugas_id', $laporan->assigned_petugas_id) === (string) $petugas->id)>
                                        {{ $petugas->name }}{{ $petugas->no_id_card ? ' - '.$petugas->no_id_card : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_petugas_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            <p class="mt-1 text-xs text-gray-500">Pilihan Petugas tidak diperlukan jika laporan ditolak.</p>
                        </div>

                        <div>
                            <label for="catatan_admin" class="mb-1 block text-xs font-bold text-gray-700">Catatan internal (tidak terlihat pelapor)</label>
                            <textarea id="catatan_admin" name="catatan_admin" rows="3" class="w-full rounded-xl border-gray-200 p-3 text-xs focus:border-rose-500 focus:ring-rose-500" placeholder="Catatan internal Admin...">{{ old('catatan_admin', $laporan->catatan_admin) }}</textarea>
                            @error('catatan_admin')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="pesan_pelapor" class="mb-1 block text-xs font-bold text-gray-700">Pesan untuk Pelapor</label>
                            <textarea id="pesan_pelapor" name="pesan_pelapor" rows="3" maxlength="2000" class="w-full rounded-xl border-gray-200 p-3 text-xs focus:border-rose-500 focus:ring-rose-500" placeholder="Pesan yang dapat dibaca pelapor...">{{ old('pesan_pelapor') }}</textarea>
                            @error('pesan_pelapor')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-rose-500 py-2.5 text-xs font-bold text-white shadow-md shadow-rose-200 hover:bg-rose-600">Simpan Keputusan</button>
                    </form>
                @elseif ($laporan->status_penanganan === 'Sedang Diproses' && $laporan->assigned_petugas_id === null)
                    <p class="mb-4 text-sm leading-6 text-amber-800">Laporan ini sudah diverifikasi, tetapi belum memiliki Petugas. Tetapkan Petugas tanpa mengubah status atau riwayat.</p>
                    <form action="{{ route('laporan.assign-petugas', $laporan->id_laporan) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="assigned_petugas_id" class="mb-1 block text-xs font-bold text-gray-700">Petugas aktif</label>
                            <select id="assigned_petugas_id" name="assigned_petugas_id" required class="w-full rounded-xl border-gray-200 p-3 text-xs focus:border-rose-500 focus:ring-rose-500">
                                <option value="">Pilih Petugas</option>
                                @foreach ($petugasAktif as $petugas)
                                    <option value="{{ $petugas->id }}" @selected((string) old('assigned_petugas_id') === (string) $petugas->id)>
                                        {{ $petugas->name }}{{ $petugas->no_id_card ? ' - '.$petugas->no_id_card : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assigned_petugas_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-rose-500 py-2.5 text-xs font-bold text-white hover:bg-rose-600">Tetapkan Petugas</button>
                    </form>
                @else
                    <p class="text-sm leading-6 text-gray-600">
                        Status: <strong>{{ $laporan->status_penanganan }}</strong>
                    </p>
                    <p class="mt-2 text-sm leading-6 text-gray-600">
                        Petugas:
                        <strong>{{ $laporan->petugas?->name ?? '-' }}</strong>
                    </p>
                    <p class="mt-3 text-xs text-gray-500">Perubahan status menjadi Diproses atau Selesai dilakukan oleh Petugas.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>