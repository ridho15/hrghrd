@extends('layouts.app')
@section('title', 'Jadwal Shift Kerja')

@section('content')
<div class="space-y-6">
    {{-- Header & Tombol Aksi Tambah --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Siklus Penjadwalan Terstruktur</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Jadwal Shift Kerja
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola jadwal kerja karyawan. Shift baru dibuat sebagai draf dan memerlukan persetujuan manajerial sebelum berlaku.
            </p>
        </div>

        @if(in_array(auth()->user()->role, ['admin', 'manager']))
            <div class="flex items-center gap-2.5 shrink-0">
                <a
                    href="{{ route('shifts.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>+ Buat Jadwal Shift Baru</span>
                </a>
            </div>
        @endif
    </div>

    {{-- Tabel Jadwal Shift Bersih (Full-Width) --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        {{-- Toolbar Filter & Pencarian --}}
        <x-table-toolbar :action="route('shifts')" search-placeholder="Cari nama karyawan...">
            <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                <div class="w-44 sm:w-48">
                    @php
                        $shiftBranchOpts = collect([['value' => '', 'label' => 'Semua Cabang', 'sublabel' => '']])
                            ->merge($branches->map(fn($b) => ['value' => (string)$b->id, 'label' => $b->name, 'sublabel' => $b->code]));
                    @endphp
                    <x-searchable-select
                        name="branch_id"
                        id="filter-shift-branch"
                        size="sm"
                        :value="request('branch_id')"
                        :options="$shiftBranchOpts"
                        placeholder="Semua Cabang"
                        searchPlaceholder="Cari cabang..."
                    />
                </div>

                <select name="status" class="h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs">
                    <option value="">Semua Status</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draf</option>
                    <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                </select>

                <div class="flex items-center gap-1.5">
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" title="Dari Tanggal">
                    <span class="text-xs text-slate-400">s/d</span>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" title="Sampai Tanggal">
                </div>
            </div>
        </x-table-toolbar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">Karyawan</th>
                        <th class="py-3.5 px-4">Cabang</th>
                        <th class="py-3.5 px-4">Jadwal Jam Kerja</th>
                        <th class="py-3.5 px-4">Status & Versi</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($shifts as $s)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Nama Karyawan --}}
                            <td class="py-4 px-4 sm:px-6 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 font-bold flex items-center justify-center shrink-0 border border-emerald-200/60 text-xs">
                                        {{ strtoupper(substr($s->employee_name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('shifts.show', $s->id) }}" class="font-bold text-slate-900 hover:text-emerald-700 hover:underline block">
                                            {{ $s->employee_name }}
                                        </a>
                                        <span class="text-[11px] text-slate-400 font-mono">{{ $s->user?->email }}</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Cabang --}}
                            <td class="py-4 px-4 font-medium text-slate-700 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                    <span>{{ $s->branch_name }}</span>
                                </span>
                            </td>

                            {{-- Waktu Kerja --}}
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span class="font-bold text-slate-900 block tabular-nums">
                                    {{ \Carbon\Carbon::parse($s->start_at)->translatedFormat('d M Y') }}
                                </span>
                                <span class="text-xs text-slate-500 tabular-nums">
                                    {{ \Carbon\Carbon::parse($s->start_at)->format('H:i') }} – {{ \Carbon\Carbon::parse($s->end_at)->format('H:i') }} WIB
                                </span>
                            </td>

                            {{-- Status & Versi --}}
                            <td class="py-4 px-4 whitespace-nowrap">
                                @if($s->status === 'approved')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>Disetujui &middot; v{{ $s->version }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        <span>Draf &middot; v{{ $s->version }}</span>
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi Detail, Persetujuan & Revisi --}}
                            <td class="py-4 px-4 sm:px-6 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Tombol Detail Mandiri --}}
                                    <a
                                        href="{{ route('shifts.show', $s->id) }}"
                                        class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200/80 transition-colors inline-flex items-center gap-1 shadow-2xs"
                                        title="Lihat detail jadwal dan realisasi presensi"
                                    >
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        <span>Detail</span>
                                    </a>

                                    @if(auth()->user()->role === 'admin')
                                        @if($s->status === 'draft')
                                            <form method="post" action="{{ route('shifts.approve', $s->id) }}" class="inline">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    data-confirm="Setujui jadwal shift untuk {{ $s->user?->name ?? 'Karyawan' }} pada tanggal {{ \Carbon\Carbon::parse($s->start_at)->translatedFormat('d M Y') }}?"
                                                    data-confirm-title="Persetujuan Jadwal Shift"
                                                    data-confirm-variant="primary"
                                                    data-confirm-btn="Ya, Setujui"
                                                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors shadow-xs cursor-pointer"
                                                >
                                                    Setujui
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Tombol Revisi Mandiri --}}
                                        <a
                                            href="{{ route('shifts.edit', $s->id) }}"
                                            class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors inline-flex items-center gap-1"
                                            title="Revisi jadwal kerja shift"
                                        >
                                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            <span>Revisi</span>
                                        </a>

                                        {{-- Tombol Batalkan / Hapus Shift --}}
                                        @if(!$s->attendance)
                                            <button
                                                type="button"
                                                data-confirm="Batalkan dan hapus jadwal shift untuk {{ $s->user?->name ?? 'Karyawan' }} pada tanggal {{ \Carbon\Carbon::parse($s->start_at)->translatedFormat('d M Y') }} ({{ \Carbon\Carbon::parse($s->start_at)->format('H:i') }} – {{ \Carbon\Carbon::parse($s->end_at)->format('H:i') }})?"
                                                data-confirm-title="Batalkan Jadwal Shift"
                                                data-confirm-variant="danger"
                                                data-confirm-btn="Ya, Batalkan Shift"
                                                data-confirm-action="{{ route('shifts.destroy', $s->id) }}"
                                                class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold border border-rose-200/80 transition-colors cursor-pointer inline-flex items-center"
                                                title="Batalkan & Hapus Shift"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <p class="text-xs font-semibold m-0">Tidak ada jadwal shift yang sesuai dengan filter pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginasi Data --}}
        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            <x-pagination :paginator="$shifts" />
        </div>
    </div>
</div>
@endsection
