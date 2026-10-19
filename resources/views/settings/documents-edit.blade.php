@extends('layouts.app')
@section('title', 'Editor Template · ' . $template->title)

@section('content')
<div class="space-y-6">

    {{-- Breadcrumb --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('master-settings.documents.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Kembali ke Master Dokumen</span>
                </a>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Editor Template: {{ $template->title }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kustomisasi klausul, gunakan variabel data dinamis terdaftar, dan lakukan pratinjau sebelum menerbitkan versi aktif baru.
            </p>
        </div>

        {{-- Version Badge & Status --}}
        @php
            $targetVer = $draftVersion ?: $latestVersion;
        @endphp
        <div class="flex items-center gap-2">
            @if($targetVer)
                <span class="px-3 py-1.5 rounded-xl text-xs font-extrabold bg-slate-100 text-slate-800 border border-slate-200">
                    Sedang Mengedit: Versi {{ $targetVer->version_number }} ({{ strtoupper($targetVer->status) }})
                </span>
            @endif
        </div>
    </div>

    {{-- Registered Variables Bar --}}
    <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs space-y-2">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                Variabel Data Dinamis (Klik untuk Menyisipkan ke Kursor):
            </span>
            <span class="text-[11px] text-slate-400">Variabel yang tidak terdaftar akan otomatis ditolak oleh sistem</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
            @foreach($registeredVariables as $varKey => $varLabel)
                <button type="button" onclick="insertVariable('{{ '{' . $varKey . '}' }}');"
                    class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-[11px] font-mono font-bold transition-colors cursor-pointer"
                    title="{{ $varLabel }}">
                    {{ '{' . $varKey . '}' }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Editor & Live Preview Tabs --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Left: Textarea Editor --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-sm font-extrabold text-slate-900 m-0">Naskah Dokumen Template (Markdown)</h3>
                <span class="text-[11px] text-slate-400">Gunakan format naskah resmi Indonesia</span>
            </div>

            <form id="template-form" method="POST" action="{{ route('master-settings.documents.draft', $template->type) }}" class="space-y-4">
                @csrf
                <div>
                    <textarea id="template-editor" name="content" rows="22" required
                        class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 leading-relaxed focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 custom-scrollbar">{{ old('content', $targetVer?->content) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Ringkasan Catatan Perubahan (Changelog):
                    </label>
                    <input type="text" name="change_summary" value="{{ old('change_summary', $targetVer?->change_summary) }}" placeholder="Contoh: Penyesuaian klausul kerahasiaan sesuai UU PDP"
                        class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900">
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors cursor-pointer">
                        Simpan Perubahan Draf
                    </button>
                    <button type="button" onclick="refreshLivePreview();" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span>Segarkan Pratinjau</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Right: Live Rendered Preview & Actions --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4 flex flex-col justify-between">
            <div class="space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <h3 class="text-sm font-extrabold text-slate-900 m-0">Pratinjau Hasil Akhir (Live Preview)</h3>
                    </div>
                    <span id="validation-indicator" class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                        Valid
                    </span>
                </div>

                {{-- Validation Errors Box --}}
                <div id="preview-errors" class="hidden p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs space-y-1"></div>

                {{-- Preview Box --}}
                <div id="preview-container" class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 leading-relaxed font-mono whitespace-pre-wrap max-h-[460px] overflow-y-auto custom-scrollbar select-text shadow-inner">
                    Memuat pratinjau...
                </div>
            </div>

            {{-- Workflow Transition Buttons --}}
            @if($targetVer)
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Alur Persetujuan & Penerbitan Versi</div>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($targetVer->isDraft())
                            <form method="POST" action="{{ route('master-settings.documents.submit_review', $targetVer->id) }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold shadow-xs cursor-pointer">
                                    Ajukan Review Legal →
                                </button>
                            </form>
                        @endif

                        @if(in_array($targetVer->status, ['draft', 'in_review']))
                            <form method="POST" action="{{ route('master-settings.documents.approve', $targetVer->id) }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 rounded-xl bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold shadow-xs cursor-pointer">
                                    Setujui Versi Ini (Approval) ✓
                                </button>
                            </form>
                        @endif

                        @if(in_array($targetVer->status, ['draft', 'in_review', 'approved']))
                            <form method="POST" action="{{ route('master-settings.documents.publish', $targetVer->id) }}" onsubmit="return confirm('Terbitkan versi ini sebagai versi aktif untuk seluruh dokumen baru?');">
                                @csrf
                                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs cursor-pointer">
                                    Terbitkan Versi Aktif (Publish) 🚀
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    </div>

</div>

<script>
    function insertVariable(varText) {
        const textarea = document.getElementById('template-editor');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;

        textarea.value = text.substring(0, start) + varText + text.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + varText.length;
        textarea.focus();
        refreshLivePreview();
    }

    async function refreshLivePreview() {
        const content = document.getElementById('template-editor').value;
        const container = document.getElementById('preview-container');
        const errorBox = document.getElementById('preview-errors');
        const indicator = document.getElementById('validation-indicator');

        try {
            const res = await fetch("{{ route('master-settings.documents.preview') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ content: content })
            });

            const data = await res.json();
            container.textContent = data.rendered;

            if (data.valid) {
                indicator.textContent = 'Valid';
                indicator.className = 'text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200';
                errorBox.classList.add('hidden');
            } else {
                indicator.textContent = 'Ada Kesalahan';
                indicator.className = 'text-[11px] font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200';
                errorBox.innerHTML = '<strong>Peringatan Validasi:</strong><ul class="list-disc pl-4 mt-1">' + data.errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
                errorBox.classList.remove('hidden');
            }
        } catch (err) {
            console.error('Preview error:', err);
        }
    }

    document.addEventListener('DOMContentLoaded', refreshLivePreview);
</script>
@endsection
