<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Ulang Kata Sandi | Sahabat PPA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        brand: {
                            pink: '#FF6B95',
                            header: '#FA5C83',
                            button: '#D82357',
                            buttonHover: '#B81A48',
                            text: '#BE2654',
                            border: '#F4B0C4',
                            bg: '#FFF1F4'
                        }
                    }
                }
            }
        };
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .header-wave { background: linear-gradient(135deg, #FF6EA7 0%, #F44376 100%); }
    </style>
</head>
<body class="min-h-screen bg-brand-bg text-slate-800 flex items-center justify-center p-4 sm:p-6">
    <main class="w-full max-w-[580px] my-auto">
        <div class="overflow-hidden rounded-[36px] bg-white shadow-2xl shadow-rose-200/50 border border-rose-100">
            <section class="header-wave relative h-28 rounded-t-[36px] text-white">
                <div class="absolute inset-x-0 bottom-0 leading-none">
                    <svg class="block h-12 w-full text-white" viewBox="0 0 1440 320" preserveAspectRatio="none" fill="currentColor" aria-hidden="true">
                        <path d="M0,160L48,176C96,192,192,224,288,213.3C384,203,480,149,576,138.7C672,128,768,160,864,186.7C960,213,1056,235,1152,218.7C1248,203,1344,149,1392,122.7L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                    </svg>
                </div>

                <!-- Logo SAPA Mengambang di Tengah -->
                <div class="absolute -bottom-6 left-1/2 z-20 -translate-x-1/2 transform">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-white bg-white p-2.5 shadow-lg shadow-rose-200/60">
                        <img
                            src="{{ asset('image/logo.png') }}"
                            alt="Logo SAPA"
                            class="h-full w-full object-contain select-none"
                        >
                    </div>
                </div>
            </section>

            <section class="px-6 pb-9 pt-14 sm:px-12">
                <div class="mb-6 text-center">
                    <h1 id="stepTitle" class="mb-1 text-2xl font-bold tracking-tight text-brand-text">Atur Ulang Kata Sandi</h1>
                    <p id="stepDescription" class="text-sm text-slate-500">Masukkan email akun Anda untuk menerima kode OTP.</p>
                </div>

                <div id="feedback" role="status" aria-live="polite" class="mb-5 hidden rounded-xl border px-4 py-3 text-center text-sm"></div>

                <form id="emailForm" class="space-y-4">
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold text-brand-text">Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" required maxlength="255" placeholder="contoh@email.com"
                            class="w-full rounded-full border border-brand-border bg-white px-5 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-brand-header focus:ring-2 focus:ring-rose-100">
                    </div>
                    <button id="sendButton" type="submit" class="w-full rounded-xl bg-brand-button px-4 py-3.5 text-sm font-semibold text-white shadow-md transition hover:bg-brand-buttonHover disabled:cursor-wait disabled:opacity-70">
                        Kirim Kode OTP
                    </button>
                    <a href="{{ route('pelapor.login') }}" class="flex w-full items-center justify-center rounded-xl border border-brand-button bg-white px-4 py-3.5 text-sm font-semibold text-brand-button transition hover:bg-rose-50">
                        Kembali ke Masuk
                    </a>
                </form>

                <section id="otpSection" class="hidden">
                    <p class="mb-5 text-center text-sm text-slate-600">Kode 6 digit dikirim ke <strong id="sentEmail" class="text-brand-text"></strong>.</p>
                    <div id="otpInputs" class="mb-4 flex justify-center gap-2 sm:gap-3" dir="ltr">
                        @for ($i = 0; $i < 6; $i++)
                            <input type="text" inputmode="numeric" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" maxlength="1" aria-label="Digit OTP {{ $i + 1 }}"
                                class="otp-digit h-12 w-10 rounded-2xl border border-brand-border text-center text-lg font-bold text-brand-text outline-none transition focus:border-brand-header focus:ring-2 focus:ring-rose-100 sm:h-14 sm:w-12">
                        @endfor
                    </div>
                    <button id="verifyButton" type="button" class="w-full rounded-xl bg-brand-button px-4 py-3.5 text-sm font-semibold text-white shadow-md transition hover:bg-brand-buttonHover disabled:cursor-wait disabled:opacity-70">
                        Verifikasi Kode
                    </button>
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-sm">
                        <button id="resendButton" type="button" disabled class="font-semibold text-brand-text hover:underline disabled:cursor-not-allowed disabled:text-slate-400">Kirim ulang OTP</button>
                        <span id="countdown" class="text-slate-500"></span>
                    </div>
                    <button id="changeEmailButton" type="button" class="mt-3 w-full text-center text-sm text-slate-500 hover:text-brand-text">Ubah email</button>
                </section>

                <form id="resetForm" class="hidden space-y-4">
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold text-brand-text">Kata Sandi Baru</label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password" placeholder="Minimal 6 karakter"
                                class="w-full rounded-full border border-brand-border bg-white px-5 py-3 pr-12 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-brand-header focus:ring-2 focus:ring-rose-100">
                            <button type="button" data-toggle-password="password" aria-label="Tampilkan kata sandi" class="absolute inset-y-0 right-3 flex items-center text-brand-text">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-brand-text">Ulangi Kata Sandi Baru</label>
                        <div class="relative">
                            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6" autocomplete="new-password" placeholder="Ulangi kata sandi baru"
                                class="w-full rounded-full border border-brand-border bg-white px-5 py-3 pr-12 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-brand-header focus:ring-2 focus:ring-rose-100">
                            <button type="button" data-toggle-password="password_confirmation" aria-label="Tampilkan kata sandi" class="absolute inset-y-0 right-3 flex items-center text-brand-text">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>
                    <button id="resetButton" type="submit" class="w-full rounded-xl bg-brand-button px-4 py-3.5 text-sm font-semibold text-white shadow-md transition hover:bg-brand-buttonHover disabled:cursor-wait disabled:opacity-70">
                        Simpan Kata Sandi Baru
                    </button>
                    <button id="backToOtpButton" type="button" class="w-full rounded-xl border border-brand-button bg-white px-4 py-3.5 text-sm font-semibold text-brand-button transition hover:bg-rose-50">
                        Kembali ke Kode OTP
                    </button>
                </form>

                <section id="successSection" class="hidden text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6"/></svg>
                    </div>
                    <p class="mb-5 text-sm text-slate-600">Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru.</p>
                    <a href="{{ route('pelapor.login') }}" class="flex w-full items-center justify-center rounded-xl bg-brand-button px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-buttonHover">Kembali ke Masuk</a>
                </section>
            </section>
        </div>
    </main>

    <script>
        const emailForm = document.getElementById('emailForm');
        const otpSection = document.getElementById('otpSection');
        const resetForm = document.getElementById('resetForm');
        const successSection = document.getElementById('successSection');
        const feedback = document.getElementById('feedback');
        const title = document.getElementById('stepTitle');
        const description = document.getElementById('stepDescription');
        const emailInput = document.getElementById('email');
        const otpInputs = Array.from(document.querySelectorAll('.otp-digit'));
        let verifiedOtp = '';
        let countdownTimer;

        function showFeedback(message, isError = true) {
            feedback.textContent = message;
            feedback.className = `mb-5 rounded-xl border px-4 py-3 text-center text-sm ${
                isError
                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-700'
            }`;
        }

        function clearFeedback() {
            feedback.textContent = '';
            feedback.className = 'mb-5 hidden rounded-xl border px-4 py-3 text-center text-sm';
        }

        function showStep(step) {
            emailForm.classList.toggle('hidden', step !== 'email');
            otpSection.classList.toggle('hidden', step !== 'otp');
            resetForm.classList.toggle('hidden', step !== 'reset');
            successSection.classList.toggle('hidden', step !== 'success');
            clearFeedback();

            if (step === 'email') {
                title.textContent = 'Atur Ulang Kata Sandi';
                description.textContent = 'Masukkan email akun Anda untuk menerima kode OTP.';
            } else if (step === 'otp') {
                title.textContent = 'Verifikasi Kode OTP';
                description.textContent = 'Masukkan kode 6 digit yang kami kirim ke email Anda.';
                otpInputs[0].focus();
            } else if (step === 'reset') {
                title.textContent = 'Atur Ulang Kata Sandi';
                description.textContent = 'Buat kata sandi baru untuk melindungi akun Anda.';
            } else {
                title.textContent = 'Kata Sandi Diperbarui';
                description.textContent = 'Proses reset kata sandi telah selesai.';
            }
        }

        async function postJson(endpoint, body) {
            const response = await fetch(`/api/forgot-password/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(body)
            });
            let payload;
            try {
                payload = await response.json();
            } catch (_) {
                throw new Error('Respons server tidak valid. Silakan coba lagi.');
            }
            if (!response.ok) {
                const errors = payload?.data?.errors;
                const firstError = errors && Object.values(errors).flat()[0];
                throw new Error(firstError || payload?.message || 'Permintaan gagal. Silakan coba lagi.');
            }
            return payload;
        }

        function startCountdown() {
            clearInterval(countdownTimer);
            let remaining = 60;
            const resendButton = document.getElementById('resendButton');
            const countdown = document.getElementById('countdown');
            resendButton.disabled = true;
            countdown.textContent = `Tersedia dalam ${remaining} detik`;
            countdownTimer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(countdownTimer);
                    resendButton.disabled = false;
                    countdown.textContent = '';
                    return;
                }
                countdown.textContent = `Tersedia dalam ${remaining} detik`;
            }, 1000);
        }

        async function sendOtp(button) {
            clearFeedback();
            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = 'Mengirim kode...';
            try {
                await postJson('send-otp', { email: emailInput.value.trim() });
                document.getElementById('sentEmail').textContent = emailInput.value.trim();
                otpInputs.forEach((input) => { input.value = ''; });
                showStep('otp');
                startCountdown();
                showFeedback('Kode OTP telah dikirim ke email Anda.', false);
            } catch (error) {
                showFeedback(error.message);
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        }

        emailForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (emailForm.reportValidity()) sendOtp(document.getElementById('sendButton'));
        });

        otpInputs.forEach((input, index) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '').slice(-1);
                if (input.value && index < otpInputs.length - 1) otpInputs[index + 1].focus();
                if (input.value && index === otpInputs.length - 1) input.blur();
            });
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !input.value && index > 0) otpInputs[index - 1].focus();
            });
            input.addEventListener('paste', (event) => {
                const digits = (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                if (!digits) return;
                event.preventDefault();
                digits.split('').forEach((digit, digitIndex) => { otpInputs[digitIndex].value = digit; });
                otpInputs[Math.min(digits.length, otpInputs.length) - 1].focus();
            });
        });

        document.getElementById('verifyButton').addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const otp = otpInputs.map((input) => input.value).join('');
            if (!/^\d{6}$/.test(otp)) {
                showFeedback('Masukkan 6 digit kode OTP.');
                return;
            }
            button.disabled = true;
            try {
                await postJson('verify-otp', { email: emailInput.value.trim(), otp });
                verifiedOtp = otp;
                showStep('reset');
            } catch (error) {
                showFeedback(error.message);
            } finally {
                button.disabled = false;
            }
        });

        document.getElementById('resendButton').addEventListener('click', (event) => {
            sendOtp(event.currentTarget);
        });
        document.getElementById('changeEmailButton').addEventListener('click', () => showStep('email'));
        document.getElementById('backToOtpButton').addEventListener('click', () => showStep('otp'));

        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                input.type = input.type === 'password' ? 'text' : 'password';
                button.setAttribute('aria-label', input.type === 'password' ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
            });
        });

        resetForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!resetForm.reportValidity()) return;
            const password = document.getElementById('password').value;
            const confirmation = document.getElementById('password_confirmation').value;
            if (password !== confirmation) {
                showFeedback('Konfirmasi kata sandi tidak cocok.');
                return;
            }

            const button = document.getElementById('resetButton');
            button.disabled = true;
            try {
                await postJson('reset', {
                    email: emailInput.value.trim(),
                    otp: verifiedOtp,
                    password,
                    password_confirmation: confirmation
                });
                showStep('success');
            } catch (error) {
                showFeedback(error.message);
            } finally {
                button.disabled = false;
            }
        });
    </script>
</body>
</html>