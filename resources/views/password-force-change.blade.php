@extends('layouts.app')
@section('title', 'Pembaruan Kata Sandi Wajib')

@section('content')
<div class="max-w-2xl mx-auto my-6 sm:my-12 px-4 sm:px-0">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
        {{-- Header Kartu --}}
        <div class="p-6 sm:p-8 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border-b border-amber-100">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-bold shadow-sm shadow-amber-500/30">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </span>
                <div>
                    <span class="inline-block text-[10px] font-bold uppercase tracking-wider text-amber-700 bg-amber-100/80 px-2 py-0.5 rounded-md mb-0.5">
                        Keamanan Akun Karyawan
                    </span>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                        Wajib Perbarui Kata Sandi
                    </h1>
                </div>
            </div>

            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                Kata sandi akun Anda baru saja di-reset oleh administrator ke default (<strong class="font-mono text-slate-800">12345678</strong>). Demi menjaga kerahasiaan dan keamanan data, silakan buat kata sandi baru pribadi Anda sebelum dapat melanjutkan ke sistem.
            </p>
        </div>

        {{-- Info Profil Singkat --}}
        <div class="px-6 sm:px-8 py-4 bg-slate-50/70 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs">
                    {{ Str::substr($user->name, 0, 2) }}
                </div>
                <div>
                    <strong class="text-slate-900 block font-bold">{{ $user->name }}</strong>
                    <span class="text-slate-400 text-[11px]">{{ $user->email }} @if($user->username)&middot; &#64;{{ $user->username }}@endif</span>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Penempatan</span>
                <span class="font-semibold text-slate-700">{{ $user->branch?->name ?? 'Semua Cabang / Pusat' }}</span>
            </div>
        </div>

        {{-- Formulir Ganti Password --}}
        <div class="p-6 sm:p-8">
            <form method="POST" action="{{ route('password.force_change.update') }}" class="space-y-5">
                @csrf

                <div>
                    <x-password-input 
                        name="current_password" 
                        id="current_password" 
                        label="Kata Sandi Saat Ini / Default" 
                        placeholder="Masukkan kata sandi reset (12345678)" 
                        required 
                        autocomplete="current-password"
                        size="lg"
                    />
                    <p class="mt-1 text-[11px] text-slate-400">Masukkan kata sandi sementara yang diberikan oleh HRD/Admin (misal: 12345678).</p>
                </div>

                <div>
                    <x-password-input 
                        name="password" 
                        id="password" 
                        label="Kata Sandi Baru" 
                        placeholder="Minimal 8 karakter baru" 
                        required 
                        minlength="8"
                        autocomplete="new-password"
                        size="lg"
                    />
                    <p class="mt-1 text-[11px] text-slate-400">Gunakan kombinasi minimal 8 karakter. Tidak boleh sama dengan kata sandi default.</p>
                </div>

                <div>
                    <x-password-input 
                        name="password_confirmation" 
                        id="password_confirmation" 
                        label="Konfirmasi Kata Sandi Baru" 
                        placeholder="Ulangi kata sandi baru di atas" 
                        required 
                        minlength="8"
                        autocomplete="new-password"
                        size="lg"
                    />
                </div>

                <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100">
                    <button type="submit" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase tracking-wider transition-colors shadow-xs flex items-center justify-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>Simpan & Lanjutkan ke Dashboard</span>
                    </button>
                    
                    <button type="submit" form="force-logout-form" class="w-full sm:w-auto px-4 py-3 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 text-xs font-semibold transition-colors cursor-pointer text-center block">
                        Keluar dari Akun
                    </button>
                </div>
            </form>

            <form id="force-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
                @csrf
            </form>
        </div>
    </div>
</div>
@endsection
