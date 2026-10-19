@extends('layouts.app')
@section('title', 'Buat Draf Kontrak Kerja · ' . $employee->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('contracts.index') }}" class="hover:text-emerald-700 transition-colors">Kontrak Kerja</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Buat Draf Kontrak</span>
    </nav>

    {{-- Header --}}
    <div class="pb-4 border-b border-slate-200/80">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            Penyusunan Kontrak Kerja (PKWT)
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            Menyiapkan naskah perjanjian kerja resmi untuk <strong>{{ $employee->name }}</strong> berdasarkan template aktif v{{ $template->activeVersion?->version_number }}.
        </p>
    </div>

    {{-- Form Card --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8">
        <form method="POST" action="{{ route('contracts.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="user_id" value="{{ $employee->id }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nomor Registrasi Kontrak <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="document_number" value="{{ old('document_number', $defaultDocNumber) }}" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Tanggal Efektif Kontrak <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="start_date" value="{{ old('start_date', $employee->hired_at?->format('Y-m-d') ?: date('Y-m-d')) }}" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Naskah Perjanjian Kerja (Markdown / Teks) <span class="text-rose-500">*</span>
                </label>
                <textarea name="rendered_content" rows="18" required
                    class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 leading-relaxed focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 custom-scrollbar">{{ old('rendered_content', $rendered) }}</textarea>
                <p class="text-[11px] text-slate-400 mt-1">Naskah telah diisi otomatis dengan fakta kerja terverifikasi karyawan.</p>
            </div>

            <div class="pt-4 border-t border-slate-200/80 flex items-center justify-between gap-4">
                <a href="{{ route('contracts.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    Simpan Draf Kontrak Kerja →
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
