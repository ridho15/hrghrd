@extends('layouts.app')
@section('title', 'Buat Jadwal Shift Baru')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('shifts') }}" class="hover:text-emerald-700 transition-colors">Jadwal Shift</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Buat Jadwal Baru</span>
    </nav>

    {{-- Header Halaman & Tombol Kembali --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Penjadwalan Operasional</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Buat Jadwal Shift Baru
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Alokasikan jadwal kerja karyawan ke cabang operasional tertentu. Jadwal baru tersimpan sebagai draf dan memerlukan persetujuan manajerial.
            </p>
        </div>

        <a
            href="{{ route('shifts') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider shadow-2xs transition-all self-start sm:self-auto"
        >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            <span>Kembali ke Jadwal</span>
        </a>
    </div>

    @if($errors->has('shift'))
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-3">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <strong class="text-xs font-bold text-rose-800 uppercase tracking-wider block">Gagal Membuat Shift</strong>
                <p class="text-xs text-rose-700 mt-0.5">{{ $errors->first('shift') }}</p>
            </div>
        </div>
    @endif

    {{-- Formulir Tambah Shift --}}
    <form method="POST" action="{{ route('shifts.store') }}" class="space-y-6" id="shift-create-form">
        @csrf

        {{-- KARTU 1: Personel & Lokasi Cabang --}}
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                    1
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Alokasi Karyawan & Cabang</h2>
                    <p class="text-xs text-slate-500">Pilih staf yang bertugas dan pastikan cabang penugasan sesuai dengan unit kerja karyawan.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- Pilihan Karyawan --}}
                <div>
                    @php
                        $peopleOpts = $people->map(fn($p) => [
                            'value' => (string)$p->id,
                            'label' => $p->name,
                            'sublabel' => ($p->position?->title ?? 'Staf') . ' • ' . ($p->branch?->name ?? 'Semua Cabang'),
                        ]);
                    @endphp
                    <x-searchable-select
                        name="user_id"
                        id="shift-user-select"
                        label="Pilih Karyawan *"
                        :value="old('user_id')"
                        :options="$peopleOpts"
                        placeholder="Cari atau pilih nama karyawan..."
                        searchPlaceholder="Ketik nama karyawan..."
                    />
                    @error('user_id')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Pilihan Cabang --}}
                <div>
                    @php
                        $branchOpts = $branches->map(fn($b) => [
                            'value' => (string)$b->id,
                            'label' => $b->name,
                            'sublabel' => 'Kode: ' . $b->code,
                        ]);
                    @endphp
                    <x-searchable-select
                        name="branch_id"
                        id="shift-branch-select"
                        label="Cabang Penugasan *"
                        :value="old('branch_id', auth()->user()->branch_id)"
                        :options="$branchOpts"
                        placeholder="Pilih cabang operasional..."
                        searchPlaceholder="Ketik nama cabang..."
                    />
                    @error('branch_id')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="p-4 bg-emerald-50/60 border border-emerald-200/80 rounded-2xl flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-xs text-emerald-900 leading-relaxed">
                    <strong>Ketentuan Cabang:</strong> Karyawan harus ditugaskan pada cabang yang sama dengan lokasi kerja terdaftar mereka. Sistem akan menolak apabila cabang penugasan shift berbeda dengan cabang asal karyawan.
                </div>
            </div>
        </div>

        {{-- KARTU 2: Waktu Kerja & Tanggal --}}
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                        2
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Tanggal & Jam Kerja</h2>
                        <p class="text-xs text-slate-500">Tentukan hari pelaksanaan dan interval waktu kerja yang dialokasikan.</p>
                    </div>
                </div>

                {{-- Preset Cepat Jam Kerja --}}
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="text-slate-400 font-semibold mr-1">Preset Cepat:</span>
                    <button type="button" onclick="setShiftPreset('08:00', '17:00')" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-semibold rounded-lg transition-colors cursor-pointer">
                        Pagi (08:00-17:00)
                    </button>
                    <button type="button" onclick="setShiftPreset('13:00', '21:00')" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-semibold rounded-lg transition-colors cursor-pointer">
                        Siang (13:00-21:00)
                    </button>
                    <button type="button" onclick="setShiftPreset('21:00', '05:00')" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-semibold rounded-lg transition-colors cursor-pointer">
                        Malam (21:00-05:00)
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                {{-- Tanggal --}}
                <div>
                    <label for="shift-date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Tanggal Shift <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="date"
                        id="shift-date"
                        required
                        value="{{ old('date', now('Asia/Jakarta')->toDateString()) }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('date') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('date')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Jam Mulai --}}
                <div>
                    <label for="shift-start-time" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Jam Mulai <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="time"
                        name="start_time"
                        id="shift-start-time"
                        required
                        value="{{ old('start_time', '08:00') }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('start_time') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('start_time')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Jam Selesai --}}
                <div>
                    <label for="shift-end-time" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Jam Selesai <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="time"
                        name="end_time"
                        id="shift-end-time"
                        required
                        value="{{ old('end_time', '17:00') }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('end_time') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('end_time')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs text-slate-600 flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Rentang pergantian hari (lintas tengah malam) didukung secara otomatis oleh sistem.</span>
                </span>
                <span class="text-[11px] font-mono text-slate-400">WIB (Asia/Jakarta)</span>
            </div>
        </div>

        {{-- Tombol Aksi Simpan & Batal --}}
        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('shifts') }}"
                class="w-full sm:w-auto px-6 py-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs uppercase tracking-wider text-center transition-all shadow-2xs"
            >
                Batal
            </a>
            <button
                type="submit"
                class="w-full sm:w-auto px-7 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition-all flex items-center justify-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Simpan Draf Shift</span>
            </button>
        </div>
    </form>
</div>

<script>
    function setShiftPreset(start, end) {
        const startInput = document.getElementById('shift-start-time');
        const endInput = document.getElementById('shift-end-time');
        if (startInput && endInput) {
            startInput.value = start;
            endInput.value = end;
        }
    }
</script>
@endsection
