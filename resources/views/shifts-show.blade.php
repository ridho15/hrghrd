@extends('layouts.app')
@section('title', 'Detail Jadwal Shift: ' . ($shift->employee_name ?? 'Karyawan'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('shifts') }}" class="hover:text-emerald-700 transition-colors">Jadwal Shift</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">#SHF-{{ str_pad($shift->id, 5, '0', STR_PAD_LEFT) }}</span>
    </nav>

    {{-- Header Dossier & Tombol Aksi --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-7">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-800 font-extrabold text-xl flex items-center justify-center border border-emerald-200/60 shadow-2xs shrink-0">
                    {{ strtoupper(substr($shift->employee_name ?? 'SH', 0, 2)) }}
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-xs font-mono">
                            #SHF-{{ str_pad($shift->id, 5, '0', STR_PAD_LEFT) }}
                        </span>
                        @if($shift->status === 'approved')
                            <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold text-xs inline-flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>Disetujui Operasional</span>
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 font-bold text-xs inline-flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                <span>Menunggu Persetujuan (Draf)</span>
                            </span>
                        @endif
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-semibold text-[11px]">
                            Versi {{ $shift->version }}
                        </span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        {{ $shift->employee_name }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                        <span>Jabatan: <strong class="text-slate-700">{{ $shift->user?->position?->name ?? 'Staf' }}</strong></span>
                        <span>&middot;</span>
                        <span>Cabang: <strong class="text-slate-700">{{ $shift->branch_name }}</strong></span>
                        @if($shift->user)
                            <span>&middot;</span>
                            <a href="{{ route('people.show', $shift->user_id) }}" class="text-emerald-700 hover:underline font-semibold inline-flex items-center gap-1">
                                <span>Lihat Berkas Karyawan</span>
                                <svg class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Tombol Aksi Mandiri --}}
            <div class="flex flex-wrap items-center gap-2.5">
                <a
                    href="{{ route('shifts') }}"
                    class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Daftar Shift</span>
                </a>

                {{-- Tombol Persetujuan Khusus Admin --}}
                @if($shift->status === 'draft' && auth()->user()->role === 'admin')
                    <button
                        type="button"
                        data-confirm="Setujui jadwal shift ini agar berlaku efektif untuk karyawan?"
                        data-confirm-title="Persetujuan Jadwal Shift"
                        data-confirm-variant="info"
                        data-confirm-btn="Ya, Setujui Shift"
                        data-confirm-action="{{ route('shifts.approve', $shift->id) }}"
                        class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all inline-flex items-center gap-1.5 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Setujui Shift</span>
                    </button>
                @endif

                {{-- Tombol Edit, Ganti/Tukar & Batalkan jika belum ada absensi atau Super Admin --}}
                @if(!$shift->attendance || \App\Support\Access::admin())
                    @if(!$shift->attendance)
                        <button
                            type="button"
                            data-open-modal="modal-exchange-shift"
                            class="px-4 py-2.5 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-800 border border-sky-200 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                            title="Ajukan pergantian atau pertukaran shift"
                        >
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            <span>Ganti / Tukar Shift</span>
                        </button>

                        @if(in_array(auth()->user()->role, ['admin', 'manager']))
                            <button
                                type="button"
                                data-open-modal="modal-reassign-shift"
                                class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                                title="Tugaskan pengganti secara langsung"
                            >
                                <span>Tugaskan Pengganti</span>
                            </button>
                        @endif
                    @endif

                    @if($shift->status === 'draft' || \App\Support\Access::admin())
                        <a
                            href="{{ route('shifts.edit', $shift->id) }}"
                            class="px-4 py-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5"
                            title="{{ $shift->status === 'approved' ? 'Super Admin Override: Revisi Jam Shift' : 'Edit Jadwal' }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span>{{ $shift->status === 'approved' ? 'Revisi Jadwal' : 'Edit Jadwal' }}</span>
                        </a>
                    @endif

                    @if(! $shift->attendance && ! $shift->exceptions?->count())
                        <button
                            type="button"
                            data-confirm="{{ 'Apakah Anda yakin ingin membatalkan dan menghapus jadwal shift untuk ' . $shift->employee_name . ' pada ' . \Carbon\Carbon::parse($shift->start_at)->translatedFormat('d M Y') . '?' }}"
                            data-confirm-title="Batalkan Jadwal Shift"
                            data-confirm-variant="danger"
                            data-confirm-btn="Ya, Batalkan Jadwal"
                            data-confirm-action="{{ route('shifts.destroy', $shift->id) }}"
                            class="p-2.5 rounded-xl text-rose-600 hover:bg-rose-50 border border-rose-200 transition-colors cursor-pointer"
                            title="Batalkan & Hapus Shift"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    @elseif(\App\Support\Access::admin())
                        {{-- Super Admin Force Delete Override --}}
                        <button
                            type="button"
                            data-confirm="{{ 'PERINGATAN SUPER ADMIN: Shift ini sudah memiliki rekaman absensi/tiket kendala. Menghapus shift ini akan menghapus paksa seluruh presensi terkait dan tercatat di Jejak Audit. Lanjutkan penghapusan jadwal untuk ' . $shift->employee_name . '?' }}"
                            data-confirm-title="Hapus Paksa Shift (Super Admin Override)"
                            data-confirm-variant="danger"
                            data-confirm-btn="Ya, Hapus Paksa Shift & Presensi"
                            data-confirm-action="{{ route('shifts.destroy', $shift->id) }}"
                            class="px-3.5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5 shadow-sm cursor-pointer"
                            title="Super Admin: Hapus Paksa Shift & Presensi Terkait"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <span>Hapus Paksa (Admin)</span>
                        </button>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 text-slate-500 border border-slate-200 text-xs font-semibold" title="Shift ini memiliki catatan presensi atau tiket kendala sehingga tidak dapat dihapus demi integritas audit">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <span>Terkunci (Ada Presensi)</span>
                        </span>
                    @endif
                @endif
            </div>
        </div>

        {{-- Kartu Metrik Ringkas --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-6">
            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Tanggal Pelaksanaan</span>
                <span class="text-lg font-black text-slate-900 block mt-1">
                    {{ \Carbon\Carbon::parse($shift->start_at)->translatedFormat('l, d F Y') }}
                </span>
                <span class="text-[11px] text-slate-500 font-medium block">Zona Waktu: Asia/Jakarta</span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Jam Kerja Rencana</span>
                <span class="text-lg font-black text-emerald-800 block mt-1 tabular-nums">
                    {{ \Carbon\Carbon::parse($shift->start_at)->format('H:i') }} – {{ \Carbon\Carbon::parse($shift->end_at)->format('H:i') }} WIB
                </span>
                @php
                    $durationHours = round(\Carbon\Carbon::parse($shift->start_at)->diffInMinutes(\Carbon\Carbon::parse($shift->end_at)) / 60, 1);
                @endphp
                <span class="text-[11px] text-slate-500 font-medium block">Total Rencana: {{ $durationHours }} Jam</span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Status Presensi Aktual</span>
                <span class="text-lg font-bold block mt-1 {{ $shift->attendance ? 'text-emerald-700' : 'text-slate-500' }}">
                    {{ $shift->attendance ? 'Presensi Terekam' : 'Belum Ada Presensi' }}
                </span>
                <span class="text-[11px] text-slate-400 font-medium block">
                    {{ $shift->attendance ? 'Sesuai catatan sistem' : 'Menunggu kedatangan karyawan' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Detail Rencana Shift & Realisasi Presensi --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- KARTU 1: Informasi Penugasan & Lokasi --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Cabang & Lokasi Tugas</h2>
                    <p class="text-xs text-slate-500">Titik operasional tempat karyawan wajib melakukan presensi.</p>
                </div>
            </div>

            <div class="space-y-3 text-xs">
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500">Nama Cabang</span>
                    <strong class="text-slate-900">{{ $shift->branch?->name ?? $shift->branch_name }}</strong>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500">Kode Cabang</span>
                    <span class="font-mono font-bold text-slate-800">{{ $shift->branch?->code ?? '—' }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500">Radius Geofence</span>
                    <span class="font-semibold text-slate-800">{{ $shift->branch?->radius_m ?? '100' }} meter</span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500">Koordinat</span>
                    <span class="font-mono text-slate-600">
                        {{ $shift->branch?->latitude ?? '—' }}, {{ $shift->branch?->longitude ?? '—' }}
                    </span>
                </div>
                @if($shift->branch)
                    <div class="pt-2">
                        <a href="{{ route('branches.show', $shift->branch_id) }}" class="text-emerald-700 hover:underline font-bold inline-flex items-center gap-1">
                            <span>Buka Dossier Cabang Ini</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- KARTU 2: Realisasi Presensi (Attendance Record) --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Catatan Presensi Aktual</h2>
                    <p class="text-xs text-slate-500">Perbandingan jadwal rencana dengan waktu riil kehadiran.</p>
                </div>
            </div>

            @if($shift->attendance)
                <div class="space-y-3 text-xs">
                    <div class="flex justify-between py-2 border-b border-slate-100">
                        <span class="text-slate-500">Status Kehadiran</span>
                        <span class="font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-md border border-emerald-200">
                            {{ ucfirst($shift->attendance->status ?? 'Hadir') }}
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100">
                        <span class="text-slate-500">Waktu Masuk (Clock-in)</span>
                        <strong class="text-slate-900 tabular-nums">
                            {{ $shift->attendance->checkin_at ? $shift->attendance->checkin_at->format('H:i:s') . ' WIB' : '—' }}
                        </strong>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100">
                        <span class="text-slate-500">Waktu Pulang (Clock-out)</span>
                        <strong class="text-slate-900 tabular-nums">
                            {{ $shift->attendance->checkout_at ? $shift->attendance->checkout_at->format('H:i:s') . ' WIB' : '—' }}
                        </strong>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100">
                        <span class="text-slate-500">Keterlambatan</span>
                        <span class="font-semibold {{ ($shift->attendance->late_minutes ?? 0) > 0 ? 'text-amber-700 font-bold' : 'text-slate-700' }}">
                            {{ $shift->attendance->late_minutes ?? 0 }} Menit
                        </span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-slate-100">
                        <span class="text-slate-500">Lembur (Overtime)</span>
                        <span class="font-semibold text-slate-700">
                            {{ $shift->attendance->overtime_minutes ?? 0 }} Menit
                        </span>
                    </div>
                </div>
            @else
                <div class="p-8 text-center bg-slate-50/70 rounded-2xl border border-dashed border-slate-200 space-y-2">
                    <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center">
                        <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Belum Ada Presensi</h3>
                    <p class="text-xs text-slate-500 max-w-xs mx-auto leading-relaxed">
                        Karyawan belum melakukan clock-in pada jadwal ini. Riwayat kehadiran akan muncul secara otomatis setelah verifikasi QR/lokasi.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- KARTU KHUSUS SUPER ADMIN: ENTRI PRESENSI MANUAL --}}
    @if(\App\Support\Access::admin())
        <div class="bg-white rounded-2xl border border-amber-300/80 shadow-xs p-6 sm:p-7 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-amber-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-800 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Entri Presensi Manual (Super Admin Override)</h2>
                        <p class="text-xs text-slate-500">Kewenangan penuh Super Admin untuk membuat baru atau menimpa rekaman kehadiran shift ini.</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300 self-start sm:self-auto">
                    {{ $shift->attendance ? 'Timpa Rekaman Ada' : 'Buat Presensi Baru' }}
                </span>
            </div>

            <form method="post" action="{{ route('shifts.attendance.manual', $shift->id) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Status Kehadiran</label>
                        <select name="status" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                            <option value="present" {{ ($shift->attendance?->status === 'present') ? 'selected' : '' }}>Hadir Tepat Waktu (Present)</option>
                            <option value="late" {{ ($shift->attendance?->status === 'late') ? 'selected' : '' }}>Terlambat (Late)</option>
                            <option value="absent" {{ ($shift->attendance?->status === 'absent') ? 'selected' : '' }}>Tidak Masuk / Alfa (Absent)</option>
                            <option value="early_checkout" {{ ($shift->attendance?->status === 'early_checkout') ? 'selected' : '' }}>Pulang Cepat (Early Checkout)</option>
                            <option value="corrected" {{ ($shift->attendance?->status === 'corrected') ? 'selected' : '' }}>Koreksi Admin (Corrected)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Waktu Masuk (Check-in)</label>
                        <input
                            type="datetime-local"
                            name="checkin_at"
                            value="{{ $shift->attendance?->checkin_at ? $shift->attendance->checkin_at->format('Y-m-d\TH:i') : \Carbon\Carbon::parse($shift->start_at)->format('Y-m-d\TH:i') }}"
                            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                        >
                        <span class="text-[10px] text-slate-400">Kosongkan jika status Alfa</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Waktu Pulang (Check-out)</label>
                        <input
                            type="datetime-local"
                            name="checkout_at"
                            value="{{ $shift->attendance?->checkout_at ? $shift->attendance->checkout_at->format('Y-m-d\TH:i') : \Carbon\Carbon::parse($shift->end_at)->format('Y-m-d\TH:i') }}"
                            class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                        >
                        <span class="text-[10px] text-slate-400">Kosongkan jika masih bertugas</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Alasan / Catatan Entri Manual</label>
                    <textarea
                        name="reason"
                        required
                        minlength="5"
                        placeholder="Contoh: Karyawan bertugas di lapangan dan lupa presensi GPS, diverifikasi langsung oleh manajemen."
                        class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 min-h-[60px]"
                    ></textarea>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-slate-100">
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer">
                        <input type="checkbox" name="force_override_period" value="1" checked class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-500">
                        <span>Bypass batas kunci periode penggajian (Payroll Writable Lock)</span>
                    </label>

                    <button
                        type="submit"
                        class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer inline-flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>Simpan Presensi Manual (Super Admin)</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- KARTU 3: Riwayat Audit & Revisi Jadwal --}}
    @php
        $auditLogs = \App\Models\AuditLog::with('actor')
            ->where('subject_type', 'shift')
            ->where('subject_id', $shift->id)
            ->latest('id')
            ->get();
    @endphp

    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-7 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Jejak Audit & Riwayat Perubahan</h2>
                    <p class="text-xs text-slate-500">Transparansi tata kelola revisi dan persetujuan jadwal.</p>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                {{ $auditLogs->count() }} Aktivitas
            </span>
        </div>

        @if($auditLogs->isNotEmpty())
            <div class="divide-y divide-slate-100">
                @foreach($auditLogs as $log)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                        <div>
                            <span class="font-bold text-slate-800">{{ $log->actor?->name ?? 'Sistem' }}</span>
                            <span class="text-slate-400 font-mono text-[11px] ml-1">({{ $log->action }})</span>
                            @if($log->reason)
                                <p class="text-slate-600 mt-0.5 bg-slate-50 p-2 rounded-lg border border-slate-100">
                                    "{{ $log->reason }}"
                                </p>
                            @endif
                        </div>
                        <span class="text-slate-400 text-[11px] font-mono shrink-0">
                            {{ $log->created_at ? $log->created_at->translatedFormat('d M Y, H:i') : '—' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-xs text-slate-500 py-3 text-center">
                Belum ada rekaman revisi pada jadwal shift ini.
            </p>
        @endif
    </div>

    {{-- Modal Formulir Permohonan Pergantian / Pertukaran Shift --}}
    @if(!$shift->attendance)
        <div
            id="modal-exchange-shift"
            data-modal-container
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4">
                <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-lg font-black text-slate-900">Ajukan Pergantian / Tukar Shift</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Shift: {{ \Carbon\Carbon::parse($shift->start_at)->translatedFormat('d M Y') }} ({{ \Carbon\Carbon::parse($shift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($shift->end_at)->format('H:i') }})</p>
                    </div>
                    <button type="button" data-close-modal class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form method="post" action="{{ route('shifts.exchange.store', $shift->id) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tipe Pergantian</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                <input type="radio" name="type" value="replacement" checked class="text-emerald-600 focus:ring-emerald-500" onchange="document.getElementById('show-swap-box').classList.add('hidden'); document.getElementById('show-rep-box').classList.remove('hidden');">
                                <span>Penggantian Searah</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                <input type="radio" name="type" value="swap" class="text-emerald-600 focus:ring-emerald-500" onchange="document.getElementById('show-swap-box').classList.remove('hidden'); document.getElementById('show-rep-box').classList.add('hidden');">
                                <span>Tukar Shift Dua Arah</span>
                            </label>
                        </div>
                    </div>

                    <div id="show-rep-box">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Karyawan Pengganti</label>
                        <select name="replacement_id" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                            <option value="">-- Pilih Rekan Pengganti --</option>
                            @foreach($people as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->position?->name ?? 'Staf' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="show-swap-box" class="hidden">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Jadwal Shift Rekan yang Ingin Ditukar</label>
                        <select name="target_shift_id" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                            <option value="">-- Pilih Jadwal Shift Rekan --</option>
                            @foreach($candidateShifts as $cs)
                                <option value="{{ $cs->id }}">
                                    {{ $cs->user?->name }} · {{ \Carbon\Carbon::parse($cs->start_at)->format('d M') }} ({{ \Carbon\Carbon::parse($cs->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($cs->end_at)->format('H:i') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alasan Permohonan</label>
                        <textarea
                            name="reason"
                            rows="3"
                            required
                            minlength="5"
                            maxlength="500"
                            placeholder="Jelaskan alasan pengalihan atau pertukaran shift..."
                            class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                        ></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" data-close-modal class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider">Kirim Permohonan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Formulir Penugasan Langsung oleh Manajer --}}
        @if(in_array(auth()->user()->role, ['admin', 'manager']))
            <div
                id="modal-reassign-shift"
                data-modal-container
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
            >
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4">
                    <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Tugaskan Pengganti Langsung</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Penugasan manajerial langsung tanpa alur pengajuan staf.</p>
                        </div>
                        <button type="button" data-close-modal class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form method="post" action="{{ route('shifts.reassign', $shift->id) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Karyawan Pengganti</label>
                            <select name="replacement_id" required class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                                <option value="">-- Pilih Staf Pengganti --</option>
                                @foreach($people as $p)
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
                                placeholder="Misal: Karyawan berhalangan hadir mendadak, digantikan oleh staf pengganti..."
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
    @endif
</div>
@endsection
