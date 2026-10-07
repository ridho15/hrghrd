@extends('layouts.app')
@section('title', 'Detail Cabang: ' . $branch->name)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('branches.index') }}" class="hover:text-emerald-700 transition-colors">Master Cabang</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">{{ $branch->name }}</span>
    </nav>

    {{-- Header Dossier Cabang & Tombol Aksi --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-7">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-emerald-600 text-white font-extrabold text-2xl flex items-center justify-center shadow-sm shrink-0">
                    {{ strtoupper(substr($branch->code, 0, 2)) }}
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 font-mono font-bold text-xs uppercase">
                            {{ $branch->code }}
                        </span>
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-xs">
                            ID #{{ $branch->id }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $branch->active ?? true ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : 'bg-slate-100 text-slate-500' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $branch->active ?? true ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            <span>{{ $branch->active ?? true ? 'Cabang Aktif' : 'Non-Aktif' }}</span>
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        {{ $branch->name }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1 flex items-center gap-1.5 font-mono">
                        <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Koordinat: {{ number_format($branch->latitude, 6) }}, {{ number_format($branch->longitude, 6) }}</span>
                    </p>
                </div>
            </div>

            {{-- Tombol Aksi Mandiri --}}
            <div class="flex flex-wrap items-center gap-2.5">
                <a
                    href="{{ route('branches.index') }}"
                    class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Daftar Cabang</span>
                </a>

                <a
                    href="{{ route('branches.edit', $branch->id) }}"
                    class="px-4 py-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <span>Ubah Cabang</span>
                </a>

                <a
                    href="{{ route('qr.page') }}"
                    class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider transition-all inline-flex items-center gap-1.5 shadow-2xs"
                    title="Buka QR Kiosk Cabang Ini"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                    <span>QR Presensi Cabang</span>
                </a>

                @if($branch->users_count === 0 && $branch->shifts_count === 0)
                    <button
                        type="button"
                        data-confirm="Apakah Anda yakin ingin menghapus cabang '{{ $branch->name }}'? Tindakan ini permanen."
                        data-confirm-title="Hapus Cabang"
                        data-confirm-variant="danger"
                        data-confirm-btn="Ya, Hapus Cabang"
                        data-confirm-action="{{ route('branches.destroy', $branch->id) }}"
                        class="p-2.5 rounded-xl text-rose-600 hover:bg-rose-50 border border-rose-200 transition-colors cursor-pointer"
                        title="Hapus Cabang"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- 4 Kartu Metrik Cabang --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 pt-6">
            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Karyawan Bertugas</span>
                <div class="mt-1 flex items-baseline gap-1">
                    <span class="text-2xl font-black text-slate-900">{{ $branch->users_count }}</span>
                    <span class="text-xs text-slate-500 font-semibold">Orang</span>
                </div>
                <span class="text-[11px] text-emerald-700 font-medium block mt-1">Ditempatkan di outlet ini</span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Radius Geofence</span>
                <div class="mt-1 flex items-baseline gap-1">
                    <span class="text-2xl font-black text-slate-900">{{ $branch->radius_m }}</span>
                    <span class="text-xs text-slate-500 font-semibold">Meter</span>
                </div>
                <span class="text-[11px] text-slate-500 font-medium block mt-1">Batas toleransi GPS</span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Riwayat Shift</span>
                <div class="mt-1 flex items-baseline gap-1">
                    <span class="text-2xl font-black text-slate-900">{{ $branch->shifts_count }}</span>
                    <span class="text-xs text-slate-500 font-semibold">Jadwal</span>
                </div>
                <span class="text-[11px] text-slate-500 font-medium block mt-1">Total jadwal tercatat</span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100 flex flex-col justify-between">
                <div>
                    <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Tautan Lokasi Peta</span>
                    <span class="text-xs font-bold text-slate-900 block mt-1 truncate">Google Maps</span>
                </div>
                <a
                    href="https://maps.google.com/?q={{ $branch->latitude }},{{ $branch->longitude }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-2 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-emerald-700 hover:bg-emerald-50 text-xs font-bold transition-all shadow-2xs"
                >
                    <span>Buka Lokasi Peta</span>
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    {{-- Peta Visualisasi Lokasi & Radius Geofence --}}
    <x-map-picker 
        id="branch-show-map"
        :latitude="$branch->latitude"
        :longitude="$branch->longitude"
        :radius="$branch->radius_m"
        :readonly="true"
        height="320px"
        title="Batas Lokasi Presensi (Geofence)"
        :branchName="$branch->name"
    />

    {{-- KONTEN BAGIAN 1: Daftar Karyawan Cabang --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Daftar Staf Cabang Ini</h2>
                <p class="text-xs text-slate-500">Seluruh karyawan dan manager yang bertugas aktif di {{ $branch->name }}.</p>
            </div>
            <a
                href="{{ route('people.create') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold uppercase tracking-wider transition-colors self-start sm:self-auto"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>+ Tambah Karyawan</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6">Nama & Email</th>
                        <th class="py-3.5 px-4">Jabatan</th>
                        <th class="py-3.5 px-4 text-center">Peran Akun</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                    @forelse($branch->users as $u)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-4 sm:px-6 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0 border border-slate-200">
                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('people.show', $u->id) }}" class="font-bold text-slate-900 hover:text-emerald-700 hover:underline block">
                                            {{ $u->name }}
                                        </a>
                                        <span class="text-[11px] text-slate-400 font-mono">{{ $u->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap font-medium text-slate-800">
                                {{ $u->position?->name ?? 'Belum Ditentukan' }}
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $u->role === 'manager' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                    {{ $u->role }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $u->active ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $u->active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-right whitespace-nowrap">
                                <a
                                    href="{{ route('people.show', $u->id) }}"
                                    class="inline-flex items-center gap-1 text-emerald-700 hover:underline font-bold text-xs"
                                >
                                    <span>Lihat Profil</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400">
                                <p class="text-xs italic m-0">Belum ada karyawan yang ditempatkan pada cabang ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- KONTEN BAGIAN 2: Riwayat Jadwal Shift Terakhir --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-900">10 Jadwal Shift Terakhir Cabang Ini</h2>
            <p class="text-xs text-slate-500">Aktivitas jadwal kerja karyawan yang terdaftar di lokasi {{ $branch->name }}.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6">Karyawan</th>
                        <th class="py-3.5 px-4">Jam Mulai</th>
                        <th class="py-3.5 px-4">Jam Selesai</th>
                        <th class="py-3.5 px-4 text-center">Status Kehadiran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                    @forelse($branch->shifts as $s)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-4 sm:px-6 whitespace-nowrap font-bold text-slate-900">
                                {{ $s->user?->name ?? 'Karyawan Dihapus' }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap font-mono text-slate-600">
                                {{ \Carbon\Carbon::parse($s->start_at)->translatedFormat('d M Y, H:i') }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap font-mono text-slate-600">
                                {{ \Carbon\Carbon::parse($s->end_at)->translatedFormat('d M Y, H:i') }}
                            </td>
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if($s->attendance)
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        Sudah Presensi
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">
                                        Belum Absen
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-400">
                                <p class="text-xs italic m-0">Belum ada catatan jadwal shift pada cabang ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
