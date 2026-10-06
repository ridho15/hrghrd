@extends('layouts.app')
@section('title', 'Tambah Karyawan Baru')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    {{-- Header Navigasi & Judul --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('people') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Kembali ke Direktori Karyawan</span>
                </a>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Pendaftaran Karyawan Baru
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Lengkapi identitas personel, hak akses peran, cabang penempatan, dan kompensasi kerja.
            </p>
        </div>
    </div>

    {{-- Formulir Tambah Karyawan --}}
    <form method="post" action="{{ route('people.store') }}" class="space-y-6">
        @csrf

        {{-- Kartu 1: Identitas Pribadi & Akun Login --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm border border-emerald-100">
                    1
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Identitas Pribadi & Akun</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Nama lengkap dan kredensial yang akan digunakan staf untuk masuk</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Lengkap Karyawan <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        name="name" 
                        value="{{ old('name') }}" 
                        required 
                        placeholder="Contoh: Budi Santoso" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('name')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Alamat Email Perusahaan <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        name="email" 
                        type="email" 
                        value="{{ old('email') }}" 
                        required 
                        placeholder="budi@perusahaan.com" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('email')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <x-password-input 
                        name="password" 
                        label="Kata Sandi Sementara (Minimal 10 Karakter)" 
                        minlength="10" 
                        required 
                        placeholder="••••••••••" 
                    />
                    <p class="text-xs text-slate-400 mt-1.5">
                        Gunakan kombinasi huruf, angka, atau simbol. Karyawan dapat memperbarui sandi ini secara mandiri nanti.
                    </p>
                </div>
            </div>
        </section>

        {{-- Kartu 2: Penempatan Kerja & Hak Akses Organisasi --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm border border-emerald-100">
                    2
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Penempatan Kerja & Peran</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Struktur penempatan outlet/kantor cabang dan tingkat wewenang sistem</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Tingkat Peran Akun <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        name="role" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-semibold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer"
                    >
                        <option value="employee" @selected(old('role') === 'employee')>Karyawan Biasa</option>
                        <option value="manager" @selected(old('role') === 'manager')>Manager Cabang</option>
                        <option value="admin" @selected(old('role') === 'admin')>Super Admin</option>
                    </select>
                </div>

                <div>
                    <x-searchable-select
                        name="branch_id"
                        id="person-create-branch"
                        label="Cabang Penempatan"
                        :options="$branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name, 'sublabel' => $b->code])"
                        placeholder="Pilih Cabang..."
                        searchPlaceholder="Cari nama atau kode cabang..."
                    />
                </div>

                <div>
                    <x-searchable-select
                        name="position_id"
                        id="person-create-position"
                        label="Jabatan Organisasi"
                        :options="$positions->map(fn($p) => ['value' => $p->id, 'label' => $p->name, 'sublabel' => 'ID #'.$p->id])"
                        placeholder="Pilih Jabatan..."
                        searchPlaceholder="Cari nama jabatan..."
                    />
                </div>
            </div>
        </section>

        {{-- Kartu 3: Kompensasi & Tanggal Kontrak Kerja --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm border border-emerald-100">
                    3
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Kompensasi & Masa Kerja</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Penetapan gaji pokok bulanan dan tanggal resmi mulai aktif bertugas</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Gaji Pokok Bulanan (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        name="base_salary" 
                        type="number" 
                        min="0" 
                        value="{{ old('base_salary', 3500000) }}" 
                        required 
                        placeholder="3500000" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-bold tabular-nums focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('base_salary')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Tanggal Mulai Bekerja <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        name="hired_at" 
                        type="date" 
                        value="{{ old('hired_at', now('Asia/Jakarta')->toDateString()) }}" 
                        required 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('hired_at')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Tanggal Berakhir (Opsional)
                    </label>
                    <input 
                        name="ended_at" 
                        type="date" 
                        value="{{ old('ended_at') }}" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('ended_at')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- Tombol Tindakan Formulir --}}
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
            <a href="{{ route('people') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold shadow-xs transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold shadow-sm transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Simpan Karyawan Baru</span>
            </button>
        </div>
    </form>
</div>
@endsection
