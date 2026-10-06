@extends('layouts.app')
@section('title', 'Revisi Jadwal Shift: ' . ($shift->employee_name ?? 'Karyawan'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('shifts') }}" class="hover:text-emerald-700 transition-colors">Jadwal Shift</a>
        <span>/</span>
        <a href="{{ route('shifts.show', $shift->id) }}" class="hover:text-emerald-700 transition-colors">#SHF-{{ str_pad($shift->id, 5, '0', STR_PAD_LEFT) }}</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Revisi Jadwal</span>
    </nav>

    {{-- Header Halaman & Tombol Kembali --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-blue-700 bg-blue-50 px-2.5 py-1 rounded-md mb-2 border border-blue-200/60">
                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <span>Penyesuaian Jadwal Terjadwal</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Revisi Jadwal Shift
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Perbarui tanggal atau jam kerja untuk <strong>{{ $shift->employee_name }}</strong>. Setiap perubahan akan dicatat dalam audit trail.
            </p>
        </div>

        <a
            href="{{ route('shifts.show', $shift->id) }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider shadow-2xs transition-all self-start sm:self-auto"
        >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            <span>Kembali ke Detail</span>
        </a>
    </div>

    {{-- Info Card Penugasan (Read-only) --}}
    <div class="p-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-3xl shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-white/10 text-emerald-400 font-extrabold text-base flex items-center justify-center border border-white/10 shrink-0">
                {{ strtoupper(substr($shift->employee_name ?? 'SH', 0, 2)) }}
            </div>
            <div>
                <span class="text-xs text-slate-400 block font-mono">Shift ID #SHF-{{ str_pad($shift->id, 5, '0', STR_PAD_LEFT) }} &middot; Versi {{ $shift->version }}</span>
                <h3 class="text-base font-bold text-white">{{ $shift->employee_name }}</h3>
                <span class="text-xs text-emerald-300 font-medium">
                    {{ $shift->user?->position?->name ?? 'Staf' }} &middot; Cabang {{ $shift->branch_name }}
                </span>
            </div>
        </div>
        <div class="sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-700">
            <span class="text-[11px] uppercase tracking-wider text-slate-400 block font-bold">Jadwal Eksisting</span>
            <span class="text-sm font-bold text-amber-300 tabular-nums">
                {{ \Carbon\Carbon::parse($shift->start_at)->translatedFormat('d M Y') }} ({{ \Carbon\Carbon::parse($shift->start_at)->format('H:i') }} - {{ \Carbon\Carbon::parse($shift->end_at)->format('H:i') }})
            </span>
        </div>
    </div>

    {{-- Formulir Revisi Shift --}}
    <form method="POST" action="{{ route('shifts.update', $shift->id) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="expected_version" value="{{ $shift->version }}">

        {{-- KARTU 1: Tanggal & Waktu Baru --}}
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-700 font-black text-sm flex items-center justify-center border border-blue-200/60">
                        1
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Penyesuaian Waktu Kerja</h2>
                        <p class="text-xs text-slate-500">Tentukan tanggal dan interval jam kerja yang baru.</p>
                    </div>
                </div>

                {{-- Preset Cepat Jam Kerja --}}
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="text-slate-400 font-semibold mr-1">Preset Cepat:</span>
                    <button type="button" onclick="setShiftPreset('08:00', '17:00')" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-100 hover:text-blue-800 text-slate-700 font-semibold rounded-lg transition-colors cursor-pointer">
                        Pagi (08:00-17:00)
                    </button>
                    <button type="button" onclick="setShiftPreset('13:00', '21:00')" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-100 hover:text-blue-800 text-slate-700 font-semibold rounded-lg transition-colors cursor-pointer">
                        Siang (13:00-21:00)
                    </button>
                    <button type="button" onclick="setShiftPreset('21:00', '05:00')" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-100 hover:text-blue-800 text-slate-700 font-semibold rounded-lg transition-colors cursor-pointer">
                        Malam (21:00-05:00)
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                {{-- Tanggal --}}
                <div>
                    <label for="shift-edit-date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Tanggal Shift <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="date"
                        id="shift-edit-date"
                        required
                        value="{{ old('date', \Carbon\Carbon::parse($shift->start_at)->toDateString()) }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all @error('date') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('date')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Jam Mulai --}}
                <div>
                    <label for="shift-edit-start-time" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Jam Mulai <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="time"
                        name="start_time"
                        id="shift-edit-start-time"
                        required
                        value="{{ old('start_time', \Carbon\Carbon::parse($shift->start_at)->format('H:i')) }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all @error('start_time') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('start_time')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Jam Selesai --}}
                <div>
                    <label for="shift-edit-end-time" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Jam Selesai <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="time"
                        name="end_time"
                        id="shift-edit-end-time"
                        required
                        value="{{ old('end_time', \Carbon\Carbon::parse($shift->end_at)->format('H:i')) }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all @error('end_time') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('end_time')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- KARTU 2: Alasan Perubahan (Audit Justification) --}}
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 font-black text-sm flex items-center justify-center border border-amber-200/60">
                    2
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Alasan & Justifikasi Revisi</h2>
                    <p class="text-xs text-slate-500">Wajib diisi demi kepatuhan audit dan riwayat transparansi perubahan jadwal kerja.</p>
                </div>
            </div>

            <div>
                <label for="shift-reason" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                    Alasan Perubahan Jadwal <span class="text-rose-500">* (Min. 5 karakter)</span>
                </label>
                <textarea
                    name="reason"
                    id="shift-reason"
                    rows="3"
                    required
                    minlength="5"
                    maxlength="500"
                    placeholder="Contoh: Permintaan tukar hari shift dengan rekan kerja, atau penyesuaian jam operasional outlet..."
                    class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all @error('reason') border-rose-300 bg-rose-50/50 @enderror"
                >{{ old('reason') }}</textarea>
                @error('reason')
                    <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="p-4 bg-amber-50/70 border border-amber-200/80 rounded-2xl flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div class="text-xs text-amber-900 leading-relaxed">
                    <strong>Pemberitahuan Tata Kelola:</strong> Menyimpan revisi ini akan menaikkan nomor versi shift menjadi <strong>v{{ $shift->version + 1 }}</strong> dan mengembalikan status jadwal menjadi <strong>Draf</strong>. Jadwal harus disetujui ulang sebelum karyawan dapat presensi pada jadwal baru.
                </div>
            </div>
        </div>

        {{-- Tombol Aksi Simpan & Batal --}}
        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('shifts.show', $shift->id) }}"
                class="w-full sm:w-auto px-6 py-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs uppercase tracking-wider text-center transition-all shadow-2xs"
            >
                Batal
            </a>
            <button
                type="submit"
                class="w-full sm:w-auto px-7 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition-all flex items-center justify-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Simpan Perubahan Jadwal</span>
            </button>
        </div>
    </form>
</div>

<script>
    function setShiftPreset(start, end) {
        const startInput = document.getElementById('shift-edit-start-time');
        const endInput = document.getElementById('shift-edit-end-time');
        if (startInput && endInput) {
            startInput.value = start;
            endInput.value = end;
        }
    }
</script>
@endsection
