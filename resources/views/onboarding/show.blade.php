@extends('layouts.app')
@section('title', 'Detail Onboarding · ' . $case->user->name)

@section('content')
<div class="space-y-6">

    {{-- Breadcrumb & Back --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('onboarding.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Kembali ke Daftar Onboarding</span>
                </a>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                {{ $case->user->name }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kandidat {{ $case->position?->name }} · {{ $case->branch?->name }} · Dibuat {{ $case->created_at->translatedFormat('d F Y, H:i') }}
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2.5 flex-wrap">
            {{-- Copy Portal Link --}}
            <button type="button" onclick="navigator.clipboard.writeText('{{ route('onboarding.portal', $case->token) }}'); alert('Tautan portal onboarding berhasil disalin ke clipboard!');"
                class="px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-2xs transition-colors cursor-pointer inline-flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                <span>Salin Tautan Portal Kandidat</span>
            </button>

            @if(in_array($case->status, ['draft', 'menunggu_persetujuan_hr']))
                <form method="POST" action="{{ route('onboarding.approve_offer', $case->id) }}" onsubmit="return confirm('Setujui Offering Letter dan buka tautan penandatanganan elektronik untuk kandidat?');">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>Setujui & Kirim Offering Letter</span>
                    </button>
                </form>
            @endif

            @if($case->status === 'perlu_verifikasi')
                <button type="button" onclick="document.getElementById('revision-modal').classList.remove('hidden');"
                    class="px-3.5 py-2.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-bold shadow-2xs transition-colors cursor-pointer">
                    Minta Perbaikan Berkas
                </button>

                <form method="POST" action="{{ route('onboarding.verify_final', $case->id) }}" onsubmit="return confirm('Verifikasi akhir dan aktifkan akun karyawan resmi?');">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Verifikasi & Selesaikan Onboarding</span>
                    </button>
                </form>
            @endif

            @if(!in_array($case->status, ['selesai', 'dibatalkan']))
                <form method="POST" action="{{ route('onboarding.cancel', $case->id) }}" onsubmit="return confirm('Batalkan kasus onboarding ini? Dokumen yang belum ditandatangani akan dibatalkan.');">
                    @csrf
                    <input type="hidden" name="reason" value="Dibatalkan oleh HR melalui konsol kasus.">
                    <button type="submit" class="px-3 py-2.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold shadow-2xs transition-colors cursor-pointer">
                        Batalkan
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Case Profile & Status Overview --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Candidate Details Card --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-12 h-12 rounded-xl bg-emerald-700 text-white font-extrabold text-lg flex items-center justify-center shrink-0">
                    {{ strtoupper(substr($case->user->name, 0, 2)) }}
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 tracking-tight m-0">{{ $case->user->name }}</h3>
                    <div class="text-xs text-slate-500">{{ $case->user->email }}</div>
                </div>
            </div>

            <div class="text-xs space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Status Alur:</span>
                    <span class="px-2.5 py-0.5 rounded-full font-bold border {{ $case->status_badge_class }}">
                        {{ $case->status_label }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Cabang Penempatan:</span>
                    <span class="font-bold text-slate-800">{{ $case->branch?->name }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Posisi / Jabatan:</span>
                    <span class="font-bold text-slate-800">{{ $case->position?->name }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Atasan Langsung:</span>
                    <span class="font-bold text-slate-800">{{ $case->supervisor?->name ?: 'Pimpinan Puncak' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Gaji Ditawarkan:</span>
                    <span class="font-bold text-emerald-700 font-mono">Rp {{ number_format($case->salary_offered, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Tanggal Mulai:</span>
                    <span class="font-bold text-slate-800">{{ $case->start_date ? $case->start_date->format('d/m/Y') : '-' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Batas Berlaku:</span>
                    <span class="font-bold text-slate-800">{{ $case->offer_valid_until ? $case->offer_valid_until->format('d/m/Y') : '-' }}</span>
                </div>
                @if($case->user->nik_masked)
                    <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                        <span class="text-slate-500">NIK (Masked):</span>
                        <span class="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded">{{ $case->user->nik_masked }}</span>
                    </div>
                @endif
            </div>

            <div class="pt-3 border-t border-slate-100">
                <a href="{{ route('onboarding.portal', $case->token) }}" target="_blank" class="w-full py-2.5 rounded-xl border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition-colors inline-flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    <span>Buka Tampilan Portal Kandidat</span>
                </a>
            </div>
        </div>

        {{-- Right: Stepped Progress Cards --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- 1. Offering Letter Card --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs">1</span>
                        <div>
                            <h4 class="text-sm font-extrabold text-slate-900 m-0">Surat Penawaran Kerja (Offering Letter)</h4>
                            <span class="text-[11px] text-slate-500 font-mono">{{ $offeringDoc?->document_number ?: 'Nomor Draf Otomatis' }}</span>
                        </div>
                    </div>
                    <div>
                        @if($offeringDoc?->isSigned())
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                ✓ Selesai Ditandatangani
                            </span>
                        @elseif($offeringDoc?->isPendingSignature())
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                                Menunggu Tanda Tangan Kandidat
                            </span>
                        @else
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-50 text-purple-800 border border-purple-200">
                                Draf (Menunggu Persetujuan HR)
                            </span>
                        @endif
                    </div>
                </div>

                @if($offeringDoc)
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5 font-mono text-slate-700">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Versi Template:</span>
                            <span>v{{ $offeringDoc->templateVersion?->version_number ?: 1 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Integritas Hash (SHA-256):</span>
                            <span class="truncate max-w-xs">{{ $offeringDoc->file_hash }}</span>
                        </div>
                        @if($offeringDoc->signed_at)
                            <div class="flex justify-between text-emerald-800 font-bold">
                                <span>Waktu Penandatanganan:</span>
                                <span>{{ $offeringDoc->signed_at->format('d/m/Y H:i:s T') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <button type="button" onclick="document.getElementById('doc-preview-offer').classList.toggle('hidden');" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 underline cursor-pointer">
                            Tampilkan / Sembunyikan Redaksi Dokumen
                        </button>
                        @if($offeringDoc->isSigned())
                            <span class="text-slate-300">·</span>
                            <a href="{{ route('onboarding.stream_file', ['id' => $case->id, 'type' => 'evidence_offer']) }}" target="_blank" class="text-xs font-bold text-blue-700 hover:text-blue-800 underline">
                                Unduh Evidence Pack (JSON)
                            </a>
                        @endif
                    </div>

                    <div id="doc-preview-offer" class="hidden p-6 sm:p-8 rounded-2xl bg-white border border-slate-200/90 shadow-sm max-h-96 overflow-y-auto custom-scrollbar select-text">
                        <div class="document-legal-body">
                            {!! $offeringDoc->rendered_html !!}
                        </div>
                    </div>
                @endif
            </div>

            {{-- 2. NDA Card --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-800 font-bold flex items-center justify-center text-xs">2</span>
                        <div>
                            <h4 class="text-sm font-extrabold text-slate-900 m-0">Perjanjian Kerahasiaan (Non-Disclosure Agreement)</h4>
                            <span class="text-[11px] text-slate-500 font-mono">{{ $ndaDoc?->document_number ?: 'Terkunci (Menunggu Offering Ditandatangani)' }}</span>
                        </div>
                    </div>
                    <div>
                        @if($ndaDoc?->isSigned())
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                ✓ Selesai Ditandatangani
                            </span>
                        @elseif($ndaDoc?->isPendingSignature())
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                                Siap Ditandatangani Kandidat
                            </span>
                        @else
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                Tahap Belum Terbuka
                            </span>
                        @endif
                    </div>
                </div>

                @if($ndaDoc)
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5 font-mono text-slate-700">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Versi Template:</span>
                            <span>v{{ $ndaDoc->templateVersion?->version_number ?: 1 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Integritas Hash (SHA-256):</span>
                            <span class="truncate max-w-xs">{{ $ndaDoc->file_hash }}</span>
                        </div>
                        @if($ndaDoc->signed_at)
                            <div class="flex justify-between text-emerald-800 font-bold">
                                <span>Waktu Penandatanganan:</span>
                                <span>{{ $ndaDoc->signed_at->format('d/m/Y H:i:s T') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <button type="button" onclick="document.getElementById('doc-preview-nda').classList.toggle('hidden');" class="text-xs font-bold text-blue-700 hover:text-blue-800 underline cursor-pointer">
                            Tampilkan / Sembunyikan Naskah NDA
                        </button>
                        @if($ndaDoc->isSigned())
                            <span class="text-slate-300">·</span>
                            <a href="{{ route('onboarding.stream_file', ['id' => $case->id, 'type' => 'evidence_nda']) }}" target="_blank" class="text-xs font-bold text-blue-700 hover:text-blue-800 underline">
                                Unduh Evidence Pack (JSON)
                            </a>
                        @endif
                    </div>

                    <div id="doc-preview-nda" class="hidden p-6 sm:p-8 rounded-2xl bg-white border border-slate-200/90 shadow-sm max-h-96 overflow-y-auto custom-scrollbar select-text">
                        <div class="document-legal-body">
                            {!! $ndaDoc->rendered_html !!}
                        </div>
                    </div>
                @else
                    <p class="text-xs text-slate-400 italic m-0">
                        Sesuai standar operasional, naskah NDA baru akan otomatis disiapkan setelah Offering Letter selesai ditandatangani kandidat.
                    </p>
                @endif
            </div>

            {{-- 3. KTP Submission Card --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-800 font-bold flex items-center justify-center text-xs">3</span>
                        <div>
                            <h4 class="text-sm font-extrabold text-slate-900 m-0">Dokumen KTP (Identitas Penduduk)</h4>
                            <span class="text-[11px] text-slate-500 font-mono">Privat & Dilindungi UU PDP</span>
                        </div>
                    </div>
                    <div>
                        @if($latestKtp)
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $latestKtp->status === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200' }} border">
                                {{ $latestKtp->status === 'approved' ? '✓ KTP Terverifikasi' : 'Menunggu Review HR' }}
                            </span>
                        @else
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                Belum Diunggah
                            </span>
                        @endif
                    </div>
                </div>

                @if($latestKtp)
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5 text-slate-700">
                        <div class="flex justify-between">
                            <span class="text-slate-400">NIK (Masked):</span>
                            <span class="font-mono font-bold">{{ $latestKtp->id_number_masked }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Nama Tertera:</span>
                            <span class="font-bold">{{ $latestKtp->full_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Ukuran & Format:</span>
                            <span>{{ number_format($latestKtp->file_size / 1024, 1) }} KB ({{ $latestKtp->mime_type }})</span>
                        </div>
                    </div>

                    <div class="pt-1">
                        <a href="{{ route('onboarding.stream_file', ['id' => $case->id, 'type' => 'ktp']) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <span>Buka Berkas KTP Asli (Akses Terotorisasi)</span>
                        </a>
                    </div>
                @else
                    <p class="text-xs text-slate-400 italic m-0">Berkas KTP akan diunggah oleh kandidat setelah menyelesaikan penandatanganan NDA.</p>
                @endif
            </div>

            {{-- 4. Liveness Photo Card --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-purple-100 text-purple-800 font-bold flex items-center justify-center text-xs">4</span>
                        <div>
                            <h4 class="text-sm font-extrabold text-slate-900 m-0">Verifikasi Biometrik Wajah (Liveness)</h4>
                            <span class="text-[11px] text-slate-500 font-mono">Pemeriksaan Anti-Spoofing & Framing Wajah</span>
                        </div>
                    </div>
                    <div>
                        @if($latestLiveness)
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $latestLiveness->result === 'lulus' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200' }} border">
                                Hasil: {{ strtoupper($latestLiveness->result) }} (Skor: {{ $latestLiveness->liveness_score }})
                            </span>
                        @else
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                Belum Diambil
                            </span>
                        @endif
                    </div>
                </div>

                @if($latestLiveness)
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5 text-slate-700">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Penyedia Liveness:</span>
                            <span class="font-mono">{{ $latestLiveness->provider }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Deteksi Spoofing:</span>
                            <span class="{{ $latestLiveness->spoof_detected ? 'text-rose-600 font-bold' : 'text-emerald-700 font-bold' }}">
                                {{ $latestLiveness->spoof_detected ? 'Terdeteksi Potensi Spoofing' : 'Tidak Terdeteksi (Alami)' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Catatan Sistem:</span>
                            <span>{{ $latestLiveness->review_notes }}</span>
                        </div>
                    </div>

                    <div class="pt-1">
                        <a href="{{ route('onboarding.stream_file', ['id' => $case->id, 'type' => 'liveness']) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-bold transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <span>Lihat Foto Wajah Liveness (Akses Terotorisasi)</span>
                        </a>
                    </div>
                @else
                    <p class="text-xs text-slate-400 italic m-0">Pemeriksaan foto wajah liveness dilakukan kandidat setelah mengunggah KTP.</p>
                @endif
            </div>

            {{-- 5. Employment Contract Link (Post Onboarding) --}}
            @if($case->status === 'selesai')
                <div class="p-5 rounded-2xl bg-emerald-50 border border-emerald-200/90 flex items-center justify-between gap-4">
                    <div>
                        <h4 class="text-sm font-extrabold text-emerald-950 m-0">Kontrak Kerja Resmi (PKWT/PKWTT)</h4>
                        <p class="text-xs text-emerald-800 mt-0.5">
                            Karyawan telah aktif. Anda dapat menerbitkan naskah Kontrak Kerja resmi terpisah.
                        </p>
                    </div>
                    <a href="{{ route('contracts.create', $case->user_id) }}" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs shrink-0 transition-colors">
                        Buat Kontrak Kerja →
                    </a>
                </div>
            @endif

        </div>
    </div>

    {{-- Audit Trail Section --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
        <div class="flex items-center gap-2 pb-2 border-b border-slate-100 text-xs font-bold text-slate-900 uppercase tracking-wider">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Jejak Audit & Riwayat Aktivitas Kasus</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 font-bold uppercase text-[10px]">
                        <th class="py-2.5 px-3">Waktu (WIB)</th>
                        <th class="py-2.5 px-3">Aksi</th>
                        <th class="py-2.5 px-3">Pelaku / IP</th>
                        <th class="py-2.5 px-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($auditLogs as $log)
                        <tr>
                            <td class="py-2.5 px-3 text-slate-500 font-mono">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="py-2.5 px-3 font-bold text-slate-800">{{ $log->action }}</td>
                            <td class="py-2.5 px-3 text-slate-600">{{ $log->actor?->name ?: 'Kandidat (Portal)' }} ({{ $log->ip ?: '-' }})</td>
                            <td class="py-2.5 px-3 text-slate-700">{{ $log->reason }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-slate-400">Belum ada jejak audit yang terekam.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal: Request Revision --}}
    <div id="revision-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl">
            <h3 class="text-lg font-bold text-slate-900">Minta Perbaikan Berkas Kepada Kandidat</h3>
            <p class="text-xs text-slate-500">
                Berkas lama tidak akan dihapus demi integritas riwayat audit. Kandidat akan diminta mengunggah ulang item yang dipilih.
            </p>

            <form method="POST" action="{{ route('onboarding.request_revision', $case->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Target Berkas yang Diperbaiki <span class="text-rose-500">*</span></label>
                    <select name="target" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                        <option value="ktp">Dokumen KTP (Foto buram / NIK tidak cocok)</option>
                        <option value="liveness">Foto Wajah Liveness (Pencahayaan kurang / wajah miring)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Instruksi Catatan untuk Kandidat <span class="text-rose-500">*</span></label>
                    <textarea name="notes" rows="3" required placeholder="Jelaskan alasan perbaikan secara sopan dan jelas..."
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('revision-modal').classList.add('hidden');" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold">
                        Kirim Permintaan Perbaikan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
