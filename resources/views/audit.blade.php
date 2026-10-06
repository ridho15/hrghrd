@extends('layouts.app')
@section('title', 'Jejak Audit')

@section('content')
<div class="space-y-8">
    {{-- Header Modul --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Integritas & Kepatuhan Sistem</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Jejak Audit Aktivitas
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Catatan komprehensif perubahan data dan aktivitas penting dalam sistem. Meliputi alasan koreksi presensi, persetujuan shift, penguncian payroll, dan identitas pelaksana aksi.
            </p>
        </div>
    </div>

    {{-- Tabel Log Audit Aktivitas --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden space-y-0">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Log Riwayat Peristiwa Terkini</h2>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                {{ $events->total() }} Peristiwa
            </span>
        </div>

        {{-- Toolbar Filter & Pencarian Audit --}}
        <x-table-toolbar :action="route('audit')" search-placeholder="Cari aktor, alasan, ID subjek...">
            <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                <select name="subject_type" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                    <option value="">Semua Entitas</option>
                    <option value="Attendance" @selected(request('subject_type') === 'Attendance')>Presensi (Attendance)</option>
                    <option value="Shift" @selected(request('subject_type') === 'Shift')>Shift Kerja</option>
                    <option value="PayrollRun" @selected(request('subject_type') === 'PayrollRun')>Payroll</option>
                    <option value="LeaveRequest" @selected(request('subject_type') === 'LeaveRequest')>Cuti & Sakit</option>
                    <option value="User" @selected(request('subject_type') === 'User')>Karyawan / User</option>
                </select>

                <select name="action" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                    <option value="">Semua Tindakan</option>
                    <option value="created" @selected(request('action') === 'created')>Created</option>
                    <option value="updated" @selected(request('action') === 'updated')>Updated</option>
                    <option value="approved" @selected(request('action') === 'approved')>Approved</option>
                    <option value="rejected" @selected(request('action') === 'rejected')>Rejected</option>
                    <option value="corrected" @selected(request('action') === 'corrected')>Corrected</option>
                    <option value="locked" @selected(request('action') === 'locked')>Locked</option>
                    <option value="deleted" @selected(request('action') === 'deleted')>Deleted</option>
                </select>
            </div>
        </x-table-toolbar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">Waktu Kejadian (WIB)</th>
                        <th class="py-3.5 px-4">Aktor Pelaksana</th>
                        <th class="py-3.5 px-4">Objek Entitas</th>
                        <th class="py-3.5 px-4">Tindakan</th>
                        <th class="py-3.5 px-4">Alasan & Catatan</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($events as $e)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Waktu --}}
                            <td class="py-4 px-4 sm:px-6 font-mono text-xs tabular-nums text-slate-600 whitespace-nowrap">
                                {{ $e->created_at }}
                            </td>

                            {{-- Pelaku --}}
                            <td class="py-4 px-4">
                                @if($e->actor_name)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-700 font-bold flex items-center justify-center text-[10px] shrink-0 border border-emerald-200/60">
                                            {{ strtoupper(substr($e->actor_name, 0, 2)) }}
                                        </div>
                                        <span class="font-bold text-slate-900">{{ $e->actor_name }}</span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        Sistem Otomatis
                                    </span>
                                @endif
                            </td>

                            {{-- Objek --}}
                            <td class="py-4 px-4 font-mono text-xs text-slate-700 whitespace-nowrap">
                                <span class="bg-slate-100 px-2 py-1 rounded-md border border-slate-200 font-semibold">
                                    {{ $e->subject_type }} #{{ $e->subject_id }}
                                </span>
                            </td>

                            {{-- Tindakan --}}
                            <td class="py-4 px-4">
                                @php
                                    $actionClass = match(strtolower($e->action)) {
                                        'approved', 'locked' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'rejected', 'deleted' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        'corrected', 'updated' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        default => 'bg-slate-100 text-slate-700 border-slate-200'
                                    };
                                @endphp
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold border uppercase tracking-wider {{ $actionClass }}">
                                    {{ $e->action }}
                                </span>
                            </td>

                            {{-- Alasan & Catatan --}}
                            <td class="py-4 px-4 text-xs text-slate-600">
                                <div class="font-medium text-slate-800 line-clamp-2">
                                    {{ $e->reason ?? '—' }}
                                </div>
                            </td>

                            {{-- Tombol Detail & Modal Jejak Audit --}}
                            <td class="py-4 px-4 sm:px-6 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    data-open-modal="detail-audit-{{ $e->id }}"
                                    class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200/80 transition-colors cursor-pointer inline-flex items-center gap-1 shadow-2xs"
                                    title="Inspeksi detail rekaman audit dan perubahan state"
                                >
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <span>Detail</span>
                                </button>

                                {{-- Modal Detail Audit Lengkap --}}
                                <x-detail-modal
                                    id="detail-audit-{{ $e->id }}"
                                    title="Inspeksi Rekaman Audit Log"
                                    subtitle="Peristiwa #AUD-{{ str_pad($e->id, 6, '0', STR_PAD_LEFT) }} &middot; {{ $e->created_at }}"
                                    badge="{{ strtoupper($e->action) }}"
                                    badgeColor="{{ $actionClass }}"
                                    maxWidth="3xl"
                                >
                                    {{-- Banner Identitas Peristiwa --}}
                                    <div class="p-4 rounded-2xl bg-gradient-to-r from-slate-900 to-emerald-950 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div class="flex items-center gap-3.5">
                                            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-300 font-extrabold flex items-center justify-center text-base border border-emerald-500/30 shrink-0">
                                                {{ strtoupper(substr($e->actor_name ?? 'SYS', 0, 2)) }}
                                            </div>
                                            <div>
                                                <h4 class="text-base font-bold text-white m-0">{{ $e->actor_name ?? 'Sistem Otomatis (Cron / Background)' }}</h4>
                                                <span class="text-xs text-slate-300 block font-mono">Actor ID: {{ $e->actor_id ? '#'.$e->actor_id : 'System Daemon' }}</span>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="text-[11px] font-semibold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-md border border-emerald-500/20 font-mono">
                                                        Waktu: {{ $e->created_at }} WIB
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-left sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-700/60">
                                            <span class="text-[11px] uppercase tracking-wider text-slate-400 block font-bold">Event Log ID</span>
                                            <span class="text-sm font-mono font-bold text-emerald-300">#AUD-{{ str_pad($e->id, 6, '0', STR_PAD_LEFT) }}</span>
                                        </div>
                                    </div>

                                    {{-- Entitas Objek & Parameter Aksi --}}
                                    <div class="space-y-3">
                                        <h5 class="text-xs font-bold text-slate-900 uppercase tracking-wider m-0">Metadata Entitas & Aksi</h5>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/70">
                                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Target Entitas</span>
                                                <strong class="text-xs font-bold text-slate-900 block font-mono">
                                                    {{ strtoupper($e->subject_type) }} #{{ $e->subject_id }}
                                                </strong>
                                            </div>

                                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/70">
                                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Tindakan / Action</span>
                                                <strong class="text-xs font-bold text-slate-900 block uppercase">
                                                    {{ $e->action }}
                                                </strong>
                                            </div>

                                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/70">
                                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Perekaman Database</span>
                                                <strong class="text-xs font-bold text-slate-900 block tabular-nums">
                                                    {{ \Carbon\Carbon::parse($e->created_at)->translatedFormat('d F Y') }}
                                                </strong>
                                                <span class="text-[11px] text-slate-500">{{ \Carbon\Carbon::parse($e->created_at)->format('H:i:s') }} WIB</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Alasan & Konteks Perubahan --}}
                                    <div class="space-y-2">
                                        <h5 class="text-xs font-bold text-slate-900 uppercase tracking-wider m-0">Catatan & Alasan Perubahan</h5>
                                        <div class="p-3.5 bg-white rounded-xl border border-slate-200 shadow-2xs text-xs text-slate-800 leading-relaxed">
                                            {{ $e->reason ?? 'Tidak ada catatan naratif khusus yang disertakan pada log peristiwa ini.' }}
                                        </div>
                                    </div>

                                    {{-- Inspeksi Perbandingan Data (Before vs After) --}}
                                    <div class="space-y-3">
                                        <h5 class="text-xs font-bold text-slate-900 uppercase tracking-wider m-0">Inspeksi Snapshot Data (Before vs After)</h5>
                                        @if($e->before || $e->after)
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 font-mono text-[11px]">
                                                {{-- Nilai Sebelum (Before) --}}
                                                <div class="p-3.5 rounded-xl border border-rose-200 bg-rose-50/40 space-y-2">
                                                    <div class="flex items-center justify-between pb-1.5 border-b border-rose-200">
                                                        <span class="font-bold text-rose-800 uppercase tracking-wider text-[10px]">Keadaan Sebelum (Before)</span>
                                                        <span class="text-[9px] text-rose-600 bg-rose-100 px-1.5 py-0.5 rounded font-bold">STATE AWAL</span>
                                                    </div>
                                                    @if($e->before)
                                                        <pre class="whitespace-pre-wrap text-rose-950 overflow-x-auto m-0 leading-relaxed">{{ is_string($e->before) ? $e->before : json_encode($e->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    @else
                                                        <span class="text-slate-400 italic font-sans text-xs">Tidak ada data sebelumnya (entitas baru dibuat).</span>
                                                    @endif
                                                </div>

                                                {{-- Nilai Sesudah (After) --}}
                                                <div class="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50/40 space-y-2">
                                                    <div class="flex items-center justify-between pb-1.5 border-b border-emerald-200">
                                                        <span class="font-bold text-emerald-800 uppercase tracking-wider text-[10px]">Keadaan Sesudah (After)</span>
                                                        <span class="text-[9px] text-emerald-600 bg-emerald-100 px-1.5 py-0.5 rounded font-bold">STATE AKHIR</span>
                                                    </div>
                                                    @if($e->after)
                                                        <pre class="whitespace-pre-wrap text-emerald-950 overflow-x-auto m-0 leading-relaxed">{{ is_string($e->after) ? $e->after : json_encode($e->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    @else
                                                        <span class="text-slate-400 italic font-sans text-xs">Tidak ada data sesudah (entitas dihapus / diarsipkan).</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div class="p-4 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-center">
                                                <svg class="w-7 h-7 text-slate-300 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                <p class="text-xs text-slate-500 m-0">Aksi ini tidak menyimpan snapshot delta perbandingan nilai data (contoh: log trigger, kunci audit, atau peristiwa baca).</p>
                                            </div>
                                        @endif
                                    </div>
                                </x-detail-modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <p class="text-sm font-semibold text-slate-500 m-0">Belum ada peristiwa log aktivitas yang tercatat atau sesuai pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$events" />
    </section>
</div>
@endsection
