@extends('layouts.app')
@section('title', 'Detail Jabatan: ' . $position->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('positions.index') }}" class="hover:text-emerald-700 transition-colors">Master Jabatan</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">{{ $position->name }}</span>
    </nav>

    {{-- Header Detail Jabatan & Tombol Aksi --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-7">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-200/60 shadow-2xs shrink-0">
                    <svg class="w-8 h-8 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-xs">
                            ID #{{ $position->id }}
                        </span>
                        <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold text-xs">
                            {{ $position->users_count }} Karyawan Terdaftar
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        {{ $position->name }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Terdaftar sejak: {{ $position->created_at ? $position->created_at->translatedFormat('d F Y') : '-' }}
                    </p>
                </div>
            </div>

            {{-- Tombol Aksi Mandiri --}}
            <div class="flex flex-wrap items-center gap-2.5">
                <a
                    href="{{ route('positions.index') }}"
                    class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Daftar Jabatan</span>
                </a>

                <a
                    href="{{ route('positions.edit', $position->id) }}"
                    class="px-4 py-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <span>Ubah Nama</span>
                </a>

                @if($position->users_count === 0)
                    <button
                        type="button"
                        data-confirm="Apakah Anda yakin ingin menghapus jabatan '{{ $position->name }}'? Tindakan ini permanen."
                        data-confirm-title="Hapus Jabatan"
                        data-confirm-variant="danger"
                        data-confirm-btn="Ya, Hapus Jabatan"
                        data-confirm-action="{{ route('positions.destroy', $position->id) }}"
                        class="p-2.5 rounded-xl text-rose-600 hover:bg-rose-50 border border-rose-200 transition-colors cursor-pointer"
                        title="Hapus Jabatan"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Metrik Ringkas --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-6">
            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100 flex items-center justify-between">
                <div>
                    <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Total Karyawan Aktif</span>
                    <span class="text-2xl font-black text-slate-900 block mt-0.5">{{ $position->users_count }} Orang</span>
                    <span class="text-[11px] text-slate-500 font-medium block">Mengemban jabatan ini</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200/60 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100 flex items-center justify-between">
                <div>
                    <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Status Penugasan</span>
                    <span class="text-base font-bold {{ $position->users_count > 0 ? 'text-emerald-700' : 'text-slate-600' }} block mt-1">
                        {{ $position->users_count > 0 ? 'Sedang Digunakan Operasional' : 'Belum Ada Karyawan Terdaftar' }}
                    </span>
                    <span class="text-[11px] text-slate-400 font-medium block">Kesiapan alokasi staf</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-600 border border-slate-200/60 flex items-center justify-center">
                    <svg class="w-6 h-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Karyawan Pemegang Jabatan --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Daftar Karyawan dengan Jabatan Ini</h2>
                <p class="text-xs text-slate-500">Seluruh staf yang memiliki peran kerja sebagai {{ $position->name }}.</p>
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
                        <th class="py-3.5 px-4">Cabang Penempatan</th>
                        <th class="py-3.5 px-4 text-center">Peran Akun</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                    @forelse($position->users as $u)
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
                                {{ $u->branch?->name ?? 'Belum Ditentukan' }}
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
                                <p class="text-xs italic m-0">Belum ada karyawan yang ditempatkan pada jabatan ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
