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
                        <th class="py-3.5 px-4 sm:px-6">Alasan & Perubahan Nilai</th>
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

                            {{-- Alasan & Diff Perubahan --}}
                            <td class="py-4 px-4 sm:px-6 text-xs text-slate-600">
                                <div class="font-medium text-slate-800 mb-1">
                                    {{ $e->reason ?? '—' }}
                                </div>

                                @if($e->before || $e->after)
                                    <details class="text-[11px] text-slate-500 pt-1">
                                        <summary class="cursor-pointer text-emerald-700 font-semibold hover:underline">
                                            Inspeksi Perbandingan Data
                                        </summary>
                                        <div class="mt-2 p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5 font-mono text-[10px]">
                                            @if($e->before)
                                                <div class="text-rose-700">
                                                    <strong>Sebelum:</strong>
                                                    <pre class="whitespace-pre-wrap mt-0.5">{{ is_string($e->before) ? $e->before : json_encode($e->before, JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                            @endif
                                            @if($e->after)
                                                <div class="text-emerald-700 pt-1 border-t border-slate-200">
                                                    <strong>Sesudah:</strong>
                                                    <pre class="whitespace-pre-wrap mt-0.5">{{ is_string($e->after) ? $e->after : json_encode($e->after, JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
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
