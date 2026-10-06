@extends('layouts.app')
@section('title', 'Impor Karyawan')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    {{-- Header Modul & Penjelasan Stepper --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                <span>Migrasi & Penambahan Massal</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Impor Data Karyawan
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Unggah spreadsheet CSV atau XLSX. Data diverifikasi di memori sebelum disimpan, hanya baris valid yang dimasukkan ke sistem.
            </p>
        </div>
    </div>

    {{-- Visual Stepper Bar --}}
    <div class="grid grid-cols-3 gap-2 p-2 bg-slate-100 rounded-2xl border border-slate-200/80 text-xs font-bold">
        <div class="py-2 px-3 rounded-xl flex items-center justify-center gap-2 {{ !isset($headers) && !isset($result) ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-500' }}">
            <span class="w-5 h-5 rounded-full {{ !isset($headers) && !isset($result) ? 'bg-emerald-700 text-white' : 'bg-slate-300 text-slate-600' }} flex items-center justify-center text-[10px]">1</span>
            <span>Unggah File</span>
        </div>
        <div class="py-2 px-3 rounded-xl flex items-center justify-center gap-2 {{ isset($headers) && !isset($result) ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-500' }}">
            <span class="w-5 h-5 rounded-full {{ isset($headers) && !isset($result) ? 'bg-emerald-700 text-white' : 'bg-slate-300 text-slate-600' }} flex items-center justify-center text-[10px]">2</span>
            <span>Pemetaan Kolom</span>
        </div>
        <div class="py-2 px-3 rounded-xl flex items-center justify-center gap-2 {{ isset($result) ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-500' }}">
            <span class="w-5 h-5 rounded-full {{ isset($result) ? 'bg-emerald-700 text-white' : 'bg-slate-300 text-slate-600' }} flex items-center justify-center text-[10px]">3</span>
            <span>Hasil Impor</span>
        </div>
    </div>

    {{-- Langkah 1: Unggah File (Dropzone) --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
        <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
            <span class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold text-xs">1</span>
            <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Pilih Berkas Spreadsheet</h2>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed m-0">
            Pastikan file memuat kolom data dasar: <strong>Nama, Email, Kode Cabang, Jabatan, Tanggal Mulai, Gaji Bulanan, Status</strong>. Cabang harus sudah terdaftar terlebih dahulu di master data.
        </p>

        <form method="post" action="{{ route('import.preview') }}" enctype="multipart/form-data" class="space-y-4 pt-2">
            @csrf
            <div class="p-6 border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl bg-slate-50/50 text-center transition-colors">
                <svg class="w-10 h-10 text-slate-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-emerald-700 hover:text-emerald-800 cursor-pointer block">
                        Pilih Berkas CSV atau XLSX
                        <input type="file" name="file" accept=".csv,.xlsx" required class="sr-only" onchange="document.getElementById('file-chosen').textContent = this.files[0]?.name || ''">
                    </label>
                    <span id="file-chosen" class="text-xs font-semibold text-slate-700 block"></span>
                    <p class="text-[11px] text-slate-400">Ukuran file maksimal 10 MB</p>
                </div>
            </div>

            <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition-colors cursor-pointer flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                <span>Pratinjau Data File</span>
            </button>
        </form>
    </section>

    {{-- Langkah 2: Pemetaan Kolom (Jika Headers Ada) --}}
    @isset($headers)
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-6">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                <span class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold text-xs">2</span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Pemetaan Kolom Data</h2>
            </div>

            {{-- Pratinjau Sampel Tabel --}}
            <div class="space-y-2">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Sampel 3 Baris Teratas</h3>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                @foreach($headers as $h)
                                    <th class="py-2.5 px-3 whitespace-nowrap">{{ $h }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                            @foreach($sample as $row)
                                <tr class="hover:bg-slate-50/50">
                                    @foreach($headers as $i => $h)
                                        <td class="py-2 px-3 whitespace-nowrap">{{ $row[$i] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Form Mapping Dropdown --}}
            <form method="post" action="{{ route('import.commit') }}" class="space-y-5">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach(['name'=>'Nama Lengkap','email'=>'Alamat Email','branch'=>'Kode Cabang','position'=>'Nama Jabatan','hired_at'=>'Tanggal Mulai','base_salary'=>'Gaji Pokok Bulanan','active'=>'Status Aktif'] as $key => $label)
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                {{ $label }}
                            </label>
                            <select name="{{ $key }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                                <option value="">Pilih Kolom Spreadsheet</option>
                                @foreach($headers as $i => $h)
                                    <option value="{{ $i }}" @selected(str_contains(strtolower($h), strtolower(explode(' ', $label)[0])))>
                                        {{ $h }} (Kolom #{{ $i + 1 }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>

                <div class="pt-2">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition-colors cursor-pointer flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>Impor Seluruh Baris Valid</span>
                    </button>
                    <p class="text-[11px] text-slate-400 mt-2">Akun baru akan dibuat dengan sandi acak aman. Setel sandi sementara pada halaman Karyawan sebelum karyawan login pertama kali.</p>
                </div>
            </form>
        </section>
    @endisset

    {{-- Langkah 3: Hasil Impor (Jika Result Ada) --}}
    @isset($result)
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                <span class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold text-xs">3</span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Ringkasan Hasil Impor</h2>
            </div>

            <div class="flex items-center gap-4 text-xs">
                <div class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 font-bold">
                    ✓ {{ $result['imported'] }} Baris Berhasil Masuk
                </div>
                <div class="px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 font-bold">
                    ✕ {{ $result['failed'] }} Baris Gagal
                </div>
            </div>

            @if($result['errors'])
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200/80 text-rose-900 space-y-2">
                    <strong class="text-xs font-bold block uppercase tracking-wider">Catatan Galat Validasi:</strong>
                    <ul class="text-xs list-disc pl-5 space-y-1">
                        @foreach($result['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="pt-2">
                <a href="{{ route('people') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider transition-colors shadow-xs">
                    <span>Buka Direktori Karyawan &rarr;</span>
                </a>
            </div>
        </section>
    @endisset
</div>
@endsection
