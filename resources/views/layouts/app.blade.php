<!doctype html>
<html lang="id" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#047857">
    <title>@yield('title', 'HR Group') · HR Group Enterprise</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-800 font-sans selection:bg-emerald-500 selection:text-white">

@guest
    {{-- Tampilan Publik / Tamu (Login) --}}
    <div class="min-h-screen flex flex-col justify-between bg-slate-50">
        <header class="py-5 px-6 border-b border-slate-200/80 bg-white/80 backdrop-blur-md sticky top-0 z-20">
            <div class="max-w-6xl mx-auto flex items-center justify-between">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 group">
                    <span class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-800 via-emerald-700 to-emerald-500 flex items-center justify-center text-white font-extrabold text-base shadow-sm shadow-emerald-700/30">H</span>
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
            @if(session('ok'))
                <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-900 flex items-start gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div class="text-sm font-medium">{{ session('ok') }}</div>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-xl bg-rose-50 border border-rose-200 p-4 text-rose-900 flex items-start gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div class="text-sm">
                        <strong class="font-semibold block mb-1">Periksa kembali masukan Anda:</strong>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

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

        {{-- Sidebar Navigasi Desktop & Mobile Drawer --}}
        <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-900 text-slate-300 flex flex-col justify-between shrink-0 transform -translate-x-full lg:translate-x-0 lg:static transition-transform duration-200 ease-in-out border-r border-slate-800 shadow-xl lg:shadow-none">
            {{-- Bagian Atas: Logo Brand --}}
            <div>
                <div class="p-5 border-b border-slate-800 flex items-center justify-between">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-800 via-emerald-700 to-emerald-500 flex items-center justify-center text-white font-extrabold text-lg shadow-md shadow-emerald-500/20">H</span>
                        <div>
                            <span class="text-base font-extrabold text-white tracking-tight block leading-tight">HR Group</span>
                            <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest block">Enterprise HRIS</span>
                        </div>
                    </a>
                    <button id="close-mobile-sidebar" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800" aria-label="Tutup menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                {{-- Daftar Menu Navigasi --}}
                <nav class="p-3.5 space-y-6 overflow-y-auto max-h-[calc(100vh-12rem)]" aria-label="Menu navigasi">
                    {{-- Grup 1: Operasional Utama --}}
                    <div>
                        <div class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">Operasional</div>
                        <div class="space-y-1">
                            <a href="{{ route('home') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('home') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                                <span>Beranda</span>
                            </a>
                            <a href="{{ route('leave') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('leave*') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span>Izin & Sakit</span>
                            </a>
                        </div>
                    </div>

                    {{-- Grup 2: Manajemen Operasi (Admin & Manager) --}}
                    @if(in_array(auth()->user()->role, ['admin', 'manager']))
                        <div>
                            <div class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">Manajemen Operasi</div>
                            <div class="space-y-1">
                                <a href="{{ route('shifts') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('shifts*') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Jadwal Shift</span>
                                </a>
                                <a href="{{ route('attendance.review') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('attendance.review') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                    <span>Tinjau Absensi</span>
                                </a>
                                <a href="{{ route('qr.page') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('qr.*') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                    <span>QR Cabang</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    {{-- Grup 3: Administrasi & Finansial (Super Admin) --}}
                    @if(auth()->user()->role === 'admin')
                        <div>
                            <div class="px-3 mb-2 text-[11px] font-bold tracking-wider text-slate-400 uppercase">Administrasi & Finansial</div>
                            <div class="space-y-1">
                                <a href="{{ route('people') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('people*') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span>Karyawan & Cabang</span>
                                </a>
                                <a href="{{ route('import') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('import*') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                    <span>Impor Data</span>
                                </a>
                                <a href="{{ route('payroll.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('payroll*') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Payroll</span>
                                </a>
                                <a href="{{ route('settings') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('settings*') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <span>Aturan & Kebijakan</span>
                                </a>
                                <a href="{{ route('audit') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('audit') ? 'bg-emerald-500/15 text-emerald-300 border-l-2 border-emerald-400' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span>Jejak Audit</span>
                                </a>
                            </div>
                        </div>
                    @endif
                </nav>
            </div>

            {{-- Bagian Bawah: Profil Pengguna & Keluar --}}
            <div class="p-3.5 border-t border-slate-800 bg-slate-950/40">
                <div class="flex items-center justify-between p-2 rounded-xl bg-slate-800/60 border border-slate-700/60 mb-2">
                    <div class="flex items-center gap-2.5 overflow-hidden">
                        <div class="w-9 h-9 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold text-sm shrink-0 border border-emerald-500/30">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <div class="truncate">
                            <span class="block text-xs font-semibold text-white truncate">{{ auth()->user()->name }}</span>
                            @php
                                $roleBadge = [
                                    'admin' => ['label' => 'Super Admin', 'color' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'],
                                    'manager' => ['label' => 'Manager', 'color' => 'bg-amber-500/20 text-amber-300 border-amber-500/30'],
                                    'employee' => ['label' => 'Karyawan', 'color' => 'bg-sky-500/20 text-sky-300 border-sky-500/30']
                                ][auth()->user()->role] ?? ['label' => auth()->user()->role, 'color' => 'bg-slate-700 text-slate-300 border-slate-600'];
                            @endphp
                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $roleBadge['color'] }}">
                                {{ $roleBadge['label'] }}
                            </span>
                        </div>
                    </div>
                </div>

                <form method="post" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-semibold text-rose-300 hover:text-white hover:bg-rose-900/40 transition-colors border border-rose-800/30">
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

                    <div class="flex items-center gap-3">
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
                    {{-- Flash Notifikasi Sukses --}}
                    @if(session('ok'))
                        <div id="flash-success" class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200/80 p-4 text-emerald-900 flex items-start justify-between gap-3 shadow-xs transition-all duration-200" role="status">
                            <div class="flex items-start gap-3">
                                <span class="w-7 h-7 rounded-lg bg-emerald-500 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                </span>
                                <div>
                                    <h4 class="text-xs font-bold text-emerald-800 uppercase tracking-wider mb-0.5">Operasi Berhasil</h4>
                                    <p class="text-sm font-medium text-emerald-900 m-0">{{ session('ok') }}</p>
                                </div>
                            </div>
                            <button onclick="document.getElementById('flash-success').remove()" class="p-1 text-emerald-700 hover:text-emerald-950 rounded-lg hover:bg-emerald-100/60" aria-label="Tutup">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    @endif

                    {{-- Flash Notifikasi Error / Validasi Form --}}
                    @if($errors->any())
                        <div id="flash-errors" class="mb-6 rounded-2xl bg-rose-50 border border-rose-200/80 p-4 text-rose-900 flex items-start justify-between gap-3 shadow-xs" role="alert">
                            <div class="flex items-start gap-3">
                                <span class="w-7 h-7 rounded-lg bg-rose-500 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </span>
                                <div>
                                    <h4 class="text-xs font-bold text-rose-800 uppercase tracking-wider mb-1">Periksa Kembali Masukan:</h4>
                                    <ul class="text-sm space-y-1 list-disc pl-5 m-0 text-rose-950 font-medium">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <button onclick="document.getElementById('flash-errors').remove()" class="p-1 text-rose-700 hover:text-rose-950 rounded-lg hover:bg-rose-100/60" aria-label="Tutup">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>

            {{-- Footer Area Dashboard --}}
            <footer class="py-4 px-6 border-t border-slate-200/80 bg-white/50 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
                <span>&copy; {{ date('Y') }} HR Group &middot; Enterprise HRIS & Payroll System</span>
                <span class="text-slate-400">Sinkronisasi Waktu Server Asia/Jakarta (WIB) &middot; Multi-Factor Anti-Fraud</span>
            </footer>
        </div>
    </div>
@endauth

</body>
</html>
