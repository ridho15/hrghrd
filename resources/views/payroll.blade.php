@extends('layouts.app')
@section('title', 'Payroll')

@section('content')
<div class="space-y-8">
    {{-- Header Modul & Status Siklus --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Otomasi Penggajian & Audit</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Kalkulasi & Rekap Payroll
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Pratinjau rinci kalkulasi slip gaji per karyawan. Siklus draf, persetujuan, dan penguncian periode dilakukan bertahap demi integritas data.
            </p>
        </div>

        {{-- Status Periode Badge --}}
        <div>
            @php
                $statusColor = match($run?->status) {
                    'locked' => 'bg-emerald-50 text-emerald-800 border-emerald-300 ring-2 ring-emerald-500/20',
                    'approved' => 'bg-emerald-50 text-emerald-800 border-emerald-300',
                    'draft' => 'bg-amber-50 text-amber-800 border-amber-300',
                    default => 'bg-slate-100 text-slate-600 border-slate-200'
                };
            @endphp
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-extrabold uppercase tracking-wider border {{ $statusColor }}">
                <span class="w-2 h-2 rounded-full {{ $run?->status === 'locked' ? 'bg-emerald-500' : ($run?->status === 'approved' ? 'bg-emerald-500' : ($run?->status === 'draft' ? 'bg-amber-500 animate-pulse' : 'bg-slate-400')) }}"></span>
                <span>STATUS: {{ $run ? strtoupper($run->status) : 'BELUM ADA DRAF' }}</span>
            </span>
        </div>
    </div>

    {{-- Filter Periode & Bilah Aksi Utama --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-5">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 pb-4 border-b border-slate-100">
            {{-- Form Seleksi Cabang & Bulan --}}
            <form method="get" action="{{ route('payroll.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="branch-id" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Cabang
                    </label>
                    <select id="branch-id" name="branch_id" class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected($b->id == $branchId)>{{ $b->name }} ({{ $b->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="payroll-month" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Periode Bulan
                    </label>
                    <input id="payroll-month" type="month" name="month" value="{{ $month }}" required class="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 tabular-nums">
                </div>

                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-sm font-bold border border-slate-200 transition-colors cursor-pointer h-[38px]">
                    Muat Data
                </button>
            </form>

            {{-- Tombol Aksi Siklus Penggajian --}}
            <div class="flex flex-wrap items-center gap-2.5">
                @if(!$run || $run->status === 'draft')
                    <form method="post" action="{{ route('payroll.generate') }}">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-xs transition-colors cursor-pointer flex items-center gap-1.5 h-[38px]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>{{ $run ? 'Hitung Ulang Draf' : 'Simpan Draf Payroll' }}</span>
                        </button>
                    </form>
                @endif

                @if($run && $run->status === 'draft')
                    <form method="post" action="{{ route('payroll.approve') }}">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider shadow-xs transition-colors cursor-pointer flex items-center gap-1.5 h-[38px]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Setujui Periode</span>
                        </button>
                    </form>
                @endif

                @if($run && $run->status === 'approved')
                    <form method="post" action="{{ route('payroll.lock') }}">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold uppercase tracking-wider shadow-xs transition-colors cursor-pointer flex items-center gap-1.5 h-[38px]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <span>Kunci Periode Permanen</span>
                        </button>
                    </form>
                @endif

                @if($run && in_array($run->status, ['approved', 'locked']))
                    <a href="{{ route('payroll.export', ['branch_id' => $branchId, 'month' => $month]) }}" class="px-4 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold uppercase tracking-wider border border-emerald-200 transition-colors shadow-xs flex items-center gap-1.5 h-[38px]">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>Ekspor CSV</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Banner Peringatan 7-Gate Blockers --}}
        @if($blockers)
            <div class="rounded-xl bg-amber-50/90 border border-amber-200/90 p-4 text-amber-900 flex items-start gap-3 shadow-xs">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <div class="text-xs sm:text-sm">
                    <strong class="font-bold block text-amber-950 mb-1">Prasyarat Persetujuan Belum Terpenuhi:</strong>
                    <p class="m-0 text-amber-800 leading-relaxed">
                        Sebelum periode ini disetujui, selesaikan kendala berikut terlebih dahulu: <strong>{{ implode(', ', $blockers) }}</strong>.
                    </p>
                </div>
            </div>
        @endif
    </section>

    {{-- Form Koreksi Manual (Khusus Periode Belum Terkunci) --}}
    @if(!$run || $run->status === 'draft')
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Tambah Koreksi Finansial Manual</h2>
            </div>

            <form method="post" action="{{ route('payroll.adjustment') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 items-end">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Pilih Karyawan</label>
                    <select name="user_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                        @foreach($allBranchEmployees ?? [] as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Jumlah (+ / − Rp)</label>
                    <input type="number" name="amount" required placeholder="Contoh: 150000 atau -50000" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>

                <div class="sm:col-span-3 lg:col-span-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Alasan Penyesuaian (Min 10 Karakter)</label>
                    <input name="reason" required minlength="10" placeholder="Contoh: Bonus operasional shift malam..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>

                <div class="sm:col-span-3 pt-1">
                    <button type="submit" class="w-full sm:w-auto px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider transition-colors shadow-xs cursor-pointer">
                        Simpan Koreksi Manual
                    </button>
                </div>
            </form>
        </section>
    @endif

    {{-- Rincian Slip Gaji Karyawan --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">
                    Slip Gaji Cabang {{ $branch->name }} &middot; Periode {{ $month }}
                </h2>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                {{ $paginatedEmployees->total() }} Karyawan
            </span>
        </div>

        {{-- Toolbar Pencarian Karyawan Payroll --}}
        <x-table-toolbar :action="route('payroll.index')" search-placeholder="Cari nama karyawan...">
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
            <input type="hidden" name="month" value="{{ $month }}">
        </x-table-toolbar>

        <div class="space-y-4">
            @forelse($lines as $line)
                @php($d = $line['detail'])
                <article class="p-4 sm:p-5 rounded-2xl border border-slate-200/90 bg-slate-50/40 space-y-4 hover:border-slate-300 transition-colors">
                    {{-- Baris Atas Ringkasan --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
                        <div>
                            <strong class="text-base font-bold text-slate-900 block">{{ $line['employee']->name }}</strong>
                            <span class="text-xs text-slate-500 block mt-0.5 tabular-nums">
                                Gaji Pokok: Rp{{ number_format($d['monthly_salary'], 0, ',', '.') }} &middot; Bekerja: {{ $d['employed_days'] }}/{{ $d['calendar_days'] }} Hari Kalender
                            </span>
                        </div>
                        <div class="text-left sm:text-right">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 block">Total Diterima (Net Pay)</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-slate-900 tabular-nums">
                                Rp{{ number_format($d['net'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    {{-- Rincian Akuntansi --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                        <div class="p-3 bg-white rounded-xl border border-slate-200 flex items-center justify-between">
                            <span class="text-slate-600">Prorata Gaji Pokok</span>
                            <strong class="text-emerald-700 font-bold tabular-nums">+ Rp{{ number_format($d['prorated_base'], 0, ',', '.') }}</strong>
                        </div>

                        <div class="p-3 bg-white rounded-xl border border-slate-200 flex items-center justify-between">
                            <span class="text-slate-600">Hari Unpaid ({{ count($d['unpaid_dates']) }})</span>
                            <strong class="text-rose-700 font-bold tabular-nums">− Rp{{ number_format($d['unpaid_deduction'], 0, ',', '.') }}</strong>
                        </div>

                        <div class="p-3 bg-white rounded-xl border border-slate-200 flex items-center justify-between">
                            <span class="text-slate-600">Potongan Terlambat ({{ $d['late_units'] }} Unit)</span>
                            <strong class="text-rose-700 font-bold tabular-nums">− Rp{{ number_format($d['late_deduction'], 0, ',', '.') }}</strong>
                        </div>

                        <div class="p-3 bg-white rounded-xl border border-slate-200 flex items-center justify-between">
                            <span class="text-slate-600">Lembur Disetujui ({{ $d['overtime_minutes'] }} Menit)</span>
                            <strong class="text-emerald-700 font-bold tabular-nums">+ Rp{{ number_format($d['overtime_pay'], 0, ',', '.') }}</strong>
                        </div>

                        <div class="p-3 bg-white rounded-xl border border-slate-200 flex items-center justify-between">
                            <span class="text-slate-600">Koreksi Manual</span>
                            <strong class="font-bold tabular-nums {{ $d['manual_total'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $d['manual_total'] >= 0 ? '+' : '−' }} Rp{{ number_format(abs($d['manual_total']), 0, ',', '.') }}
                            </strong>
                        </div>

                        <div class="p-3 bg-slate-100 rounded-xl border border-slate-200 flex items-center justify-between text-[11px]">
                            <span class="text-slate-500">Tarif Harian / Jam</span>
                            <span class="font-mono text-slate-700 font-semibold tabular-nums">
                                Rp{{ number_format($d['daily_rate'], 0, ',', '.') }} / Rp{{ number_format($d['hourly_rate'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    {{-- Jejak Sumber Audit Transparan --}}
                    <div class="pt-2 border-t border-slate-200 text-[11px] text-slate-500">
                        <details class="group">
                            <summary class="cursor-pointer text-emerald-700 font-semibold hover:underline list-none flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                <span>Lihat Sumber Referensi Audit (ID Absensi & Koreksi)</span>
                            </summary>
                            <div class="mt-2 p-3 bg-white rounded-xl border border-slate-200 space-y-1 font-mono text-[10px] text-slate-600">
                                <div><strong>Tanggal Unpaid:</strong> {{ implode(', ', $d['unpaid_dates']) ?: '—' }}</div>
                                <div><strong>ID Presensi Terlambat:</strong> {{ implode(', ', array_column($d['late_sources'], 'attendance_id')) ?: '—' }}</div>
                                <div><strong>ID Presensi Lembur:</strong> {{ implode(', ', array_column($d['overtime_sources'], 'attendance_id')) ?: '—' }}</div>
                                <div><strong>ID Koreksi Finansial:</strong> {{ implode(', ', array_column($d['adjustments'], 'id')) ?: '—' }}</div>
                            </div>
                        </details>
                    </div>
                </article>
            @empty
                <div class="text-center py-10 text-slate-400">
                    <p class="text-sm font-semibold text-slate-500 m-0">Tidak ada data karyawan aktif untuk cabang dan periode bulan ini atau sesuai pencarian.</p>
                </div>
            @endforelse
        </div>

        <x-pagination :paginator="$paginatedEmployees" />
    </section>
</div>
@endsection
