@extends('layouts.app')
@section('title', 'Kontrak Kerja · ' . $contract->document_number)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Breadcrumb & Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('contracts.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Kembali ke Daftar Kontrak</span>
                </a>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                {{ $contract->document_number }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Karyawan: <strong>{{ $contract->user->name }}</strong> · Posisi: {{ $contract->user->position?->name }} · {{ $contract->user->branch?->name }}
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            @if($contract->status === 'draft')
                <form method="POST" action="{{ route('contracts.send_esign', $contract->id) }}" onsubmit="return confirm('Kirim kontrak kerja ini untuk proses tanda tangan elektronik Para Pihak?');">
                    @csrf
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        <span>Kirim untuk Tanda Tangan Elektronik</span>
                    </button>
                </form>
            @endif

            <button type="button" onclick="window.print();" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-2xs transition-colors cursor-pointer inline-flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Cetak / Unduh PDF</span>
            </button>
        </div>
    </div>

    {{-- Meta & Signers Box --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status Dokumen</span>
            <div>
                @if($contract->isSigned())
                    <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-200 inline-block">
                        ✓ Sah & Ditandatangani
                    </span>
                @elseif($contract->isPendingSignature())
                    <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-blue-50 text-blue-800 border border-blue-200 inline-block">
                        Menunggu Penandatanganan
                    </span>
                @else
                    <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700 border border-slate-200 inline-block">
                        Draf Persiapan
                    </span>
                @endif
            </div>
            <div class="text-[11px] text-slate-400 font-mono mt-1">Hash: {{ substr($contract->file_hash, 0, 16) }}...</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pihak Pertama (Perusahaan)</span>
            <div class="text-xs font-bold text-slate-800">{{ $contract->user->entity_name ?: 'PT HR Group Indonesia' }}</div>
            <div class="text-[11px] text-slate-500">Authorized HR Signatory</div>
        </div>

        <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pihak Kedua (Karyawan)</span>
            <div class="text-xs font-bold text-slate-800">{{ $contract->user->name }}</div>
            <div class="text-[11px] text-slate-500 font-mono">NIK: {{ $contract->user->nik_masked ?: '3171************' }}</div>
        </div>
    </div>

    {{-- Rendered Contract Body (Official Legal Paper Canvas) --}}
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-6 sm:p-12 space-y-6">
        <div class="document-legal-body">
            {!! $contract->rendered_html !!}
        </div>
    </div>

</div>
@endsection
