@extends('layouts.app')
@section('title', 'Master Settings · Dokumen & Tanda Tangan')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Master Settings</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Dokumen & Tanda Tangan Elektronik
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola template draf Offering Letter, NDA, dan Kontrak Kerja tanpa mengubah source code. Kontrol versi dan persetujuan legal.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('master-settings.documents.esign') }}" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-2xs transition-colors inline-flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                <span>Pengaturan Penandatangan & E-Sign</span>
            </a>
        </div>
    </div>

    {{-- Templates Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($templates as $tpl)
            @php
                $activeVer = $tpl->activeVersion;
            @endphp
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono">{{ $tpl->code }}</span>
                        @if($activeVer)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                Versi Aktif: v{{ $activeVer->version_number }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                Belum Terbit
                            </span>
                        @endif
                    </div>

                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 tracking-tight">{{ $tpl->title }}</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ $tpl->type_name }} · Lingkup: {{ $tpl->entity_name ?: 'Seluruh Entitas' }}
                        </p>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1 text-slate-600">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Total Riwayat Versi:</span>
                            <span class="font-bold">{{ $tpl->versions->count() }} versi</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Terakhir Diterbitkan:</span>
                            <span>{{ $activeVer?->published_at ? $activeVer->published_at->translatedFormat('d M Y') : '-' }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <a href="{{ route('master-settings.documents.edit', $tpl->type) }}" class="w-full py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold text-center block shadow-2xs transition-colors">
                        Edit Redaksi & Klausul Template →
                    </a>

                    {{-- Versions Modal trigger / collapsible history --}}
                    <details class="text-xs text-slate-600">
                        <summary class="font-bold text-slate-700 cursor-pointer hover:text-emerald-700 py-1">
                            Lihat Riwayat Versi & Rollback
                        </summary>
                        <div class="mt-2 space-y-1.5 border-t border-slate-100 pt-2 max-h-48 overflow-y-auto">
                            @foreach($tpl->versions as $v)
                                <div class="p-2 rounded-lg bg-slate-50 flex items-center justify-between text-[11px]">
                                    <div>
                                        <strong class="text-slate-900">v{{ $v->version_number }}</strong>
                                        <span class="text-slate-400">({{ $v->status }})</span>
                                        <div class="text-[10px] text-slate-500 truncate max-w-[150px]">{{ $v->change_summary }}</div>
                                    </div>
                                    @if($activeVer && $activeVer->id !== $v->id && in_array($v->status, ['published', 'archived']))
                                        <form method="POST" action="{{ route('master-settings.documents.rollback', $v->id) }}" onsubmit="return confirm('Alihkan versi aktif baru ke v{{ $v->version_number }}?');">
                                            @csrf
                                            <button type="submit" class="px-2 py-1 rounded bg-slate-200 hover:bg-slate-300 text-slate-700 text-[10px] font-bold cursor-pointer">
                                                Rollback
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Legal Governance Notice --}}
    <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 text-xs text-slate-600 space-y-2">
        <div class="font-bold text-slate-900 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Ketentuan Tata Kelola Template & Integritas Dokumen</span>
        </div>
        <p class="leading-relaxed m-0">
            Pembaruan atau publikasi versi baru di Master Settings hanya berlaku untuk calon karyawan atau dokumen yang dibuat <strong>setelah tanggal publikasi</strong>. Dokumen yang sedang berlangsung atau telah ditandatangani tetap mengacu pada snapshot versi asli saat penawaran dikirim, dan berkas PDF/hash kriptografis tidak akan diubah secara in-place.
        </p>
    </div>

</div>
@endsection
