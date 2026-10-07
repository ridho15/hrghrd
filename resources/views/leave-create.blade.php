@extends('layouts.app')
@section('title', 'Formulir Pengajuan Izin & Sakit')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('leave') }}" class="hover:text-emerald-700 transition-colors">Izin & Sakit</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Pengajuan Baru</span>
    </nav>

    {{-- Header Halaman & Tombol Kembali --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Permohonan Ketidakhadiran</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Formulir Pengajuan Izin & Sakit
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kirimkan permohonan izin cuti terencana atau pemberitahuan sakit darurat untuk peninjauan manajemen.
            </p>
        </div>

        <a
            href="{{ route('leave') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider shadow-2xs transition-all self-start sm:self-auto"
        >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            <span>Kembali ke Riwayat</span>
        </a>
    </div>

    {{-- Kotak Informasi Kebijakan --}}
    <div class="p-5 bg-slate-900 border border-slate-800 text-white rounded-2xl shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-300 font-extrabold flex items-center justify-center shrink-0 border border-emerald-500/30">
                📋
            </div>
            <div class="text-xs space-y-1">
                <strong class="text-sm text-emerald-200 font-bold block">Ketentuan Kebijakan Perusahaan</strong>
                <p class="text-slate-300 m-0 leading-relaxed">
                    &bull; Izin biasa / cuti tahunan wajib diajukan minimal <strong>H-{{ \App\Support\Rules::int('leave_notice_days') }}</strong> sebelum tanggal pelaksanaan.<br>
                    &bull; Izin sakit wajib melampirkan <strong>Surat Keterangan Dokter</strong> resmi (Maksimal 5 MB).
                </p>
            </div>
        </div>
        <div class="text-left sm:text-right border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-700/60 shrink-0">
            <span class="text-[11px] uppercase tracking-wider text-slate-400 font-bold block">Notice Days</span>
            <span class="text-lg font-black text-emerald-400">H-{{ \App\Support\Rules::int('leave_notice_days') }}</span>
        </div>
    </div>

    {{-- Formulir Utama --}}
    <form method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data" class="space-y-6" id="leave-create-form">
        @csrf

        {{-- KARTU 1: Karyawan Pemohon & Jenis Pengajuan --}}
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                    1
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Pemohon & Jenis Perizinan</h2>
                    <p class="text-xs text-slate-500">Tentukan karyawan yang mengajukan dan kategori perizinan.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- Khusus Admin & Manager: Pilihan Karyawan Pemohon --}}
                @if(in_array(auth()->user()->role, ['admin', 'manager']))
                    <div>
                        @php
                            $leavePeopleOpts = collect([
                                ['value' => (string)auth()->id(), 'label' => 'Saya Sendiri (' . auth()->user()->name . ')', 'sublabel' => auth()->user()->email]
                            ])->merge($people->where('id', '!=', auth()->id())->map(fn($p) => [
                                'value' => (string)$p->id,
                                'label' => $p->name,
                                'sublabel' => ($p->position?->title ?? 'Staf') . ' • ' . ($p->branch?->name ?? 'Semua Cabang'),
                            ]));
                        @endphp
                        <x-searchable-select
                            name="user_id"
                            id="leave-user-select"
                            label="Karyawan Pemohon *"
                            :value="old('user_id', (string)auth()->id())"
                            :options="$leavePeopleOpts"
                            placeholder="Pilih Karyawan..."
                            searchPlaceholder="Cari nama karyawan..."
                        />
                        @error('user_id')
                            <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Karyawan Pemohon
                        </label>
                        <input
                            type="text"
                            disabled
                            value="{{ auth()->user()->name }} ({{ auth()->user()->position?->name ?? 'Staf' }})"
                            class="w-full h-11 px-3.5 bg-slate-100 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 cursor-not-allowed"
                        >
                    </div>
                @endif

                {{-- Jenis Pengajuan --}}
                <div>
                    <label for="leave-type-select" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Jenis Pengajuan <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="leave-type-select"
                        name="type"
                        required
                        onchange="toggleCertificateRequirement(this.value)"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all cursor-pointer @error('type') border-rose-300 bg-rose-50/50 @enderror"
                    >
                        <option value="leave" @selected(old('type') === 'leave')>📅 Izin Biasa / Cuti Terencana</option>
                        <option value="sick" @selected(old('type') === 'sick')>🩺 Sakit (Wajib Surat Dokter)</option>
                    </select>
                    @error('type')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- KARTU 2: Periode Tanggal & Alasan --}}
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                    2
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Periode Tanggal & Keperluan</h2>
                    <p class="text-xs text-slate-500">Tentukan rentang tanggal ketidakhadiran dan uraikan alasan permohonan.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- Tanggal Mulai --}}
                <div>
                    <label for="leave-start-date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Tanggal Mulai <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="start_date"
                        id="leave-start-date"
                        required
                        value="{{ old('start_date') }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('start_date') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('start_date')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tanggal Selesai --}}
                <div>
                    <label for="leave-end-date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Tanggal Selesai <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="end_date"
                        id="leave-end-date"
                        required
                        value="{{ old('end_date') }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('end_date') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('end_date')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="leave-reason" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                    Alasan Lengkap Pengajuan <span class="text-rose-500">* (Min. 10 karakter)</span>
                </label>
                <textarea
                    name="reason"
                    id="leave-reason"
                    rows="3"
                    required
                    minlength="10"
                    maxlength="1000"
                    placeholder="Uraikan alasan keperluan izin atau kondisi kesehatan yang dialami secara jelas..."
                    class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('reason') border-rose-300 bg-rose-50/50 @enderror"
                >{{ old('reason') }}</textarea>
                @error('reason')
                    <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- KARTU 3: Lampiran Surat Dokter --}}
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-4" id="certificate-card">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-700 font-black text-sm flex items-center justify-center border border-purple-200/60">
                    3
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Lampiran Dokumen Medis</h2>
                    <p class="text-xs text-slate-500" id="certificate-desc">
                        Unggah surat keterangan sakit dari dokter atau fasilitas kesehatan berwenang.
                    </p>
                </div>
            </div>

            <div class="p-4 bg-slate-50/80 rounded-2xl border border-dashed border-slate-300 space-y-2">
                <label for="leave-certificate" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Pilih Berkas Surat Sakit <span id="cert-required-star" class="text-rose-500 hidden">*</span>
                </label>
                <input
                    type="file"
                    name="certificate"
                    id="leave-certificate"
                    accept=".pdf,.jpg,.jpeg,.png"
                    class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer"
                >
                <div class="text-[11px] text-slate-400">
                    Format file yang didukung: <strong>PDF, JPG, JPEG, PNG</strong> (Maksimal ukuran 5 MB).
                </div>
                @error('certificate')
                    <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Tombol Aksi Simpan & Batal --}}
        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('leave') }}"
                class="w-full sm:w-auto px-6 py-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs uppercase tracking-wider text-center transition-all shadow-2xs"
            >
                Batal
            </a>
            <button
                type="submit"
                class="w-full sm:w-auto px-7 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition-all flex items-center justify-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
                <span>Kirim Permohonan Izin</span>
            </button>
        </div>
    </form>
</div>

<script>
    function toggleCertificateRequirement(type) {
        const star = document.getElementById('cert-required-star');
        const desc = document.getElementById('certificate-desc');
        const fileInput = document.getElementById('leave-certificate');

        if (type === 'sick') {
            if (star) star.classList.remove('hidden');
            if (desc) desc.textContent = 'Surat keterangan sakit dari dokter wajib dilampirkan untuk jenis sakit.';
            if (fileInput) fileInput.required = true;
        } else {
            if (star) star.classList.add('hidden');
            if (desc) desc.textContent = 'Opsional: Dapat melampirkan berkas pendukung jika diperlukan.';
            if (fileInput) fileInput.required = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('leave-type-select');
        if (select) {
            toggleCertificateRequirement(select.value);
        }
    });
</script>
@endsection
