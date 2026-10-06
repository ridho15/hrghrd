@extends('layouts.app')
@section('title', 'Beranda')

@section('content')
<div class="space-y-6">
    {{-- Header Sambutan & Aksi Utama --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-2 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>{{ now('Asia/Jakarta')->translatedFormat('l, d F Y') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Halo, {{ Str::before(auth()->user()->name, ' ') }} 👋
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Jadwal shift kerja aktif, presensi lokasi terverifikasi, dan ringkasan pengajuan Anda hari ini.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('leave') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-sm font-bold border border-emerald-200/80 transition-colors shadow-xs">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Ajukan Izin / Sakit</span>
            </a>
        </div>
    </div>

    {{-- Banner Notifikasi Tindakan Tertunda (Manager / Admin) --}}
    @if($pending)
        <div class="rounded-2xl bg-amber-50/90 border border-amber-200/80 p-4 sm:p-5 text-amber-900 flex items-start justify-between gap-4 shadow-xs">
            <div class="flex items-start gap-3.5">
                <span class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0 mt-0.5 border border-amber-500/30 font-bold text-sm">
                    {{ $pending }}
                </span>
                <div>
                    <h3 class="text-sm font-bold text-amber-950 mb-0.5">Tugas Menunggu Persetujuan</h3>
                    <p class="text-xs sm:text-sm text-amber-800 m-0 leading-relaxed">
                        Ada <strong>{{ $pending }}</strong> pengajuan izin/sakit anggota tim yang memerlukan keputusan manajerial.
                    </p>
                </div>
            </div>
            <a href="{{ route('leave') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shrink-0 transition-colors shadow-xs">
                <span>Tinjau</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
    @endif

    {{-- Bento Grid: 2 Kolom Utama --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Kolom Kiri: Jadwal Shift & Panel Presensi (7 Cols) --}}
        <section class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Shift Terdekat</h2>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                    {{ $shifts->count() }} Jadwal
                </span>
            </div>

            <div class="space-y-4">
                @forelse($shifts as $shift)
                    <div class="rounded-xl border border-slate-200/70 p-4 transition-all hover:border-slate-300 bg-slate-50/40">
                        <article class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            {{-- Tanggal & Info Jam --}}
                            <div class="flex items-start sm:items-center gap-3.5">
                                <div class="w-13 h-13 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-800 flex flex-col items-center justify-center shrink-0">
                                    <b class="text-lg font-extrabold leading-none">{{ \Carbon\Carbon::parse($shift->start_at)->format('d') }}</b>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 mt-0.5">{{ \Carbon\Carbon::parse($shift->start_at)->translatedFormat('M') }}</span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <strong class="text-sm sm:text-base font-bold text-slate-900 tabular-nums">
                                             {{ \Carbon\Carbon::parse($shift->start_at)->format('H:i') }} – {{ \Carbon\Carbon::parse($shift->end_at)->format('H:i') }}
                                        </strong>
                                        @if($shift->status === 'approved')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60">Disetujui</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">Draf</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-slate-500 mt-1">
                                        <span class="flex items-center gap-1 font-medium text-slate-600">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                            {{ $shift->branch_name }}
                                        </span>
                                        @if($shift->attendance_status)
                                            <span>&middot;</span>
                                            @if($shift->attendance_status === 'absent')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Alfa</span>
                                            @elseif($shift->checkout_at)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Selesai ({{ \Carbon\Carbon::parse($shift->checkout_at)->format('H:i') }})</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Sudah Check-in ({{ \Carbon\Carbon::parse($shift->checkin_at)->format('H:i') }})</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Tombol Aksi Check-in/Check-out --}}
                            @if($shift->status === 'approved' && !$shift->checkout_at && $shift->attendance_status !== 'absent')
                                <button 
                                    type="button" 
                                    data-open-attendance="{{ $shift->id }}" 
                                    data-action="{{ $shift->checkin_at ? 'out' : 'in' }}"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-xs shrink-0 cursor-pointer {{ $shift->checkin_at ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-emerald-700 hover:bg-emerald-800 text-white' }}"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>{{ $shift->checkin_at ? 'Verifikasi Check-out' : 'Verifikasi Check-in' }}</span>
                                </button>
                            @endif
                        </article>

                        {{-- Panel Presensi Tersembunyi (Dikontrol JS via [data-open-attendance]) --}}
                        <div id="attendance-{{ $shift->id }}" class="mt-4 pt-4 border-t border-slate-200 bg-white rounded-xl p-4 sm:p-5 border shadow-xs" hidden>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                                <h3 class="text-sm font-bold text-slate-900 m-0">
                                    Verifikasi Kehadiran {{ $shift->checkin_at ? 'Check-out' : 'Check-in' }}
                                </h3>
                            </div>
                            <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                                Pastikan Anda berada di area cabang <strong>{{ $shift->branch_name }}</strong>. Lokasi GPS dan identitas perangkat diperiksa saat tombol ditekan.
                            </p>

                            {{-- Formulir Presensi (Dikelola app.js) --}}
                            <form method="post" action="{{ route('attendance.act', $shift->id) }}" class="space-y-4 attendance-form">
                                @csrf
                                <input type="hidden" name="action" value="{{ $shift->checkin_at ? 'out' : 'in' }}">
                                <input type="hidden" name="latitude">
                                <input type="hidden" name="longitude">
                                <input type="hidden" name="accuracy">

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                            Kode Cabang 8 Karakter
                                        </label>
                                        <input 
                                            name="qr_code" 
                                            maxlength="8" 
                                            minlength="8" 
                                            autocapitalize="characters" 
                                            placeholder="Contoh: A1B2C3D4" 
                                            required
                                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono font-bold tracking-widest uppercase focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 text-sm"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                            Angka Tantangan: Ketik <span class="text-emerald-700 font-extrabold text-sm">{{ $challenge }}</span>
                                        </label>
                                        <input 
                                            name="challenge" 
                                            inputmode="numeric" 
                                            maxlength="3" 
                                            required 
                                            placeholder="3 Digit"
                                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono font-bold tracking-wider focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 text-sm"
                                        >
                                    </div>
                                </div>

                                {{-- Area Pemindai Kamera & Viewfinder --}}
                                <div class="space-y-2">
                                    <button type="button" data-scan-qr class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold border border-slate-200 transition-colors cursor-pointer">
                                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        <span>Buka Kamera Pemindai QR</span>
                                    </button>
                                    <video class="qr-scanner w-full max-h-56 rounded-xl border border-slate-300 bg-black object-cover" playsinline hidden></video>
                                </div>

                                {{-- Tombol Submit Presensi --}}
                                <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <button class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs sm:text-sm font-bold shadow-sm transition-colors cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                        <span>Ambil Lokasi & Kirim Presensi</span>
                                    </button>
                                    <small class="form-status text-xs font-semibold text-emerald-700" aria-live="polite"></small>
                                </div>
                            </form>

                            {{-- Accordion Pengecualian Presensi --}}
                            <div class="mt-4 pt-3 border-t border-slate-100">
                                <details class="group">
                                    <summary class="text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer list-none flex items-center gap-1.5">
                                        <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                        <span>Mengalami kendala GPS, kamera, atau jaringan? Ajukan pengecualian</span>
                                    </summary>
                                    <form method="post" action="{{ route('attendance.exception', $shift->id) }}" class="mt-3 p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                                        @csrf
                                        <input type="hidden" name="action" value="{{ $shift->checkin_at ? 'out' : 'in' }}">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                                Alasan Kendala / Pengecualian
                                            </label>
                                            <textarea 
                                                name="reason" 
                                                minlength="10" 
                                                required 
                                                placeholder="Jelaskan kendala teknis dan waktu kejadian secara rinci..."
                                                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 min-h-[60px]"
                                            ></textarea>
                                        </div>
                                        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold transition-colors cursor-pointer">
                                            Kirim Pengecualian ke Manager
                                        </button>
                                    </form>
                                </details>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 px-4 rounded-xl border border-dashed border-slate-200">
                        <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <p class="text-xs sm:text-sm font-semibold text-slate-500 m-0">Belum ada shift terjadwal untuk hari ini.</p>
                        <p class="text-xs text-slate-400 mt-1">Hubungi manajer cabang jika jadwal seharusnya sudah tersedia.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Kolom Kanan: Status Pengajuan Terakhir (5 Cols) --}}
        <section class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </span>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Pengajuan Izin & Sakit</h2>
                </div>
                <a href="{{ route('leave') }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 transition-colors">
                    Lihat Semua &rarr;
                </a>
            </div>

            <div class="space-y-3">
                @forelse($leaves as $leave)
                    <div class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/50 flex items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-900">
                                    {{ $leave->type === 'sick' ? 'Surat Sakit' : 'Izin Kerja' }}
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-500 block mt-0.5 tabular-nums">
                                {{ $leave->start_date }} s.d. {{ $leave->end_date }}
                            </span>
                        </div>
                        @php
                            $leaveBadge = [
                                'pending' => ['label' => 'Menunggu', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                                'approved' => ['label' => 'Disetujui', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                'partial' => ['label' => 'Sebagian', 'class' => 'bg-sky-50 text-sky-700 border-sky-200'],
                                'rejected' => ['label' => 'Ditolak', 'class' => 'bg-rose-50 text-rose-700 border-rose-200']
                            ][$leave->status] ?? ['label' => $leave->status, 'class' => 'bg-slate-100 text-slate-600 border-slate-200'];
                        @endphp
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $leaveBadge['class'] }}">
                            {{ $leaveBadge['label'] }}
                        </span>
                    </div>
                @empty
                    <div class="text-center py-8 px-4 rounded-xl border border-dashed border-slate-200">
                        <p class="text-xs text-slate-400 m-0">Belum ada riwayat pengajuan izin atau sakit.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
