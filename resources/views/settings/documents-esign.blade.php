@extends('layouts.app')
@section('title', 'Konfigurasi E-Sign & Penandatangan Perusahaan')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('master-settings.documents.index') }}" class="hover:text-emerald-700 transition-colors">Master Dokumen</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Pengaturan E-Sign</span>
    </nav>

    {{-- Header --}}
    <div class="pb-4 border-b border-slate-200/80">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Konfigurasi Tanda Tangan Elektronik (E-Sign)
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            Atur identitas penandatangan resmi perusahaan, penyedia sertifikat e-sign, masa berlaku tautan token kandidat, dan webhook audit.
        </p>
    </div>

    {{-- Settings Form --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8">
        <form method="POST" action="{{ route('master-settings.documents.esign.save') }}" class="space-y-6">
            @csrf

            <div class="space-y-4">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 text-xs font-bold text-slate-900 uppercase tracking-wider">
                    <span class="w-5 h-5 rounded-md bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px]">1</span>
                    <span>Pejabat Penandatangan Resmi Perusahaan</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nama Pejabat Penandatangan <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="company_signatory_name" value="{{ old('company_signatory_name', $settings['company_signatory_name']) }}" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Jabatan Resmi Perusahaan <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="company_signatory_title" value="{{ old('company_signatory_title', $settings['company_signatory_title']) }}" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    </div>
                </div>
            </div>

            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 text-xs font-bold text-slate-900 uppercase tracking-wider">
                    <span class="w-5 h-5 rounded-md bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px]">2</span>
                    <span>Penyedia & Parameter Tanda Tangan Elektronik</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Penyedia / Adapter E-Sign <span class="text-rose-500">*</span>
                        </label>
                        <select name="esign_provider" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                            <option value="internal_esign" {{ $settings['esign_provider'] === 'internal_esign' ? 'selected' : '' }}>Internal Digital Signature (Kepatuhan UU ITE & Hash SHA-256)</option>
                            <option value="privy_adapter" {{ $settings['esign_provider'] === 'privy_adapter' ? 'selected' : '' }}>PrivyID Adapter (Tersertifikasi Kominfo)</option>
                            <option value="peruri_adapter" {{ $settings['esign_provider'] === 'peruri_adapter' ? 'selected' : '' }}>Peruri Tera Adapter (BUMN Indonesia)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Masa Berlaku Tautan Penawaran (Hari) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" name="token_expiry_days" value="{{ old('token_expiry_days', $settings['token_expiry_days']) }}" min="1" max="30" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1">
                    <div class="font-bold text-slate-800">Kunci Rahasia Webhook (Webhook Secret):</div>
                    <div class="font-mono text-slate-600 bg-white p-2 rounded-lg border border-slate-200 truncate select-all">
                        {{ $settings['webhook_secret'] }}
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1 m-0">Kunci ini dipakai untuk memverifikasi keaslian signature header payload webhook vendor e-sign eksternal.</p>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200/80 flex items-center justify-between gap-4">
                <a href="{{ route('master-settings.documents.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">
                    Kembali
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    Simpan Konfigurasi E-Sign
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
