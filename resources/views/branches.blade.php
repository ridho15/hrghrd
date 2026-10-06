@extends('layouts.app')
@section('title', 'Master Cabang')

@section('content')
<div class="space-y-6">
    {{-- Header Halaman & Tombol Aksi Tambah --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-2 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span>Master Data Organisasi</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Master Cabang Perusahaan
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola daftar lokasi kantor cabang, titik koordinat GPS presensi, dan radius batas geofence absensi karyawan.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a
                href="{{ route('branches.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Tambah Cabang Baru</span>
            </a>
        </div>
    </div>

    {{-- Kartu Tabel Data Cabang --}}
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        {{-- Toolbar Pencarian --}}
        <x-table-toolbar
            action="{{ route('branches.index') }}"
            searchPlaceholder="Cari nama atau kode cabang..."
            resetUrl="{{ route('branches.index') }}"
        />

        {{-- Tabel Data --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6">Kode Cabang</th>
                        <th class="py-3.5 px-4">Nama Cabang</th>
                        <th class="py-3.5 px-4">Titik Koordinat GPS</th>
                        <th class="py-3.5 px-4">Radius Geofence</th>
                        <th class="py-3.5 px-4 text-center">Total Karyawan</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                    @forelse($branches as $b)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            {{-- Kode Cabang --}}
                            <td class="py-3.5 px-4 sm:px-6 font-mono font-bold text-slate-900 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 text-slate-700">
                                    {{ $b->code }}
                                </span>
                            </td>

                            {{-- Nama Cabang --}}
                            <td class="py-3.5 px-4 font-bold text-slate-900 whitespace-nowrap">
                                <a href="{{ route('branches.show', $b->id) }}" class="hover:text-emerald-700 hover:underline transition-colors">
                                    {{ $b->name }}
                                </a>
                            </td>

                            {{-- Titik Koordinat GPS --}}
                            <td class="py-3.5 px-4 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                <a
                                    href="https://maps.google.com/?q={{ $b->latitude }},{{ $b->longitude }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 hover:text-emerald-700 hover:underline transition-colors"
                                    title="Buka lokasi di Google Maps"
                                >
                                    <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span>{{ number_format($b->latitude, 6) }}, {{ number_format($b->longitude, 6) }}</span>
                                </a>
                            </td>

                            {{-- Radius Geofence --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-800 font-semibold text-[11px]">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>{{ $b->radius_m }} m</span>
                                </span>
                            </td>

                            {{-- Total Karyawan --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $b->users_count > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}">
                                    {{ $b->users_count }} Karyawan
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3.5 px-4 sm:px-6 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    {{-- Tombol Detail Mandiri --}}
                                    <a
                                        href="{{ route('branches.show', $b->id) }}"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 transition-colors"
                                        title="Lihat Detail Cabang"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>

                                    {{-- Tombol Edit Mandiri --}}
                                    <a
                                        href="{{ route('branches.edit', $b->id) }}"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-blue-700 hover:bg-blue-50 transition-colors"
                                        title="Edit Cabang"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>

                                    {{-- Tombol Hapus (Dilindungi Confirm Modal) --}}
                                    <button
                                        type="button"
                                        data-confirm="Apakah Anda yakin ingin menghapus cabang '{{ $b->name }}' ({{ $b->code }})? Tindakan ini permanen."
                                        data-confirm-title="Hapus Cabang"
                                        data-confirm-variant="danger"
                                        data-confirm-btn="Ya, Hapus Cabang"
                                        data-confirm-action="{{ route('branches.destroy', $b->id) }}"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                        title="Hapus Cabang"
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
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                <p class="text-xs font-semibold m-0">Tidak ada data cabang yang cocok dengan pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginasi Data --}}
        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            <x-pagination :paginator="$branches" />
        </div>
    </div>
</div>
@endsection
