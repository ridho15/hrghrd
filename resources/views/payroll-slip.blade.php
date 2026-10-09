@extends('layouts.app')
@section('title', 'Slip Gaji: ' . $employee->name . ' (' . \Carbon\Carbon::parse($month.'-01')->translatedFormat('F Y') . ')')

@push('styles')
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm 15mm;
    }
    body, html {
        background: #ffffff !important;
        color: #0f172a !important;
    }
    .print\:hidden {
        display: none !important;
    }
    .slip-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
}
</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi (Sembunyi saat cetak) --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 print:hidden">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('payroll.index', ['branch_id' => $branch->id, 'month' => $month]) }}" class="hover:text-emerald-700 transition-colors">Rekap Payroll</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Slip: {{ $employee->name }}</span>
    </nav>

    {{-- Bilah Aksi Utama (Sembunyi saat cetak) --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80 print:hidden">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Dokumen Remunerasi Resmi</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Slip Gaji Karyawan
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Rincian penghasilan hak gaji dan kewajiban potongan untuk <strong>{{ $employee->name }}</strong> periode {{ \Carbon\Carbon::parse($month.'-01')->translatedFormat('F Y') }}.
            </p>
        </div>

        <div class="flex items-center gap-2.5 self-start sm:self-auto">
            <a
                href="{{ route('payroll.index', ['branch_id' => $branch->id, 'month' => $month]) }}"
                class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5 shadow-2xs"
            >
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <span>Kembali ke Rekap</span>
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all inline-flex items-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                <span>Cetak Slip / PDF</span>
            </button>
        </div>
    </div>

    @php
        $grossEarnings = $detail['prorated_base'] + $detail['overtime_pay'] + max(0, $detail['manual_total']);
        $totalDeductions = $detail['unpaid_deduction'] + $detail['late_deduction'] + abs(min(0, $detail['manual_total']));
        $refSlipNumber = 'SLIP-' . str_replace('-', '', $month) . '-' . str_pad($employee->id, 4, '0', STR_PAD_LEFT);
    @endphp

    {{-- LEMBAR SLIP GAJI UTAMA (PRINTABLE A4) --}}
    <div class="slip-card bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-10 space-y-6 print:border-none print:shadow-none print:p-0">
        {{-- Kop Surat Resmi Korporat (Clean Header) --}}
        <div class="flex items-start justify-between gap-4 pb-6 border-b-2 border-slate-900">
            {{-- Logo Saja di Header Kiri --}}
            <div class="flex items-center">
                <img src="{{ asset('images/logo-hr-group.png') }}" alt="HR Group" class="h-10 sm:h-12 w-auto object-contain shrink-0">
            </div>

            {{-- Identitas Bukti & Nama Karyawan di Header Kanan --}}
            <div class="text-right">
                <span class="text-[11px] font-extrabold uppercase tracking-widest text-slate-500 block">BUKTI PENGGAJIAN RESMI</span>
                <span class="text-base sm:text-lg font-black text-slate-900 block mt-0.5">{{ $employee->name }}</span>
                <span class="text-xs font-mono font-bold text-slate-600 block">{{ $refSlipNumber }}</span>
                <span class="text-xs text-slate-500 block">Periode: {{ \Carbon\Carbon::parse($month.'-01')->translatedFormat('F Y') }}</span>
                @if($employee->bank_account_number)
                    <span class="text-[11px] font-medium text-emerald-800 block mt-1">
                        {{ $employee->bank_name ?? 'Bank' }}: <strong class="font-mono">{{ $employee->bank_account_number }}</strong> ({{ $employee->bank_account_name ?? $employee->name }})
                    </span>
                @endif
            </div>
        </div>

        {{-- Komparasi Dua Kolom: Pendapatan vs Potongan --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
            {{-- Kolom Pendapatan (Earnings) --}}
            <div class="rounded-2xl border border-emerald-200/90 bg-emerald-50/20 p-5 space-y-4">
                <div class="flex items-center justify-between pb-2.5 border-b border-emerald-200">
                    <h3 class="text-xs font-bold text-emerald-950 uppercase tracking-wider m-0">1. Pendapatan (Earnings)</h3>
                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-md">Kredit (+)</span>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-slate-800 font-medium block">Prorata Gaji Pokok</span>
                            <span class="text-[10px] text-slate-400">Dasar Bulanan: Rp{{ number_format($detail['monthly_salary'], 0, ',', '.') }}</span>
                        </div>
                        <strong class="text-emerald-800 tabular-nums font-bold">+ Rp{{ number_format($detail['prorated_base'], 0, ',', '.') }}</strong>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-slate-800 font-medium block">Upah Lembur Resmi</span>
                            <span class="text-[10px] text-slate-400">{{ $detail['overtime_minutes'] }} Menit lembur terverifikasi</span>
                        </div>
                        <strong class="text-emerald-800 tabular-nums font-bold">+ Rp{{ number_format($detail['overtime_pay'], 0, ',', '.') }}</strong>
                    </div>

                    @if(max(0, $detail['manual_total']) > 0)
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-slate-800 font-medium block">Bonus & Insentif Operasional</span>
                                <span class="text-[10px] text-slate-400">Koreksi manual disetujui</span>
                            </div>
                            <strong class="text-emerald-800 tabular-nums font-bold">+ Rp{{ number_format(max(0, $detail['manual_total']), 0, ',', '.') }}</strong>
                        </div>
                    @endif
                </div>

                <div class="pt-3 border-t border-emerald-200 flex items-center justify-between text-xs font-bold text-emerald-950">
                    <span>Total Pendapatan Kotor (Gross)</span>
                    <span class="text-sm font-extrabold text-emerald-800 tabular-nums">Rp{{ number_format($grossEarnings, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- Kolom Potongan (Deductions) --}}
            <div class="rounded-2xl border border-rose-200/90 bg-rose-50/20 p-5 space-y-4">
                <div class="flex items-center justify-between pb-2.5 border-b border-rose-200">
                    <h3 class="text-xs font-bold text-rose-950 uppercase tracking-wider m-0">2. Potongan (Deductions)</h3>
                    <span class="text-[10px] font-bold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-md">Debit (−)</span>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-slate-800 font-medium block">Hari Tanpa Gaji (Unpaid/Alfa)</span>
                            <span class="text-[10px] text-slate-400">{{ count($detail['unpaid_dates']) }} Hari tidak hadir</span>
                        </div>
                        <strong class="text-rose-700 tabular-nums font-bold">− Rp{{ number_format($detail['unpaid_deduction'], 0, ',', '.') }}</strong>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-slate-800 font-medium block">Denda Keterlambatan Presensi</span>
                            <span class="text-[10px] text-slate-400">{{ $detail['late_units'] }} Unit keterlambatan</span>
                        </div>
                        <strong class="text-rose-700 tabular-nums font-bold">− Rp{{ number_format($detail['late_deduction'], 0, ',', '.') }}</strong>
                    </div>

                    @if(abs(min(0, $detail['manual_total'])) > 0)
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-slate-800 font-medium block">Potongan Kasbon / Lainnya</span>
                                <span class="text-[10px] text-slate-400">Penyesuaian manual</span>
                            </div>
                            <strong class="text-rose-700 tabular-nums font-bold">− Rp{{ number_format(abs(min(0, $detail['manual_total'])), 0, ',', '.') }}</strong>
                        </div>
                    @endif
                </div>

                <div class="pt-3 border-t border-rose-200 flex items-center justify-between text-xs font-bold text-rose-950">
                    <span>Total Seluruh Potongan</span>
                    <span class="text-sm font-extrabold text-rose-700 tabular-nums">Rp{{ number_format($totalDeductions, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Highlight Take Home Pay / Gaji Bersih --}}
        <div class="p-6 bg-slate-900 border border-slate-800 text-white rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-400 block">Total Gaji Bersih Diterima (Take Home Pay)</span>
                <p class="text-xs text-slate-300 m-0 mt-0.5">
                    Kalkulasi: Total Pendapatan Kotor (Rp{{ number_format($grossEarnings, 0, ',', '.') }}) − Total Potongan (Rp{{ number_format($totalDeductions, 0, ',', '.') }})
                </p>
            </div>
            <div class="text-left sm:text-right">
                <span class="text-2xl sm:text-3xl font-black text-emerald-300 tabular-nums block">
                    Rp{{ number_format($detail['net'], 0, ',', '.') }}
                </span>
                <span class="text-[10px] text-slate-400 uppercase tracking-widest font-mono">Transfer Payroll Resmi</span>
            </div>
        </div>

        {{-- Tanda Tangan Penerima Dokumen (Hanya Kiri Sesuai Permintaan) --}}
        <div class="pt-8 border-t border-slate-200 text-xs">
            <div class="max-w-xs space-y-12">
                <span class="text-slate-500 block">Diterima oleh Karyawan,</span>
                <div class="border-b border-slate-400 w-48"></div>
                <strong class="text-slate-900 block font-bold">({{ $employee->name }})</strong>
            </div>
        </div>
    </div>
</div>
@endsection
