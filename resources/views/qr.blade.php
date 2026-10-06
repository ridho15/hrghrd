@extends('layouts.app')
@section('title', 'QR Cabang')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header & Pemilih Cabang --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <span>Display Layar Cabang</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                QR Presensi Cabang
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Tampilkan layar ini pada tablet atau monitor di area cabang. Kode berotasi otomatis setiap 30 detik untuk anti-fraud.
            </p>
        </div>

        {{-- Form Ganti Cabang --}}
        <form method="get" action="{{ route('qr.page') }}" class="sm:shrink-0">
            <div class="relative">
                <label for="branch-selector" class="sr-only">Pilih Cabang</label>
                <select 
                    id="branch-selector"
                    name="branch_id" 
                    onchange="this.form.submit()"
                    class="pl-4 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-bold text-slate-800 shadow-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer"
                >
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected($branch->id == $b->id)>
                            📍 {{ $b->name }} ({{ $b->code }})
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    {{-- Kiosk Card Display --}}
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xl shadow-slate-900/5 p-6 sm:p-10 text-center relative overflow-hidden">
        {{-- Ambient Glow --}}
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-96 h-96 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-md mx-auto space-y-6">
            {{-- Branch Badge --}}
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Cabang Aktif: <strong>{{ $branch->name }}</strong> ({{ $branch->code }})</span>
            </div>

            {{-- Dynamic QR Display Wrapper (Dikontrol oleh app.js) --}}
            <div class="qr-display flex flex-col items-center justify-center gap-4" data-qr-url="{{ route('qr.code', ['branch_id' => $branch->id]) }}">
                <div class="p-4 bg-white rounded-3xl border-2 border-slate-200/90 shadow-md inline-block">
                    <canvas width="256" height="256" class="w-56 h-56 sm:w-64 sm:h-64 rounded-xl" aria-label="QR kode cabang"></canvas>
                </div>

                {{-- Kode 8 Karakter Monospace --}}
                <div class="space-y-1">
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-400 block">Kode Backup Alternatif</span>
                    <strong class="qr-code text-3xl sm:text-4xl font-mono font-extrabold tracking-widest text-slate-900 block select-all">
                        Memuat...
                    </strong>
                </div>

                {{-- Countdown Timer --}}
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200/80 text-xs font-bold text-emerald-800">
                    <svg class="w-4 h-4 text-emerald-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="qr-timer">Menunggu rotasi...</span>
                </div>
            </div>

            {{-- Ketentuan Penggunaan --}}
            <div class="pt-6 border-t border-slate-100 text-xs text-slate-500 space-y-1">
                <p class="m-0 font-medium">Karyawan dapat memindai QR menggunakan kamera atau memasukkan kode 8 karakter di atas.</p>
                <p class="m-0 text-slate-400">Titik lokasi koordinat GPS dan perangkat terdaftar tetap diverifikasi oleh sistem secara ketat.</p>
            </div>
        </div>
    </div>
</div>
@endsection
