@extends('layouts.app')
@section('title', 'Karyawan')

@section('content')
<div class="space-y-8">
    {{-- Header Administrasi & Aksi Cepat --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span>Administrasi Organisasi</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Karyawan & Lokasi Cabang
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola hak akses akun, struktur jabatan, gaji dasar, dan radius geofence titik presensi cabang.
            </p>
        </div>

        <div>
            <a href="{{ route('import') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-900 text-sm font-bold border border-emerald-200/80 transition-colors shadow-xs">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                <span>Impor Excel / CSV</span>
            </a>
        </div>
    </div>

    {{-- Grid: Form Tambah Cabang & Form Tambah Akun Karyawan --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        {{-- Card: Tambah Cabang Baru --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Tambah Cabang Baru</h2>
            </div>

            <form method="post" action="{{ route('branches.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Kode Cabang</label>
                    <input name="code" required placeholder="Contoh: JKT01" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-mono font-bold uppercase focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Cabang</label>
                    <input name="name" required placeholder="Kantor Pusat Jakarta" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Lintang (Latitude)</label>
                    <input name="latitude" type="number" step="any" required placeholder="-6.200000" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-mono focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Bujur (Longitude)</label>
                    <input name="longitude" type="number" step="any" required placeholder="106.816666" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-mono focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Radius Geofence (Meter)</label>
                    <input name="radius_m" type="number" value="100" min="20" max="1000" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-semibold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div class="sm:col-span-2 pt-1">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition-colors cursor-pointer">
                        Simpan Cabang
                    </button>
                </div>
            </form>
            <p class="text-[11px] text-slate-400 m-0">Koordinat dan radius meter dipakai untuk validasi Haversine presensi otomatis.</p>
        </section>

        {{-- Card: Tambah Akun Karyawan Baru --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Tambah Akun Karyawan</h2>
            </div>

            <form method="post" action="{{ route('people.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Lengkap</label>
                    <input name="name" required placeholder="Budi Santoso" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Email Perusahaan</label>
                    <input name="email" type="email" required placeholder="budi@perusahaan.com" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div class="sm:col-span-2">
                    <x-password-input name="password" label="Sandi Sementara (Min 10 Karakter)" minlength="10" required placeholder="••••••••••" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Peran Akun</label>
                    <select name="role" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-semibold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                        <option value="employee">Karyawan</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Super Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Cabang Penempatan</label>
                    <select name="branch_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                        <option value="">Pilih Cabang</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Jabatan</label>
                    <select name="position_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                        <option value="">Pilih Jabatan</option>
                        @foreach($positions as $pos)
                            <option value="{{ $pos->id }}">{{ $pos->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tanggal Mulai Bekerja</label>
                    <input name="hired_at" type="date" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tanggal Akhir (Opsional)</label>
                    <input name="ended_at" type="date" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Gaji Pokok Bulanan (Rp)</label>
                    <input name="base_salary" type="number" min="0" required placeholder="3000000" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-bold tabular-nums focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <div class="sm:col-span-2 pt-1">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition-colors cursor-pointer">
                        Buat Akun Karyawan
                    </button>
                </div>
            </form>
        </section>
    </div>

    {{-- Sub-Grid: Master Jabatan & Titik Lokasi Cabang --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Master Jabatan --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-3">
            <h3 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100 m-0">Tambah Jabatan</h3>
            <form method="post" action="{{ route('positions.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Jabatan</label>
                    <input name="name" required maxlength="100" placeholder="Contoh: Barista, Kasir..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>
                <button type="submit" class="w-full py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors cursor-pointer border border-slate-200">
                    Tambah Jabatan
                </button>
            </form>
        </section>

        {{-- Titik Lokasi Cabang & Pembaruan Koordinat Geofence --}}
        <section class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-3">
            <h3 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100 m-0">Pengaturan Titik Cabang (Geofencing)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($branches as $b)
                    <details class="group p-3 rounded-xl border border-slate-200 bg-slate-50/50">
                        <summary class="flex items-center justify-between cursor-pointer list-none">
                            <div>
                                <strong class="text-xs font-bold text-slate-900 block">{{ $b->name }}</strong>
                                <span class="text-[10px] text-slate-400 font-mono">{{ $b->code }} &middot; R: {{ $b->radius_m }}m</span>
                            </div>
                            <span class="text-xs text-emerald-700 font-bold group-open:rotate-180 transition-transform">▼</span>
                        </summary>

                        <form method="post" action="{{ route('branches.update', $b->id) }}" class="mt-3 pt-3 border-t border-slate-200 space-y-2.5">
                            @csrf
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase">Nama Cabang</label>
                                <input name="name" value="{{ $b->name }}" required class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs font-medium">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase">Lintang</label>
                                    <input type="number" step="any" name="latitude" value="{{ $b->latitude }}" required class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase">Bujur</label>
                                    <input type="number" step="any" name="longitude" value="{{ $b->longitude }}" required class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs font-mono">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase">Radius (Meter)</label>
                                <input type="number" name="radius_m" value="{{ $b->radius_m }}" min="20" max="1000" required class="w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs font-semibold">
                            </div>
                            <button type="submit" class="w-full py-1.5 rounded bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition-colors cursor-pointer">
                                Simpan Perubahan Cabang
                            </button>
                        </form>
                    </details>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Tabel Direktori Karyawan Utama --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden space-y-0">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Direktori Karyawan Terdaftar</h2>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                {{ $people->total() }} Akun
            </span>
        </div>

        {{-- Toolbar Pencarian & Filter Dinamis --}}
        <x-table-toolbar
            :action="route('people')"
            searchPlaceholder="Cari nama atau email karyawan..."
            :searchValue="request('search')"
            :resetUrl="route('people')"
        >
            <x-slot:filters>
                {{-- Filter Cabang --}}
                <select name="branch_id" class="h-9 px-3 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                    <option value="">Semua Cabang</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>

                {{-- Filter Peran --}}
                <select name="role" class="h-9 px-3 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                    <option value="">Semua Peran</option>
                    <option value="admin" @selected(request('role') === 'admin')>Super Admin</option>
                    <option value="manager" @selected(request('role') === 'manager')>Manager</option>
                    <option value="employee" @selected(request('role') === 'employee')>Karyawan</option>
                </select>

                {{-- Filter Status --}}
                <select name="status" class="h-9 px-3 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                    <option value="">Semua Status</option>
                    <option value="1" @selected(request('status') === '1')>Aktif</option>
                    <option value="0" @selected(request('status') === '0')>Nonaktif</option>
                </select>
            </x-slot:filters>
        </x-table-toolbar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">Nama & Email</th>
                        <th class="py-3.5 px-4">Cabang / Jabatan</th>
                        <th class="py-3.5 px-4">Peran</th>
                        <th class="py-3.5 px-4">Gaji Pokok</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Kelola</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($people as $p)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Nama & Avatar --}}
                            <td class="py-4 px-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-800 font-extrabold flex items-center justify-center shrink-0 border border-emerald-200/70 text-xs">
                                        {{ strtoupper(substr($p->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <strong class="font-bold text-slate-900 block">{{ $p->name }}</strong>
                                        <span class="text-xs text-slate-400 block">{{ $p->email }}</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Cabang & Jabatan --}}
                            <td class="py-4 px-4">
                                <span class="font-medium text-slate-800 block">{{ $p->branch_name ?? '—' }}</span>
                                <span class="text-xs text-slate-500 block">{{ $p->position_name ?? '—' }}</span>
                            </td>

                            {{-- Peran --}}
                            <td class="py-4 px-4">
                                @php
                                    $roleBadge = [
                                        'admin' => ['label' => 'Super Admin', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                        'manager' => ['label' => 'Manager', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                                        'employee' => ['label' => 'Karyawan', 'class' => 'bg-sky-50 text-sky-700 border-sky-200']
                                    ][$p->role] ?? ['label' => $p->role, 'class' => 'bg-slate-100 text-slate-600 border-slate-200'];
                                @endphp
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $roleBadge['class'] }}">
                                    {{ $roleBadge['label'] }}
                                </span>
                            </td>

                            {{-- Gaji Bulanan --}}
                            <td class="py-4 px-4 font-bold text-slate-900 tabular-nums">
                                Rp{{ number_format($p->base_salary, 0, ',', '.') }}
                            </td>

                            {{-- Status Aktif/Nonaktif --}}
                            <td class="py-4 px-4">
                                @if($p->active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>Aktif</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        <span>Nonaktif</span>
                                    </span>
                                @endif
                            </td>

                            {{-- Kelola / Edit Panel --}}
                            <td class="py-4 px-4 sm:px-6 text-right">
                                <details class="group relative">
                                    <summary class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer list-none inline-flex items-center gap-1">
                                        <span>Ubah</span>
                                        <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </summary>

                                    <div class="absolute right-0 top-full mt-2 w-80 sm:w-96 bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 z-20 text-left space-y-3.5">
                                        <div class="pb-2 border-b border-slate-100">
                                            <strong class="text-xs font-bold text-slate-900 block">Edit Data Karyawan</strong>
                                            <span class="text-[10px] text-slate-400">Pembaruan data langsung tersinkronisasi</span>
                                        </div>

                                        <form method="post" action="{{ route('people.update', $p->id) }}" class="space-y-3">
                                            @csrf
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Nama</label>
                                                    <input name="name" value="{{ $p->name }}" required class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Email</label>
                                                    <input type="email" name="email" value="{{ $p->email }}" required class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium">
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Peran</label>
                                                    <select name="role" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium cursor-pointer">
                                                        @foreach(['admin'=>'Super Admin','manager'=>'Manager','employee'=>'Karyawan'] as $role => $label)
                                                            <option value="{{ $role }}" @selected($p->role === $role)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Status</label>
                                                    <select name="active" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold cursor-pointer">
                                                        <option value="1" @selected($p->active)>Aktif</option>
                                                        <option value="0" @selected(!$p->active)>Nonaktif</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Cabang</label>
                                                    <select name="branch_id" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium cursor-pointer">
                                                        <option value="">Tanpa Cabang</option>
                                                        @foreach($branches as $b)
                                                            <option value="{{ $b->id }}" @selected($p->branch_id == $b->id)>{{ $b->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Jabatan</label>
                                                    <select name="position_id" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium cursor-pointer">
                                                        <option value="">Tanpa Jabatan</option>
                                                        @foreach($positions as $pos)
                                                            <option value="{{ $pos->id }}" @selected($p->position_id == $pos->id)>{{ $pos->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Mulai Kerja</label>
                                                    <input type="date" name="hired_at" value="{{ $p->hired_at }}" required class="w-full px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Akhir Kerja</label>
                                                    <input type="date" name="ended_at" value="{{ $p->ended_at }}" class="w-full px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium">
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-700 uppercase">Gaji Pokok Bulanan (Rp)</label>
                                                <input type="number" name="base_salary" min="0" value="{{ $p->base_salary }}" required class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold tabular-nums">
                                            </div>

                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Sandi Baru (Opsional)</label>
                                                <x-password-input name="password" minlength="10" autocomplete="new-password" placeholder="Biarkan kosong jika tidak diubah" />
                                            </div>

                                            <div class="pt-1">
                                                <label class="flex items-center gap-2 text-xs font-bold text-amber-800 bg-amber-50 p-2 rounded-lg border border-amber-200/70 cursor-pointer">
                                                    <input type="checkbox" name="reset_device" value="1" class="rounded text-emerald-600 focus:ring-emerald-500">
                                                    <span>Reset Pengikatan Perangkat (Device Binding)</span>
                                                </label>
                                            </div>

                                            <button type="submit" class="w-full py-2 px-3 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                                Simpan Pembaruan Data
                                            </button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 px-4 text-center">
                                <div class="max-w-xs mx-auto text-center space-y-2">
                                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto text-xl font-bold">
                                        👥
                                    </div>
                                    <p class="text-sm font-bold text-slate-800">Tidak ada data karyawan ditemukan</p>
                                    <p class="text-xs text-slate-500">Coba ubah kata kunci pencarian atau sesuaikan filter cabang, jabatan, dan status.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$people" />
    </section>
</div>
@endsection
