<!doctype html>
<html lang="id" class="h-full bg-slate-50 text-slate-800 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#047857">
    <title>Display QR Presensi · {{ $branch->name }} ({{ $branch->code }}) · HR Group</title>

    {{-- Favicon & Official App Icons --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 text-slate-800 flex flex-col justify-between font-sans selection:bg-emerald-500 selection:text-white">

    {{-- Top Bar: Brand, Live Digital Clock & Branch Selector --}}
    <header class="w-full bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 shadow-2xs">
        <div class="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4">
            {{-- Logo & Application Brand --}}
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo-hr-group.png') }}" alt="HR Group Enterprise" class="h-7 w-auto object-contain">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-extrabold text-slate-900 tracking-tight">HR Group Enterprise</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider text-emerald-800 bg-emerald-50 border border-emerald-200">
                            Display Presensi
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-medium m-0">Layar QR Presensi Kiosk Mandiri Cabang</p>
                </div>
            </div>

            {{-- Live Digital Clock & Pemilih Cabang --}}
            <div class="flex flex-wrap items-center justify-center sm:justify-end gap-2.5 sm:gap-3 w-full sm:w-auto">
                {{-- Live Clock --}}
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-700">
                    <svg class="w-4 h-4 text-emerald-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="leading-tight">
                        <div id="live-clock" class="text-xs font-mono font-bold text-slate-900">--:--:-- WIB</div>
                        <div id="live-date" class="text-[10px] text-slate-500 font-medium">Memuat...</div>
                    </div>
                </div>

                {{-- Pemilih Cabang (Dropdown Ganti-ganti Cabang Tanpa Login) --}}
                <div class="relative">
                    <label for="kiosk-branch-select" class="sr-only">Pilih Cabang</label>
                    <select
                        id="kiosk-branch-select"
                        onchange="if(this.value) window.location.href = '{{ route('kiosk.qr') }}/' + this.value;"
                        class="pl-3.5 pr-8 py-2 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-800 shadow-2xs hover:border-slate-400 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer transition-colors"
                        title="Ganti Cabang Tampilan"
                    >
                        @foreach($branches as $b)
                            <option value="{{ $b->code }}" @selected($branch->id === $b->id)>
                                {{ $b->name }} ({{ $b->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </header>

    {{-- Main Kiosk Display Card (Clean Light Theme) --}}
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 my-auto">
        <div class="w-full max-w-md mx-auto">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-8 text-center space-y-5">
                
                {{-- Corporate Logo --}}
                <div class="flex items-center justify-center">
                    <img src="{{ asset('images/logo-hr-group.png') }}" alt="HR Group" class="h-8 w-auto object-contain">
                </div>

                {{-- Branch Badge --}}
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                    <span>Cabang Aktif: <strong class="text-slate-900">{{ $branch->name }}</strong> ({{ $branch->code }})</span>
                </div>

                {{-- Dynamic QR Display Wrapper (Dikontrol otomatis oleh app.js) --}}
                <div
                    id="kiosk-qr-box"
                    class="qr-display flex flex-col items-center justify-center gap-4"
                    data-qr-url="{{ route('kiosk.qr.code', $branch->code) }}"
                >
                    {{-- QR Canvas Frame --}}
                    <div class="p-3.5 bg-white rounded-2xl border border-slate-200 shadow-2xs inline-block">
                        <canvas
                            id="kiosk-qr-canvas"
                            width="256"
                            height="256"
                            class="w-56 h-56 sm:w-64 sm:h-64 rounded-xl block"
                            aria-label="QR kode cabang {{ $branch->name }}"
                        ></canvas>
                    </div>

                    {{-- Kode 8 Digit Angka Monospace --}}
                    <div class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400 block">
                            Kode Backup Alternatif (8 Digit Angka)
                        </span>
                        <strong class="qr-code text-3xl sm:text-4xl font-mono font-extrabold tracking-widest text-slate-900 block select-all">
                            Memuat...
                        </strong>
                    </div>

                    {{-- Countdown Timer --}}
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200/80 text-xs font-bold text-emerald-800">
                        <svg class="w-4 h-4 text-emerald-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="qr-timer">Menunggu rotasi...</span>
                    </div>
                </div>

                {{-- Guidance Footer --}}
                <div class="pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1">
                    <p class="m-0 font-medium text-slate-600">Pindai QR menggunakan kamera aplikasi HR Group atau ketik 8 digit angka manual di atas.</p>
                    <p class="m-0 text-slate-400 text-[11px]">Radius toleransi GPS dan verifikasi perangkat terdaftar tetap diproses oleh sistem.</p>
                </div>
            </div>
        </div>
    </main>

    {{-- Bottom Utility Bar: Controls & Shareable Link --}}
    <footer class="w-full bg-white border-t border-slate-200 px-4 sm:px-8 py-3 text-xs text-slate-600">
        <div class="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-slate-500">URL Cabang Ini:</span>
                <code class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-800 font-mono text-xs font-bold">
                    {{ route('kiosk.qr', $branch->code) }}
                </code>
                <button
                    type="button"
                    id="copy-kiosk-url-btn"
                    data-url="{{ route('kiosk.qr', $branch->code) }}"
                    class="px-2.5 py-1 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 font-bold inline-flex items-center gap-1.5 cursor-pointer shadow-2xs transition-colors"
                    title="Salin Tautan Kiosk Cabang Ini"
                >
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    <span id="copy-btn-text">Salin URL</span>
                </button>
            </div>

            <div class="flex items-center gap-3">
                {{-- Fullscreen Mode Button --}}
                <button
                    type="button"
                    id="fullscreen-toggle-btn"
                    class="px-3 py-1 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 font-bold inline-flex items-center gap-1.5 cursor-pointer shadow-2xs transition-colors"
                    title="Beralih ke Layar Penuh"
                >
                    <svg id="fullscreen-icon" class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0 0l-5-5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                    </svg>
                    <span id="fullscreen-text">Layar Penuh</span>
                </button>

                <span class="text-slate-400 font-medium">HR Group Enterprise &copy; {{ date('Y') }}</span>
            </div>
        </div>
    </footer>

    {{-- Kiosk Real-Time Script --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Live Digital Clock (WIB)
            const clockEl = document.getElementById('live-clock');
            const dateEl = document.getElementById('live-date');

            function updateClock() {
                const now = new Date();
                const optionsTime = { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
                const optionsDate = { timeZone: 'Asia/Jakarta', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
                if (clockEl) clockEl.textContent = now.toLocaleTimeString('id-ID', optionsTime) + ' WIB';
                if (dateEl) dateEl.textContent = now.toLocaleDateString('id-ID', optionsDate);
            }
            updateClock();
            setInterval(updateClock, 1000);

            // 2. Fullscreen Toggle
            const fsBtn = document.getElementById('fullscreen-toggle-btn');
            const fsText = document.getElementById('fullscreen-text');
            if (fsBtn) {
                fsBtn.addEventListener('click', () => {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen().catch(err => {
                            console.warn('Gagal beralih ke layar penuh:', err);
                        });
                    } else {
                        if (document.exitFullscreen) document.exitFullscreen();
                    }
                });

                document.addEventListener('fullscreenchange', () => {
                    if (document.fullscreenElement) {
                        if (fsText) fsText.textContent = 'Keluar Layar Penuh';
                    } else {
                        if (fsText) fsText.textContent = 'Layar Penuh';
                    }
                });
            }

            // 3. Copy URL Button
            const copyBtn = document.getElementById('copy-kiosk-url-btn');
            const copyBtnText = document.getElementById('copy-btn-text');
            if (copyBtn) {
                copyBtn.addEventListener('click', async () => {
                    const url = copyBtn.dataset.url || window.location.href;
                    try {
                        await navigator.clipboard.writeText(url);
                        if (copyBtnText) copyBtnText.textContent = 'Tersalin!';
                        setTimeout(() => {
                            if (copyBtnText) copyBtnText.textContent = 'Salin URL';
                        }, 2500);
                    } catch (e) {
                        const input = document.createElement('input');
                        input.value = url;
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        document.body.removeChild(input);
                        if (copyBtnText) copyBtnText.textContent = 'Tersalin!';
                        setTimeout(() => {
                            if (copyBtnText) copyBtnText.textContent = 'Salin URL';
                        }, 2500);
                    }
                });
            }
        });
    </script>
</body>
</html>
