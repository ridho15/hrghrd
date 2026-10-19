@extends('layouts.app')
@section('title', 'Input Calon Karyawan Baru')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('onboarding.index') }}" class="hover:text-emerald-700 transition-colors">Onboarding Digital</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Input Calon Karyawan Baru</span>
    </nav>

    {{-- Title --}}
    <div class="pb-4 border-b border-slate-200/80">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Pendaftaran Calon Karyawan
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            Isi data identitas, cabang, posisi, atasan langsung, dan remunerasi. Sistem akan otomatis menyusun **draf Offering Letter** dari template resmi aktif.
        </p>
    </div>

    {{-- Form Card --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8">
        <form method="POST" action="{{ route('onboarding.store') }}" class="space-y-6">
            @csrf

            {{-- Bagian 1: Identitas Pribadi --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 text-xs font-bold text-slate-900 uppercase tracking-wider">
                    <span class="w-5 h-5 rounded-md bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px]">1</span>
                    <span>Identitas & Kontak Calon Karyawan</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nama Lengkap <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Contoh: Rian Anggara, S.T."
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('name') border-rose-300 bg-rose-50/50 @enderror">
                        @error('name') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Alamat Email Aktif <span class="text-rose-500">*</span>
                        </label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="rian.anggara@email.com"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('email') border-rose-300 bg-rose-50/50 @enderror">
                        @error('email') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nomor WhatsApp / Telepon
                        </label>
                        <input type="text" name="phone" value="{{ old('phone') }}" placeholder="0812-xxxx-xxxx"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Alamat Domisili Karyawan
                        </label>
                        <input type="text" name="address" value="{{ old('address') }}" placeholder="Kota / Alamat lengkap domisili"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                    </div>
                </div>
            </div>

            {{-- Bagian 2: Penempatan & Struktur Organisasi --}}
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 text-xs font-bold text-slate-900 uppercase tracking-wider">
                    <span class="w-5 h-5 rounded-md bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px]">2</span>
                    <span>Penempatan & Posisi Struktur Organisasi</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Entitas Perusahaan <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="entity_name" value="{{ old('entity_name', 'PT HR Group Indonesia') }}" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Cabang Penempatan <span class="text-rose-500">*</span>
                        </label>
                        <select name="branch_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                            <option value="">Pilih Cabang</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Jabatan / Posisi <span class="text-rose-500">*</span>
                        </label>
                        <select name="position_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                            <option value="">Pilih Jabatan</option>
                            @foreach($positions as $p)
                                <option value="{{ $p->id }}" {{ old('position_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('position_id') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Atasan Langsung (Direct Supervisor)
                        </label>
                        <select name="supervisor_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                            <option value="">Tidak ada atasan langsung (Pimpinan Puncak)</option>
                            @foreach($supervisors as $s)
                                <option value="{{ $s->id }}" {{ old('supervisor_id') == $s->id ? 'selected' : '' }}>{{ $s->name }} ({{ $s->position?->name }} · {{ $s->branch?->name }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Unit / Divisi / Departemen
                        </label>
                        <input type="text" name="unit" value="{{ old('unit', 'Operasional') }}" placeholder="Contoh: Divisi Operasional"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                    </div>
                </div>
            </div>

            {{-- Bagian 3: Kompensasi & Tanggal --}}
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 text-xs font-bold text-slate-900 uppercase tracking-wider">
                    <span class="w-5 h-5 rounded-md bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px]">3</span>
                    <span>Remunerasi & Masa Berlaku Penawaran</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Gaji Pokok yang Ditawarkan (IDR) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" name="salary_offered" value="{{ old('salary_offered', 5000000) }}" min="1000000" step="100000" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('salary_offered') border-rose-300 bg-rose-50/50 @enderror">
                        @error('salary_offered') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tanggal Efektif Mulai Bekerja <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" name="start_date" value="{{ old('start_date', date('Y-m-d', strtotime('+7 days'))) }}" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                        @error('start_date') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Batas Akhir Berlaku Penawaran <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" name="offer_valid_until" value="{{ old('offer_valid_until', date('Y-m-d', strtotime('+3 days'))) }}" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                        @error('offer_valid_until') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Paket Tunjangan & Fasilitas yang Termasuk:
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-700">
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                            <input type="checkbox" name="benefits[]" value="BPJS Ketenagakerjaan (JKK, JKM, JHT, JP)" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>BPJS Ketenagakerjaan (JKK, JKM, JHT, JP)</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                            <input type="checkbox" name="benefits[]" value="BPJS Kesehatan" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>BPJS Kesehatan</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                            <input type="checkbox" name="benefits[]" value="Tunjangan Hari Raya (THR) Proporsional" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Tunjangan Hari Raya (THR) Proporsional</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                            <input type="checkbox" name="benefits[]" value="Hak Cuti Tahunan 12 Hari Kerja" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Hak Cuti Tahunan 12 Hari Kerja</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Submit Action --}}
            <div class="pt-4 border-t border-slate-200/80 flex items-center justify-between gap-4">
                <a href="{{ route('onboarding.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    Simpan & Buat Draf Offering Letter →
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
