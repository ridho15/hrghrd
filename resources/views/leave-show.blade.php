@extends('layouts.app')
@section('title', 'Detail Pengajuan Izin: ' . ($leave->employee_name ?? 'Karyawan'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('leave') }}" class="hover:text-emerald-700 transition-colors">Izin & Sakit</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">#LVR-{{ str_pad($leave->id, 5, '0', STR_PAD_LEFT) }}</span>
    </nav>

    @php
        $statusBadge = [
            'pending' => ['label' => 'Menunggu Persetujuan', 'class' => 'bg-amber-50 text-amber-800 border-amber-200'],
            'approved' => ['label' => 'Disetujui Sepenuhnya', 'class' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
            'partial' => ['label' => 'Disetujui Sebagian', 'class' => 'bg-sky-50 text-sky-800 border-sky-200'],
            'rejected' => ['label' => 'Permohonan Ditolak', 'class' => 'bg-rose-50 text-rose-800 border-rose-200']
        ][$leave->status] ?? ['label' => $leave->status, 'class' => 'bg-slate-100 text-slate-600 border-slate-200'];

        $durationDays = \Carbon\Carbon::parse($leave->start_date)->diffInDays(\Carbon\Carbon::parse($leave->end_date)) + 1;

        $canCancel = ($leave->status === 'pending' && (
            auth()->user()->role === 'admin' ||
            (auth()->user()->role === 'manager' && $leave->user?->branch_id === auth()->user()->branch_id) ||
            $leave->user_id === auth()->id()
        ));

        $canReview = (in_array(auth()->user()->role, ['admin', 'manager']) && $leave->status === 'pending' && $leave->created_by !== auth()->id());
    @endphp

    {{-- Header Dossier & Tombol Aksi --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-7">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-800 font-extrabold text-xl flex items-center justify-center border border-emerald-200/60 shadow-2xs shrink-0">
                    {{ strtoupper(substr($leave->employee_name ?? 'LV', 0, 2)) }}
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-xs font-mono">
                            #LVR-{{ str_pad($leave->id, 5, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="px-2.5 py-0.5 rounded-md border font-bold text-xs inline-flex items-center gap-1 {{ $statusBadge['class'] }}">
                            <span>{{ $statusBadge['label'] }}</span>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-md text-xs font-bold {{ $leave->type === 'sick' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                            {{ $leave->type === 'sick' ? '🩺 Izin Sakit' : '📅 Cuti / Izin' }}
                        </span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        {{ $leave->employee_name }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                        <span>Jabatan: <strong class="text-slate-700">{{ $leave->user?->position?->name ?? 'Staf' }}</strong></span>
                        <span>&middot;</span>
                        <span>Cabang: <strong class="text-slate-700">{{ $leave->user?->branch?->name ?? 'Cabang —' }}</strong></span>
                        @if($leave->user)
                            <span>&middot;</span>
                            <a href="{{ route('people.show', $leave->user_id) }}" class="text-emerald-700 hover:underline font-semibold">
                                Lihat Berkas Karyawan ↗
                            </a>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Tombol Aksi Mandiri --}}
            <div class="flex flex-wrap items-center gap-2.5">
                <a
                    href="{{ route('leave') }}"
                    class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Daftar Pengajuan</span>
                </a>

                @if($canCancel)
                    <button
                        type="button"
                        data-confirm="Apakah Anda yakin ingin membatalkan pengajuan ini? Tindakan ini akan menghapus berkas dan permohonan."
                        data-confirm-title="Batalkan Pengajuan"
                        data-confirm-variant="danger"
                        data-confirm-btn="Ya, Batalkan Pengajuan"
                        data-confirm-action="{{ route('leave.destroy', $leave->id) }}"
                        class="px-4 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <span>Batalkan Pengajuan</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Kartu Metrik Ringkas --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-6">
            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Rentang Periode</span>
                <span class="text-base font-black text-slate-900 block mt-1">
                    {{ \Carbon\Carbon::parse($leave->start_date)->translatedFormat('d M Y') }} – {{ \Carbon\Carbon::parse($leave->end_date)->translatedFormat('d M Y') }}
                </span>
                <span class="text-[11px] text-slate-500 font-medium block">Total Durasi: {{ $durationDays }} Hari</span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Lampiran Surat Dokter</span>
                <span class="text-base font-bold block mt-1 {{ $leave->certificate_path ? 'text-emerald-700' : 'text-slate-500' }}">
                    {{ $leave->certificate_path ? 'Dokumen Terlampir' : 'Tidak Ada Lampiran' }}
                </span>
                <span class="text-[11px] text-slate-400 font-medium block">
                    {{ $leave->certificate_name ?: 'Bukan kategori sakit atau nihil' }}
                </span>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-100">
                <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider block">Waktu Pengajuan</span>
                <span class="text-base font-bold text-slate-800 block mt-1">
                    {{ $leave->created_at ? $leave->created_at->translatedFormat('d F Y, H:i') : '—' }}
                </span>
                <span class="text-[11px] text-slate-400 font-medium block">WIB (Asia/Jakarta)</span>
            </div>
        </div>
    </div>

    {{-- Detail Alasan & Matriks Keputusan Harian --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Sisi Kiri (5 Cols): Alasan & Viewer Berkas --}}
        <div class="lg:col-span-5 space-y-6">
            {{-- Alasan Pengajuan --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-3">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xs">
                        ✍️
                    </span>
                    <h2 class="text-sm font-bold text-slate-900">Alasan Permohonan</h2>
                </div>
                <p class="text-xs text-slate-700 leading-relaxed m-0 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    {{ $leave->reason }}
                </p>
            </div>

            {{-- Dokumen Surat Keterangan Dokter --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-purple-50 text-purple-700 flex items-center justify-center font-bold text-xs">
                            📑
                        </span>
                        <h2 class="text-sm font-bold text-slate-900">Berkas Surat Dokter</h2>
                    </div>
                </div>

                @if($leave->certificate_path)
                    <div class="p-4 rounded-2xl bg-purple-50/50 border border-purple-200/70 space-y-3">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center text-lg shrink-0">
                                📄
                            </div>
                            <div class="min-w-0 flex-1">
                                <strong class="text-xs font-bold text-slate-900 block truncate">
                                    {{ $leave->certificate_name ?: 'surat-keterangan-dokter' }}
                                </strong>
                                <span class="text-[11px] text-purple-700 font-semibold block mt-0.5">
                                    Dokumen Resmi Terverifikasi
                                </span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <a
                                href="{{ route('leave.certificate', $leave->id) }}"
                                target="_blank"
                                class="w-full py-2.5 px-4 rounded-xl bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold shadow-xs transition-colors flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                <span>Unduh / Buka Dokumen Penuh ↗</span>
                            </a>
                        </div>
                    </div>
                @else
                    <div class="p-6 text-center bg-slate-50/70 rounded-2xl border border-dashed border-slate-200 text-xs text-slate-400">
                        Tidak ada berkas lampiran yang disertakan pada permohonan ini.
                    </div>
                @endif
            </div>

            {{-- Catatan Peninjau Terdahulu Jika Ada --}}
            @if($leave->review_note)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-2">
                    <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                        <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center font-bold text-xs">
                            💬
                        </span>
                        <h2 class="text-sm font-bold text-slate-900">Catatan Evaluasi Peninjau</h2>
                    </div>
                    <p class="text-xs text-slate-700 leading-relaxed m-0 bg-amber-50/60 p-3.5 rounded-xl border border-amber-200/60">
                        "{{ $leave->review_note }}"
                    </p>
                </div>
            @endif
        </div>

        {{-- Sisi Kanan (7 Cols): Matriks Hari & Form Keputusan Manajer --}}
        <div class="lg:col-span-7 space-y-6">
            {{-- Tabel / Rincian Keputusan per Hari --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xs">
                            🗓️
                        </span>
                        <h2 class="text-sm font-bold text-slate-900">Matriks Status per Hari</h2>
                    </div>
                    <span class="text-xs font-bold text-slate-500">
                        {{ $leave->days->count() }} Hari Diajukan
                    </span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($leave->days->sortBy('date') as $day)
                        @php
                            $dayStatusBadge = [
                                'approved' => ['label' => 'Disetujui', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                'rejected' => ['label' => 'Ditolak', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
                                'pending' => ['label' => 'Menunggu', 'class' => 'bg-slate-100 text-slate-600 border-slate-200'],
                            ][$day->status] ?? ['label' => $day->status, 'class' => 'bg-slate-100 text-slate-600 border-slate-200'];
                        @endphp
                        <div class="py-3 flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 font-bold flex items-center justify-center tabular-nums text-xs">
                                    {{ substr($day->date, 8, 2) }}
                                </span>
                                <div>
                                    <strong class="text-slate-800 block">
                                        {{ \Carbon\Carbon::parse($day->date)->translatedFormat('l, d F Y') }}
                                    </strong>
                                    <span class="text-[11px] text-slate-400">
                                        {{ $day->paid ? 'Gaji Tetap Dibayar (Paid Leave)' : 'Tidak Dibayar (Unpaid)' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if($day->status === 'approved')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $day->paid ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $day->paid ? 'Paid' : 'Unpaid' }}
                                    </span>
                                @endif
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $dayStatusBadge['class'] }}">
                                    {{ $dayStatusBadge['label'] }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 py-3 text-center">Tidak ada rincian tanggal.</p>
                    @endforelse
                </div>
            </div>

            {{-- Formulir Keputusan Peninjau Manajerial --}}
            @if($canReview)
                <div class="bg-white rounded-2xl border-2 border-emerald-600/30 shadow-xs p-6 sm:p-7 space-y-5">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-xl bg-emerald-700 text-white flex items-center justify-center font-bold text-sm">
                            ⚖️
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Keputusan Peninjauan Manajerial</h2>
                            <p class="text-xs text-slate-500">Tentukan persetujuan per tanggal dan status pembayaran gaji.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('leave.review', $leave->id) }}" class="space-y-5">
                        @csrf

                        <div class="p-4 bg-emerald-50/70 rounded-2xl border border-emerald-200 text-xs text-emerald-900 leading-relaxed space-y-1">
                            <strong class="block font-bold">Panduan Keputusan:</strong>
                            <p class="m-0">Centang kotak pada tanggal yang disetujui. Tanggal yang tidak dicentang otomatis akan ditolak oleh sistem. Untuk izin sakit, 2 hari pertama yang disetujui otomatis berbayar (Paid).</p>
                        </div>

                        {{-- Daftar Centang Hari --}}
                        <div class="space-y-2.5">
                            @foreach($leave->days->sortBy('date') as $d)
                                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-xs hover:border-slate-300 transition-colors">
                                    <label class="flex items-center gap-2.5 font-bold text-slate-800 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="approved_dates[]"
                                            value="{{ $d->date }}"
                                            class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                        >
                                        <span>Setujui: {{ \Carbon\Carbon::parse($d->date)->translatedFormat('l, d M Y') }}</span>
                                    </label>

                                    @if($leave->type === 'leave')
                                        <label class="flex items-center gap-1.5 font-semibold text-slate-600 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                name="paid_dates[]"
                                                value="{{ $d->date }}"
                                                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                            >
                                            <span class="text-[11px]">Dibayar (Paid)</span>
                                        </label>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Catatan Peninjau --}}
                        <div>
                            <label for="review-note" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Catatan / Alasan Keputusan <span class="text-rose-500">* (Min. 5 karakter)</span>
                            </label>
                            <textarea
                                name="review_note"
                                id="review-note"
                                rows="3"
                                required
                                minlength="5"
                                maxlength="1000"
                                placeholder="Tuliskan pertimbangan atau instruksi manajerial terkait permohonan ini..."
                                class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('review_note') border-rose-300 bg-rose-50/50 @enderror"
                            >{{ old('review_note') }}</textarea>
                            @error('review_note')
                                <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="w-full py-3.5 px-6 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Simpan Keputusan Evaluasi</span>
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
