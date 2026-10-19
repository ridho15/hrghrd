@extends('layouts.app')
@section('title', 'Bagan Struktur Organisasi')

@push('styles')
<style>
    .tree-node {
        position: relative;
        transition: all 0.2s ease-in-out;
    }
    .drop-active {
        border-color: #059669 !important;
        background-color: #ecfdf5 !important;
        transform: scale(1.02);
        box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);
    }
    .tree-line-horizontal {
        height: 2px;
        background-color: #cbd5e1;
    }
    .tree-line-vertical {
        width: 2px;
        background-color: #cbd5e1;
    }
</style>
@endpush

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <span>Hirarki & Manajemen Posisi</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Bagan Struktur Organisasi
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Visualisasi pohon organisasi dari pimpinan puncak hingga staf. Tarik dan lepas (drag-and-drop) kartu karyawan untuk mutasi atasan dengan validasi anti-siklus.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <button type="button" onclick="openKeyboardReassignModal();" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-2xs transition-colors inline-flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                <span>Pindahkan Atasan (Keyboard/Form)</span>
            </button>
            <a href="{{ route('people.create') }}" class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Tambah Karyawan</span>
            </a>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('org-chart.index') }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-1 flex-wrap">
                <div class="w-full sm:w-64">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau jabatan..."
                        class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                </div>

                <div class="w-full sm:w-56">
                    <select name="branch_id" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <option value="">Semua Cabang Organisasi</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors cursor-pointer">
                    Filter
                </button>

                @if(request()->hasAny(['search', 'branch_id']))
                    <a href="{{ route('org-chart.index') }}" class="text-xs font-bold text-slate-500 hover:text-slate-800">Reset</a>
                @endif
            </div>

            <div class="text-[11px] text-slate-500 hidden md:block">
                💡 <em>Tips: Seret kartu karyawan dan lepaskan di atas calon atasan barunya.</em>
            </div>
        </form>
    </div>

    {{-- Tree Canvas View --}}
    <div class="p-6 sm:p-8 bg-slate-100/70 border border-slate-200/80 rounded-2xl overflow-x-auto min-h-[500px]">
        @if(empty($tree))
            <div class="py-16 text-center text-slate-400">
                <div class="text-3xl mb-2">🏢</div>
                <div class="text-sm font-bold text-slate-700">Tidak ada data bagan organisasi yang ditemukan.</div>
                <div class="text-xs text-slate-500 mt-1">Pastikan terdapat karyawan aktif yang terdaftar dalam sistem.</div>
            </div>
        @else
            <div class="flex flex-col items-center space-y-8 min-w-[700px]">
                @foreach($tree as $rootNode)
                    @include('org-chart.partials.node', ['node' => $rootNode])
                @endforeach
            </div>
        @endif
    </div>

    {{-- Modal: Confirmation & Impact Preview for Reassignment --}}
    <div id="reassign-confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <span class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                    ⇄
                </span>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 m-0">Konfirmasi Perpindahan Atasan</h3>
                    <p class="text-xs text-slate-500 m-0">Periksa rincian dampak mutasi sebelum menyimpan ke riwayat organisasi.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('org-chart.reassign') }}" class="space-y-4">
                @csrf
                <input type="hidden" id="modal-user-id" name="user_id" value="">
                <input type="hidden" id="modal-supervisor-id" name="supervisor_id" value="">

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Karyawan yang Dipindahkan:</span>
                        <strong id="modal-employee-name" class="text-slate-900">-</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Calon Atasan Baru:</span>
                        <strong id="modal-supervisor-name" class="text-emerald-700">-</strong>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Tanggal Efektif Mutasi <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="effective_from" value="{{ date('Y-m-d') }}" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Alasan Perpindahan / Mutasi <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="reason" required placeholder="Contoh: Restrukturisasi Tim / Promosi / Rotasi Divisi"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900">
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeReassignModal();" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs">
                        Simpan Perpindahan Struktur
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Keyboard Reassignment Form (Accessibility Fallback) --}}
    <div id="keyboard-reassign-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-base font-extrabold text-slate-900 m-0">Mutasi Atasan (Mode Formulir / Keyboard)</h3>
                <button type="button" onclick="closeKeyboardReassignModal();" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form method="POST" action="{{ route('org-chart.reassign') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Karyawan yang Dipindahkan <span class="text-rose-500">*</span></label>
                    <select name="user_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                        <option value="">Pilih Karyawan</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->position?->name }} · {{ $e->branch?->name }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Atasan Langsung Baru</label>
                    <select name="supervisor_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                        <option value="">Jadikan Pimpinan Puncak (Akar / Tanpa Atasan)</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->position?->name }} · {{ $e->branch?->name }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tanggal Efektif <span class="text-rose-500">*</span></label>
                    <input type="date" name="effective_from" value="{{ date('Y-m-d') }}" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Alasan Perpindahan <span class="text-rose-500">*</span></label>
                    <input type="text" name="reason" required placeholder="Contoh: Penyesuaian Lini Manajemen" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeKeyboardReassignModal();" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold">Simpan</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    let draggedUserId = null;
    let draggedUserName = null;

    function handleDragStart(e, id, name) {
        draggedUserId = id;
        draggedUserName = name;
        e.dataTransfer.setData('text/plain', id);
        e.dataTransfer.effectAllowed = 'move';
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const card = e.currentTarget;
        if (!card.classList.contains('drop-active')) {
            card.classList.add('drop-active');
        }
    }

    function handleDragLeave(e) {
        e.currentTarget.classList.remove('drop-active');
    }

    function handleDrop(e, targetSupervisorId, targetSupervisorName) {
        e.preventDefault();
        e.currentTarget.classList.remove('drop-active');

        if (!draggedUserId) return;

        // Prevent dropping on self
        if (draggedUserId == targetSupervisorId) {
            alert('Peringatan: Karyawan tidak dapat menjadi atasan untuk dirinya sendiri.');
            return;
        }

        // Open confirm impact modal
        document.getElementById('modal-user-id').value = draggedUserId;
        document.getElementById('modal-supervisor-id').value = targetSupervisorId;
        document.getElementById('modal-employee-name').textContent = draggedUserName;
        document.getElementById('modal-supervisor-name').textContent = targetSupervisorName;
        document.getElementById('reassign-confirm-modal').classList.remove('hidden');
    }

    function closeReassignModal() {
        document.getElementById('reassign-confirm-modal').classList.add('hidden');
    }

    function openKeyboardReassignModal() {
        document.getElementById('keyboard-reassign-modal').classList.remove('hidden');
    }

    function closeKeyboardReassignModal() {
        document.getElementById('keyboard-reassign-modal').classList.add('hidden');
    }
</script>
@endsection
