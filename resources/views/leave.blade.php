@extends('layouts.app')
@section('title', 'Izin & Sakit')

@section('content')
<div class="space-y-6">
    {{-- Header Modul & Tombol Tambah Pengajuan --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Ketentuan Cuti & Sakit</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Pengajuan Izin & Sakit
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola permohonan ketidakhadiran karyawan. Izin biasa diajukan minimal H-{{ \App\Support\Rules::int('leave_notice_days') }}, sedangkan sakit wajib melampirkan surat medis dokter.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a
                href="{{ route('leave.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>+ Ajukan Izin / Sakit</span>
            </a>
        </div>
    </div>

    {{-- Tabel Bersih Full-Width --}}
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        {{-- Toolbar Filter & Pencarian Pengajuan --}}
        <x-table-toolbar :action="route('leave')" search-placeholder="Cari nama karyawan atau alasan...">
            <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                <select name="type" class="h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs">
                    <option value="">Semua Jenis</option>
                    <option value="leave" @selected(request('type') === 'leave')>Izin / Cuti</option>
                    <option value="sick" @selected(request('type') === 'sick')>Sakit (Surat Dokter)</option>
                </select>

                <select name="status" class="h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs">
                    <option value="">Semua Status</option>
                    <option value="pending" @selected(request('status') === 'pending')>Menunggu</option>
                    <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                    <option value="partial" @selected(request('status') === 'partial')>Sebagian</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Ditolak</option>
                </select>
            </div>
        </x-table-toolbar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">Karyawan Pemohon</th>
                        <th class="py-3.5 px-4">Jenis & Surat</th>
                        <th class="py-3.5 px-4">Periode Tanggal</th>
                        <th class="py-3.5 px-4">Alasan</th>
                        <th class="py-3.5 px-4">Status & Harian</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($requests as $r)
                        @php
                            $statusBadge = [
                                'pending' => ['label' => 'Menunggu', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                                'approved' => ['label' => 'Disetujui', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                'partial' => ['label' => 'Sebagian', 'class' => 'bg-sky-50 text-sky-700 border-sky-200'],
                                'rejected' => ['label' => 'Ditolak', 'class' => 'bg-rose-50 text-rose-700 border-rose-200']
                            ][$r->status] ?? ['label' => $r->status, 'class' => 'bg-slate-100 text-slate-600 border-slate-200'];

                            $canCancel = ($r->status === 'pending' && (
                                auth()->user()->role === 'admin' ||
                                (auth()->user()->role === 'manager' && $r->user?->branch_id === auth()->user()->branch_id) ||
                                $r->user_id === auth()->id()
                            ));
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Nama Karyawan --}}
                            <td class="py-4 px-4 sm:px-6 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 font-bold flex items-center justify-center shrink-0 border border-emerald-200/60 text-xs">
                                        {{ strtoupper(substr($r->employee_name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('leave.show', $r->id) }}" class="font-bold text-slate-900 hover:text-emerald-700 hover:underline block">
                                            {{ $r->employee_name }}
                                        </a>
                                        <span class="text-[11px] text-slate-400 font-mono">{{ $r->user?->branch?->name ?? 'Cabang —' }}</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Jenis & Berkas Surat Dokter --}}
                            <td class="py-4 px-4 whitespace-nowrap">
                                <div class="space-y-1">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-bold {{ $r->type === 'sick' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                                        {{ $r->type === 'sick' ? '🩺 Surat Sakit' : '📅 Izin / Cuti' }}
                                    </span>
                                    @if($r->certificate_path)
                                        <div class="block">
                                            <a href="{{ route('leave.certificate', $r->id) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:underline">
                                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                                <span>Surat Dokter ↗</span>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            {{-- Periode Tanggal --}}
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span class="font-bold text-slate-900 block tabular-nums">
                                    {{ \Carbon\Carbon::parse($r->start_date)->translatedFormat('d M Y') }} – {{ \Carbon\Carbon::parse($r->end_date)->translatedFormat('d M Y') }}
                                </span>
                                @php
                                    $dayCount = \Carbon\Carbon::parse($r->start_date)->diffInDays(\Carbon\Carbon::parse($r->end_date)) + 1;
                                @endphp
                                <span class="text-xs text-slate-500 tabular-nums">
                                    Durasi: {{ $dayCount }} Hari
                                </span>
                            </td>

                            {{-- Alasan --}}
                            <td class="py-4 px-4 max-w-xs">
                                <p class="text-xs text-slate-600 line-clamp-2 m-0" title="{{ $r->reason }}">
                                    {{ $r->reason }}
                                </p>
                            </td>

                            {{-- Status & Matrix Harian Ringkas --}}
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold border {{ $statusBadge['class'] }} inline-block">
                                    {{ $statusBadge['label'] }}
                                </span>
                                @if(isset($days[$r->id]) && $days[$r->id]->isNotEmpty())
                                    <div class="flex items-center gap-1 mt-1.5 flex-wrap max-w-[200px]">
                                        @foreach($days[$r->id]->take(3) as $d)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold tabular-nums {{ $d->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($d->status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600') }}">
                                                {{ substr($d->date, 8, 2) }}:{{ substr($d->status, 0, 1) }}
                                            </span>
                                        @endforeach
                                        @if($days[$r->id]->count() > 3)
                                            <span class="text-[10px] text-slate-400 font-semibold">+{{ $days[$r->id]->count() - 3 }} hr</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            {{-- Tindakan --}}
                            <td class="py-4 px-4 sm:px-6 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <a
                                        href="{{ route('leave.show', $r->id) }}"
                                        class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200/80 transition-colors inline-flex items-center gap-1 shadow-2xs"
                                        title="Buka berkas lengkap dan matriks persetujuan"
                                    >
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        <span>Detail</span>
                                    </a>

                                    @if($canCancel)
                                        <button
                                            type="button"
                                            data-confirm="Batalkan pengajuan {{ $r->type === 'sick' ? 'izin sakit' : 'cuti' }} untuk {{ $r->employee_name }}? Pengajuan akan dihapus permanen."
                                            data-confirm-title="Batalkan Pengajuan Izin"
                                            data-confirm-variant="danger"
                                            data-confirm-btn="Ya, Batalkan Pengajuan"
                                            data-confirm-action="{{ route('leave.destroy', $r->id) }}"
                                            class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 border border-rose-200 transition-colors cursor-pointer"
                                            title="Batalkan Pengajuan"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center font-bold text-sm">
                                        📅
                                    </div>
                                    <p class="text-xs font-semibold text-slate-700">Belum ada data pengajuan izin atau sakit.</p>
                                    <p class="text-[11px] text-slate-400">Gunakan tombol "+ Ajukan Izin / Sakit" untuk mengirim permohonan baru.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
