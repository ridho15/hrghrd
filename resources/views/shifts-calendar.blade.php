@extends('layouts.app')
@section('title', $mode === 'attendance' ? 'Kalender Tinjau Presensi' : 'Kalender Jadwal Shift')

@section('content')
<div class="space-y-6">
    {{-- Header & Navigasi Tab --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>{{ $mode === 'attendance' ? 'Pusat Audit & Tinjau Kehadiran' : 'Visualisasi Roster & Penjadwalan Tim' }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                {{ $mode === 'attendance' ? 'Kalender Tinjau Presensi' : 'Kalender Jadwal Shift' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $mode === 'attendance'
                    ? 'Pantau realisasi kehadiran staf harian, lacak keterlambatan, deteksi mangkir (alfa), dan tinjau bukti GPS/QR presensi.'
                    : 'Pantau distribusi penugasan kerja per hari, deteksi kekurangan tenaga kerja, dan kelola pertukaran shift secara visual.' }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            {{-- Mode Switcher Pill (Jadwal Shift vs Tinjau Presensi) --}}
            <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200 shadow-2xs">
                <a
                    href="{{ route('shifts.calendar', array_merge(request()->query(), ['mode' => 'schedule'])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $mode === 'schedule' ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Jadwal Shift</span>
                </a>
                <a
                    href="{{ route('shifts.calendar', array_merge(request()->query(), ['mode' => 'attendance'])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $mode === 'attendance' ? 'bg-emerald-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Tinjau Presensi</span>
                </a>
            </div>

            {{-- Lingkup Khusus Karyawan (Jadwal Saya vs Roster Cabang) --}}
            @if(auth()->user()->role === 'employee')
                <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200 shadow-2xs">
                    <a
                        href="{{ route('shifts.calendar', array_merge(request()->query(), ['scope' => 'me', 'user_id' => auth()->id()])) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ ($scope ?? 'me') === 'me' ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <span>Jadwal Saya</span>
                    </a>
                    <a
                        href="{{ route('shifts.calendar', array_merge(request()->except('user_id'), ['scope' => 'branch'])) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ ($scope ?? '') === 'branch' ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <span>Roster Cabang</span>
                    </a>
                </div>
            @endif

            @if(in_array(auth()->user()->role, ['admin', 'manager']) && $mode === 'schedule')
                <a
                    href="{{ route('shifts.create') }}"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>+ Buat Shift</span>
                </a>
            @endif
        </div>
    </div>

    {{-- Tab Switcher Modul: Kalender vs Tabel vs Tukar Shift vs Pusat Tinjau --}}
    <div class="flex items-center gap-2 border-b border-slate-200 overflow-x-auto text-xs sm:text-sm">
        <a
            href="{{ route('shifts.calendar', request()->query()) }}"
            class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-3 border-b-2 border-emerald-600 text-emerald-800 font-bold -mb-px shrink-0"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <span>Kalender {{ $mode === 'attendance' ? 'Presensi' : 'Shift' }}</span>
            <span class="px-2 py-0.5 rounded-full text-xs {{ $mode === 'attendance' ? 'bg-emerald-700 text-white' : 'bg-emerald-100 text-emerald-800' }}">
                {{ $shifts->count() }}
            </span>
        </a>
        @if(in_array(auth()->user()->role, ['admin', 'manager']))
            <a
                href="{{ route('shifts') }}"
                class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 font-medium transition-colors shrink-0"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span>Tabel Jadwal Shift</span>
            </a>
        @endif
        @if(in_array(auth()->user()->role, ['admin', 'manager']))
            <a
                href="{{ route('shifts.exchanges') }}"
                class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 font-medium transition-colors shrink-0"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                <span>Permohonan Tukar Shift</span>
            </a>
        @endif
        @if(in_array(auth()->user()->role, ['admin', 'manager']))
            <a
                href="{{ route('attendance.review') }}"
                class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 font-medium transition-colors shrink-0"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                <span>Pusat Tinjau Presensi</span>
            </a>
        @endif
    </div>

    {{-- Banner Jadwal Kosong & Akses Pengajuan Ganti Shift / Ganti Libur --}}
    @if(in_array(auth()->user()->role, ['admin', 'manager']) && isset($openSlots) && $openSlots->isNotEmpty())
        <div class="p-3.5 sm:p-4 rounded-2xl bg-gradient-to-r from-amber-50 via-amber-50/70 to-emerald-50/50 border border-amber-200/90 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-xl bg-amber-100 text-amber-800 shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-amber-900">Jadwal Kosong Tersedia</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-extrabold bg-amber-200/80 text-amber-900">{{ $openSlots->count() }} Slot Terbuka</span>
                    </div>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Tersedia slot jadwal kerja kosong untuk level setingkat dengan Anda. Ajukan <strong>Ganti Shift</strong> atau <strong>Ganti Libur</strong> untuk mengisi slot ini dan menunggu persetujuan Super Admin / HRD.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    data-open-modal="modal-general-open-slot"
                    class="w-full sm:w-auto px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold uppercase tracking-wider shadow-xs transition-all inline-flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                    <span>Ajukan Ganti Shift / Libur</span>
                </button>
            </div>
        </div>
    @endif

    {{-- Banner Metrik Ringkasan Khusus Mode Tinjau Presensi --}}
    @if($mode === 'attendance')
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="p-3.5 rounded-2xl bg-white border border-slate-200 shadow-2xs">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Total Shift Bulan Ini</span>
                <span class="text-xl font-black text-slate-900 block mt-0.5 tabular-nums">{{ $monthSummary['total_shifts'] }}</span>
                <span class="text-[10px] text-slate-400">Jadwal aktif terdata</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200 shadow-2xs">
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800 block">Hadir Tepat Waktu</span>
                <span class="text-xl font-black text-emerald-700 block mt-0.5 tabular-nums">{{ $monthSummary['total_present'] }}</span>
                <span class="text-[10px] text-emerald-600 font-medium">Presensi valid tanpa denda</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200 shadow-2xs">
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800 block">Terlambat Presensi</span>
                <span class="text-xl font-black text-amber-700 block mt-0.5 tabular-nums">{{ $monthSummary['total_late'] }}</span>
                <span class="text-[10px] text-amber-600 font-medium">Melewati batas toleransi</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-rose-50/70 border border-rose-200 shadow-2xs">
                <span class="text-[11px] font-bold uppercase tracking-wider text-rose-800 block">Alfa / Mangkir</span>
                <span class="text-xl font-black text-rose-700 block mt-0.5 tabular-nums">{{ $monthSummary['total_absent'] }}</span>
                <span class="text-[10px] text-rose-600 font-medium">Shift lewat tanpa hadir</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-purple-50/70 border border-purple-200 shadow-2xs col-span-2 sm:col-span-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-purple-800 block">Pengecualian Menunggu</span>
                <span class="text-xl font-black text-purple-700 block mt-0.5 tabular-nums">{{ $monthSummary['total_exceptions'] }}</span>
                <span class="text-[10px] text-purple-600 font-medium">Menunggu persetujuan</span>
            </div>
        </div>
    @endif

    {{-- Toolbar Kalender: Navigasi Bulan, Filter Terpadu & Switcher Tampilan Mobile --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-3.5 sm:p-4 space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            {{-- Navigasi Bulan Cepat --}}
            <div class="flex items-center justify-between sm:justify-start gap-2">
                <div class="flex items-center gap-1">
                    <a
                        href="{{ route('shifts.calendar', array_merge(request()->query(), ['month' => $prevMonth])) }}"
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                        title="Bulan Sebelumnya"
                        aria-label="Bulan Sebelumnya"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                    </a>

                    <div class="text-center sm:text-left px-2 min-w-[140px] sm:min-w-[170px]">
                        <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-none">
                            {{ $currentMonth->translatedFormat('F Y') }}
                        </h2>
                        <span class="text-[11px] text-slate-400 font-medium">
                            {{ $shifts->count() }} shift tercatat
                        </span>
                    </div>

                    <a
                        href="{{ route('shifts.calendar', array_merge(request()->query(), ['month' => $nextMonth])) }}"
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                        title="Bulan Berikutnya"
                        aria-label="Bulan Berikutnya"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>

                <div class="flex items-center gap-1.5">
                    <a
                        href="{{ route('shifts.calendar', array_merge(request()->query(), ['month' => now('Asia/Jakarta')->format('Y-m'), 'date' => now('Asia/Jakarta')->toDateString()])) }}"
                        class="px-2.5 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors"
                    >
                        Hari Ini
                    </a>

                    {{-- Tombol Toggle Filter di Mobile --}}
                    <button
                        type="button"
                        id="btn-toggle-mobile-filter"
                        class="lg:hidden px-2.5 py-1.5 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold inline-flex items-center gap-1.5 hover:bg-slate-50 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        <span>Filter</span>
                        @if(request()->hasAny(['branch_id', 'user_id', 'status', 'attendance_status']))
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        @endif
                    </button>
                </div>
            </div>

            {{-- Switcher Tampilan Khusus Mobile: [ Kalender Mini ] vs [ List Agenda ] --}}
            <div class="flex lg:hidden items-center justify-between border-t border-slate-100 pt-2.5">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tampilan Mobile:</span>
                <div class="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 text-xs">
                    <button
                        type="button"
                        id="btn-mobile-view-cal"
                        class="px-3 py-1 rounded-md font-bold transition-all bg-white text-emerald-800 shadow-2xs cursor-pointer"
                    >
                        📅 Kalender Mini
                    </button>
                    <button
                        type="button"
                        id="btn-mobile-view-list"
                        class="px-3 py-1 rounded-md font-semibold text-slate-600 hover:text-slate-900 transition-all cursor-pointer"
                    >
                        📋 List Agenda
                    </button>
                </div>
            </div>

            {{-- Formulir Filter (Collapsible di mobile, Inline di Desktop) --}}
            <div id="filter-wrapper" class="hidden lg:block">
                <form method="get" action="{{ route('shifts.calendar') }}" class="flex flex-wrap items-center gap-2 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                    <input type="hidden" name="month" value="{{ $monthStr }}">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    @if(request()->filled('scope') || isset($scope))
                        <input type="hidden" name="scope" value="{{ $scope ?? request('scope') }}">
                    @endif

                    {{-- Filter Cabang (Admin) --}}
                    @if(auth()->user()->role === 'admin')
                        <div class="w-full sm:w-44">
                            @php
                                $calBranchOpts = collect([['value' => '', 'label' => 'Semua Cabang', 'sublabel' => '']])
                                    ->merge($branches->map(fn($b) => ['value' => (string)$b->id, 'label' => $b->name, 'sublabel' => $b->code]));
                            @endphp
                            <x-searchable-select
                                name="branch_id"
                                id="filter-cal-branch"
                                size="sm"
                                :value="request('branch_id')"
                                :options="$calBranchOpts"
                                placeholder="Semua Cabang"
                                searchPlaceholder="Cari cabang..."
                            />
                        </div>
                    @endif

                    {{-- Filter Karyawan (Admin, Manager, atau Karyawan saat Roster Cabang) --}}
                    @if(in_array(auth()->user()->role, ['admin', 'manager']) || ($scope ?? '') === 'branch')
                        <div class="w-full sm:w-44">
                            @php
                                $calPeopleOpts = collect([['value' => '', 'label' => 'Semua Karyawan', 'sublabel' => '']])
                                    ->merge($people->map(fn($p) => ['value' => (string)$p->id, 'label' => $p->name, 'sublabel' => $p->email]));
                            @endphp
                            <x-searchable-select
                                name="user_id"
                                id="filter-cal-user"
                                size="sm"
                                :value="request('user_id')"
                                :options="$calPeopleOpts"
                                placeholder="Semua Karyawan"
                                searchPlaceholder="Cari karyawan..."
                            />
                        </div>
                    @endif

                    {{-- Filter Status Shift (Jika Mode Jadwal) --}}
                    @if($mode === 'schedule')
                        <select
                            name="status"
                            class="w-full sm:w-auto h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs"
                        >
                            <option value="">Semua Status Jadwal</option>
                            <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                            <option value="draft" @selected(request('status') === 'draft')>Draf</option>
                        </select>
                    @endif

                    {{-- Filter Khusus Status Presensi (Jika Mode Attendance) --}}
                    @if($mode === 'attendance')
                        <select
                            name="attendance_status"
                            class="w-full sm:w-auto h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs"
                        >
                            <option value="">Semua Status Presensi</option>
                            <option value="present" @selected($attendanceStatus === 'present')>🟢 Hadir Tepat Waktu</option>
                            <option value="late" @selected($attendanceStatus === 'late')>🟡 Terlambat</option>
                            <option value="absent" @selected($attendanceStatus === 'absent')>🔴 Alfa / Belum Absen</option>
                            <option value="on_duty" @selected($attendanceStatus === 'on_duty')>🔵 Sedang Bertugas</option>
                            <option value="pending_exception" @selected($attendanceStatus === 'pending_exception')>🟣 Pengecualian Pending</option>
                        </select>
                    @endif

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button
                            type="submit"
                            class="flex-1 sm:flex-none h-8 px-3 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition-colors cursor-pointer"
                        >
                            Terapkan
                        </button>

                        @if(request()->hasAny(['branch_id', 'user_id', 'status', 'attendance_status']))
                            <a
                                href="{{ route('shifts.calendar', array_filter(['month' => $monthStr, 'mode' => $mode, 'scope' => $scope ?? null])) }}"
                                class="h-8 px-2.5 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-800 text-xs font-bold inline-flex items-center transition-colors"
                            >
                                Reset
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Legenda Shift & Status Presensi --}}
    <div class="flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-slate-500">
        <div class="flex flex-wrap items-center gap-3">
            @if($mode === 'schedule')
                <span class="inline-flex items-center gap-1.5 font-medium">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black uppercase bg-sky-100 text-sky-800 border border-sky-300/80">S1</span>
                    <span>Shift 1 (Pagi: Mulai &lt; 12:00)</span>
                </span>
                <span class="inline-flex items-center gap-1.5 font-medium">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black uppercase bg-amber-100 text-amber-800 border border-amber-300/80">S2</span>
                    <span>Shift 2 (Siang: Mulai &ge; 12:00)</span>
                </span>
                <span class="inline-flex items-center gap-1.5 font-medium">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold text-slate-500 bg-slate-100 border border-slate-200">Libur</span>
                    <span>Hari Libur (Off-day)</span>
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <span>Hadir Tepat Waktu</span>
                </span>
                <span class="inline-flex items-center gap-1.5 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <span>Terlambat</span>
                </span>
                <span class="inline-flex items-center gap-1.5 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                    <span>Alfa / Mangkir</span>
                </span>
                <span class="inline-flex items-center gap-1.5 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                    <span>Sedang Bertugas</span>
                </span>
            @endif
        </div>
        <div class="text-[11px] text-slate-400">
            * {{ $mode === 'attendance' ? 'Klik kartu untuk melihat forensik presensi & rincian' : 'Klik kartu shift untuk rincian & menugaskan staf pengganti' }}
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- A. TAMPILAN KHUSUS MOBILE (< 768px): KALENDER MINI SENTUH + AGENDA DETAIL --}}
    {{-- ========================================================================= --}}
    <div class="block md:hidden space-y-4">
        {{-- 1. Mode Kalender Mini Sentuh Mobile --}}
        <div id="mobile-cal-section" class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-3">
                {{-- Header Singkat Hari (Sen - Min) --}}
                <div class="grid grid-cols-7 text-center text-[10px] font-black tracking-wider text-slate-500 uppercase pb-2 border-b border-slate-100">
                    <div>Sn</div>
                    <div>Sl</div>
                    <div>Rb</div>
                    <div>Km</div>
                    <div>Jm</div>
                    <div class="text-emerald-700">Sb</div>
                    <div class="text-rose-600">Mg</div>
                </div>

                {{-- Matriks Sel Kalender Mini --}}
                <div class="grid grid-cols-7 gap-1 pt-2">
                    @php
                        $mCursor = $gridStart->copy();
                        $todayStr = now('Asia/Jakarta')->toDateString();
                    @endphp

                    @while($mCursor->lte($gridEnd))
                        @php
                            $mDateStr = $mCursor->toDateString();
                            $mIsCurrentMonth = $mCursor->isSameMonth($currentMonth);
                            $mIsToday = $mDateStr === $todayStr;
                            $mIsSelected = $mDateStr === $selectedDate;
                            $mDayShifts = $groupedShifts->get($mDateStr, collect());
                            $mDayCount = $mDayShifts->count();
                            $mSummary = $attendanceSummaryByDate[$mDateStr] ?? null;
                        @endphp

                        <button
                            type="button"
                            onclick="selectMobileDate('{{ $mDateStr }}')"
                            data-cal-date="{{ $mDateStr }}"
                            class="mobile-date-cell h-11 rounded-xl flex flex-col items-center justify-between p-1 transition-all cursor-pointer relative
                                {{ $mIsSelected ? 'bg-emerald-700 text-white shadow-xs font-black ring-2 ring-emerald-500' : ($mIsToday ? 'bg-emerald-50 text-emerald-900 border border-emerald-300 font-bold' : ($mIsCurrentMonth ? 'text-slate-800 hover:bg-slate-50' : 'text-slate-300 bg-slate-50/40')) }}"
                        >
                            <span class="text-xs tabular-nums {{ $mIsSelected ? 'text-white' : '' }}">
                                {{ $mCursor->day }}
                            </span>

                            {{-- Status Dots di Bawah Tanggal --}}
                            <div class="flex items-center justify-center gap-0.5 h-1.5 w-full">
                                @if($mode === 'schedule')
                                    @if($mDayCount > 0)
                                        <span class="w-1.5 h-1.5 rounded-full {{ $mIsSelected ? 'bg-white' : 'bg-emerald-500' }}"></span>
                                        @if($mDayCount > 1)
                                            <span class="w-1.5 h-1.5 rounded-full {{ $mIsSelected ? 'bg-emerald-200' : 'bg-sky-400' }}"></span>
                                        @endif
                                    @endif
                                @else
                                    @if($mSummary && $mSummary['total_shifts'] > 0)
                                        @if($mSummary['absent'] > 0)
                                            <span class="w-1.5 h-1.5 rounded-full {{ $mIsSelected ? 'bg-rose-200' : 'bg-rose-500' }}" title="Ada Alfa"></span>
                                        @endif
                                        @if($mSummary['late'] > 0)
                                            <span class="w-1.5 h-1.5 rounded-full {{ $mIsSelected ? 'bg-amber-200' : 'bg-amber-500' }}" title="Ada Terlambat"></span>
                                        @endif
                                        @if($mSummary['present'] > 0)
                                            <span class="w-1.5 h-1.5 rounded-full {{ $mIsSelected ? 'bg-emerald-200' : 'bg-emerald-500' }}" title="Ada Hadir"></span>
                                        @endif
                                    @endif
                                @endif
                            </div>
                        </button>

                        @php $mCursor->addDay(); @endphp
                    @endwhile
                </div>
            </div>

            {{-- 2. Panel Kartu Detail Tanggal Terpilih (Mobile Agenda Panel) --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 space-y-3">
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Agenda Detail Harian:</span>
                        <h3 id="mobile-selected-label" class="text-sm font-black text-slate-900 tracking-tight mt-0.5">
                            {{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }}
                        </h3>
                    </div>
                    @if(in_array(auth()->user()->role, ['admin', 'manager']) && $mode === 'schedule')
                        <a
                            id="mobile-add-shift-link"
                            href="{{ route('shifts.create', ['date' => $selectedDate]) }}"
                            class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-[11px] font-bold border border-emerald-200 inline-flex items-center gap-1"
                        >
                            <span>+ Shift</span>
                        </a>
                    @endif
                </div>

                {{-- Wadah Kartu Shift untuk Tanggal Terpilih --}}
                <div id="mobile-agenda-cards-container" class="space-y-3">
                    @php
                        $activeDayShifts = $groupedShifts->get($selectedDate, collect());
                    @endphp

                    @forelse($activeDayShifts as $shiftItem)
                        @include('partials.shift-calendar-mobile-card', ['shiftItem' => $shiftItem, 'mode' => $mode])
                    @empty
                        <div class="text-center py-8 text-slate-400">
                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-xs font-semibold text-slate-600 m-0">Tidak ada jadwal shift pada tanggal ini.</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Ketuk tanggal lain pada kalender mini di atas.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 3. Mode List / Agenda Kronologis Mobile (Tampil saat tombol List Agenda ditekan) --}}
        <div id="mobile-list-section" class="space-y-4 hidden">
            @php
                $hasAnyShifts = $shifts->isNotEmpty();
            @endphp

            @if($hasAnyShifts)
                @foreach($groupedShifts as $listDateStr => $dayListShifts)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                            <span class="text-xs font-black text-slate-900">
                                📅 {{ \Carbon\Carbon::parse($listDateStr)->translatedFormat('l, d M Y') }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                {{ $dayListShifts->count() }} Shift
                            </span>
                        </div>

                        <div class="space-y-2.5">
                            @foreach($dayListShifts as $shiftItem)
                                @include('partials.shift-calendar-mobile-card', ['shiftItem' => $shiftItem, 'mode' => $mode])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-400">
                    <p class="text-xs font-semibold text-slate-600">Tidak ada jadwal shift pada periode bulan ini.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- B. TAMPILAN KHUSUS DESKTOP (≥ 768px): MATRIKS 7-KOLOM LENGKAP             --}}
    {{-- ========================================================================= --}}
    <div class="hidden md:block bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        {{-- Header Nama Hari (Senin - Minggu) --}}
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/80 text-center text-xs font-black tracking-wider text-slate-600 uppercase py-3">
            <div>Senin</div>
            <div>Selasa</div>
            <div>Rabu</div>
            <div>Kamis</div>
            <div>Jumat</div>
            <div class="text-emerald-700">Sabtu</div>
            <div class="text-rose-600">Minggu</div>
        </div>

        {{-- Matriks Sel Kalender Desktop --}}
        <div class="grid grid-cols-7 auto-rows-fr divide-x divide-y divide-slate-100">
            @php
                $cursor = $gridStart->copy();
                $todayStr = now('Asia/Jakarta')->toDateString();
            @endphp

            @while($cursor->lte($gridEnd))
                @php
                    $dateStr = $cursor->toDateString();
                    $isCurrentMonth = $cursor->isSameMonth($currentMonth);
                    $isToday = $dateStr === $todayStr;
                    $dayShifts = $groupedShifts->get($dateStr, collect());
                    $dayCount = $dayShifts->count();
                    $daySummary = $attendanceSummaryByDate[$dateStr] ?? null;
                @endphp

                <div class="min-h-[140px] p-2 flex flex-col justify-between transition-colors {{ $isCurrentMonth ? 'bg-white' : 'bg-slate-50/40 text-slate-400' }} {{ $isToday ? 'ring-2 ring-emerald-500/80 ring-inset bg-emerald-50/10' : '' }}">
                    {{-- Header Sel Tanggal Desktop --}}
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs sm:text-sm font-black tabular-nums {{ $isToday ? 'w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center' : ($isCurrentMonth ? 'text-slate-800' : 'text-slate-400') }}">
                                {{ $cursor->day }}
                            </span>
                            @if($isToday)
                                <span class="hidden sm:inline-block px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-emerald-100 text-emerald-800 uppercase tracking-wider">Hari Ini</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-1">
                            @if($mode === 'attendance' && $daySummary && $daySummary['total_shifts'] > 0)
                                <div class="flex items-center gap-1 text-[9px] font-bold">
                                    @if($daySummary['present'] > 0)
                                        <span class="px-1 py-0.2 rounded bg-emerald-100 text-emerald-800" title="Hadir Tepat Waktu">{{ $daySummary['present'] }}H</span>
                                    @endif
                                    @if($daySummary['late'] > 0)
                                        <span class="px-1 py-0.2 rounded bg-amber-100 text-amber-800" title="Terlambat">{{ $daySummary['late'] }}T</span>
                                    @endif
                                    @if($daySummary['absent'] > 0)
                                        <span class="px-1 py-0.2 rounded bg-rose-100 text-rose-800" title="Alfa/Mangkir">{{ $daySummary['absent'] }}A</span>
                                    @endif
                                </div>
                            @elseif($dayCount > 0)
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 tabular-nums">
                                    {{ $dayCount }}
                                </span>
                            @endif

                            @if(in_array(auth()->user()->role, ['admin', 'manager']))
                                <a
                                    href="{{ route('shifts.create', ['date' => $dateStr]) }}"
                                    class="w-5 h-5 rounded-md hover:bg-emerald-100 text-slate-400 hover:text-emerald-700 flex items-center justify-center transition-colors text-xs font-bold"
                                    title="Tambah shift di tanggal ini"
                                >
                                    +
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Daftar Chip Shift Desktop di Tanggal Ini --}}
                    <div class="space-y-1.5 flex-1 overflow-y-auto max-h-[130px] pr-0.5">
                        @if($dayCount === 0 && (request()->filled('user_id') || auth()->user()->role === 'employee'))
                            <div class="h-full flex items-center justify-center p-2 min-h-[50px]">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold text-slate-400 bg-slate-100/80 border border-slate-200/60 uppercase tracking-wider">
                                    Libur
                                </span>
                            </div>
                        @endif

                        @foreach($dayShifts->take(3) as $shiftItem)
                            @php
                                $startTime = \Carbon\Carbon::parse($shiftItem->start_at)->format('H:i');
                                $endTime = \Carbon\Carbon::parse($shiftItem->end_at)->format('H:i');
                                $startHour = (int) \Carbon\Carbon::parse($shiftItem->start_at)->format('H');
                                $isShift1 = $startHour < 12;
                                $shiftTypeLabel = $isShift1 ? 'Shift 1 (Pagi)' : 'Shift 2 (Siang)';
                                $shiftBadge = $isShift1 ? 'S1' : 'S2';
                                $isApproved = $shiftItem->status === 'approved';
                                $att = $shiftItem->attendance;
                                $attStatus = $att?->status;

                                $isCompleted = $att && $att->checkin_at && $att->checkout_at;
                                $isOnDuty = $att && $att->checkin_at && ! $att->checkout_at;
                                $isPast = \Carbon\Carbon::parse($shiftItem->end_at)->isPast();

                                if ($shiftItem->isOpenSlot) {
                                    // Slot Kosong Level Setingkat
                                    $chipTheme = 'bg-amber-50/90 text-amber-950 border-amber-300 border-dashed hover:bg-amber-100 shadow-2xs';
                                    $badgeClass = 'bg-amber-200 text-amber-900 border border-amber-300';
                                    $dotColor = 'bg-amber-500';
                                    $timeColor = 'text-amber-800 font-semibold';
                                    $branchColor = 'text-amber-700/80';
                                    $statusLabel = 'Slot Kosong (' . $shiftItem->positionName . ')';
                                } elseif ($isCompleted) {
                                    // Sudah check-in dan check-out: Biru agak pekat (solid blue)
                                    $chipTheme = 'bg-blue-600 text-white border-blue-700 hover:bg-blue-700 shadow-2xs';
                                    $badgeClass = 'bg-white/20 text-white border border-white/30';
                                    $dotColor = 'bg-emerald-300';
                                    $timeColor = 'text-blue-100';
                                    $branchColor = 'text-blue-100/90';
                                    $statusLabel = $attStatus === 'late' ? 'Terlambat (Selesai)' : 'Hadir';
                                } elseif ($isOnDuty) {
                                    // Sudah check-in, belum check-out: Biru aktif (sedang berdinas)
                                    $chipTheme = 'bg-sky-100 text-sky-950 border-sky-400 hover:bg-sky-200';
                                    $badgeClass = $isShift1 ? 'bg-sky-200 text-sky-900 border border-sky-300' : 'bg-amber-100 text-amber-800 border border-amber-300';
                                    $dotColor = 'bg-sky-500 animate-pulse';
                                    $timeColor = 'text-sky-800 font-bold';
                                    $branchColor = 'text-slate-500';
                                    $statusLabel = 'Sedang Berdinas';
                                } elseif ($attStatus === 'absent' || (! $att && $isPast)) {
                                    // Alfa / mangkir
                                    $chipTheme = 'bg-rose-50 text-rose-950 border-rose-300 hover:bg-rose-100';
                                    $badgeClass = $isShift1 ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-300';
                                    $dotColor = 'bg-rose-500';
                                    $timeColor = 'text-slate-500';
                                    $branchColor = 'text-slate-500';
                                    $statusLabel = 'Alfa';
                                } elseif ($isApproved) {
                                    // Belum check-in / jadwal sah: Biru muda
                                    $chipTheme = 'bg-sky-50 text-sky-950 border-sky-300 hover:bg-sky-100';
                                    $badgeClass = $isShift1 ? 'bg-sky-100 text-sky-800 border border-sky-300/80' : 'bg-amber-100 text-amber-800 border border-amber-300/80';
                                    $dotColor = 'bg-sky-500';
                                    $timeColor = 'text-slate-500';
                                    $branchColor = 'text-slate-500';
                                    $statusLabel = 'Jadwal Sah';
                                } else {
                                    // Draf
                                    $chipTheme = 'bg-slate-50 text-slate-800 border-slate-200 hover:bg-slate-100';
                                    $badgeClass = 'bg-slate-100 text-slate-700 border border-slate-200';
                                    $dotColor = 'bg-slate-400';
                                    $timeColor = 'text-slate-500';
                                    $branchColor = 'text-slate-500';
                                    $statusLabel = 'Draf';
                                }
                            @endphp

                            <button
                                type="button"
                                data-open-modal="modal-shift-{{ $shiftItem->id }}"
                                title="{{ $statusLabel }}"
                                class="w-full text-left p-1.5 rounded-lg border {{ $chipTheme }} transition-all text-[11px] block shadow-2xs group cursor-pointer"
                            >
                                <div class="flex items-center justify-between gap-1">
                                    <div class="flex items-center gap-1.5 truncate font-bold">
                                        <span class="px-1 py-0.2 rounded text-[9px] font-black uppercase tracking-wider shrink-0 {{ $badgeClass }}">
                                            {{ $shiftBadge }}
                                        </span>
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }} shrink-0"></span>
                                        <span class="truncate">{{ $shiftItem->isOpenSlot ? 'Slot Kosong (' . $shiftItem->positionName . ')' : ($shiftItem->user?->name ?? 'Karyawan') }}</span>
                                    </div>
                                    <span class="text-[10px] font-mono {{ $timeColor }} shrink-0">{{ $startTime }}</span>
                                </div>
                                <div class="flex items-center justify-between text-[10px] {{ $branchColor }} mt-0.5">
                                    <span class="truncate">{{ $shiftItem->branch?->name }}</span>
                                    @if($isCompleted && $att)
                                        <span class="font-bold text-blue-100">
                                            {{ $att->checkin_at ? \Carbon\Carbon::parse($att->checkin_at)->format('H:i') : '-' }}-{{ $att->checkout_at ? \Carbon\Carbon::parse($att->checkout_at)->format('H:i') : '-' }}
                                        </span>
                                    @elseif($isOnDuty && $att)
                                        <span class="font-bold text-sky-800">
                                            Masuk: {{ \Carbon\Carbon::parse($att->checkin_at)->format('H:i') }}
                                        </span>
                                    @else
                                        <span class="font-semibold">{{ $startTime }}-{{ $endTime }}</span>
                                    @endif
                                </div>
                            </button>
                        @endforeach

                        @if($dayCount > 3)
                            @if(in_array(auth()->user()->role, ['admin', 'manager']))
                                <a
                                    href="{{ route('shifts', ['date_from' => $dateStr, 'date_to' => $dateStr]) }}"
                                    class="text-[10px] font-bold text-emerald-700 hover:text-emerald-800 hover:underline block text-center py-1 bg-emerald-50/50 rounded"
                                >
                                    +{{ $dayCount - 3 }} shift lainnya
                                </a>
                            @else
                                <a
                                    href="{{ route('shifts.calendar', array_merge(request()->query(), ['date' => $dateStr])) }}"
                                    class="text-[10px] font-bold text-emerald-700 hover:text-emerald-800 hover:underline block text-center py-1 bg-emerald-50/50 rounded"
                                >
                                    +{{ $dayCount - 3 }} shift lainnya
                                </a>
                            @endif
                        @endif
                    </div>
                </div>

                @php $cursor->addDay(); @endphp
            @endwhile
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL GLOBAL CONTAINER: DETAIL SHIFT, TUKAR SHIFT, TUGASKAN PENGGANTI     --}}
    {{-- ========================================================================= --}}
    @foreach($shifts as $shiftItem)
        @php
            $startTime = \Carbon\Carbon::parse($shiftItem->start_at)->format('H:i');
            $endTime = \Carbon\Carbon::parse($shiftItem->end_at)->format('H:i');
            $startHour = (int) \Carbon\Carbon::parse($shiftItem->start_at)->format('H');
            $isShift1 = $startHour < 12;
            $shiftTypeLabel = $isShift1 ? 'Shift 1 (Pagi)' : 'Shift 2 (Siang)';
            $isApproved = $shiftItem->status === 'approved';
            $att = $shiftItem->attendance;
        @endphp

        {{-- 1. Modal Detail Lengkap Shift & Presensi --}}
        <div
            id="modal-shift-{{ $shiftItem->id }}"
            data-modal-container
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-bold {{ $isApproved ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                <span>{{ $isApproved ? 'Shift Disetujui' : 'Draf Shift' }} · v{{ $shiftItem->version }}</span>
                            </span>
                            @if($att)
                                <span class="px-2 py-0.5 rounded text-xs font-bold {{ $att->status === 'present' ? 'bg-emerald-100 text-emerald-800' : ($att->status === 'late' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                    Presensi: {{ strtoupper($att->status) }}
                                </span>
                            @endif
                        </div>
                        <h3 class="text-lg font-black text-slate-900 mt-1">Dossier Jadwal & Presensi</h3>
                    </div>
                    <button
                        type="button"
                        data-close-modal
                        class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs sm:text-sm">
                    {{-- Profil Karyawan & Cabang --}}
                    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                        @if($shiftItem->isOpenSlot)
                            <div>
                                <span class="text-amber-700 text-xs font-bold block">Status Jadwal:</span>
                                <span class="font-extrabold text-amber-900 block mt-0.5">Slot Kosong (Belum Ditugaskan)</span>
                                <span class="text-xs text-emerald-700 font-mono font-bold">Level Jabatan: {{ $shiftItem->positionName }}</span>
                            </div>
                        @else
                            <div>
                                <span class="text-slate-400 text-xs block">Karyawan Bertugas:</span>
                                <span class="font-bold text-slate-900 block mt-0.5">{{ $shiftItem->user?->name }}</span>
                                <span class="text-xs text-slate-500 font-mono">{{ $shiftItem->user?->email }}</span>
                            </div>
                        @endif
                        <div>
                            <span class="text-slate-400 text-xs block">Lokasi Cabang:</span>
                            <span class="font-bold text-slate-900 block mt-0.5">{{ $shiftItem->branch?->name }}</span>
                            <span class="text-xs text-slate-500 font-mono">Kode: {{ $shiftItem->branch?->code }}</span>
                        </div>
                    </div>

                    {{-- Waktu Kerja Rencana --}}
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-1.5">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Tipe Shift:</span>
                            <span class="font-bold {{ $isShift1 ? 'text-sky-700' : 'text-amber-700' }}">
                                {{ $shiftTypeLabel }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Tanggal Shift:</span>
                            <span class="font-bold text-slate-900">{{ \Carbon\Carbon::parse($shiftItem->start_at)->translatedFormat('l, d F Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Jam Jadwal Roster:</span>
                            <span class="font-bold text-slate-900 font-mono">{{ $startTime }} – {{ $endTime }} WIB</span>
                        </div>
                    </div>

                    {{-- Data Realisasi Presensi (Audit Box) --}}
                    <div class="p-3 rounded-xl border {{ $att ? 'bg-emerald-50/40 border-emerald-200' : 'bg-slate-50 border-slate-200' }} space-y-1.5">
                        <span class="text-xs font-bold text-slate-900 block uppercase tracking-wider">Realisasi Kehadiran:</span>
                        @if($att)
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-600">Jam Check-in:</span>
                                <span class="font-mono font-bold text-slate-900">
                                    {{ $att->checkin_at ? \Carbon\Carbon::parse($att->checkin_at)->format('H:i:s') : 'Belum Check-in' }}
                                </span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-600">Jam Check-out:</span>
                                <span class="font-mono font-bold text-slate-900">
                                    {{ $att->checkout_at ? \Carbon\Carbon::parse($att->checkout_at)->format('H:i:s') : 'Belum Check-out' }}
                                </span>
                            </div>
                            @if($att->late_units > 0)
                                <div class="flex justify-between text-xs text-amber-800 font-semibold">
                                    <span>Keterlambatan:</span>
                                    <span>{{ $att->late_units }} Unit ({{ $att->late_units * 15 }} Menit) &middot; Denda Rp{{ number_format($att->late_units * 10000, 0, ',', '.') }}</span>
                                </div>
                            @endif
                        @else
                            <p class="text-xs text-slate-500 m-0">
                                {{ $shiftItem->isOpenSlot ? 'Slot jadwal kosong ini dapat diajukan oleh staf level setingkat dengan persetujuan Super Admin / HRD.' : (\Carbon\Carbon::parse($shiftItem->end_at)->isPast() ? 'Karyawan tidak hadir / mangkir pada jadwal ini.' : 'Shift belum berlangsung atau karyawan belum melakukan scan presensi.') }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Tombol Aksi Cepat Modal --}}
                <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                    @if(in_array(auth()->user()->role, ['admin', 'manager']))
                        <a
                            href="{{ route('shifts.show', $shiftItem->id) }}"
                            class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors inline-flex items-center gap-1.5"
                        >
                            <span>Lihat Roster Lengkap</span>
                        </a>
                    @else
                        <button
                            type="button"
                            data-close-modal
                            class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                        >
                            <span>Tutup</span>
                        </button>
                    @endif

                    <div class="flex items-center gap-2">
                        @if($shiftItem->isOpenSlot)
                            @if(in_array(auth()->user()->role, ['admin', 'manager']))
                                <button
                                    type="button"
                                    data-close-modal
                                    data-open-modal="modal-claim-open-slot-{{ $shiftItem->id }}"
                                    class="px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer shadow-xs"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                    <span>Ajukan Ambil Jadwal Ini</span>
                                </button>
                            @endif
                        @elseif(! $shiftItem->attendance()->exists())
                            @if(in_array(auth()->user()->role, ['admin', 'manager']))
                                <button
                                    type="button"
                                    data-close-modal
                                    data-open-modal="modal-reassign-shift-{{ $shiftItem->id }}"
                                    class="px-3.5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                                >
                                    <span>Tugaskan Pengganti</span>
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Modal Formulir Permohonan Pergantian / Pertukaran Shift Milik Karyawan --}}
        @if(! $shiftItem->isOpenSlot && ! $shiftItem->attendance()->exists() && in_array(auth()->user()->role, ['admin', 'manager']))
            <div
                id="modal-exchange-shift-{{ $shiftItem->id }}"
                data-modal-container
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
            >
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Ajukan Pergantian / Tukar Shift</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Shift: {{ \Carbon\Carbon::parse($shiftItem->start_at)->translatedFormat('d M Y') }} ({{ $startTime }}-{{ $endTime }})</p>
                        </div>
                        <button type="button" data-close-modal class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form method="post" action="{{ route('shifts.exchange.store', $shiftItem->id) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilihan Jenis Pergantian</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                    <input type="radio" name="type" value="replacement" checked class="text-emerald-600 focus:ring-emerald-500" onchange="toggleExchangeBoxes('{{ $shiftItem->id }}', 'replacement')">
                                    <span>Penggantian Rekan (Searah)</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                    <input type="radio" name="type" value="swap" class="text-emerald-600 focus:ring-emerald-500" onchange="toggleExchangeBoxes('{{ $shiftItem->id }}', 'swap')">
                                    <span>Tukar Rekan (Dua Arah)</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-amber-200 bg-amber-50/40 cursor-pointer hover:bg-amber-50 text-xs font-bold text-amber-900">
                                    <input type="radio" name="type" value="change_shift" class="text-amber-600 focus:ring-amber-500" onchange="toggleExchangeBoxes('{{ $shiftItem->id }}', 'change_shift')">
                                    <span>Ganti ke Jadwal Kosong</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-indigo-200 bg-indigo-50/40 cursor-pointer hover:bg-indigo-50 text-xs font-bold text-indigo-900">
                                    <input type="radio" name="type" value="change_off_day" class="text-indigo-600 focus:ring-indigo-500" onchange="toggleExchangeBoxes('{{ $shiftItem->id }}', 'change_off_day')">
                                    <span>Ganti Libur ke Jadwal Kosong</span>
                                </label>
                            </div>
                        </div>

                        {{-- Box Penggantian Searah Rekan --}}
                        <div id="exchange-rep-box-{{ $shiftItem->id }}">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Karyawan Pengganti</label>
                            <select name="replacement_id" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                                <option value="">-- Pilih Rekan Pengganti --</option>
                                @foreach($people->where('id', '!=', $shiftItem->user_id) as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->position?->name ?? 'Staf' }})</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Box Tukar Dua Arah Rekan --}}
                        <div id="exchange-swap-box-{{ $shiftItem->id }}" class="hidden">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Jadwal Shift Rekan yang Ingin Ditukar</label>
                            <select name="target_shift_id" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                                <option value="">-- Pilih Jadwal Shift Rekan --</option>
                                @foreach($shifts->where('user_id', '!=', $shiftItem->user_id)->whereNotNull('user_id') as $otherShift)
                                    <option value="{{ $otherShift->id }}">
                                        {{ $otherShift->user?->name }} · {{ \Carbon\Carbon::parse($otherShift->start_at)->format('d M') }} ({{ \Carbon\Carbon::parse($otherShift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($otherShift->end_at)->format('H:i') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Box Jadwal Kosong (Ganti Shift & Ganti Libur) --}}
                        <div id="exchange-open-box-{{ $shiftItem->id }}" class="hidden">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Jadwal Kosong Level Setingkat *</label>
                            @if(isset($openSlots) && $openSlots->isNotEmpty())
                                <select name="target_shift_id" class="w-full h-10 px-3 bg-white border border-amber-300 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600">
                                    <option value="">-- Pilih Slot Jadwal Kosong --</option>
                                    @foreach($openSlots as $slot)
                                        <option value="{{ $slot->id }}">
                                            {{ \Carbon\Carbon::parse($slot->start_at)->translatedFormat('l, d M Y') }} ({{ \Carbon\Carbon::parse($slot->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($slot->end_at)->format('H:i') }}) · Level: {{ $slot->positionName }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] text-amber-800 mt-1">Jadwal kosong harus setingkat dengan posisi Anda dan langsung diteruskan ke Super Admin / HRD.</p>
                            @else
                                <div class="p-3 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-xs">
                                    Tidak ada jadwal kosong yang tersedia untuk level posisi Anda pada cabang ini.
                                </div>
                            @endif
                        </div>

                        <div id="exchange-off-date-box-{{ $shiftItem->id }}" class="hidden">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tanggal Hari Libur yang Diganti</label>
                            <input type="date" name="target_date" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" value="{{ $shiftItem->start_at->toDateString() }}">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alasan Permohonan *</label>
                            <textarea
                                name="reason"
                                rows="3"
                                required
                                minlength="5"
                                maxlength="500"
                                placeholder="Jelaskan alasan pengalihan, pertukaran, atau pergantian jadwal kosong ini..."
                                class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                            ></textarea>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" data-close-modal class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50 cursor-pointer">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider cursor-pointer">Kirim Permohonan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- 2B. Modal Formulir Pengajuan Ambil Khusus Slot Kosong Ini --}}
        @if($shiftItem->isOpenSlot && in_array(auth()->user()->role, ['admin', 'manager']))
            <div
                id="modal-claim-open-slot-{{ $shiftItem->id }}"
                data-modal-container
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
            >
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                        <div>
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 text-[11px] font-bold border border-amber-200 mb-1">
                                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                <span>Level Setingkat: {{ $shiftItem->positionName }}</span>
                            </div>
                            <h3 class="text-lg font-black text-slate-900">Ajukan Ambil Jadwal Kosong</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ \Carbon\Carbon::parse($shiftItem->start_at)->translatedFormat('l, d M Y') }} ({{ $startTime }}-{{ $endTime }})</p>
                        </div>
                        <button type="button" data-close-modal class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form method="post" action="{{ route('shifts.exchanges.open-slot') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="target_shift_id" value="{{ $shiftItem->id }}">

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tipe Pengajuan</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                    <input type="radio" name="type" value="change_shift" checked class="text-emerald-600 focus:ring-emerald-500" onchange="document.getElementById('claim-req-shift-box-{{ $shiftItem->id }}').classList.remove('hidden'); document.getElementById('claim-target-date-box-{{ $shiftItem->id }}').classList.add('hidden');">
                                    <span>Ganti Shift Kerja</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                    <input type="radio" name="type" value="change_off_day" class="text-emerald-600 focus:ring-emerald-500" onchange="document.getElementById('claim-req-shift-box-{{ $shiftItem->id }}').classList.add('hidden'); document.getElementById('claim-target-date-box-{{ $shiftItem->id }}').classList.remove('hidden');">
                                    <span>Ganti Hari Libur</span>
                                </label>
                            </div>
                        </div>

                        <div id="claim-req-shift-box-{{ $shiftItem->id }}">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Shift Asal Anda (Yang Mau Diliburkan) *</label>
                            @php
                                $myShiftsList = $shifts->where('user_id', auth()->id())->where('end_at', '>', now('Asia/Jakarta'));
                            @endphp
                            <select name="requester_shift_id" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                                <option value="">-- Pilih Shift Kerja Anda --</option>
                                @foreach($myShiftsList as $ms)
                                    <option value="{{ $ms->id }}">
                                        {{ \Carbon\Carbon::parse($ms->start_at)->translatedFormat('l, d M Y') }} ({{ \Carbon\Carbon::parse($ms->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($ms->end_at)->format('H:i') }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1">Shift asal Anda akan otomatis dialihkan menjadi hari libur jika disetujui.</p>
                        </div>

                        <div id="claim-target-date-box-{{ $shiftItem->id }}" class="hidden">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tanggal Hari Libur yang Diganti</label>
                            <input type="date" name="target_date" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" value="{{ now('Asia/Jakarta')->toDateString() }}">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alasan Permohonan *</label>
                            <textarea
                                name="reason"
                                rows="3"
                                required
                                minlength="5"
                                maxlength="500"
                                placeholder="Jelaskan alasan pengajuan pergantian shift/libur ke jadwal kosong ini..."
                                class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                            ></textarea>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" data-close-modal class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50 cursor-pointer">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold uppercase tracking-wider cursor-pointer shadow-xs">Ajukan ke Super Admin / HRD</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- 3. Modal Penugasan Langsung oleh Manajer --}}
        @if(! $shiftItem->isOpenSlot && in_array(auth()->user()->role, ['admin', 'manager']))
            <div
                id="modal-reassign-shift-{{ $shiftItem->id }}"
                data-modal-container
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
            >
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4">
                    <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Tugaskan Pengganti Langsung</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Penugasan manajerial langsung tanpa alur pengajuan staf.</p>
                        </div>
                        <button type="button" data-close-modal class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form method="post" action="{{ route('shifts.reassign', $shiftItem->id) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Karyawan Pengganti</label>
                            <select name="replacement_id" required class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                                <option value="">-- Pilih Staf Pengganti --</option>
                                @foreach($people->where('id', '!=', $shiftItem->user_id) as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->position?->name ?? 'Staf' }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alasan Penugasan</label>
                            <textarea
                                name="reason"
                                rows="3"
                                required
                                minlength="5"
                                maxlength="500"
                                placeholder="Misal: Karyawan berhalangan sakit mendadak, digantikan oleh staf pengganti..."
                                class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                            ></textarea>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" data-close-modal class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider">Tugaskan Sekarang</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

    {{-- 4. Modal Formulir Pengajuan Ganti Shift / Libur ke Jadwal Kosong --}}
    @if(in_array(auth()->user()->role, ['admin', 'manager']))
    <div
        id="modal-general-open-slot"
        data-modal-container
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
    >
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 text-[11px] font-bold border border-amber-200 mb-1">
                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span>Level Setingkat & Persetujuan Super Admin / HRD</span>
                    </div>
                    <h3 class="text-lg font-black text-slate-900">Ajukan Ganti Shift / Libur</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Ambil jadwal kosong yang tersedia di cabang Anda.</p>
                </div>
                <button type="button" data-close-modal class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="post" action="{{ route('shifts.exchanges.open-slot') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tipe Permohonan</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                            <input type="radio" name="type" value="change_shift" checked class="text-emerald-600 focus:ring-emerald-500" onchange="document.getElementById('gen-req-shift-box').classList.remove('hidden'); document.getElementById('gen-target-date-box').classList.add('hidden');">
                            <span>Ganti Shift Kerja</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                            <input type="radio" name="type" value="change_off_day" class="text-emerald-600 focus:ring-emerald-500" onchange="document.getElementById('gen-req-shift-box').classList.add('hidden'); document.getElementById('gen-target-date-box').classList.remove('hidden');">
                            <span>Ganti Hari Libur</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Jadwal Kosong (Level Setingkat) *</label>
                    @if(isset($openSlots) && $openSlots->isNotEmpty())
                        <select name="target_shift_id" required class="w-full h-10 px-3 bg-white border border-amber-300 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-600">
                            <option value="">-- Pilih Jadwal Kosong Tersedia --</option>
                            @foreach($openSlots as $slot)
                                <option value="{{ $slot->id }}">
                                    {{ \Carbon\Carbon::parse($slot->start_at)->translatedFormat('l, d M Y') }} ({{ \Carbon\Carbon::parse($slot->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($slot->end_at)->format('H:i') }}) · Level: {{ $slot->positionName }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="p-3 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-xs">
                            Tidak ada jadwal kosong yang tersedia untuk level setingkat pada periode ini.
                        </div>
                    @endif
                </div>

                <div id="gen-req-shift-box">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Shift Asal Milik Anda (Yang Ingin Diliburkan / Dipindah) *</label>
                    @php
                        $myShifts = $shifts->where('user_id', auth()->id())->where('end_at', '>', now('Asia/Jakarta'));
                    @endphp
                    <select name="requester_shift_id" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <option value="">-- Pilih Shift Kerja Anda --</option>
                        @foreach($myShifts as $ms)
                            <option value="{{ $ms->id }}">
                                {{ \Carbon\Carbon::parse($ms->start_at)->translatedFormat('l, d M Y') }} ({{ \Carbon\Carbon::parse($ms->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($ms->end_at)->format('H:i') }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">Shift asal Anda akan otomatis diliburkan setelah disetujui Super Admin / HRD.</p>
                </div>

                <div id="gen-target-date-box" class="hidden">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tanggal Hari Libur yang Diganti</label>
                    <input type="date" name="target_date" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" value="{{ now('Asia/Jakarta')->toDateString() }}">
                    <p class="text-[11px] text-slate-500 mt-1">Tanggal libur Anda yang ingin digantikan dengan jadwal kosong ini.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alasan Permohonan *</label>
                    <textarea
                        name="reason"
                        rows="3"
                        required
                        minlength="5"
                        maxlength="500"
                        placeholder="Jelaskan alasan pengajuan ganti shift atau ganti libur ini..."
                        class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    ></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" data-close-modal class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50 cursor-pointer">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold uppercase tracking-wider cursor-pointer shadow-xs">Kirim ke Super Admin / HRD</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

{{-- Skrip Interaktivitas Kalender Mobile & Switcher Tampilan --}}
@push('scripts')
<script>
    // Helper Switcher Box Modal Exchange
    window.toggleExchangeBoxes = function(shiftId, type) {
        const repBox = document.getElementById('exchange-rep-box-' + shiftId);
        const swapBox = document.getElementById('exchange-swap-box-' + shiftId);
        const openBox = document.getElementById('exchange-open-box-' + shiftId);
        const offBox = document.getElementById('exchange-off-date-box-' + shiftId);

        if (repBox) repBox.classList.add('hidden');
        if (swapBox) swapBox.classList.add('hidden');
        if (openBox) openBox.classList.add('hidden');
        if (offBox) offBox.classList.add('hidden');

        if (type === 'replacement' && repBox) repBox.classList.remove('hidden');
        if (type === 'swap' && swapBox) swapBox.classList.remove('hidden');
        if ((type === 'change_shift' || type === 'change_off_day') && openBox) openBox.classList.remove('hidden');
        if (type === 'change_off_day' && offBox) offBox.classList.remove('hidden');
    };

    // 1. Toggle Filter Drawer di Mobile
    const btnToggleFilter = document.getElementById('btn-toggle-mobile-filter');
    const filterWrapper = document.getElementById('filter-wrapper');
    if (btnToggleFilter && filterWrapper) {
        btnToggleFilter.addEventListener('click', () => {
            filterWrapper.classList.toggle('hidden');
        });
    }

    // 2. Switcher Antara Kalender Mini vs List Agenda di Mobile
    const btnCal = document.getElementById('btn-mobile-view-cal');
    const btnList = document.getElementById('btn-mobile-view-list');
    const secCal = document.getElementById('mobile-cal-section');
    const secList = document.getElementById('mobile-list-section');

    if (btnCal && btnList && secCal && secList) {
        btnCal.addEventListener('click', () => {
            secCal.classList.remove('hidden');
            secList.classList.add('hidden');
            btnCal.classList.add('bg-white', 'text-emerald-800', 'shadow-2xs');
            btnCal.classList.remove('text-slate-600');
            btnList.classList.remove('bg-white', 'text-emerald-800', 'shadow-2xs');
            btnList.classList.add('text-slate-600');
        });

        btnList.addEventListener('click', () => {
            secCal.classList.add('hidden');
            secList.classList.remove('hidden');
            btnList.classList.add('bg-white', 'text-emerald-800', 'shadow-2xs');
            btnList.classList.remove('text-slate-600');
            btnCal.classList.remove('bg-white', 'text-emerald-800', 'shadow-2xs');
            btnCal.classList.add('text-slate-600');
        });
    }

    // 3. Data Dictionary Shift Per Tanggal untuk Mobile Fast Tap Switching
    const shiftDataByDate = @json($groupedShifts);

    // Fungsi Saat Mengetuk Tanggal di Kalender Mini Mobile
    window.selectMobileDate = function(dateStr) {
        // Update styling tombol tanggal aktif
        document.querySelectorAll('.mobile-date-cell').forEach(cell => {
            if (cell.dataset.calDate === dateStr) {
                cell.classList.add('bg-emerald-700', 'text-white', 'shadow-xs', 'font-black', 'ring-2', 'ring-emerald-500');
                cell.classList.remove('text-slate-800', 'hover:bg-slate-50', 'bg-emerald-50', 'text-emerald-900');
            } else {
                cell.classList.remove('bg-emerald-700', 'text-white', 'shadow-xs', 'font-black', 'ring-2', 'ring-emerald-500');
                if (cell.dataset.calDate === "{{ now('Asia/Jakarta')->toDateString() }}") {
                    cell.classList.add('bg-emerald-50', 'text-emerald-900', 'border', 'border-emerald-300');
                } else {
                    cell.classList.add('text-slate-800', 'hover:bg-slate-50');
                }
            }
        });

        // Format label tanggal
        const dateObj = new Date(dateStr + 'T00:00:00');
        const formattedDate = dateObj.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        const labelEl = document.getElementById('mobile-selected-label');
        if (labelEl) labelEl.textContent = formattedDate;

        const addShiftLink = document.getElementById('mobile-add-shift-link');
        if (addShiftLink) {
            addShiftLink.href = "{{ route('shifts.create') }}?date=" + dateStr;
        }

        // Render kartu-kartu shift untuk tanggal ini
        const cardsContainer = document.getElementById('mobile-agenda-cards-container');
        const shifts = shiftDataByDate[dateStr] || [];

        if (shifts.length === 0) {
            cardsContainer.innerHTML = `
                <div class="text-center py-8 text-slate-400">
                    <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <p class="text-xs font-semibold text-slate-600 m-0">Tidak ada jadwal shift pada tanggal ini.</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Ketuk tanggal lain pada kalender mini di atas.</p>
                </div>
            `;
            return;
        }

        // Render setiap kartu shift
        let html = '';
        shifts.forEach(s => {
            const startHour = parseInt((s.start_at || '').substring(11, 13) || '0');
            const isS1 = startHour < 12;
            const shiftBadge = isS1 ? 'S1' : 'S2';
            const shiftTypeLabel = isS1 ? 'Shift 1 (Pagi)' : 'Shift 2 (Siang)';
            const startTime = (s.start_at || '').substring(11, 16);
            const endTime = (s.end_at || '').substring(11, 16);
            const att = s.attendance;
            const userName = s.user ? s.user.name : 'Karyawan';
            const branchName = s.branch ? s.branch.name : '-';
            const posName = (s.user && s.user.position) ? s.user.position.name : 'Staf';

            let attBadgeHtml = '';
            let attCardBorder = 'border-slate-200';
            const isCompleted = att && att.checkin_at && att.checkout_at;
            const isOnDuty = att && att.checkin_at && !att.checkout_at;

            if (isCompleted) {
                attBadgeHtml = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-600 text-white shadow-2xs">Presensi Tuntas</span>`;
                attCardBorder = 'border-blue-400 bg-blue-50/40';
            } else if (isOnDuty) {
                attBadgeHtml = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-300">Sedang Berdinas</span>`;
                attCardBorder = 'border-sky-300 bg-sky-50/40';
            } else if (att && att.status === 'absent') {
                attBadgeHtml = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">Alfa</span>`;
                attCardBorder = 'border-rose-200 bg-rose-50/20';
            } else {
                attBadgeHtml = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-50 text-sky-800 border border-sky-200">Jadwal Sah</span>`;
                attCardBorder = 'border-slate-200 bg-white';
            }

            html += `
                <div class="p-3.5 rounded-xl border ${attCardBorder} shadow-2xs space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase ${isS1 ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800'}">
                                    ${shiftBadge} · ${shiftTypeLabel}
                                </span>
                                ${attBadgeHtml}
                            </div>
                            <h4 class="text-sm font-extrabold text-slate-900 mt-1">${userName}</h4>
                            <span class="text-[11px] text-slate-500">${posName} · ${branchName}</span>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-xs font-mono font-bold text-slate-900 block">${startTime} – ${endTime}</span>
                            <span class="text-[10px] text-slate-400">WIB</span>
                        </div>
                    </div>

                    ${att ? `
                        <div class="p-2.5 rounded-lg bg-white border border-slate-100 text-xs space-y-1">
                            <div class="flex justify-between text-[11px]">
                                <span class="text-slate-500">Scan Check-in:</span>
                                <span class="font-mono font-bold text-slate-800">${(att.checkin_at || '').substring(11, 16) || '-'}</span>
                            </div>
                            <div class="flex justify-between text-[11px]">
                                <span class="text-slate-500">Scan Check-out:</span>
                                <span class="font-mono font-bold text-slate-800">${(att.checkout_at || '').substring(11, 16) || '-'}</span>
                            </div>
                        </div>
                    ` : ''}

                    <div class="pt-2 border-t border-slate-100/80 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            data-open-modal="modal-shift-${s.id}"
                            class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors cursor-pointer"
                        >
                            Detail Penuh
                        </button>
                    </div>
                </div>
            `;
        });

        cardsContainer.innerHTML = html;
    };
</script>
@endpush
@endsection
