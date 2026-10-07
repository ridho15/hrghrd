<!doctype html>
<html lang="id" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#047857">
    <title>@yield('title', 'HR Group') · HR Group Enterprise</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full text-slate-800 font-sans selection:bg-emerald-500 selection:text-white">

    {{-- Floating Toast Notification Container (Global) --}}
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-3 max-w-sm sm:max-w-md w-full pointer-events-none px-4 sm:px-0" aria-live="polite">
        {{-- Flash Notifikasi Sukses --}}
        @if(session('ok'))
            <div id="flash-success" role="status" class="toast-item pointer-events-auto relative overflow-hidden rounded-2xl bg-white/95 backdrop-blur-md border border-emerald-200/90 p-4 text-emerald-950 shadow-xl shadow-emerald-950/10 flex items-start justify-between gap-3 transform transition-all duration-300 translate-x-0 opacity-100" data-toast-type="success" data-duration="5000">
                <div class="flex items-start gap-3">
                    <span class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-xs shadow-emerald-500/30">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    </span>
                    <div class="space-y-0.5">
                        <h4 class="text-xs font-bold text-emerald-800 uppercase tracking-wider m-0">Operasi Berhasil</h4>
                        <p class="text-xs font-medium text-slate-700 m-0 leading-relaxed">{{ session('ok') }}</p>
                    </div>
                </div>
                <button type="button" data-toast-close class="p-1 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer shrink-0" aria-label="Tutup notifikasi">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
                <div class="toast-progress absolute bottom-0 left-0 h-1 bg-emerald-600 w-full transition-all duration-100"></div>
            </div>
        @endif

        {{-- Flash Notifikasi Error / Validasi Form --}}
        @if($errors->any())
            <div id="flash-errors" role="alert" class="toast-item pointer-events-auto relative overflow-hidden rounded-2xl bg-white/95 backdrop-blur-md border border-rose-200/90 p-4 text-rose-950 shadow-xl shadow-rose-950/10 flex items-start justify-between gap-3 transform transition-all duration-300 translate-x-0 opacity-100" data-toast-type="error" data-duration="7000">
                <div class="flex items-start gap-3">
                    <span class="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-xs shadow-rose-500/30">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </span>
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold text-rose-800 uppercase tracking-wider m-0">Periksa Kembali Masukan Anda</h4>
                        <ul class="text-xs text-slate-700 space-y-0.5 list-disc pl-4 m-0 font-medium">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <button type="button" data-toast-close class="p-1 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer shrink-0" aria-label="Tutup notifikasi">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
                <div class="toast-progress absolute bottom-0 left-0 h-1 bg-rose-600 w-full transition-all duration-100"></div>
            </div>
        @endif
    </div>

@guest
    {{-- Tampilan Publik / Tamu (Login) --}}
    <div class="min-h-screen flex flex-col justify-between bg-slate-50">
        <header class="py-5 px-6 border-b border-slate-200/80 bg-white/80 backdrop-blur-md sticky top-0 z-20">
            <div class="max-w-6xl mx-auto flex items-center justify-between">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 group">
                    <span class="w-9 h-9 rounded-xl bg-emerald-700 flex items-center justify-center text-white font-extrabold text-base shadow-xs">H</span>
                    <div>
                        <span class="text-base font-bold text-slate-900 tracking-tight block leading-tight">HR Group</span>
                        <span class="text-[11px] font-semibold text-emerald-700 uppercase tracking-widest block">Enterprise HRIS</span>
                    </div>
                </a>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500 bg-slate-100/80 px-3 py-1.5 rounded-full border border-slate-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>WIB (UTC+7) Jakarta</span>
                </div>
            </div>
        </header>

        <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-6 lg:p-8">
            @yield('content')
        </main>

        <footer class="py-5 border-t border-slate-200 text-center text-xs text-slate-500">
            HR Group Enterprise HRIS &middot; Sistem presensi dan kalkulasi waktu berstandar Asia/Jakarta
        </footer>
    </div>
@endguest

@auth
    {{-- Tampilan Aplikasi Lengkap (Sidebar + Topbar + Content) --}}
    <div class="min-h-screen flex bg-slate-50">
        {{-- Mobile Overlay Backdrop --}}
        <div id="mobile-sidebar-backdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-40 hidden lg:hidden transition-opacity duration-200"></div>

        {{-- Sidebar Navigasi Desktop & Mobile Drawer (Light Theme Clean Enterprise) --}}
        <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white text-slate-700 flex flex-col justify-between shrink-0 transform -translate-x-full lg:translate-x-0 lg:static transition-transform duration-200 ease-in-out border-r border-slate-200/90 shadow-xl lg:shadow-none">
            {{-- Bagian Atas: Logo Brand --}}
            <div>
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-emerald-700 flex items-center justify-center text-white font-extrabold text-lg shadow-xs">H</span>
                        <div>
                            <span class="text-base font-extrabold text-slate-900 tracking-tight block leading-tight">HR Group</span>
                            <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-widest block">Enterprise HRIS</span>
                        </div>
                    </a>
                    <button id="close-mobile-sidebar" class="lg:hidden p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer" aria-label="Tutup menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                {{-- Daftar Menu Navigasi --}}
                <nav class="p-3.5 space-y-6 overflow-y-auto max-h-[calc(100vh-12rem)]" aria-label="Menu navigasi">
                    {{-- Grup 1: Operasional Utama --}}
                    <div>
                        <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Operasional</div>
                        <div class="space-y-1">
                            <a href="{{ route('home') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('home') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                                <span>Beranda</span>
                            </a>
                            <a href="{{ route('leave') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('leave*') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span>Izin & Sakit</span>
                            </a>
                        </div>
                    </div>

                    {{-- Grup 2: Manajemen Operasi (Admin & Manager) --}}
                    @if(in_array(auth()->user()->role, ['admin', 'manager']))
                        <div>
                            <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Manajemen Operasi</div>
                            <div class="space-y-1">
                                <a href="{{ route('shifts') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('shifts*') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Jadwal Shift</span>
                                </a>
                                <a href="{{ route('attendance.review') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('attendance.review') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                    <span>Tinjau Absensi</span>
                                </a>
                                <a href="{{ route('qr.page') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('qr.*') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                    <span>QR Cabang</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    {{-- Grup 3: Administrasi & Finansial (Super Admin) --}}
                    @if(auth()->user()->role === 'admin')
                        <div>
                            <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Administrasi & Finansial</div>
                            <div class="space-y-1">
                                <a href="{{ route('people') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('people*') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    <span>Karyawan</span>
                                </a>
                                <a href="{{ route('branches.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('branches*') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    <span>Cabang</span>
                                </a>
                                <a href="{{ route('positions.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('positions*') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    <span>Jabatan</span>
                                </a>

                                <a href="{{ route('import') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('import*') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    <span>Impor Data</span>
                                </a>
                                <a href="{{ route('payroll.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('payroll*') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Payroll</span>
                                </a>
                                <a href="{{ route('settings') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('settings*') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <span>Aturan & Kebijakan</span>
                                </a>
                                <a href="{{ route('audit') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ request()->routeIs('audit') ? 'bg-emerald-50 text-emerald-800 border-l-2 border-emerald-600 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span>Jejak Audit</span>
                                </a>
                            </div>
                        </div>
                    @endif
                </nav>
            </div>

            {{-- Bagian Bawah: Profil Pengguna & Keluar --}}
            <div class="p-3.5 border-t border-slate-200/80 bg-slate-50/80">
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-slate-200 mb-2.5 shadow-2xs">
                    <div class="flex items-center gap-2.5 overflow-hidden">
                        <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm shrink-0 border border-emerald-200">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <div class="truncate">
                            <span class="block text-xs font-bold text-slate-900 truncate">{{ auth()->user()->name }}</span>
                            @php
                                $roleBadge = [
                                    'admin' => ['label' => 'Super Admin', 'color' => 'bg-emerald-100 text-emerald-800 border-emerald-300'],
                                    'manager' => ['label' => 'Manager', 'color' => 'bg-amber-100 text-amber-800 border-amber-300'],
                                    'employee' => ['label' => 'Karyawan', 'color' => 'bg-sky-100 text-sky-800 border-sky-300']
                                ][auth()->user()->role] ?? ['label' => auth()->user()->role, 'color' => 'bg-slate-100 text-slate-700 border-slate-300'];
                            @endphp
                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $roleBadge['color'] }}">
                                {{ $roleBadge['label'] }}
                            </span>
                        </div>
                    </div>
                </div>

                <form method="post" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-semibold text-rose-700 bg-white hover:bg-rose-50 hover:border-rose-300 transition-colors border border-rose-200 cursor-pointer shadow-2xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        <span>Keluar Akun</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Area Konten Utama --}}
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            {{-- Header Top Bar Desktop & Mobile --}}
            <header class="bg-white border-b border-slate-200/80 sticky top-0 z-30 shadow-xs">
                <div class="px-4 sm:px-6 lg:px-8 min-h-[4rem] flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button id="open-mobile-sidebar" class="lg:hidden p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-hidden" aria-label="Buka navigasi">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        </button>

                        <div class="flex items-center gap-2 text-sm text-slate-500">
                            <span class="font-medium text-slate-400 hidden sm:inline">HR Group</span>
                            <span class="text-slate-300 hidden sm:inline">/</span>
                            <h1 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight m-0 leading-none">@yield('title', 'Beranda')</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5">
                        {{-- Tombol Bantuan Pintasan Keyboard --}}
                        <button
                            type="button"
                            data-open-modal="modal-keyboard-shortcuts"
                            class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold border border-slate-200/80 transition-colors cursor-pointer"
                            title="Panduan Pintasan Keyboard (Tekan ?)"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                            <span>Pintasan</span>
                            <kbd class="px-1 py-0.2 bg-white text-[10px] font-mono text-slate-500 rounded border border-slate-200 font-bold">?</kbd>
                        </button>

                        <div class="hidden sm:flex items-center gap-2 text-xs font-semibold text-slate-600 bg-slate-100/80 px-3 py-1.5 rounded-full border border-slate-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Asia/Jakarta (WIB)</span>
                        </div>
                        <span class="text-xs font-bold text-slate-700 bg-emerald-50 text-emerald-800 border border-emerald-200/60 px-2.5 py-1 rounded-lg">
                            {{ ['admin'=>'Super Admin','manager'=>'Manager','employee'=>'Karyawan'][auth()->user()->role] ?? '' }}
                        </span>
                    </div>
                </div>
            </header>

            {{-- Wadah Isi Halaman --}}
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <div class="max-w-7xl mx-auto">
                    @yield('content')
                </div>
            </main>

            {{-- Footer Area Dashboard --}}
            <footer class="py-4 px-6 border-t border-slate-200/80 bg-white/50 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span>&copy; {{ date('Y') }} HR Group &middot; Enterprise HRIS & Payroll System</span>
                    <span class="text-slate-300">&middot;</span>
                    <button type="button" data-open-modal="modal-keyboard-shortcuts" class="text-emerald-700 hover:text-emerald-800 font-semibold cursor-pointer inline-flex items-center gap-1">
                        <span>Pintasan Keyboard</span>
                        <kbd class="px-1 py-0.2 bg-slate-100 text-[10px] font-mono text-slate-500 rounded border border-slate-200">?</kbd>
                    </button>
                </div>
                <span class="text-slate-400">Sinkronisasi Waktu Server Asia/Jakarta (WIB) &middot; Multi-Factor Anti-Fraud</span>
            </footer>
        </div>
    </div>
@endauth

{{-- Modal Bantuan Pintasan Keyboard Interaktif --}}
<x-detail-modal
    id="modal-keyboard-shortcuts"
    title="Panduan Pintasan Keyboard"
    subtitle="Akses cepat navigasi & pencarian sistem HR Group"
    badge="Pintasan Cepat"
    badgeColor="bg-emerald-50 text-emerald-700 border-emerald-200"
    maxWidth="md"
>
    <div class="space-y-4 text-xs">
        <p class="text-slate-600 m-0">Gunakan tombol pintasan berikut untuk mempercepat navigasi dan pencarian data di seluruh modul:</p>
        
        <div class="rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-2.5 px-3.5">Tombol</th>
                        <th class="py-2.5 px-3.5">Fungsi / Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr>
                        <td class="py-2.5 px-3.5 whitespace-nowrap">
                            <kbd class="px-2 py-1 text-xs font-mono font-bold text-slate-800 bg-slate-100 border border-slate-200 rounded shadow-2xs">/</kbd>
                        </td>
                        <td class="py-2.5 px-3.5 text-slate-700 font-medium">
                            Fokus instan ke kolom pencarian data pada tabel aktif
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-3.5 whitespace-nowrap">
                            <kbd class="px-2 py-1 text-xs font-mono font-bold text-slate-800 bg-slate-100 border border-slate-200 rounded shadow-2xs">Esc</kbd>
                        </td>
                        <td class="py-2.5 px-3.5 text-slate-700 font-medium">
                            Tutup modal aktif, hilangkan fokus pencarian, atau dismiss notifikasi
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-3.5 whitespace-nowrap">
                            <kbd class="px-2 py-1 text-xs font-mono font-bold text-slate-800 bg-slate-100 border border-slate-200 rounded shadow-2xs">?</kbd> <span class="text-slate-400 text-[10px]">(Shift + /)</span>
                        </td>
                        <td class="py-2.5 px-3.5 text-slate-700 font-medium">
                            Buka / tutup jendela panduan pintasan keyboard ini
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200/80 text-[11px] text-emerald-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Pintasan tombol tidak akan terpicu ketika Anda sedang mengetik di dalam kolom isian form.</span>
        </div>
    </div>
</x-detail-modal>

{{-- Global Confirm Modal (Tindakan Penting / Destruktif) --}}
<x-confirm-modal id="global-confirm-modal" />

@stack('scripts')
</body>
</html>
