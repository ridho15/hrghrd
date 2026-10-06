@extends('layouts.app')
@section('title', 'Masuk')

@section('content')
<div class="max-w-5xl mx-auto my-4 sm:my-8 lg:my-10 bg-white border border-slate-200/90 rounded-3xl shadow-xl shadow-slate-900/5 overflow-hidden grid grid-cols-1 lg:grid-cols-12">
    {{-- Sisi Kiri: Form Autentikasi --}}
    <div class="col-span-12 lg:col-span-7 p-6 sm:p-10 lg:p-12 flex flex-col justify-between">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200/80 uppercase tracking-wider mb-4">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                <span>Portal Karyawan & HR</span>
            </div>
            
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-2">
                Selamat Datang Kembali
            </h1>
            <p class="text-sm text-slate-500 mb-8 leading-relaxed">
                Masuk ke portal HR Group untuk melihat jadwal shift, melakukan presensi lokasi, mengajukan izin kerja, dan mengelola penggajian.
            </p>

            <form method="post" action="{{ route('login') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Alamat Email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206"></path></svg>
                        </div>
                        <input 
                            id="email" 
                            type="email" 
                            name="email" 
                            value="{{ old('email') }}" 
                            autocomplete="username" 
                            required 
                            autofocus 
                            placeholder="nama@perusahaan.com"
                            class="w-full pl-12 pr-4 py-3 bg-slate-50/70 border border-slate-200 rounded-xl text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all text-sm font-medium"
                        >
                    </div>
                </div>

                <div>
                    <x-password-input 
                        name="password" 
                        id="password" 
                        label="Kata Sandi" 
                        placeholder="••••••••••••" 
                        required 
                        autocomplete="current-password"
                        size="lg"
                    />
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-emerald-700 via-emerald-700 to-emerald-600 hover:from-emerald-800 hover:to-emerald-700 text-white font-bold text-sm tracking-wide shadow-md shadow-emerald-700/20 transition-all flex items-center justify-center gap-2 group cursor-pointer">
                        <span>Masuk ke Akun</span>
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-100 flex items-center gap-2 text-xs text-slate-400 font-medium">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <span>Koneksi terenkripsi & validasi pengikatan perangkat (Device Binding) aktif</span>
        </div>
    </div>

    {{-- Sisi Kanan: Panel Showcase Korporat --}}
    <div class="col-span-12 lg:col-span-5 bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950 p-8 sm:p-10 text-white flex flex-col justify-between relative overflow-hidden">
        {{-- Ambient Glow Efek --}}
        <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>

        <div>
            <div class="flex items-center gap-2.5 mb-6">
                <span class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center justify-center font-bold text-sm">H</span>
                <span class="text-xs font-bold uppercase tracking-widest text-emerald-300">HR Group Ecosystem</span>
            </div>

            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-white mb-2 leading-snug">
                Satu Tempat untuk Seluruh Hari Kerja
            </h2>
            <p class="text-xs text-slate-300 leading-relaxed mb-8">
                Infrastruktur HR modern yang dirancang untuk keadilan, transparansi, dan efisiensi operasional menyeluruh.
            </p>

            <div class="space-y-4">
                <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-800/40 border border-slate-700/50">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-white mb-0.5">Presensi Multi-Faktor</h3>
                        <p class="text-[11px] text-slate-400 leading-relaxed m-0">Geofencing GPS, Dynamic QR 30 detik, dan validasi device fingerprint.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-800/40 border border-slate-700/50">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-white mb-0.5">Shift & Izin Berjenjang</h3>
                        <p class="text-[11px] text-slate-400 leading-relaxed m-0">Penjadwalan transparan dan verifikasi izin sakit berbasis surat dokter.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-800/40 border border-slate-700/50">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-white mb-0.5">Payroll Otomatis & Terkunci</h3>
                        <p class="text-[11px] text-slate-400 leading-relaxed m-0">Kalkulasi prorata, potongan terlambat, lembur resmi, dan riwayat audit.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-6 mt-6 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
            <span class="flex items-center gap-1.5 font-medium">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Server Jakarta Sinkron</span>
            </span>
            <span class="font-semibold text-slate-300">WIB (UTC+7)</span>
        </div>
    </div>
</div>
@endsection
