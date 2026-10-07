@extends('layouts.app')
@section('title', 'Kalkulasi & Rekap Payroll')

@section('content')
<div class="space-y-6">
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
                Rekapitulasi gaji dan remunerasi cabang <strong>{{ $branch->name }}</strong> untuk periode <strong>{{ \Carbon\Carbon::parse($month.'-01')->translatedFormat('F Y') }}</strong>.
            </p>
        </div>

        {{-- Status Siklus Penggajian --}}
        <div>
            @php
                $statusConfig = match($run?->status) {
                    'locked' => ['label' => 'TERKUNCI PERMANEN', 'class' => 'bg-emerald-50 text-emerald-800 border-emerald-300 ring-2 ring-emerald-500/20', 'dot' => 'bg-emerald-500'],
                    'approved' => ['label' => 'DISETUJUI MANAJER', 'class' => 'bg-emerald-50 text-emerald-800 border-emerald-300', 'dot' => 'bg-emerald-500'],
                    'draft' => ['label' => 'DRAF TERSIMPAN', 'class' => 'bg-amber-50 text-amber-800 border-amber-300', 'dot' => 'bg-amber-500 animate-pulse'],
                    default => ['label' => 'BELUM ADA DRAF', 'class' => 'bg-slate-100 text-slate-600 border-slate-200', 'dot' => 'bg-slate-400']
                };
            @endphp
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-extrabold uppercase tracking-wider border {{ $statusConfig['class'] }}">
                <span class="w-2 h-2 rounded-full {{ $statusConfig['dot'] }}"></span>
                <span>STATUS: {{ $statusConfig['label'] }}</span>
            </span>
        </div>
    </div>

    {{-- Kartu Ringkasan Finansial Eksekutif (4 Metrik Utama) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Kas Bersih (Net Pay) --}}
        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 text-white shadow-xs">
            <span class="text-xs uppercase font-bold tracking-wider text-emerald-400 block">Total Pengeluaran Kas (Net)</span>
            <span class="text-2xl font-black tracking-tight text-white block mt-1 tabular-nums">
                Rp{{ number_format($summary['total_net'], 0, ',', '.') }}
            </span>
            <span class="text-[11px] text-slate-300 mt-1 block">
                Total yang ditransfer ke {{ $summary['employee_count'] }} karyawan
            </span>
        </div>

        {{-- Total Gaji Pokok Prorata --}}
        <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
            <span class="text-xs uppercase font-bold tracking-wider text-slate-500 block">Gaji Pokok Prorata</span>
            <span class="text-xl font-black tracking-tight text-slate-900 block mt-1 tabular-nums">
                Rp{{ number_format($summary['total_base'], 0, ',', '.') }}
            </span>
            <span class="text-[11px] text-slate-400 mt-1 block">
                Dasar upah terhitung bulan ini
            </span>
        </div>

        {{-- Total Tunjangan Lembur --}}
        <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
            <span class="text-xs uppercase font-bold tracking-wider text-emerald-700 block">Tunjangan Lembur Sah</span>
            <span class="text-xl font-black tracking-tight text-emerald-800 block mt-1 tabular-nums">
                + Rp{{ number_format($summary['total_overtime'], 0, ',', '.') }}
            </span>
            <span class="text-[11px] text-slate-400 mt-1 block">
                Kompensasi lembur disetujui
            </span>
        </div>

        {{-- Total Potongan --}}
        <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs">
            <span class="text-xs uppercase font-bold tracking-wider text-rose-700 block">Potongan Disiplin & Alfa</span>
            <span class="text-xl font-black tracking-tight text-rose-700 block mt-1 tabular-nums">
                − Rp{{ number_format($summary['total_deductions'], 0, ',', '.') }}
            </span>
            <span class="text-[11px] text-slate-400 mt-1 block">
                Potongan mangkir & denda terlambat
            </span>
        </div>
    </div>

    {{-- Filter Periode & Bilah Aksi Utama --}}
    <section class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 sm:p-6 space-y-5">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 pb-4 border-b border-slate-100">
            {{-- Form Seleksi Cabang & Bulan --}}
            <form method="get" action="{{ route('payroll.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="w-56 sm:w-64">
                    <x-searchable-select
                        name="branch_id"
                        id="payroll-branch-select"
                        label="Cabang Operasional"
                        :value="$branchId"
                        :options="$branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name, 'sublabel' => $b->code])"
                        placeholder="Pilih Cabang..."
                        searchPlaceholder="Cari cabang..."
                    />
                </div>

                <div>
                    <label for="payroll-month" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Periode Bulan
                    </label>
                    <input id="payroll-month" type="month" name="month" value="{{ $month }}" required class="h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 tabular-nums">
                </div>

                <button type="submit" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold uppercase tracking-wider border border-slate-200 transition-colors cursor-pointer h-10">
                    Muat Data
                </button>
            </form>

            {{-- Tombol Aksi Siklus Penggajian --}}
            <div class="flex flex-wrap items-center gap-2">
                @if(!$run || $run->status === 'draft')
                    <form method="post" action="{{ route('payroll.generate') }}">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button type="submit" title="Simpan Draf Payroll" class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer flex items-center gap-1.5 h-10">
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
                        <button
                            type="submit"
                            data-confirm="Setujui seluruh kalkulasi penggajian periode {{ $month }} untuk cabang terpilih? Pastikan seluruh prasyarat dan jam kerja telah diverifikasi."
                            data-confirm-title="Persetujuan Penggajian"
                            data-confirm-variant="primary"
                            data-confirm-btn="Ya, Setujui"
                            class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer flex items-center gap-1.5 h-10"
                        >
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
                        <button
                            type="submit"
                            data-confirm="Kunci penggajian periode {{ $month }} secara permanen? Data yang telah dikunci tidak dapat diubah, disesuaikan, atau dihitung ulang lagi demi kepatuhan audit."
                            data-confirm-title="Kunci Permanen Penggajian"
                            data-confirm-variant="warning"
                            data-confirm-btn="Ya, Kunci Permanen"
                            class="px-4 py-2.5 rounded-xl bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer flex items-center gap-1.5 h-10"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <span>Kunci Periode Permanen</span>
                        </button>
                    </form>
                @endif

                @if($run && $run->status !== 'locked' && auth()->user()->role === 'admin')
                    <form method="post" action="{{ route('payroll.reset') }}">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button
                            type="submit"
                            data-confirm="Reset seluruh draf penggajian periode {{ $month }} cabang ini? Semua kalkulasi tersimpan dan penyesuaian manual akan dihapus sehingga data absensi dihitung ulang secara dinamis."
                            data-confirm-title="Reset Draf Penggajian"
                            data-confirm-variant="danger"
                            data-confirm-btn="Ya, Reset Draf"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-700 text-xs font-bold uppercase tracking-wider border border-slate-200 hover:border-rose-200 transition-colors shadow-2xs cursor-pointer flex items-center gap-1.5 h-10"
                            title="Hapus draf dan reset kalkulasi penggajian"
                        >
                            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            <span>Reset Draf</span>
                        </button>
                    </form>
                @endif

                @if($run && in_array($run->status, ['approved', 'locked']))
                    <a href="{{ route('payroll.export', ['branch_id' => $branchId, 'month' => $month]) }}" class="px-4 py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold uppercase tracking-wider border border-emerald-200 transition-colors shadow-2xs flex items-center gap-1.5 h-10">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>Ekspor CSV</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Banner Peringatan 7-Gate Blockers --}}
        @if($blockers)
            <div class="rounded-2xl bg-amber-50/90 border border-amber-200/90 p-4 text-amber-900 flex items-start gap-3 shadow-xs">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <div class="text-xs sm:text-sm">
                    <strong class="font-bold block text-amber-950 mb-1">Prasyarat Persetujuan Belum Terpenuhi:</strong>
                    <p class="m-0 text-amber-800 leading-relaxed">
                        Sebelum periode ini dapat disetujui, harap selesaikan kendala presensi/perizinan berikut terlebih dahulu: <strong>{{ implode(', ', $blockers) }}</strong>.
                    </p>
                </div>
            </div>
        @endif
    </section>

    {{-- Form Koreksi Finansial Manual (Collapsible Drawer agar tidak mengganggu) --}}
    @if(!$run || $run->status === 'draft')
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 sm:p-6">
            <details class="group">
                <summary class="flex items-center justify-between cursor-pointer list-none select-none">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 tracking-tight m-0">Tambah Koreksi Finansial Manual (Bonus / Kasbon)</h2>
                            <p class="text-xs text-slate-500 m-0">Klik untuk membuka formulir penyesuaian upah non-rutin.</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold text-emerald-700 group-open:rotate-180 transition-transform">
                        ▼
                    </span>
                </summary>

                <form method="post" action="{{ route('payroll.adjustment') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end mt-4 pt-4 border-t border-slate-100">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">

                    <div>
                        <x-searchable-select
                            name="user_id"
                            id="adjustment-user"
                            label="Pilih Karyawan"
                            required
                            :options="collect($allBranchEmployees ?? [])->map(fn($emp) => ['value' => $emp->id, 'label' => $emp->name, 'sublabel' => $emp->position?->name ?? 'Staf'])"
                            placeholder="Pilih Karyawan..."
                            searchPlaceholder="Cari nama karyawan..."
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Jumlah (+ / − Rp)</label>
                        <input type="number" name="amount" required placeholder="Contoh: 150000 atau -50000" class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alasan Penyesuaian (Min. 10 Karakter)</label>
                        <input name="reason" required minlength="10" placeholder="Contoh: Insentif performa shift malam..." class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    </div>

                    <div class="sm:col-span-3 pt-1">
                        <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider transition-colors shadow-2xs cursor-pointer">
                            Simpan Koreksi Manual
                        </button>
                    </div>
                </form>
            </details>
        </div>
    @endif

    {{-- Tabel Rekapitulasi Gaji Karyawan Full-Width --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        {{-- Toolbar Pencarian Karyawan Payroll --}}
        <x-table-toolbar :action="route('payroll.index')" search-placeholder="Cari nama karyawan...">
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
            <input type="hidden" name="month" value="{{ $month }}">
        </x-table-toolbar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">Karyawan</th>
                        <th class="py-3.5 px-4">Gaji Prorata</th>
                        <th class="py-3.5 px-4">Lembur (+)</th>
                        <th class="py-3.5 px-4">Potongan (−)</th>
                        <th class="py-3.5 px-4">Koreksi</th>
                        <th class="py-3.5 px-4">Gaji Bersih (Net)</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($lines as $line)
                        @php
                            $d = $line['detail'];
                            $emp = $line['employee'];
                            $unpaidAndLate = $d['unpaid_deduction'] + $d['late_deduction'];
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Nama Karyawan --}}
                            <td class="py-4 px-4 sm:px-6 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 font-bold flex items-center justify-center shrink-0 border border-emerald-200/60 text-xs">
                                        {{ strtoupper(substr($emp->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('payroll.slip', ['userId' => $emp->id, 'branch_id' => $branchId, 'month' => $month]) }}" class="font-bold text-slate-900 hover:text-emerald-700 hover:underline block">
                                            {{ $emp->name }}
                                        </a>
                                        <span class="text-[11px] text-slate-400">
                                            {{ $emp->position?->name ?? 'Staf' }} &middot; {{ $d['employed_days'] }}/{{ $d['calendar_days'] }} Hari
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- Gaji Prorata --}}
                            <td class="py-4 px-4 whitespace-nowrap font-medium text-slate-700 tabular-nums">
                                <span class="font-bold text-slate-900 block">Rp{{ number_format($d['prorated_base'], 0, ',', '.') }}</span>
                                <span class="text-[11px] text-slate-400">Dasar: Rp{{ number_format($d['monthly_salary'], 0, ',', '.') }}</span>
                            </td>

                            {{-- Lembur (+) --}}
                            <td class="py-4 px-4 whitespace-nowrap tabular-nums">
                                @if($d['overtime_pay'] > 0)
                                    <span class="font-bold text-emerald-700 block">+ Rp{{ number_format($d['overtime_pay'], 0, ',', '.') }}</span>
                                    <span class="text-[11px] text-emerald-600 font-medium">{{ $d['overtime_minutes'] }} Menit</span>
                                @else
                                    <span class="text-slate-400">Rp0</span>
                                @endif
                            </td>

                            {{-- Potongan (−) --}}
                            <td class="py-4 px-4 whitespace-nowrap tabular-nums">
                                @if($unpaidAndLate > 0)
                                    <span class="font-bold text-rose-700 block">− Rp{{ number_format($unpaidAndLate, 0, ',', '.') }}</span>
                                    <span class="text-[11px] text-rose-500">Alfa: {{ count($d['unpaid_dates']) }} hr &middot; Telat: {{ $d['late_units'] }} unit</span>
                                @else
                                    <span class="text-slate-400">Rp0</span>
                                @endif
                            </td>

                            {{-- Koreksi Manual --}}
                            <td class="py-4 px-4 whitespace-nowrap tabular-nums">
                                @if($d['manual_total'] != 0)
                                    <span class="font-bold {{ $d['manual_total'] > 0 ? 'text-emerald-700' : 'text-rose-700' }} block">
                                        {{ $d['manual_total'] > 0 ? '+' : '−' }} Rp{{ number_format(abs($d['manual_total']), 0, ',', '.') }}
                                    </span>
                                @else
                                    <span class="text-slate-400">Rp0</span>
                                @endif
                            </td>

                            {{-- Gaji Bersih (Net Pay) --}}
                            <td class="py-4 px-4 whitespace-nowrap tabular-nums">
                                <span class="text-base font-extrabold text-slate-900 block">
                                    Rp{{ number_format($d['net'], 0, ',', '.') }}
                                </span>
                                <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">
                                    Take Home Pay
                                </span>
                            </td>

                            {{-- Tindakan Buka Slip Resmi --}}
                            <td class="py-4 px-4 sm:px-6 text-right whitespace-nowrap">
                                <a
                                    href="{{ route('payroll.slip', ['userId' => $emp->id, 'branch_id' => $branchId, 'month' => $month]) }}"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200/80 transition-colors inline-flex items-center gap-1.5 shadow-2xs"
                                    title="Buka lembar slip gaji resmi dan cetak dokumen"
                                >
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span>Buka Slip Resmi ↗</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400">
                                <p class="text-xs font-semibold text-slate-500 m-0">Tidak ada data karyawan aktif untuk cabang dan periode bulan ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$paginatedEmployees" />
    </div>

    {{-- Glosarium Finansial & Panduan Ramah Pengguna Awam --}}
    <section class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
        <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm">
                💡
            </div>
            <div>
                <h2 class="text-base font-bold text-slate-900">Glosarium & Panduan Perhitungan Gaji</h2>
                <p class="text-xs text-slate-500">Penjelasan istilah akuntansi dan rumus remunerasi yang mudah dipahami.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <strong class="text-slate-900 block font-bold">1. Prorata Gaji Pokok</strong>
                <p class="text-slate-600 m-0 leading-relaxed">
                    Gaji pokok yang disesuaikan secara proporsional jika karyawan baru mulai bekerja di pertengahan bulan kalender.
                </p>
            </div>

            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <strong class="text-slate-900 block font-bold">2. Hari Unpaid (Mangkir/Alfa)</strong>
                <p class="text-slate-600 m-0 leading-relaxed">
                    Hari saat karyawan tidak hadir dan tidak memiliki izin berbayar resmi, sehingga gajinya dipotong sesuai tarif harian.
                </p>
            </div>

            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <strong class="text-slate-900 block font-bold">3. Denda Keterlambatan</strong>
                <p class="text-slate-600 m-0 leading-relaxed">
                    Pemotongan per kelipatan menit keterlambatan (unit denda) setelah melewati batas dispensasi waktu toleransi hadir.
                </p>
            </div>

            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <strong class="text-slate-900 block font-bold">4. Upah Lembur Sah</strong>
                <p class="text-slate-600 m-0 leading-relaxed">
                    Kompensasi resmi untuk kehadiran kerja yang melebihi durasi jam shift dan telah disetujui oleh manajer cabang.
                </p>
            </div>

            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <strong class="text-slate-900 block font-bold">5. Tarif Satuan Upah</strong>
                <p class="text-slate-600 m-0 leading-relaxed">
                    Tarif harian dihitung dari Gaji Pokok &divide; Hari Kalender. Tarif per jam dihitung dari Tarif Harian &divide; Jam Kerja Harian.
                </p>
            </div>

            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <strong class="text-slate-900 block font-bold">6. Gaji Bersih (Net Pay)</strong>
                <p class="text-slate-600 m-0 leading-relaxed">
                    Jumlah akhir uang yang diterima karyawan: <strong>(Gaji Prorata + Lembur + Bonus) − (Potongan Unpaid + Denda Telat + Kasbon)</strong>.
                </p>
            </div>
        </div>
    </section>
</div>
@endsection
