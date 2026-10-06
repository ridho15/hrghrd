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
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 sm:p-7">
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
                            <a href="{{ route('people.show', $shift->user_id) }}" class="text-emerald-700 hover:underline font-semibold">
                                Lihat Berkas Karyawan ↗
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

                {{-- Tombol Revisi & Batalkan jika belum ada absensi --}}
                @if(!$shift->attendance)
                    <a
                        href="{{ route('shifts.edit', $shift->id) }}"
                        class="px-4 py-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <span>Revisi Jadwal</span>
                    </a>

                    <button
                        type="button"
                        data-confirm="Apakah Anda yakin ingin membatalkan dan menghapus jadwal shift untuk {{ $shift->employee_name }} pada {{ \Carbon\Carbon::parse($shift->start_at)->translatedFormat('d M Y') }}?"
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
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm">
                    📍
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
                    <span class="font-semibold text-slate-800">{{ $shift->branch?->radius_meters ?? '100' }} meter</span>
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
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm">
                    ⏱️
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
                    <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center font-bold text-sm">
                        ⏳
                    </div>
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Belum Ada Presensi</h3>
                    <p class="text-xs text-slate-500 max-w-xs mx-auto leading-relaxed">
                        Karyawan belum melakukan clock-in pada jadwal ini. Riwayat kehadiran akan muncul secara otomatis setelah verifikasi QR/lokasi.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- KARTU 3: Riwayat Audit & Revisi Jadwal --}}
    @php
        $auditLogs = \App\Models\AuditLog::with('actor')
            ->where('subject_type', 'shift')
            ->where('subject_id', $shift->id)
            ->latest('id')
            ->get();
    @endphp

    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-sm">
                    📜
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
</div>
@endsection
