@extends('pelapor.layout')

@section('title', 'Beranda')

@section('content')
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-2xl font-bold text-rose-600">Selamat datang, {{ auth()->user()->name }}</h1>
            <p class="mt-2 text-xs text-slate-600">Kelola pengaduan dan pantau status penanganannya.</p>
        </div>
        @if (!$laporanTerakhir || in_array($laporanTerakhir->status_penanganan, ['Selesai', 'Ditolak'], true))
            <a href="{{ route('pelapor.laporan.create') }}" class="inline-flex items-center justify-center rounded-lg bg-rose-500 px-4 py-2.5 text-xs font-semibold text-white shadow-md shadow-rose-200 transition hover:bg-rose-600">Buat Pengaduan</a>
        @endif
    </div>

    <section class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-slate-500">Total Pengaduan</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 1.414.293l5.707 5.707A1 1 0 0 1 20 9.707V19a2 2 0 0 1-2 2Z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-extrabold tracking-tight text-slate-800">{{ $jumlahLaporan }}</p>
        </div>
        <a href="{{ route('pelapor.laporan.index', ['status' => 'Menunggu Verifikasi']) }}" class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm transition hover:border-amber-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-amber-200">
            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-slate-500">Menunggu Verifikasi</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-extrabold tracking-tight text-amber-700">{{ $menunggu }}</p>
        </a>
        <a href="{{ route('pelapor.laporan.index', ['status' => 'Sedang Diproses']) }}" class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-200">
            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-slate-500">Sedang Diproses</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5m-4.5-1A8 8 0 0 1 18 6l2 3M20 20v-5h-5m4.5 1A8 8 0 0 1 6 18l-2-3"/></svg>
                </span>
            </div>
            <p class="text-2xl font-extrabold tracking-tight text-blue-700">{{ $diproses }}</p>
        </a>
        <a href="{{ route('pelapor.laporan.index', ['status' => 'Selesai']) }}" class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-200">
            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-slate-500">Selesai</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-extrabold tracking-tight text-emerald-700">{{ $selesai }}</p>
        </a>
    </section>

    <section class="mt-7 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-center justify-between gap-3"><h2 class="text-base font-bold text-slate-800">Pengaduan Terbaru</h2><a href="{{ route('pelapor.laporan.index') }}" class="text-xs font-semibold text-rose-600 hover:underline">Lihat semua</a></div>
        @if ($laporanTerakhir)
            <div class="mt-4 flex flex-col gap-4 rounded-lg border border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-xs font-bold text-rose-600">{{ $laporanTerakhir->nomor_laporan }}</p><p class="mt-1 text-xs font-medium text-slate-700">{{ $laporanTerakhir->kategori }}</p><p class="mt-1 text-xs text-slate-500">{{ optional($laporanTerakhir->tanggal_kejadian)->format('d M Y') }}</p></div>
                <div class="flex items-center gap-3"><span class="rounded-full px-3 py-1 text-[11px] font-semibold {{ $laporanTerakhir->status_penanganan === 'Selesai' ? 'bg-emerald-50 text-emerald-700' : ($laporanTerakhir->status_penanganan === 'Sedang Diproses' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700') }}">{{ $laporanTerakhir->status_penanganan }}</span><a href="{{ route('pelapor.laporan.show', $laporanTerakhir->id_laporan) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Detail</a></div>
            </div>
        @else
            <p class="mt-4 rounded-lg bg-slate-50 px-4 py-5 text-xs text-slate-600">Belum ada pengaduan. Pengaduan yang Anda buat akan tampil di sini.</p>
        @endif
    </section>

    <section class="mt-7 grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-slate-800">Pembaruan Terbaru</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($pelaporNotifications->take(5) as $pembaruan)
                    <a href="{{ route('pelapor.laporan.show', $pembaruan['id_laporan']) }}" class="block min-w-0 py-3 first:pt-0">
                        <span class="block break-words text-xs font-semibold text-slate-800 [overflow-wrap:anywhere]">{{ $pembaruan['status'] ?? 'Pembaruan Pengaduan' }} · {{ $pembaruan['nomor_laporan'] }}</span>
                        <span class="mt-1 block break-words text-xs leading-5 text-slate-600 [overflow-wrap:anywhere]">{{ $pembaruan['pesan'] ?? $pembaruan['kategori'] }}</span>
                        @if (!empty($pembaruan['pada']))<time class="mt-1 block text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($pembaruan['pada'])->format('d M Y, H:i') }}</time>@endif
                    </a>
                @empty
                    <p class="py-3 text-xs text-slate-500">Belum ada pembaruan status atau pesan.</p>
                @endforelse
            </div>
        </div>
        <div class="min-w-0 rounded-xl border border-rose-200 bg-rose-50 p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold text-rose-700">Butuh bantuan segera?</p>
            <p class="mt-2 text-xs leading-6 text-slate-700">Hubungi layanan darurat yang tersedia kapan pun diperlukan.</p>
            <a href="{{ route('pelapor.information') }}" class="mt-4 inline-flex text-xs font-semibold text-rose-700 hover:underline">Lihat informasi layanan dan kontak</a>
        </div>
    </section>
@endsection