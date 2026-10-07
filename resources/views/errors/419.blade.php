@extends('layouts.app')
@section('title', '419 · Sesi Kedaluwarsa')

@section('content')
<div class="max-w-xl mx-auto py-12 px-4 sm:px-6 text-center">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-indigo-50 border border-indigo-200 text-indigo-600 mb-6 shadow-xs">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </div>

    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black tracking-wider uppercase bg-indigo-100 text-indigo-800 border border-indigo-300 mb-3">
        Kode Status 419 &middot; Sesi Kedaluwarsa
    </div>

    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-3">
        Sesi Keamanan Telah Kedaluwarsa
    </h1>

    <p class="text-sm text-slate-600 leading-relaxed max-w-md mx-auto mb-8 font-medium">
        Halaman telah terbuka terlalu lama tanpa aktivitas sehingga token keamanan formulir (CSRF) kedaluwarsa demi melindungi data akun Anda.
    </p>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <button type="button" onclick="window.location.reload()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            <span>Muat Ulang Halaman</span>
        </button>
        <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
            <span>Masuk Kembali</span>
        </a>
    </div>
</div>
@endsection
