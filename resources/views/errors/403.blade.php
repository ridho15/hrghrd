@extends('layouts.app')
@section('title', '403 · Akses Dibatasi')

@section('content')
<div class="max-w-xl mx-auto py-12 px-4 sm:px-6 text-center">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-amber-50 border border-amber-200 text-amber-600 mb-6 shadow-xs">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
        </svg>
    </div>

    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black tracking-wider uppercase bg-amber-100 text-amber-800 border border-amber-300 mb-3">
        Kode Status 403 &middot; Akses Dibatasi
    </div>

    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-3">
        Tindakan Tidak Diizinkan
    </h1>

    <p class="text-sm text-slate-600 leading-relaxed max-w-md mx-auto mb-8 font-medium">
        {{ $exception?->getMessage() ?: 'Akun Anda tidak memiliki wewenang atau hak akses yang memadai untuk membuka halaman atau memproses tindakan ini.' }}
    </p>

    <div class="p-4 rounded-2xl bg-white border border-slate-200/90 shadow-2xs text-left mb-8 space-y-2">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Petunjuk Operasional untuk Staf & Manajer</span>
        </div>
        <p class="text-xs text-slate-500 leading-relaxed m-0 font-medium">
            Tindakan ini dibatasi oleh kebijakan keamanan role dan cabang. Jika Anda memerlukan otorisasi untuk shift, persetujuan cuti, atau audit cabang ini, silakan koordinasikan dengan <strong>Super Admin Pusat</strong>.
        </p>
    </div>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="{{ route('home') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span>Kembali ke Beranda</span>
        </a>
        <button type="button" onclick="window.history.back()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-all cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            <span>Halaman Sebelumnya</span>
        </button>
    </div>
</div>
@endsection
