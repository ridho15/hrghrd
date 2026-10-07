@extends('layouts.app')
@section('title', 'Beranda')

@section('content')
<div class="space-y-8">
    {{-- ======================================================== --}}
    {{-- 1. HEADER SAMBUTAN & STATUS OPERASIONAL                    --}}
    {{-- ======================================================== --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>{{ now('Asia/Jakarta')->translatedFormat('l, d F Y') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Halo, {{ Str::before(auth()->user()->name, ' ') }} 👋
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                @if(\App\Support\Access::admin())
                    Pusat kendali operasional SDM, metrik presensi harian seluruh cabang, dan ringkasan eksekutif.
                @elseif(\App\Support\Access::manager())
                    Supervisi operasional {{ $managerStats['branch_name'] ?? 'cabang' }}, pemantauan shift staf, dan rekapitulasi kehadiran hari ini.
                @else
                    Jadwal shift kerja aktif, presensi lokasi terverifikasi, dan ringkasan pengajuan Anda hari ini.
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            @if(\App\Support\Access::admin())
                <a href="{{ route('people') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold shadow-xs transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    <span>+ Tambah Karyawan</span>
                </a>
                <a href="{{ route('shifts') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold border border-slate-200/90 shadow-xs transition-colors">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Jadwalkan Shift</span>
                </a>
            @elseif(\App\Support\Access::manager())
                <a href="{{ route('qr.page', ['branch_id' => auth()->user()->branch_id]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold shadow-xs transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    <span>Buka Layar Kiosk QR</span>
                </a>
            @else
                <a href="{{ route('leave') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-sm font-bold border border-emerald-200/80 transition-colors shadow-xs">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Ajukan Izin / Sakit</span>
                </a>
            @endif
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- 2. BANNER NOTIFIKASI TUGAS MENUNGGU PERSETUJUAN           --}}
    {{-- ======================================================== --}}
    @if(\App\Support\Access::admin() && ($adminStats['total_pending_tasks'] ?? 0) > 0)
        <div class="rounded-2xl bg-amber-50/90 border border-amber-200/90 p-4 sm:p-5 text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xs">
            <div class="flex items-start gap-3.5">
                <span class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0 mt-0.5 border border-amber-500/30 font-extrabold text-base">
                    {{ $adminStats['total_pending_tasks'] }}
                </span>
                <div>
                    <h3 class="text-sm font-bold text-amber-950 mb-0.5">Tugas Operasional Menunggu Keputusan</h3>
                    <p class="text-xs sm:text-sm text-amber-800 m-0 leading-relaxed">
                        Terdapat <strong>{{ $adminStats['pending_leaves'] }}</strong> pengajuan izin/cuti dan <strong>{{ $adminStats['pending_exceptions'] }}</strong> kendala presensi GPS yang memerlukan verifikasi manajerial.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('leave') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition-colors shadow-xs">
                    <span>Tinjau Cuti & Izin</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
                <a href="{{ route('attendance.review') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-amber-100/60 text-amber-900 border border-amber-300 text-xs font-bold transition-colors shadow-xs">
                    <span>Tinjau Kendala GPS</span>
                </a>
            </div>
        </div>
    @elseif(\App\Support\Access::manager() && ($managerStats['total_pending_tasks'] ?? 0) > 0)
        <div class="rounded-2xl bg-amber-50/90 border border-amber-200/90 p-4 sm:p-5 text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xs">
            <div class="flex items-start gap-3.5">
                <span class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0 mt-0.5 border border-amber-500/30 font-extrabold text-base">
                    {{ $managerStats['total_pending_tasks'] }}
                </span>
                <div>
                    <h3 class="text-sm font-bold text-amber-950 mb-0.5">Tugas Menunggu Persetujuan Cabang</h3>
                    <p class="text-xs sm:text-sm text-amber-800 m-0 leading-relaxed">
                        Ada <strong>{{ $managerStats['pending_leaves'] }}</strong> izin/sakit dan <strong>{{ $managerStats['pending_exceptions'] }}</strong> kendala presensi staf cabang yang perlu ditinjau.
                    </p>
                </div>
            </div>
            <a href="{{ route('leave') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shrink-0 transition-colors shadow-xs">
                <span>Tinjau Pengajuan</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
    @endif

    {{-- ======================================================== --}}
    {{-- 3. TAMPILAN KHUSUS SUPER ADMIN                           --}}
    {{-- ======================================================== --}}
    @if(\App\Support\Access::admin() && isset($adminStats))
        {{-- 4 Kartu KPI Utama Super Admin --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
            {{-- Kartu 1: Total Karyawan Aktif --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Karyawan Aktif</span>
                        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mt-1">{{ $adminStats['total_employees'] }}</h2>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span class="flex items-center gap-1 font-medium">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        {{ $adminStats['total_branches'] }} Cabang Aktif
                    </span>
                    <a href="{{ route('people') }}" class="font-bold text-emerald-700 hover:text-emerald-800 transition-colors">Kelola &rarr;</a>
                </div>
            </div>

            {{-- Kartu 2: Kehadiran Hari Ini --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Kehadiran Hari Ini</span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ $adminStats['attendance_pct'] }}%</h2>
                            <span class="text-xs font-bold text-slate-400">({{ $adminStats['attended_today'] }}/{{ $adminStats['shifts_today'] }})</span>
                        </div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center border border-teal-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span class="text-slate-600">
                        <strong class="text-amber-700">{{ $adminStats['late_today'] }}</strong> Telat &middot; 
                        <strong class="text-slate-700">{{ $adminStats['not_yet_present'] }}</strong> Belum Absen
                    </span>
                    <a href="{{ route('attendance.review') }}" class="font-bold text-emerald-700 hover:text-emerald-800 transition-colors">Tinjau &rarr;</a>
                </div>
            </div>

            {{-- Kartu 3: Tugas Menunggu Keputusan --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Menunggu Tindakan</span>
                        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mt-1">{{ $adminStats['total_pending_tasks'] }}</h2>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center border border-amber-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>{{ $adminStats['pending_leaves'] }} Izin &middot; {{ $adminStats['pending_exceptions'] }} Kendala</span>
                    <a href="{{ route('leave') }}" class="font-bold text-amber-700 hover:text-amber-800 transition-colors">Buka &rarr;</a>
                </div>
            </div>

            {{-- Kartu 4: Siklus Penggajian Bulan Berjalan --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-all">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Gaji: {{ \Carbon\Carbon::parse($adminStats['payroll_month'])->translatedFormat('M Y') }}</span>
                        <div class="mt-1">
                            @if($adminStats['payroll_status'] === 'locked')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Terkunci Final
                                </span>
                            @elseif($adminStats['payroll_status'] === 'approved')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                    Disetujui
                                </span>
                            @elseif($adminStats['payroll_status'] === 'draft')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Draf Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    Belum Diproses
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-slate-50 text-slate-700 flex items-center justify-center border border-slate-200 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Rekapitulasi Insentif</span>
                    <a href="{{ route('payroll.index') }}" class="font-bold text-emerald-700 hover:text-emerald-800 transition-colors">Payroll &rarr;</a>
                </div>
            </div>
        </div>

        {{-- Pusat Tindakan Cepat (Quick Action Center) Super Admin --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 text-white shadow-xs">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/80 mb-4">
                <div>
                    <h2 class="text-base font-bold text-white tracking-tight">Pusat Tindakan Cepat (Quick Action Center)</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Jalan pintas terpadu untuk mengeksekusi operasi penting harian</p>
                </div>
                <span class="text-xs font-mono font-bold text-emerald-400 bg-emerald-950/60 border border-emerald-800/80 px-2.5 py-1 rounded-lg">
                    Super Admin Mode
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <a href="{{ route('people') }}" class="p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 text-center transition-all group hover:border-emerald-500/50">
                    <div class="w-9 h-9 mx-auto rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    </div>
                    <span class="block text-xs font-bold text-slate-200 group-hover:text-white">Kelola Staf</span>
                    <span class="block text-[10px] text-slate-400 mt-0.5">Daftar & Akun</span>
                </a>

                <a href="{{ route('shifts') }}" class="p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 text-center transition-all group hover:border-emerald-500/50">
                    <div class="w-9 h-9 mx-auto rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <span class="block text-xs font-bold text-slate-200 group-hover:text-white">Jadwal Shift</span>
                    <span class="block text-[10px] text-slate-400 mt-0.5">Plot Penugasan</span>
                </a>

                <a href="{{ route('attendance.review') }}" class="p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 text-center transition-all group hover:border-emerald-500/50">
                    <div class="w-9 h-9 mx-auto rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    </div>
                    <span class="block text-xs font-bold text-slate-200 group-hover:text-white">Tinjau Presensi</span>
                    <span class="block text-[10px] text-slate-400 mt-0.5">Lembur & Kendala</span>
                </a>

                <a href="{{ route('payroll.index') }}" class="p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 text-center transition-all group hover:border-emerald-500/50">
                    <div class="w-9 h-9 mx-auto rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span class="block text-xs font-bold text-slate-200 group-hover:text-white">Penggajian</span>
                    <span class="block text-[10px] text-slate-400 mt-0.5">Kalkulasi Gaji</span>
                </a>

                <a href="{{ route('qr.page') }}" class="p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 text-center transition-all group hover:border-emerald-500/50">
                    <div class="w-9 h-9 mx-auto rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    </div>
                    <span class="block text-xs font-bold text-slate-200 group-hover:text-white">Kiosk QR Toko</span>
                    <span class="block text-[10px] text-slate-400 mt-0.5">Display Scanner</span>
                </a>

                <a href="{{ route('audit') }}" class="p-3.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 text-center transition-all group hover:border-emerald-500/50">
                    <div class="w-9 h-9 mx-auto rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <span class="block text-xs font-bold text-slate-200 group-hover:text-white">Audit Log</span>
                    <span class="block text-[10px] text-slate-400 mt-0.5">Keamanan Sistem</span>
                </a>
            </div>
        </div>

        {{-- Siaran Langsung Presensi Hari Ini (Live Attendance Feed) --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 tracking-tight">Siaran Langsung Presensi Staf Terkini</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Aktivitas check-in terbaru di seluruh cabang operasional hari ini</p>
                    </div>
                </div>
                <a href="{{ route('attendance.review') }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 transition-colors">
                    Lihat Semua &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                            <th class="py-2.5 px-3">Karyawan</th>
                            <th class="py-2.5 px-3">Cabang</th>
                            <th class="py-2.5 px-3">Waktu Masuk</th>
                            <th class="py-2.5 px-3">Waktu Pulang</th>
                            <th class="py-2.5 px-3 text-right">Status Keterlambatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentAttendances as $att)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xs uppercase shrink-0">
                                            {{ Str::substr($att->user?->name ?? 'U', 0, 2) }}
                                        </div>
                                        <div>
                                            <span class="font-bold text-slate-900 block text-xs sm:text-sm">{{ $att->user?->name ?? 'Karyawan' }}</span>
                                            <span class="text-[11px] text-slate-400">{{ $att->user?->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-xs text-slate-600 font-medium">
                                    {{ $att->shift?->branch?->name ?? 'Cabang' }}
                                </td>
                                <td class="py-3 px-3 text-xs font-mono font-bold text-slate-800 tabular-nums">
                                    {{ $att->checkin_at ? \Carbon\Carbon::parse($att->checkin_at)->format('H:i:s') : '-' }}
                                </td>
                                <td class="py-3 px-3 text-xs font-mono font-bold text-slate-800 tabular-nums">
                                    {{ $att->checkout_at ? \Carbon\Carbon::parse($att->checkout_at)->format('H:i:s') : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right">
                                    @if($att->late_minutes > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                            Terlambat {{ $att->late_minutes }}m
                                        </span>
                                    @elseif($att->checkin_at)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Tepat Waktu
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                            Belum Absen
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400 text-xs">
                                    Belum ada catatan presensi yang tercatat untuk hari ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ======================================================== --}}
    {{-- 4. TAMPILAN KHUSUS MANAGER CABANG                        --}}
    {{-- ======================================================== --}}
    @if(\App\Support\Access::manager() && !\App\Support\Access::admin() && isset($managerStats))
        {{-- Ringkasan Cabang --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200/60">
                        Supervisi Cabang
                    </span>
                    <h2 class="text-xl font-extrabold text-slate-900 tracking-tight mt-1.5">{{ $managerStats['branch_name'] }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Memantau {{ $managerStats['branch_staff_count'] }} staf terdaftar di cabang ini</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('qr.page', ['branch_id' => auth()->user()->branch_id]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                        <span>Layar Kiosk QR Toko</span>
                    </a>
                    <a href="{{ route('shifts') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors">
                        <span>Kelola Shift</span>
                    </a>
                </div>
            </div>

            {{-- 3 Metrik Cabang --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/70">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Jadwal Shift Hari Ini</span>
                    <strong class="text-2xl font-extrabold text-slate-900 mt-1 block">{{ $managerStats['shifts_today'] }} Orang</strong>
                    <span class="text-[11px] text-slate-400 mt-0.5 block">Staf toko bertugas jam ini</span>
                </div>
                <div class="p-4 rounded-xl bg-emerald-50/60 border border-emerald-200/60">
                    <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block">Sudah Check-in</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <strong class="text-2xl font-extrabold text-emerald-900">{{ $managerStats['attended_today'] }} Orang</strong>
                        <span class="text-xs font-bold text-emerald-700">({{ $managerStats['attendance_pct'] }}%)</span>
                    </div>
                    <span class="text-[11px] text-emerald-600 mt-0.5 block">{{ $managerStats['late_today'] }} orang tercatat terlambat</span>
                </div>
                <div class="p-4 rounded-xl bg-amber-50/60 border border-amber-200/60">
                    <span class="text-xs font-bold text-amber-800 uppercase tracking-wider block">Tugas Approval Cabang</span>
                    <strong class="text-2xl font-extrabold text-amber-900 mt-1 block">{{ $managerStats['total_pending_tasks'] }} Pengajuan</strong>
                    <span class="text-[11px] text-amber-700 mt-0.5 block">{{ $managerStats['pending_leaves'] }} izin &middot; {{ $managerStats['pending_exceptions'] }} kendala presensi</span>
                </div>
            </div>

            {{-- Daftar Staf Cabang yang Bertugas Hari Ini --}}
            <div class="pt-2">
                <h3 class="text-sm font-bold text-slate-900 mb-3">Daftar Staf Bertugas di {{ $managerStats['branch_name'] }} Hari Ini</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                                <th class="py-2 px-3">Nama Staf</th>
                                <th class="py-2 px-3">Jam Jadwal</th>
                                <th class="py-2 px-3">Realisasi Presensi</th>
                                <th class="py-2 px-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($branchTodayShifts as $bShift)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="py-2.5 px-3 font-bold text-slate-900">{{ $bShift->user?->name ?? '-' }}</td>
                                    <td class="py-2.5 px-3 font-mono text-slate-600">
                                        {{ \Carbon\Carbon::parse($bShift->start_at)->format('H:i') }} – {{ \Carbon\Carbon::parse($bShift->end_at)->format('H:i') }}
                                    </td>
                                    <td class="py-2.5 px-3 font-mono text-slate-700">
                                        @if($bShift->attendance?->checkin_at)
                                            Masuk: {{ \Carbon\Carbon::parse($bShift->attendance->checkin_at)->format('H:i') }}
                                            @if($bShift->attendance->checkout_at)
                                                &middot; Pulang: {{ \Carbon\Carbon::parse($bShift->attendance->checkout_at)->format('H:i') }}
                                            @endif
                                        @else
                                            <span class="text-slate-400 italic">Belum check-in</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        @if($bShift->attendance?->checkout_at)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Selesai</span>
                                        @elseif($bShift->attendance?->checkin_at)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif Bekerja</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">Belum Hadir</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-slate-400 italic">
                                        Tidak ada jadwal shift staf yang tercatat untuk cabang ini hari ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ======================================================== --}}
    {{-- 5. PANEL PRESENSI PRIBADI & RIWAYAT (EMPLOYEE / SELF-SERVICE) --}}
    {{-- ======================================================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Kolom Kiri: Jadwal Shift & Panel Presensi (7 Cols) --}}
        <section class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Shift Terdekat</h2>
                        <span class="text-xs text-slate-400">Jadwal tugas pribadi dan verifikasi kehadiran Anda</span>
                    </div>
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
                                        <span>Mengalami kendala GPS, kamera, atau jaringan? Ajukan bantuan / pengecualian</span>
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
                        <p class="text-xs sm:text-sm font-semibold text-slate-500 m-0">Belum ada shift terjadwal untuk akun Anda hari ini.</p>
                        <p class="text-xs text-slate-400 mt-1">Jadwal tugas baru akan muncul otomatis begitu manajer cabang memplot jadwal Anda.</p>
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
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Pengajuan Izin & Sakit Pribadi</h2>
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
                        <p class="text-xs text-slate-400 m-0">Belum ada riwayat pengajuan izin atau sakit pribadi.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection

