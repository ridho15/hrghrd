@extends('layouts.app')
@section('title', '409 · Konflik Status Operasi')

@section('content')
<div class="max-w-xl mx-auto py-12 px-4 sm:px-6 text-center">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-rose-50 border border-rose-200 text-rose-600 mb-6 shadow-xs">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
    </div>

    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black tracking-wider uppercase bg-rose-100 text-rose-800 border border-rose-300 mb-3">
        Kode Status 409 &middot; Konflik Status Transaksi
    </div>

    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-3">
        Operasi Terhalang Status Data
    </h1>

    <p class="text-sm text-slate-700 leading-relaxed max-w-md mx-auto mb-8 font-semibold bg-rose-50/80 p-3.5 rounded-xl border border-rose-200/80">
        {{ $exception?->getMessage() ?: 'Permintaan Anda bertentangan dengan status terkini pada sistem (misalnya: periode telah dikunci, shift sudah disetujui, atau jadwal bertumpang tindih).' }}
    </p>

    <div class="p-4 rounded-2xl bg-white border border-slate-200/90 shadow-2xs text-left mb-8 space-y-2">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Langkah Penyelesaian yang Disarankan:</span>
        </div>
        <ul class="text-xs text-slate-600 space-y-1.5 list-disc pl-4 font-medium m-0">
            <li><strong>Terkait Jadwal Shift:</strong> Jika jadwal sudah disetujui, batalkan jadwal tersebut terlebih dahulu sebelum mengajukan waktu baru.</li>
            <li><strong>Terkait Payroll:</strong> Pastikan seluruh tiket kendala absensi dan pengajuan cuti cabang pada bulan bersangkutan telah disetujui. Buat ulang draf jika terjadi pembaruan data.</li>
            <li><strong>Terkait Periode Terkunci:</strong> Data pada bulan pembukuan yang telah dikunci permanen tidak dapat diubah kembali demi integritas audit keuangan.</li>
        </ul>
    </div>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="{{ route('home') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span>Kembali ke Beranda</span>
        </a>
        <button type="button" onclick="window.history.back()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-all cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            <span>Kembali & Periksa Form</span>
        </button>
    </div>
</div>
@endsection
