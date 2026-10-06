@extends('layouts.app')
@section('title', 'Ubah Cabang: ' . $branch->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('branches.index') }}" class="hover:text-emerald-700 transition-colors">Master Cabang</a>
        <span>/</span>
        <a href="{{ route('branches.show', $branch->id) }}" class="hover:text-emerald-700 transition-colors">{{ $branch->name }}</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Ubah Cabang</span>
    </nav>

    {{-- Header Halaman & Tombol Kembali --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-blue-700 bg-blue-50 px-2.5 py-1 rounded-md mb-2 border border-blue-200/60">
                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <span>Perubahan Data Lokasi</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Ubah Data Cabang
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Perbarui nama cabang, titik koordinat GPS presensi, dan batas radius geofence karyawan.
            </p>
        </div>

        <a
            href="{{ route('branches.show', $branch->id) }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider shadow-2xs transition-all self-start sm:self-auto"
        >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            <span>Kembali ke Detail</span>
        </a>
    </div>

    {{-- Formulir Ubah Cabang --}}
    <form method="POST" action="{{ route('branches.update', $branch->id) }}" class="space-y-6">
        @csrf

        {{-- KARTU 1: Identitas Cabang --}}
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                    1
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Identitas Lokasi & Kantor</h2>
                    <p class="text-xs text-slate-500">Nama resmi cabang dan kode identifikasi permanen.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                        Kode Cabang (Permanen)
                    </label>
                    <input
                        type="text"
                        disabled
                        value="{{ $branch->code }}"
                        class="w-full h-11 px-3.5 bg-slate-100 border border-slate-200 rounded-xl text-xs font-mono font-bold uppercase tracking-wider text-slate-500 cursor-not-allowed"
                    >
                    <span class="block text-[11px] text-slate-400 mt-1">Kode cabang terkunci untuk integritas audit.</span>
                </div>

                <div class="sm:col-span-2">
                    <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Nama Cabang / Outlet <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        required
                        maxlength="100"
                        value="{{ old('name', $branch->name) }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('name') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('name')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- KARTU 2: Koordinat GPS & Batas Geofence Presensi --}}
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                    2
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Koordinat GPS & Radius Presensi (Geofence)</h2>
                    <p class="text-xs text-slate-500">Titik lokasi presensi dan batas toleransi jarak meter.</p>
                </div>
            </div>

            {{-- Bantuan Praktis Ramah Awam --}}
            <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200/70 text-xs text-amber-900 space-y-1.5">
                <div class="flex items-center gap-2 font-bold text-amber-950">
                    <svg class="w-4 h-4 text-amber-700 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Petunjuk Mudah Koordinat:</span>
                </div>
                <p class="text-[11px] text-amber-800">
                    Buka <a href="https://maps.google.com/?q={{ $branch->latitude }},{{ $branch->longitude }}" target="_blank" class="underline font-bold hover:text-amber-950">Lokasi Saat Ini di Google Maps ↗</a> untuk memastikan titik koordinat tepat berada di lokasi fisik toko/kantor Anda.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label for="latitude" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Titik Lintang (Latitude) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="number"
                        step="any"
                        name="latitude"
                        id="latitude"
                        required
                        value="{{ old('latitude', $branch->latitude) }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('latitude') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('latitude')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                    <span class="block text-[11px] text-slate-400 mt-1">Antara -90.0 s.d 90.0</span>
                </div>

                <div>
                    <label for="longitude" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Titik Bujur (Longitude) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="number"
                        step="any"
                        name="longitude"
                        id="longitude"
                        required
                        value="{{ old('longitude', $branch->longitude) }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('longitude') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('longitude')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                    <span class="block text-[11px] text-slate-400 mt-1">Antara -180.0 s.d 180.0</span>
                </div>

                <div>
                    <label for="radius_m" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Batas Radius (Meter) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="number"
                            name="radius_m"
                            id="radius_m"
                            required
                            min="20"
                            max="1000"
                            value="{{ old('radius_m', $branch->radius_m) }}"
                            class="w-full h-11 px-3.5 pr-14 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('radius_m') border-rose-300 bg-rose-50/50 @enderror"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs text-slate-400 font-semibold">
                            Meter
                        </div>
                    </div>
                    @error('radius_m')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                    <span class="block text-[11px] text-slate-400 mt-1">Jarak toleransi absensi HP karyawan.</span>
                </div>
            </div>
        </div>

        {{-- Footer Tombol Aksi --}}
        <div class="flex items-center justify-between p-4 bg-white rounded-2xl border border-slate-200/90 shadow-2xs">
            <a
                href="{{ route('branches.show', $branch->id) }}"
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
                <span>Simpan Perubahan Cabang</span>
            </button>
        </div>
    </form>
</div>
@endsection
