@extends('layouts.app')
@section('title', '500 · Gangguan Internal Server')

@section('content')
<div class="max-w-xl mx-auto py-12 px-4 sm:px-6 text-center">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-rose-50 border border-rose-200 text-rose-600 mb-6 shadow-xs">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </div>

    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black tracking-wider uppercase bg-rose-100 text-rose-800 border border-rose-300 mb-3">
        Kode Status 500 &middot; Gangguan Sistem
    </div>

    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-3">
        Terjadi Kendala Internal Server
    </h1>

    <p class="text-sm text-slate-600 leading-relaxed max-w-md mx-auto mb-8 font-medium">
        Sistem sedang mengalami kendala teknis sementara saat memproses permintaan ini. Kejadian ini telah dicatat ke dalam log pemantauan.
    </p>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="{{ route('home') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span>Kembali ke Beranda</span>
        </a>
        <button type="button" onclick="window.location.reload()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-all cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            <span>Coba Lagi</span>
        </button>
    </div>
</div>
@endsection
