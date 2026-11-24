@extends('pelapor.layout')
@section('title', 'Chat Laporan')
@section('content')
    @php
        $counterpart = $chatContext['counterpart'] ?? null;
        $ready = (bool) ($chatContext['availability']['ready'] ?? false);
        $counterpartName = $counterpart['name'] ?? 'Petugas SAPA';
        $counterpartRole = ($counterpart['role'] ?? 'petugas') === 'petugas' ? 'Petugas' : 'Pengguna';
        $initial = strtoupper(mb_substr($counterpartName, 0, 1));
    @endphp

    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <a href="{{ route('pelapor.laporan.show', $laporan->id_laporan) }}"
               class="inline-flex items-center gap-2 text-sm font-semibold text-rose-700 transition hover:gap-3 hover:text-rose-800">
                <span class="grid h-9 w-9 place-items-center rounded-full border border-rose-100 bg-white shadow-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7 7-7-7 7-7"/></svg>
                </span>
                Detail Pengaduan
            </a>

            <h1 class="mt-3 text-2xl font-bold">Chat Petugas</h1>
            <p class="mt-1 text-sm text-slate-500">
                Percakapan untuk laporan <span class="font-semibold text-rose-600">{{ $laporan->nomor_laporan }}</span>
            </p>
        </div>

        <span class="w-fit rounded-full px-3 py-1.5 text-sm font-semibold
            {{ $laporan->status_penanganan === 'Selesai'
                ? 'bg-emerald-50 text-emerald-700'
                : ($laporan->status_penanganan === 'Sedang Diproses' || $laporan->status_penanganan === 'Diproses'
                    ? 'bg-blue-50 text-blue-700'
                    : ($laporan->status_penanganan === 'Ditolak'
                        ? 'bg-rose-50 text-rose-700'
                        : 'bg-amber-50 text-amber-700')) }}">
            {{ $laporan->status_penanganan }}
        </span>
    </div>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
             id="chat-app"
             data-session-url="{{ route('pelapor.laporan.chat.session', $laporan->id_laporan) }}">
        {{-- Header percakapan --}}
        <header class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-rose-50 text-base font-bold text-rose-600 ring-1 ring-rose-100">
                {{ $initial }}
            </span>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-slate-800">{{ $counterpartName }}</p>
                <p class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="rounded-full bg-rose-50 px-2 py-0.5 font-semibold text-rose-600">{{ $counterpartRole }}</span>
                    <span class="truncate">{{ $laporan->nomor_laporan }} · {{ $laporan->kategori }}</span>
                </p>
            </div>
            <span id="chat-connection" class="ml-auto hidden items-center gap-1.5 rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <span id="chat-connection-text">Terhubung</span>
            </span>
        </header>

        @if (!$counterpart)
            <p class="border-b border-amber-100 bg-amber-50 px-5 py-3 text-xs text-amber-800">
                Laporan Anda belum memiliki Petugas. Pesan tetap tersimpan dan akan dibaca Petugas setelah laporan ditugaskan.
            </p>
        @endif

        {{-- Area pesan --}}
        <div id="chat-messages"
             class="h-[min(30rem,56vh)] space-y-3 overflow-y-auto bg-slate-50/70 px-4 py-5 sm:px-6"
             aria-live="polite">
            <div class="flex h-full flex-col items-center justify-center gap-2 text-center" id="chat-placeholder">
                <span class="grid h-12 w-12 place-items-center rounded-full bg-white text-rose-500 shadow-sm">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.6 9.6 0 0 1-4-.9L3 21l1.9-4.4a8.4 8.4 0 1 1 16.1-5.1Z"/></svg>
                </span>
                <p class="text-sm font-semibold text-slate-700">Memuat percakapan…</p>
                <p class="text-xs text-slate-500">Menyiapkan riwayat pesan laporan ini.</p>
            </div>
        </div>

        <div id="chat-alert" class="hidden border-t border-rose-100 bg-rose-50 px-5 py-3 text-xs text-rose-700" role="alert">
            <span id="chat-alert-text"></span>
            <button type="button" id="chat-retry" class="ml-2 font-bold underline">Coba lagi</button>
        </div>

        {{-- Input pesan --}}
        <form id="chat-form" class="flex items-end gap-2 border-t border-slate-100 bg-white px-3 py-3 sm:px-5 sm:py-4">
            @csrf
            <label for="chat-input" class="sr-only">Tulis pesan</label>
            <textarea id="chat-input" rows="1" maxlength="2000" disabled
                      placeholder="Tulis pesan untuk Petugas…"
                      class="max-h-32 min-h-[2.75rem] w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-rose-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-100 disabled:cursor-not-allowed disabled:opacity-60"></textarea>
            <button type="submit" id="chat-send" disabled
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-rose-500 text-white shadow-md shadow-rose-200 transition hover:bg-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-200 disabled:cursor-not-allowed disabled:bg-rose-300"
                    aria-label="Kirim pesan" title="Kirim pesan">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-7.5-15-7.5 3 7.5-3 7.5Z"/></svg>
            </button>
        </form>
        <p class="px-5 pb-3 text-[11px] text-slate-400">Pesan tersimpan pada Firestore dan hanya dapat dibaca peserta laporan ini.</p>
    </section>

    <script type="application/json" id="chat-bootstrap">
        {!! json_encode([
            'context' => $chatContext,
            'csrf' => csrf_token(),
            'viewerName' => auth()->user()->name,
            'ready' => $ready,
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}
    </script>

    <script type="module">
        import { initializeApp, getApps, getApp } from 'https://www.gstatic.com/firebasejs/10.13.2/firebase-app.js';
        import { getAuth, signInWithCustomToken } from 'https://www.gstatic.com/firebasejs/10.13.2/firebase-auth.js';
        import {
            getFirestore, collection, doc, query, orderBy, limitToLast, onSnapshot,
            writeBatch, serverTimestamp,
        } from 'https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore.js';

        const bootstrap = JSON.parse(document.getElementById('chat-bootstrap').textContent);
        const context = bootstrap.context;
        const chatId = String(context.chat_id);
        const viewer = context.viewer;
        const counterpart = context.counterpart;

        const root = document.getElementById('chat-app');
        const sessionUrl = root.dataset.sessionUrl;
        const messagesEl = document.getElementById('chat-messages');
        const placeholderEl = document.getElementById('chat-placeholder');
        const alertEl = document.getElementById('chat-alert');
        const alertTextEl = document.getElementById('chat-alert-text');
        const retryButton = document.getElementById('chat-retry');
        const formEl = document.getElementById('chat-form');
        const inputEl = document.getElementById('chat-input');
        const sendEl = document.getElementById('chat-send');
        const connectionEl = document.getElementById('chat-connection');
        const connectionTextEl = document.getElementById('chat-connection-text');

        let db = null;
        let uid = null;
        let participants = [];
        let unsubscribe = null;
        let sending = false;
        let markingRead = false;

        function showAlert(message) {
            alertTextEl.textContent = message;
            alertEl.classList.remove('hidden');
        }

        function hideAlert() {
            alertEl.classList.add('hidden');
        }

        function setConnection(online) {
            connectionEl.classList.remove('hidden');
            connectionEl.classList.add('inline-flex');
            connectionEl.classList.toggle('text-emerald-600', online);
            connectionEl.classList.toggle('bg-emerald-50', online);
            connectionEl.classList.toggle('text-slate-500', !online);
            connectionEl.classList.toggle('bg-slate-100', !online);
            connectionTextEl.textContent = online ? 'Terhubung' : 'Koneksi terputus';
        }

        function setInputState() {
            const text = inputEl.value.trim();
            inputEl.disabled = db === null;
            sendEl.disabled = db === null || sending || text.length === 0;
            sendEl.classList.toggle('opacity-60', sendEl.disabled);
        }

        function formatTime(timestamp) {
            if (!timestamp || typeof timestamp.toDate !== 'function') {
                return 'Mengirim…';
            }
            const date = timestamp.toDate();
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }

        function formatDate(timestamp) {
            if (!timestamp || typeof timestamp.toDate !== 'function') return '';
            return timestamp.toDate().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
        }

        function showEmptyState() {
            messagesEl.replaceChildren();
            const wrap = document.createElement('div');
            wrap.className = 'flex h-full flex-col items-center justify-center gap-2 px-6 text-center';

            const icon = document.createElement('span');
            icon.className = 'grid h-12 w-12 place-items-center rounded-full bg-white text-rose-500 shadow-sm';
            icon.innerHTML = '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.6 9.6 0 0 1-4-.9L3 21l1.9-4.4a8.4 8.4 0 1 1 16.1-5.1Z"/></svg>';

            const title = document.createElement('p');
            title.className = 'text-sm font-semibold text-slate-700';
            title.textContent = 'Belum ada percakapan';

            const description = document.createElement('p');
            description.className = 'max-w-sm text-xs leading-5 text-slate-500';
            description.textContent = counterpart
                ? 'Mulai percakapan dengan ' + counterpart.name + ' mengenai laporan ini. Pesan akan tersimpan pada riwayat laporan.'
                : 'Tulis pesan mengenai laporan ini. Pesan akan dibaca Petugas setelah laporan ditugaskan.';

            wrap.append(icon, title, description);
            messagesEl.append(wrap);
        }

        function renderMessages(messages) {
            if (messages.length === 0) {
                showEmptyState();
                return;
            }

            messagesEl.replaceChildren();
            let lastDate = '';

            for (const item of messages) {
                const mine = item.sender_id === uid;
                const dateLabel = formatDate(item.created_at);

                if (dateLabel && dateLabel !== lastDate) {
                    lastDate = dateLabel;
                    const divider = document.createElement('div');
                    divider.className = 'flex items-center gap-3 py-1';
                    divider.innerHTML = '<span class="h-px flex-1 bg-slate-200"></span>';
                    const label = document.createElement('span');
                    label.className = 'text-[11px] font-medium text-slate-400';
                    label.textContent = dateLabel;
                    divider.append(label);
                    const tail = document.createElement('span');
                    tail.className = 'h-px flex-1 bg-slate-200';
                    divider.append(tail);
                    messagesEl.append(divider);
                }

                const row = document.createElement('div');
                row.className = mine ? 'flex justify-end' : 'flex justify-start';

                const column = document.createElement('div');
                column.className = mine
                    ? 'flex max-w-[82%] flex-col items-end gap-1 sm:max-w-[70%]'
                    : 'flex max-w-[82%] flex-col items-start gap-1 sm:max-w-[70%]';

                if (!mine) {
                    const sender = document.createElement('span');
                    sender.className = 'px-1 text-[11px] font-semibold text-slate-500';
                    sender.textContent = item.sender_name || (counterpart ? counterpart.name : 'Petugas');
                    column.append(sender);
                }

                const bubble = document.createElement('div');
                bubble.className = mine
                    ? 'whitespace-pre-wrap break-words rounded-2xl rounded-br-md bg-rose-500 px-4 py-2.5 text-sm leading-6 text-white shadow-sm'
                    : 'whitespace-pre-wrap break-words rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-2.5 text-sm leading-6 text-slate-700 shadow-sm';
                bubble.textContent = item.message;
                bubble.style.overflowWrap = 'anywhere';

                const meta = document.createElement('span');
                meta.className = 'px-1 text-[10px] text-slate-400';
                meta.textContent = formatTime(item.created_at);

                if (mine) {
                    const receipt = document.createElement('span');
                    receipt.className = item.read_at ? 'ml-1 font-semibold text-rose-500' : 'ml-1';
                    receipt.textContent = item.read_at ? '· Dibaca' : '';
                    meta.append(receipt);
                }

                column.append(bubble, meta);
                row.append(column);
                messagesEl.append(row);
            }

            window.requestAnimationFrame(() => {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            });
        }

        async function markMessagesRead(docs) {
            if (markingRead || !db) return;
            const unread = docs.filter((item) => item.sender_id !== uid && !item.read_at).slice(0, 25);
            if (unread.length === 0) return;

            markingRead = true;
            try {
                const batch = writeBatch(db);
                for (const item of unread) {
                    batch.update(doc(db, `chats/${chatId}/messages/${item.id}`), { read_at: serverTimestamp() });
                }
                await batch.commit();
            } catch (error) {
                console.warn('Gagal menandai pesan dibaca', error);
            } finally {
                markingRead = false;
            }
        }

        function subscribe() {
            if (unsubscribe) unsubscribe();

            const messagesQuery = query(
                collection(db, `chats/${chatId}/messages`),
                orderBy('created_at', 'asc'),
                limitToLast(200),
            );

            unsubscribe = onSnapshot(messagesQuery, (snapshot) => {
                hideAlert();
                setConnection(true);
                const messages = snapshot.docs.map((item) => ({ id: item.id, ...item.data() }));
                renderMessages(messages);
                markMessagesRead(messages);
            }, (error) => {
                console.error(error);
                setConnection(false);
                showAlert('Percakapan gagal dimuat. Periksa koneksi Anda lalu coba lagi.');
            });
        }

        async function start() {
            hideAlert();
            setConnection(false);
            setInputState();

            if (!bootstrap.ready) {
                placeholderEl.innerHTML = '<p class="text-sm font-semibold text-slate-700">Fitur chat belum aktif</p><p class="mt-1 text-xs text-slate-500">Administrator perlu melengkapi konfigurasi Firebase.</p>';
                showAlert('Fitur chat belum aktif. Hubungi administrator.');
                retryButton.classList.add('hidden');
                return;
            }

            try {
                const response = await fetch(sessionUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': bootstrap.csrf,
                    },
                    body: '{}',
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || 'Sesi chat tidak dapat dibuat.');
                }

                const session = payload.data;
                uid = session.uid;
                participants = session.participants || [];

                const app = getApps().length ? getApp() : initializeApp(session.firebase);
                const auth = getAuth(app);
                const credential = await signInWithCustomToken(auth, session.custom_token);

                if (credential.user.uid !== uid) {
                    throw new Error('Identitas Firebase tidak sesuai dengan akun Anda.');
                }

                db = getFirestore(app);
                setInputState();
                subscribe();
            } catch (error) {
                console.error(error);
                setConnection(false);
                showAlert(error.message || 'Chat tidak dapat dibuka. Silakan coba lagi.');
                placeholderEl.innerHTML = '<p class="text-sm font-semibold text-slate-700">Percakapan gagal dimuat</p><p class="mt-1 text-xs text-slate-500">Periksa koneksi internet lalu tekan Coba lagi.</p>';
            }
        }

        formEl.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = inputEl.value.trim();

            if (!message || sending || !db) return;

            sending = true;
            setInputState();

            try {
                const batch = writeBatch(db);
                const messageRef = doc(collection(db, `chats/${chatId}/messages`));

                batch.set(messageRef, {
                    sender_id: uid,
                    sender_role: viewer.role,
                    sender_name: bootstrap.viewerName,
                    message,
                    created_at: serverTimestamp(),
                    read_at: null,
                    participants,
                });

                batch.update(doc(db, `chats/${chatId}`), {
                    last_message: message,
                    last_message_at: serverTimestamp(),
                    last_sender_id: uid,
                    updated_at: serverTimestamp(),
                });

                await batch.commit();
                inputEl.value = '';
                inputEl.style.height = 'auto';
                hideAlert();
            } catch (error) {
                console.error(error);
                showAlert('Pesan gagal dikirim. Periksa koneksi Anda lalu coba lagi.');
            } finally {
                sending = false;
                setInputState();
                inputEl.focus();
            }
        });

        inputEl.addEventListener('input', () => {
            inputEl.style.height = 'auto';
            inputEl.style.height = Math.min(inputEl.scrollHeight, 128) + 'px';
            setInputState();
        });

        inputEl.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                if (!sendEl.disabled) formEl.requestSubmit();
            }
        });

        retryButton.addEventListener('click', () => {
            retryButton.classList.remove('hidden');
            start();
        });

        window.addEventListener('offline', () => setConnection(false));
        window.addEventListener('online', () => {
            setConnection(true);
            if (db) subscribe();
        });

        start();
    </script>
@endsection
