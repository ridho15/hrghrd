@extends('layouts.app')

@section('title', 'Presensi Hari Ini')

@section('content')
<div class="space-y-6">
    {{-- Top Hero Banner: Digital Live Clock & User Context --}}
    <div style="background: linear-gradient(135deg, #065f46 0%, #047857 50%, #115e59 100%);" class="rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-1.5">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-950/40 text-emerald-200 border border-emerald-500/30 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Waktu Server Presensi (Asia/Jakarta)</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white m-0">
                    Presensi Kerja Hari Ini
                </h1>
                <p class="text-xs sm:text-sm text-emerald-100 font-medium m-0">
                    Halo, <strong class="text-white font-bold">{{ $user->name }}</strong> ({{ $user->username ?: $user->email }}) &middot; Silakan lakukan verifikasi kehadiran sesuai jadwal shift Anda.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                @if(\App\Support\Access::manager())
                    <a href="{{ route('qr.page', ['branch_id' => auth()->user()->branch_id]) }}" target="_blank" class="inline-flex items-center justify-center gap-2 px-3.5 py-3 rounded-xl bg-white/20 hover:bg-white/30 text-white text-xs font-bold border border-white/30 backdrop-blur-md shadow-xs transition-colors shrink-0">
                        <svg class="w-4 h-4 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                        <span>Buka Layar QR Kiosk</span>
                    </a>
                @endif

                {{-- Real-time Clock Widget --}}
                <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/20 text-center min-w-[180px] shrink-0">
                    <div id="live-time" class="text-2xl sm:text-3xl font-black font-mono tracking-wider text-white">
                        {{ $today->format('H:i:s') }}
                    </div>
                    <div class="text-[11px] font-semibold text-emerald-200 uppercase tracking-wider mt-1">
                        {{ $today->translatedFormat('l, d F Y') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Daftar Shift Hari Ini & Panel Presensi --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight m-0 flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Jadwal Shift & Aksi Presensi ({{ $today->translatedFormat('d M Y') }})</span>
            </h2>
            <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1 rounded-full border border-slate-200">
                {{ $shifts->count() }} Jadwal Terdaftar
            </span>
        </div>

        @if($shifts->isEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center shadow-xs">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800 mb-1">Tidak Ada Jadwal Shift Hari Ini</h3>
                <p class="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
                    Anda tidak memiliki jadwal tugas yang diagendakan untuk hari ini ({{ $today->translatedFormat('l, d F Y') }}). Jika Anda memiliki jadwal kerja, hubungi Admin atau Manager operasional cabang Anda.
                </p>
                <div class="mt-4">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors">
                        Kembali ke Beranda
                    </a>
                </div>
            </div>
        @else
            @foreach($shifts as $shift)
                @php
                    $attendance = $shift->attendance;
                    $hasCheckin = (bool) $attendance?->checkin_at;
                    $hasCheckout = (bool) $attendance?->checkout_at;
                    $isCompleted = $hasCheckin && $hasCheckout;
                    $isPendingIn = !$hasCheckin;
                    $isPendingOut = $hasCheckin && !$hasCheckout;
                @endphp

                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden transition-all">
                    {{-- Header Kartu Shift --}}
                    <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/50">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl {{ $isCompleted ? 'bg-emerald-100 text-emerald-800' : ($hasCheckin ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }} flex items-center justify-center font-bold text-sm shrink-0 border border-slate-200">
                                @if($isCompleted)
                                    <svg class="w-6 h-6 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                @else
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                @endif
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h3 class="text-sm sm:text-base font-bold text-slate-900 m-0">
                                        {{ $shift->branch?->name ?? 'Cabang Tidak Ditentukan' }}
                                    </h3>
                                    @if($shift->status !== 'approved')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">Menunggu Persetujuan Shift</span>
                                    @elseif($isCompleted)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">Shift Selesai</span>
                                    @elseif($hasCheckin)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-300 animate-pulse">Sedang Bertugas</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">Belum Check-in</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <span>Jam Shift: <strong>{{ \Carbon\Carbon::parse($shift->start_at)->format('H:i') }} - {{ \Carbon\Carbon::parse($shift->end_at)->format('H:i') }} WIB</strong></span>
                                    @if($hasCheckin)
                                        <span class="text-emerald-700 font-semibold">Masuk: {{ \Carbon\Carbon::parse($attendance->checkin_at)->format('H:i:s') }}</span>
                                    @endif
                                    @if($hasCheckout)
                                        <span class="text-emerald-700 font-semibold">Pulang: {{ \Carbon\Carbon::parse($attendance->checkout_at)->format('H:i:s') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Tombol Buka/Tutup Form Verifikasi --}}
                        @if($shift->status === 'approved' && !$isCompleted)
                            <div>
                                <button 
                                    type="button" 
                                    data-open-attendance="{{ $shift->id }}"
                                    data-action="{{ $hasCheckin ? 'out' : 'in' }}"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all shadow-xs cursor-pointer {{ $hasCheckin ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-emerald-700 hover:bg-emerald-800 text-white' }}"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>{{ $hasCheckin ? 'Buka Form Check-out' : 'Buka Form Check-in' }}</span>
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Form Presensi (Default Tampil Jika Belum Selesai) --}}
                    @if($shift->status === 'approved' && !$isCompleted)
                        <div id="attendance-{{ $shift->id }}" class="p-5 sm:p-6 bg-white border-t border-slate-100">
                            <div class="max-w-2xl">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $hasCheckin ? 'bg-amber-500' : 'bg-emerald-500' }} animate-ping"></span>
                                    <h4 class="text-sm font-bold text-slate-900 m-0">
                                        Formulir Verifikasi Presensi {{ $hasCheckin ? 'Check-out (Pulang)' : 'Check-in (Masuk)' }}
                                    </h4>
                                </div>
                                <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                                    Berdirilah di area cabang <strong>{{ $shift->branch?->name }}</strong>. Arahkan kamera pemindai ke layar QR Kiosk cabang untuk verifikasi instan. Koordinat GPS Anda akan divalidasi otomatis saat pengiriman.
                                </p>

                                <form method="post" action="{{ route('attendance.act', $shift->id) }}" class="space-y-4 attendance-form"
                                    data-shift-branch-code="{{ $shift->branch?->code }}"
                                    data-shift-branch-name="{{ $shift->branch?->name }}">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $hasCheckin ? 'out' : 'in' }}">
                                    <input type="hidden" name="latitude">
                                    <input type="hidden" name="longitude">
                                    <input type="hidden" name="accuracy">
                                    <input type="hidden" name="challenge" value="{{ session('attendance_challenge') }}">

                                    {{-- Area Pindai QR Kamera (Alur Utama) --}}
                                    <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-3">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <div>
                                                <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Verifikasi QR Code Cabang</span>
                                                <p class="text-[11px] text-slate-500 m-0">Arahkan kamera ke layar QR Kiosk cabang <strong>{{ $shift->branch?->name }} ({{ $shift->branch?->code }})</strong>.</p>
                                            </div>
                                            <button type="button" data-scan-qr class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold border border-slate-300 shadow-2xs transition-colors cursor-pointer shrink-0">
                                                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                <span>Buka Kamera Pemindai</span>
                                            </button>
                                        </div>

                                        <video class="qr-scanner w-full max-h-56 rounded-xl border border-slate-300 bg-black object-cover" playsinline hidden></video>

                                        {{-- Indikator Kode QR Terverifikasi Sesuai Cabang --}}
                                        <div class="qr-detected-badge hidden p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between gap-2 text-xs">
                                            <span class="text-emerald-800 font-bold flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                <span>QR Cabang Terverifikasi:</span>
                                                <code class="qr-detected-code font-mono font-black text-emerald-900 bg-white px-2 py-0.5 rounded-md border border-emerald-300"></code>
                                            </span>
                                            <span class="text-[10px] text-emerald-700 font-bold uppercase tracking-wider bg-emerald-100/80 px-2 py-0.5 rounded-full shrink-0">Siap Kirim</span>
                                        </div>

                                        {{-- Indikator Peringatan QR Tidak Sesuai Cabang Shift --}}
                                        <div class="qr-mismatch-badge hidden p-3 bg-rose-50 border border-rose-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-rose-900">
                                            <span class="font-bold flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                <span class="qr-mismatch-message">QR yang dipindai tidak sesuai dengan cabang jadwal shift Anda.</span>
                                            </span>
                                            <span class="text-[10px] text-rose-700 font-bold uppercase tracking-wider bg-rose-100/80 px-2 py-0.5 rounded-full self-start sm:self-auto shrink-0">Cabang Berbeda</span>
                                        </div>

                                        {{-- Fallback Manual: Disembunyikan rapi dalam menu bantuan --}}
                                        <details class="text-xs text-slate-500 pt-1 border-t border-slate-200/60">
                                            <summary class="cursor-pointer hover:text-slate-700 font-medium list-none flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                <span>Kamera tidak bisa memindai? Masukkan Kode Cabang 8 Karakter manual</span>
                                            </summary>
                                            <div class="mt-2.5 pt-2 flex flex-col sm:flex-row sm:items-center gap-2">
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Kode Cabang 8 Karakter</label>
                                                    <input 
                                                        name="qr_code" 
                                                        maxlength="8" 
                                                        minlength="8" 
                                                        autocapitalize="characters" 
                                                        placeholder="Contoh: A1B2C3D4" 
                                                        class="w-full sm:w-60 px-3 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono font-bold tracking-widest uppercase focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 text-xs"
                                                    >
                                                </div>
                                                <span class="text-[11px] text-slate-400 sm:mt-5">Ketik kode dari layar kiosk cabang</span>
                                            </div>
                                        </details>
                                    </div>

                                    {{-- Submit Button --}}
                                    <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <button class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs sm:text-sm font-bold shadow-sm transition-colors cursor-pointer">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                            <span>Ambil Lokasi & Kirim Presensi</span>
                                        </button>
                                        <small class="form-status text-xs font-semibold text-emerald-700" aria-live="polite"></small>
                                    </div>
                                </form>

                                {{-- Ajukan Pengecualian / Bantuan Kendala --}}
                                <div class="mt-4 pt-3 border-t border-slate-100">
                                    <details class="group">
                                        <summary class="text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer list-none flex items-center gap-1.5">
                                            <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                            <span>Mengalami kendala GPS atau kamera? Ajukan permohonan pengecualian</span>
                                        </summary>
                                        <form method="post" action="{{ route('attendance.exception', $shift->id) }}" class="mt-3 p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                                            @csrf
                                            <input type="hidden" name="action" value="{{ $hasCheckin ? 'out' : 'in' }}">
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                                    Alasan Kendala
                                                </label>
                                                <textarea name="reason" rows="2" required placeholder="Contoh: GPS handphone tidak stabil di dalam ruangan, sinyal lambat..." class="w-full p-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"></textarea>
                                            </div>
                                            <button class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-bold transition-colors cursor-pointer">
                                                Kirim Permohonan Pengecualian
                                            </button>
                                        </form>
                                    </details>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- Riwayat Percobaan Presensi Hari Ini (Logs) --}}
    @if(isset($attempts) && $attempts->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">
                Riwayat Validasi Presensi Hari Ini
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 font-semibold">
                            <th class="pb-2">Waktu</th>
                            <th class="pb-2">Aksi</th>
                            <th class="pb-2">Status</th>
                            <th class="pb-2">Keterangan / Alasan Penolakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($attempts as $attempt)
                            <tr>
                                <td class="py-2 text-slate-600 font-mono">{{ $attempt->server_at?->format('H:i:s') ?? '-' }}</td>
                                <td class="py-2 font-bold uppercase text-slate-700">{{ $attempt->action }}</td>
                                <td class="py-2">
                                    @if($attempt->result === 'success')
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Berhasil</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Ditolak</span>
                                    @endif
                                </td>
                                <td class="py-2 text-slate-500">{{ $attempt->reason ?: 'Validasi berhasil' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Live update jam digital
    setInterval(() => {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', { hour12: false, timeZone: 'Asia/Jakarta' });
        const liveTimeEl = document.getElementById('live-time');
        if (liveTimeEl) liveTimeEl.textContent = timeStr.replace(/\./g, ':');
    }, 1000);
</script>
@endpush
@endsection
