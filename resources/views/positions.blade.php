@extends('layouts.app')
@section('title', 'Master Jabatan')

@section('content')
<div class="space-y-6">
    {{-- Header Halaman & Tombol Aksi Tambah --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-2 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <span>Master Data Organisasi</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Master Jabatan & Posisi
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola struktur jabatan fungsional dan penugasan peran kerja karyawan dalam operasional perusahaan.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a
                href="{{ route('positions.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Tambah Jabatan Baru</span>
            </a>
        </div>
    </div>

    {{-- Kartu Tabel Data Jabatan --}}
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        {{-- Toolbar Pencarian --}}
        <x-table-toolbar
            action="{{ route('positions.index') }}"
            searchPlaceholder="Cari nama jabatan..."
            resetUrl="{{ route('positions.index') }}"
        />

        {{-- Tabel Data --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6 w-16 text-center">No</th>
                        <th class="py-3.5 px-4">Nama Jabatan</th>
                        <th class="py-3.5 px-4 text-center">Total Karyawan</th>
                        <th class="py-3.5 px-4">Tanggal Dibuat</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                    @forelse($positions as $p)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            {{-- Nomor Urut --}}
                            <td class="py-3.5 px-4 sm:px-6 text-center font-bold text-slate-400">
                                {{ $positions->firstItem() + $loop->index }}
                            </td>

                            {{-- Nama Jabatan --}}
                            <td class="py-3.5 px-4 font-bold text-slate-900 text-sm whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center shrink-0 font-bold border border-emerald-200/60 shadow-2xs">
                                        💼
                                    </span>
                                    <a href="{{ route('positions.show', $p->id) }}" class="hover:text-emerald-700 hover:underline transition-colors">
                                        {{ $p->name }}
                                    </a>
                                </div>
                            </td>

                            {{-- Total Karyawan --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $p->users_count > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}">
                                    {{ $p->users_count }} Karyawan
                                </span>
                            </td>

                            {{-- Tanggal Dibuat --}}
                            <td class="py-3.5 px-4 whitespace-nowrap text-slate-500 font-medium">
                                {{ $p->created_at ? $p->created_at->format('d M Y') : '-' }}
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3.5 px-4 sm:px-6 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    {{-- Tombol Detail Mandiri --}}
                                    <a
                                        href="{{ route('positions.show', $p->id) }}"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 transition-colors"
                                        title="Lihat Detail Jabatan"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>

                                    {{-- Tombol Edit Mandiri --}}
                                    <a
                                        href="{{ route('positions.edit', $p->id) }}"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-blue-700 hover:bg-blue-50 transition-colors"
                                        title="Edit Jabatan"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>

                                    {{-- Tombol Hapus (Dilindungi Confirm Modal) --}}
                                    <button
                                        type="button"
                                        data-confirm="Apakah Anda yakin ingin menghapus jabatan '{{ $p->name }}'? Tindakan ini permanen."
                                        data-confirm-title="Hapus Jabatan"
                                        data-confirm-variant="danger"
                                        data-confirm-btn="Ya, Hapus Jabatan"
                                        data-confirm-action="{{ route('positions.destroy', $p->id) }}"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                        title="Hapus Jabatan"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                <p class="text-xs font-semibold m-0">Tidak ada data jabatan yang cocok dengan pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginasi Data --}}
        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            <x-pagination :paginator="$positions" />
        </div>
    </div>
</div>
@endsection
