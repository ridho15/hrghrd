@extends('layouts.app')
@section('title', 'Tambah Jabatan Baru')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('positions.index') }}" class="hover:text-emerald-700 transition-colors">Master Jabatan</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Tambah Jabatan Baru</span>
    </nav>

    {{-- Header Halaman & Tombol Kembali --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <span>Struktur Organisasi Perusahaan</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Tambah Jabatan Baru
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Daftarkan posisi atau jabatan fungsional baru untuk standarisasi peran kerja seluruh staf.
            </p>
        </div>

        <a
            href="{{ route('positions.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider shadow-2xs transition-all self-start sm:self-auto"
        >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            <span>Kembali ke Daftar</span>
        </a>
    </div>

    {{-- Formulir Tambah Jabatan --}}
    <form method="POST" action="{{ route('positions.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                    💼
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Nama Jabatan Fungsional</h2>
                    <p class="text-xs text-slate-500">Nama posisi resmi yang akan disematkan ke akun profil karyawan.</p>
                </div>
            </div>

            <div>
                <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                    Nama Jabatan <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    required
                    maxlength="100"
                    value="{{ old('name') }}"
                    placeholder="Contoh: Barista, Kasir, Supervisor Outlet, Staff Administrasi..."
                    class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('name') border-rose-300 bg-rose-50/50 @enderror"
                >
                @error('name')
                    <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Panduan Penamaan Ramah Awam --}}
            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-200/80 text-xs text-slate-600 space-y-1">
                <span class="font-bold text-slate-800 block">💡 Tips Penamaan Jabatan:</span>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Gunakan penamaan umum yang mudah dimengerti staf operasional. Satu jabatan dapat digunakan bersama oleh banyak karyawan di berbagai cabang (misal: jabatan <em>Kasir</em> dapat diemban oleh staf di Cabang Jakarta maupun Cabang Bandung).
                </p>
            </div>
        </div>

        {{-- Footer Tombol Aksi --}}
        <div class="flex items-center justify-between p-4 bg-white rounded-2xl border border-slate-200/90 shadow-2xs">
            <a
                href="{{ route('positions.index') }}"
                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold uppercase tracking-wider transition-colors"
            >
                Batal & Kembali
            </a>

            <button
                type="submit"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Simpan Jabatan Baru</span>
            </button>
        </div>
    </form>
</div>
@endsection
