<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sahabat PPA') | Pelapor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        details > summary::-webkit-details-marker { display: none; }
        details.pelapor-menu[open] > summary .pelapor-menu-chevron { transform: rotate(180deg); }
    </style>
</head>
<body class="min-h-screen bg-pink-50/40 text-slate-800 antialiased">
    <div class="min-h-screen md:flex">
        <aside class="hidden min-h-screen w-64 shrink-0 flex-col justify-between border-r border-gray-100 bg-white p-6 md:flex">
            <div>
            <a href="{{ route('pelapor.home') }}" class="mb-8 flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-rose-500 p-2 text-white shadow-md shadow-rose-200">
                    <svg class="h-full w-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        <path d="M12 5 9.04 7.96a2.17 2.17 0 0 0 0 3.08v0c.82.82 2.13.85 3 .07l2.07-1.9"/>
                        <path d="m14 10 1.5 1.5"/>
                    </svg>
                </span>
                <span><span class="block text-lg font-extrabold leading-none text-rose-600">SAPA</span><span class="text-xs text-gray-400">Sistem Pelaporan Aman</span></span>
            </a>
            <nav class="space-y-1.5 text-sm">
                <a href="{{ route('pelapor.home') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition {{ request()->routeIs('pelapor.home') ? 'bg-rose-500 text-white shadow-md shadow-rose-200' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}" @if(request()->routeIs('pelapor.home')) aria-current="page" @endif>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10Z"/></svg>
                    <span>Beranda</span>
                </a>
                <details class="group pelapor-menu rounded-xl" {{ request()->routeIs('pelapor.laporan.create') || request()->routeIs('pelapor.emergency') ? 'open' : '' }}>
                    <summary class="flex cursor-pointer list-none items-center justify-between rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('pelapor.laporan.create') || request()->routeIs('pelapor.emergency') ? 'bg-rose-500 text-white shadow-md shadow-rose-200' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <span class="flex items-center gap-3">
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3h10l4 4v14H5z"/></svg>
                            <span>Buat Pengaduan</span>
                        </span>
                        <svg class="pelapor-menu-chevron h-4 w-4 shrink-0 transition-transform duration-200 group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="ml-3 space-y-1 border-l border-rose-100 pl-3">
                        <a href="{{ route('pelapor.laporan.create') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm {{ request()->routeIs('pelapor.laporan.create') ? 'bg-rose-50 font-semibold text-rose-700' : 'font-medium text-gray-600 hover:bg-gray-50' }}">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 3v5h5m-9 5h4m-4 4h6"/></svg>
                            <span>Laporan Biasa</span>
                        </a>
                        <a href="{{ route('pelapor.emergency') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm {{ request()->routeIs('pelapor.emergency') ? 'bg-rose-50 font-semibold text-rose-700' : 'font-medium text-gray-600 hover:bg-gray-50' }}">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.6 3.8 9 3.2l2 4.8-2 1.4a15 15 0 0 0 5.6 5.6l1.4-2 4.8 2-.6 2.4a3 3 0 0 1-3.3 2.2A17 17 0 0 1 4.4 7.1a3 3 0 0 1 2.2-3.3Z"/></svg>
                            <span>Emergency Call</span>
                        </a>
                    </div>
                </details>
                <a href="{{ route('pelapor.laporan.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('pelapor.laporan.*') && !request()->routeIs('pelapor.laporan.create') ? 'bg-rose-500 text-white shadow-md shadow-rose-200' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}" @if(request()->routeIs('pelapor.laporan.*') && !request()->routeIs('pelapor.laporan.create')) aria-current="page" @endif>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                    <span>Riwayat Pengaduan</span>
                </a>
                <a href="{{ route('pelapor.information') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('pelapor.information') ? 'bg-rose-500 text-white shadow-md shadow-rose-200' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}" @if(request()->routeIs('pelapor.information')) aria-current="page" @endif>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>
                    <span>Informasi & Edukasi</span>
                </a>
                <a href="{{ route('pelapor.profile') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('pelapor.profile') ? 'bg-rose-500 text-white shadow-md shadow-rose-200' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}" @if(request()->routeIs('pelapor.profile')) aria-current="page" @endif>
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                    <span>Profil</span>
                </a>
            </nav>
            </div>
            <div class="border-t border-gray-100 pt-4">
                <div class="flex items-center justify-between gap-2 rounded-2xl p-2">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-rose-500 text-sm font-bold text-white shadow-sm">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold leading-tight text-gray-800">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-gray-400">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('pelapor.logout') }}">@csrf<button aria-label="Keluar" title="Keluar" class="grid h-8 w-8 shrink-0 place-items-center rounded-xl text-gray-400 transition hover:bg-rose-100 hover:text-rose-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1"/></svg>
                    </button></form>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="border-b border-gray-100 bg-white px-4 py-4 sm:px-7 md:px-10">
                <div class="mx-auto flex max-w-6xl items-center gap-4">
                    <div><p class="text-xs font-medium text-rose-600">SAHABAT PPA</p><p class="text-xs font-semibold text-gray-800">Ruang Pelapor</p></div>
                    <div class="ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
                    <span class="hidden max-w-52 truncate rounded-full bg-rose-50 px-4 py-2 text-xs text-slate-600 sm:block">Halo, <strong class="font-semibold text-rose-600">{{ auth()->user()->name }}</strong></span>
                    <details class="pelapor-menu relative">
                        <summary aria-label="Notifikasi" title="Notifikasi" class="grid h-10 w-10 cursor-pointer list-none place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-rose-50 hover:text-rose-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0m6 0H9"/></svg>
                            @if ($pelaporNotifications->contains(fn ($notification) => !$notification['read']))<span class="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $pelaporNotifications->filter(fn ($notification) => !$notification['read'])->count() }}</span>@endif
                        </summary>
                        <div class="absolute right-0 z-30 mt-2 max-h-96 w-[min(22rem,calc(100vw-2rem))] overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                            <div class="flex items-center justify-between gap-2 px-2 py-1"><p class="text-sm font-bold text-gray-800">Pembaruan Pengaduan</p>
                                @if ($pelaporNotifications->contains(fn ($notification) => $notification['read']))
                                    <form method="POST" action="{{ route('pelapor.notifications.destroy-read') }}">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-600 hover:underline">Hapus semua yang sudah dibaca</button></form>
                                @endif
                            </div>
                            @forelse ($pelaporNotifications as $notification)
                                <div class="flex items-start gap-1 rounded-lg px-1 py-1 {{ $notification['read'] ? '' : 'bg-rose-50/70' }}">
                                    <form method="POST" action="{{ route('pelapor.notifications.read', [$notification['id_laporan'], $notification['event_index']]) }}" class="min-w-0 flex-1">
                                        @csrf
                                        <button type="submit" class="block w-full rounded-lg px-2 py-2 text-left hover:bg-rose-50">
                                            <span class="flex items-center justify-between gap-2"><span class="block break-words text-xs font-semibold text-rose-600">{{ $notification['nomor_laporan'] }} · {{ $notification['status'] ?? 'Pembaruan' }}</span><span class="shrink-0 text-[10px] {{ $notification['read'] ? 'text-slate-400' : 'font-semibold text-rose-600' }}">{{ $notification['read'] ? 'Sudah dibaca' : 'Belum dibaca' }}</span></span>
                                            @if (!empty($notification['pesan']))<span class="mt-1 block break-words text-sm text-slate-700 [overflow-wrap:anywhere]">{{ $notification['pesan'] }}</span>@else<span class="mt-1 block break-words text-xs text-slate-500 [overflow-wrap:anywhere]">{{ $notification['kategori'] }}</span>@endif
                                            <time class="mt-1 block text-xs text-slate-400">{{ !empty($notification['pada']) ? \Illuminate\Support\Carbon::parse($notification['pada'])->format('d M Y, H:i') : '' }}</time>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('pelapor.notifications.destroy', [$notification['id_laporan'], $notification['event_index']]) }}" class="shrink-0">
                                        @csrf @method('DELETE')
                                        <button type="submit" aria-label="Hapus notifikasi" title="Hapus notifikasi" class="mt-1 grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M18 6 6 18M6 6l12 12"/></svg></button>
                                    </form>
                                </div>
                            @empty
                                <p class="px-2 py-4 text-sm text-slate-500">Belum ada pembaruan status.</p>
                            @endforelse
                        </div>
                    </details>
                    <form method="POST" action="{{ route('pelapor.logout') }}" class="md:hidden">@csrf<button aria-label="Keluar" title="Keluar" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">Keluar</button></form>
                    </div>
                </div>
                <nav class="mx-auto mt-4 flex max-w-6xl gap-2 overflow-x-auto whitespace-nowrap text-sm md:hidden">
                    <a href="{{ route('pelapor.home') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('pelapor.home') ? 'bg-rose-50 font-semibold text-rose-700' : 'text-slate-600' }}">Beranda</a>
                    <details class="relative">
                        <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg px-3 py-2 text-slate-600 transition hover:bg-rose-50 hover:text-rose-600">
                            Buat Pengaduan
                            <svg class="pelapor-menu-chevron h-4 w-4 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="absolute left-0 z-20 mt-1 min-w-48 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                            <a href="{{ route('pelapor.laporan.create') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-rose-50">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 3v5h5m-9 5h4m-4 4h6"/></svg>
                                <span>Laporan Biasa</span>
                            </a>
                            <a href="{{ route('pelapor.emergency') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-rose-50">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.6 3.8 9 3.2l2 4.8-2 1.4a15 15 0 0 0 5.6 5.6l1.4-2 4.8 2-.6 2.4a3 3 0 0 1-3.3 2.2A17 17 0 0 1 4.4 7.1a3 3 0 0 1 2.2-3.3Z"/></svg>
                                <span>Emergency Call</span>
                            </a>
                        </div>
                    </details>
                    <a href="{{ route('pelapor.laporan.index') }}" class="rounded-lg px-3 py-2 text-slate-600">Riwayat</a>
                    <a href="{{ route('pelapor.information') }}" class="rounded-lg px-3 py-2 text-slate-600">Informasi & Edukasi</a>
                    <a href="{{ route('pelapor.profile') }}" class="rounded-lg px-3 py-2 text-slate-600">Profil</a>
                </nav>
            </header>

            <main class="mx-auto max-w-6xl px-4 py-7 sm:px-7 sm:py-9 md:px-10">
                @if (session('success'))
                    <div role="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div role="alert" class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div role="alert" class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">Mohon periksa kembali isian formulir.</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>