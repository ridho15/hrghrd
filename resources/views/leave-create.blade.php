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
                <span>Pengajuan Cuti & Izin</span>
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

    {{-- Kotak Informasi Kebijakan (Clean Enterprise Light Callout) --}}
    <div class="p-5 bg-emerald-50/70 border border-emerald-200/80 rounded-2xl shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0 border border-emerald-200">
                <svg class="w-5 h-5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <div class="text-xs space-y-1">
                <strong class="text-sm text-emerald-950 font-bold block">Ketentuan Pengajuan Izin</strong>
                <p class="text-slate-700 m-0 leading-relaxed">
                    &bull; Izin cuti wajib diajukan minimal <strong>H-{{ \App\Support\Rules::int('leave_notice_days') }}</strong> sebelum tanggal pelaksanaan.<br>
                    &bull; Izin sakit wajib melampirkan <strong>Surat Keterangan Dokter</strong> resmi (Maksimal 5 MB).
                </p>
            </div>
        </div>
        <div class="flex items-center gap-6 border-t sm:border-t-0 pt-3 sm:pt-0 border-emerald-200/60 shrink-0">
            <div class="text-left sm:text-right">
                <span class="text-[11px] uppercase tracking-wider text-slate-500 font-bold block">Batas Pengajuan</span>
                <span class="text-base font-black text-emerald-800">H-{{ \App\Support\Rules::int('leave_notice_days') }}</span>
            </div>
            @if(\App\Support\Access::manager())
                <div class="text-left sm:text-right pl-4 border-l border-emerald-200/60">
                    <span class="text-[11px] uppercase tracking-wider text-emerald-800 font-bold block">Sisa Cuti Tahunan</span>
                    <span class="text-base font-black text-slate-900">{{ auth()->user()->remainingLeaveDays() }} / {{ auth()->user()->annual_leave_quota ?? 12 }} Hari</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Formulir Utama --}}
    <form method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data" class="space-y-6" id="leave-create-form">
        @csrf

        {{-- KARTU 1: Karyawan Pemohon & Jenis Pengajuan --}}
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
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
                                ['value' => (string)auth()->id(), 'label' => 'Saya Sendiri (' . auth()->user()->name . ')', 'sublabel' => (auth()->user()->username ? '@' . auth()->user()->username . ' • ' : '') . auth()->user()->email]
                            ])->merge($people->where('id', '!=', auth()->id())->map(fn($p) => [
                                'value' => (string)$p->id,
                                'label' => $p->name,
                                'sublabel' => ($p->username ? '@' . $p->username . ' • ' : '') . ($p->position?->name ?? 'Staf') . ' • ' . ($p->branch?->name ?? 'Semua Cabang'),
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
                        <option value="leave" @selected(old('type') === 'leave')>Cuti Tahunan / Izin Biasa</option>
                        <option value="sick" @selected(old('type') === 'sick')>Izin Sakit (Wajib Surat Dokter)</option>
                        @if(\App\Support\Access::manager())
                            <option value="day_off" @selected(old('type') === 'day_off')>Pengajuan Hari Libur (Day Off)</option>
                            <option value="leave_exchange" @selected(old('type') === 'leave_exchange')>Pergantian Tanggal Cuti (Reschedule)</option>
                        @endif
                    </select>
                    @error('type')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            @if(\App\Support\Access::manager())
                {{-- Kartu Pilihan Cuti Awal untuk Pergantian Cuti --}}
                <div id="leave-exchange-card" class="p-4 bg-sky-50/80 border border-sky-200/80 rounded-xl space-y-3 hidden">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                        </span>
                        <strong class="text-xs text-sky-950 font-bold uppercase tracking-wider">Pilih Permohonan Cuti Awal yang Ingin Diganti</strong>
                    </div>
                    <p class="text-[11px] text-sky-800 m-0">
                        Pilih pengajuan cuti aktif yang ingin Anda geser tanggalnya untuk dialokasikan ke jadwal baru.
                    </p>
                    <div>
                        <select
                            name="original_leave_id"
                            id="original-leave-id"
                            class="w-full h-11 px-3.5 bg-white border border-sky-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-sky-500/20 focus:border-sky-600 transition-all cursor-pointer"
                        >
                            <option value="">-- Pilih Cuti yang Ingin Digeser/Diganti --</option>
                            @if(isset($activeLeaves))
                                @foreach($activeLeaves as $al)
                                    <option value="{{ $al->id }}" @selected(old('original_leave_id') == $al->id)>
                                        #LVR-{{ str_pad($al->id, 5, '0', STR_PAD_LEFT) }} &bull; ({{ $al->start_date ? $al->start_date->format('d M Y') : '' }} s.d. {{ $al->end_date ? $al->end_date->format('d M Y') : '' }}) - {{ Str::limit($al->reason, 40) }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        @error('original_leave_id')
                            <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif
        </div>

        {{-- KARTU 2: Periode Tanggal & Alasan --}}
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
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
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-4" id="certificate-card">
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

            <div class="p-4 bg-slate-50/80 rounded-2xl border border-dashed border-slate-300 space-y-3" id="certificate-dropzone">
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

                {{-- Notifikasi Error Ukuran Berkas --}}
                <div id="cert-error-msg" class="hidden text-[11px] text-rose-600 font-semibold p-2.5 rounded-xl bg-rose-50 border border-rose-200"></div>

                @error('certificate')
                    <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                @enderror

                {{-- Live Preview Box Interaktif --}}
                <div id="cert-preview-card" class="hidden p-4 rounded-xl bg-white border border-slate-200 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-bold text-slate-800">Pratinjau Berkas Terpilih</span>
                        </div>
                        <button type="button" id="btn-remove-cert" class="text-xs text-rose-600 hover:text-rose-700 font-bold hover:underline cursor-pointer">
                            Hapus / Ganti Berkas
                        </button>
                    </div>

                    {{-- Image Preview Frame --}}
                    <div id="cert-image-preview-wrapper" class="hidden text-center space-y-2">
                        <div class="max-h-60 overflow-hidden rounded-xl border border-slate-100 bg-slate-900/5 flex items-center justify-center p-2">
                            <img id="cert-img-preview" src="" alt="Pratinjau Dokumen" class="max-h-56 max-w-full rounded-lg object-contain shadow-2xs">
                        </div>
                    </div>

                    {{-- PDF Preview Card --}}
                    <div id="cert-pdf-preview-wrapper" class="hidden p-3.5 rounded-xl bg-purple-50/70 border border-purple-200/80 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-purple-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span id="cert-file-name" class="text-xs font-bold text-slate-900 block truncate">dokumen.pdf</span>
                                <span id="cert-file-size" class="text-[11px] text-purple-700 font-semibold block">0 KB</span>
                            </div>
                        </div>
                        <a id="cert-pdf-open-btn" href="#" target="_blank" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-[11px] font-bold shrink-0 transition-colors">
                            Buka di Tab Baru
                        </a>
                    </div>

                    {{-- Status Informasi File --}}
                    <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1">
                        <span id="cert-file-meta-info"></span>
                        <span class="text-emerald-700 font-bold inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            <span>Ukuran Memenuhi Syarat (&lt; 5 MB)</span>
                        </span>
                    </div>
                </div>
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
        const certCard = document.getElementById('certificate-card');
        const exchangeCard = document.getElementById('leave-exchange-card');
        const origSelect = document.getElementById('original-leave-id');

        if (type === 'sick') {
            if (certCard) certCard.classList.remove('hidden');
            if (exchangeCard) exchangeCard.classList.add('hidden');
            if (star) star.classList.remove('hidden');
            if (desc) desc.textContent = 'Surat keterangan sakit dari dokter wajib dilampirkan untuk jenis sakit (Maks. 5 MB).';
            if (fileInput) fileInput.required = true;
            if (origSelect) origSelect.required = false;
        } else if (type === 'leave_exchange') {
            if (certCard) certCard.classList.add('hidden');
            if (exchangeCard) exchangeCard.classList.remove('hidden');
            if (fileInput) fileInput.required = false;
            if (origSelect) origSelect.required = true;
        } else {
            if (certCard) certCard.classList.remove('hidden');
            if (exchangeCard) exchangeCard.classList.add('hidden');
            if (star) star.classList.add('hidden');
            if (desc) desc.textContent = 'Opsional: Dapat melampirkan berkas pendukung jika diperlukan.';
            if (fileInput) fileInput.required = false;
            if (origSelect) origSelect.required = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('leave-type-select');
        if (select) {
            toggleCertificateRequirement(select.value);
        }

        // Live Preview Berkas
        const certInput = document.getElementById('leave-certificate');
        const previewCard = document.getElementById('cert-preview-card');
        const imgWrapper = document.getElementById('cert-image-preview-wrapper');
        const imgPreview = document.getElementById('cert-img-preview');
        const pdfWrapper = document.getElementById('cert-pdf-preview-wrapper');
        const pdfName = document.getElementById('cert-file-name');
        const pdfSize = document.getElementById('cert-file-size');
        const pdfOpenBtn = document.getElementById('cert-pdf-open-btn');
        const certMeta = document.getElementById('cert-file-meta-info');
        const errorMsg = document.getElementById('cert-error-msg');
        const removeBtn = document.getElementById('btn-remove-cert');

        let currentObjectUrl = null;

        if (certInput) {
            certInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (currentObjectUrl) {
                    URL.revokeObjectURL(currentObjectUrl);
                    currentObjectUrl = null;
                }

                if (!file) {
                    if (previewCard) previewCard.classList.add('hidden');
                    if (errorMsg) errorMsg.classList.add('hidden');
                    return;
                }

                // Validasi Ukuran (Maksimal 5 MB = 5 * 1024 * 1024 bytes)
                const maxSize = 5 * 1024 * 1024;
                const fileSizeMb = (file.size / (1024 * 1024)).toFixed(2);

                if (file.size > maxSize) {
                    certInput.value = '';
                    if (previewCard) previewCard.classList.add('hidden');
                    if (errorMsg) {
                        errorMsg.textContent = `Ukuran berkas (${fileSizeMb} MB) melebihi batas maksimal 5 MB. Harap pilih foto/dokumen yang lebih kecil atau lakukan kompresi terlebih dahulu.`;
                        errorMsg.classList.remove('hidden');
                    }
                    return;
                }

                if (errorMsg) errorMsg.classList.add('hidden');
                if (previewCard) previewCard.classList.remove('hidden');

                const isImage = file.type.startsWith('image/');
                const isPdf = file.type === 'application/pdf';

                currentObjectUrl = URL.createObjectURL(file);

                if (isImage) {
                    if (imgWrapper) imgWrapper.classList.remove('hidden');
                    if (pdfWrapper) pdfWrapper.classList.add('hidden');
                    if (imgPreview) imgPreview.src = currentObjectUrl;
                    if (certMeta) certMeta.textContent = `${file.name} (${fileSizeMb} MB)`;
                } else if (isPdf) {
                    if (imgWrapper) imgWrapper.classList.add('hidden');
                    if (pdfWrapper) pdfWrapper.classList.remove('hidden');
                    if (pdfName) pdfName.textContent = file.name;
                    if (pdfSize) pdfSize.textContent = `${fileSizeMb} MB`;
                    if (pdfOpenBtn) pdfOpenBtn.href = currentObjectUrl;
                    if (certMeta) certMeta.textContent = `Dokumen PDF (${fileSizeMb} MB)`;
                } else {
                    if (imgWrapper) imgWrapper.classList.add('hidden');
                    if (pdfWrapper) pdfWrapper.classList.add('hidden');
                    if (certMeta) certMeta.textContent = `${file.name} (${fileSizeMb} MB)`;
                }
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (certInput) certInput.value = '';
                if (currentObjectUrl) {
                    URL.revokeObjectURL(currentObjectUrl);
                    currentObjectUrl = null;
                }
                if (previewCard) previewCard.classList.add('hidden');
                if (errorMsg) errorMsg.classList.add('hidden');
            });
        }
    });
</script>
@endsection
