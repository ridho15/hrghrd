@extends('layouts.app')
@section('title', '404 · Halaman Tidak Ditemukan')

@section('content')
<div class="max-w-xl mx-auto py-12 px-4 sm:px-6 text-center">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-slate-100 border border-slate-200 text-slate-500 mb-6 shadow-xs">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </div>

    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black tracking-wider uppercase bg-slate-200 text-slate-800 border border-slate-300 mb-3">
        Kode Status 404 &middot; Tidak Ditemukan
    </div>

    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-3">
        Halaman Tidak Ditemukan
    </h1>

    <p class="text-sm text-slate-600 leading-relaxed max-w-md mx-auto mb-8 font-medium">
        {{ $exception?->getMessage() ?: 'Halaman, berkas, atau rekaman data yang Anda cari tidak tersedia, telah dihapus, atau alamat URL yang dituju salah ketik.' }}
    </p>

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
