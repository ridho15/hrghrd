@extends('layouts.app')
@section('title', 'Ubah Data ' . $person->name)

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    {{-- Header Navigasi & Judul --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('people.show', $person->id) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Kembali ke Profil {{ $person->name }}</span>
                </a>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Ubah Data Karyawan
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Perbarui rincian identitas, penempatan cabang, jabatan, kompensasi, atau reset sandi akun.
            </p>
        </div>
    </div>

    {{-- Formulir Edit Karyawan --}}
    <form method="post" action="{{ route('people.update', $person->id) }}" class="space-y-6">
        @csrf

        {{-- Kartu 1: Identitas Pribadi & Akun Login --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm border border-emerald-100">
                    1
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Identitas Pribadi & Akun</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Nama lengkap dan email akun karyawan</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Lengkap Karyawan <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        name="name" 
                        value="{{ old('name', $person->name) }}" 
                        required 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('name')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Username Login (Opsional / Unik)
                    </label>
                    <input 
                        name="username" 
                        value="{{ old('username', $person->username) }}" 
                        placeholder="Contoh: budi.santoso (opsional)" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('username')
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
                        value="{{ old('email', $person->email) }}" 
                        required 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('email')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <x-password-input 
                        name="password" 
                        label="Ganti Kata Sandi (Kosongkan jika tidak ingin mengubah)" 
                        minlength="10" 
                        placeholder="Masukkan sandi baru jika ingin diubah..." 
                    />
                    <p class="text-xs text-slate-400 mt-1.5">
                        Biarkan isian sandi kosong jika karyawan tetap menggunakan kata sandi saat ini.
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
                    <p class="text-xs text-slate-400 mt-0.5">Penempatan cabang dan struktur jabatan resmi</p>
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
                        <option value="employee" @selected(old('role', $person->role) === 'employee')>Karyawan Biasa</option>
                        <option value="manager" @selected(old('role', $person->role) === 'manager')>Manager Cabang</option>
                        <option value="admin" @selected(old('role', $person->role) === 'admin')>Super Admin</option>
                    </select>
                </div>

                <div>
                    <x-searchable-select
                        name="branch_id"
                        id="person-edit-branch"
                        label="Cabang Penempatan"
                        :options="$branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name, 'sublabel' => $b->code])"
                        :selected="old('branch_id', $person->branch_id)"
                        placeholder="Pilih Cabang..."
                        searchPlaceholder="Cari nama atau kode cabang..."
                    />
                </div>

                <div>
                    <x-searchable-select
                        name="position_id"
                        id="person-edit-position"
                        label="Jabatan Organisasi"
                        :options="$positions->map(fn($p) => ['value' => $p->id, 'label' => $p->name, 'sublabel' => 'ID #'.$p->id])"
                        :selected="old('position_id', $person->position_id)"
                        placeholder="Pilih Jabatan..."
                        searchPlaceholder="Cari nama jabatan..."
                    />
                </div>
            </div>
        </section>

        {{-- Kartu 3: Kompensasi, Masa Kerja & Perangkat --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm border border-emerald-100">
                    3
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Kompensasi, Masa Kerja & Kunci Perangkat</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Pengaturan gaji, tanggal penugasan, dan pengikatan smartphone absensi</p>
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
                        value="{{ old('base_salary', $person->base_salary) }}" 
                        required 
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
                        value="{{ old('hired_at', $person->hired_at ? \Carbon\Carbon::parse($person->hired_at)->toDateString() : '') }}" 
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
                        value="{{ old('ended_at', $person->ended_at ? \Carbon\Carbon::parse($person->ended_at)->toDateString() : '') }}" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('ended_at')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-3 pt-2">
                    <label class="inline-flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-slate-100 cursor-pointer transition-colors w-full">
                        <input 
                            type="checkbox" 
                            name="reset_device" 
                            value="1" 
                            class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500"
                        >
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Reset Kunci HP Karyawan (Device Binding)</span>
                            <span class="block text-xs text-slate-500 mt-0.5">
                                Centang opsi ini jika karyawan mengganti smartphone atau kehilangan perangkat lamanya, agar dapat mengikat HP baru saat absensi berikutnya.
                            </span>
                        </div>
                    </label>
                </div>
            </div>
        </section>

        {{-- Kartu 4: Data Rekening Bank (Payroll) --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm border border-emerald-100">
                    4
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Rekening Bank untuk Penggajian</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Informasi akun bank atau metode pembayaran tunai (CASH) untuk transfer gaji</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Bank
                    </label>
                    <input 
                        name="bank_name" 
                        value="{{ old('bank_name', $person->bank_name ?? 'BCA') }}" 
                        placeholder="Contoh: BCA / Mandiri / BRI / CASH" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 uppercase"
                    >
                    @error('bank_name')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nomor Rekening Bank
                    </label>
                    <input 
                        name="bank_account_number" 
                        value="{{ old('bank_account_number', $person->bank_account_number) }}" 
                        placeholder="Contoh: 0680117845 (atau '-' jika Tunai)" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-mono font-bold tracking-wider focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('bank_account_number')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Pemilik Rekening
                    </label>
                    <input 
                        name="bank_account_name" 
                        value="{{ old('bank_account_name', $person->bank_account_name ?? $person->name) }}" 
                        placeholder="Nama sesuai buku tabungan" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    @error('bank_account_name')
                        <p class="text-xs font-semibold text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- Tombol Tindakan Formulir --}}
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
            <a href="{{ route('people.show', $person->id) }}" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-sm font-bold shadow-xs transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold shadow-sm transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Simpan Perubahan Data</span>
            </button>
        </div>
    </form>
</div>
@endsection
