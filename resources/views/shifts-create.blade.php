@extends('layouts.app')
@section('title', 'Buat Jadwal Shift Baru')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Breadcrumb Navigasi --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-emerald-700 transition-colors">Beranda</a>
        <span>/</span>
        <a href="{{ route('shifts') }}" class="hover:text-emerald-700 transition-colors">Jadwal Shift</a>
        <span>/</span>
        <span class="text-slate-900 font-bold">Buat Jadwal Baru</span>
    </nav>

    {{-- Header Halaman & Tombol Kembali --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Penjadwalan Operasional</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Buat Jadwal Shift Baru
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Alokasikan jadwal kerja harian atau buat jadwal rutin otomatis per rentang tanggal untuk seluruh staf cabang.
            </p>
        </div>

        <a
            href="{{ route('shifts') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider shadow-2xs transition-all self-start sm:self-auto"
        >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            <span>Kembali ke Jadwal</span>
        </a>
    </div>

    @if($errors->has('shift'))
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-3">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <strong class="text-xs font-bold text-rose-800 uppercase tracking-wider block">Gagal Membuat Shift</strong>
                <p class="text-xs text-rose-700 mt-0.5">{{ $errors->first('shift') }}</p>
            </div>
        </div>
    @endif

    {{-- Formulir Tambah Shift --}}
    <form method="POST" action="{{ route('shifts.store') }}" class="space-y-6" id="shift-create-form">
        @csrf

        @php
            $currentMode = old('mode', request('mode', 'single'));
            $isAdmin = \App\Support\Access::admin();
        @endphp

        {{-- PILIHAN MODE PENJADWALAN --}}
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-800">Pilih Mode Penjadwalan</h2>
                    <p class="text-xs text-slate-500">Tentukan apakah ingin membuat shift tunggal (1 hari) atau jadwal rutin massal untuk jangka waktu tertentu.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                {{-- Opsi 1: Single --}}
                <label
                    class="relative flex items-start gap-3.5 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 hover:border-emerald-500 hover:bg-emerald-50/20"
                    :class="mode === 'single' ? 'border-emerald-600 bg-emerald-50/40 shadow-xs' : 'border-slate-200 bg-slate-50/50'"
                    onclick="switchMode('single')"
                    id="label-mode-single"
                >
                    <input
                        type="radio"
                        name="mode"
                        value="single"
                        id="radio-mode-single"
                        class="mt-1 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                        {{ $currentMode === 'single' ? 'checked' : '' }}
                        onchange="switchMode('single')"
                    >
                    <div class="text-xs">
                        <span class="block font-bold text-slate-900 text-sm mb-0.5">Shift Tunggal (1 Hari)</span>
                        <span class="text-slate-500 leading-relaxed block">Jadwalkan satu orang staf pada tanggal dan jam tertentu secara spesifik.</span>
                    </div>
                </label>

                {{-- Opsi 2: Recurring / Rutin Massal --}}
                <label
                    class="relative flex items-start gap-3.5 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 hover:border-emerald-500 hover:bg-emerald-50/20"
                    :class="mode === 'recurring' ? 'border-emerald-600 bg-emerald-50/40 shadow-xs' : 'border-slate-200 bg-slate-50/50'"
                    onclick="switchMode('recurring')"
                    id="label-mode-recurring"
                >
                    <input
                        type="radio"
                        name="mode"
                        value="recurring"
                        id="radio-mode-recurring"
                        class="mt-1 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                        {{ $currentMode === 'recurring' ? 'checked' : '' }}
                        onchange="switchMode('recurring')"
                    >
                    <div class="text-xs">
                        <span class="block font-bold text-slate-900 text-sm mb-0.5 flex items-center gap-2">
                            <span>Jadwal Rutin & Massal</span>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">Pragmatis</span>
                        </span>
                        <span class="text-slate-500 leading-relaxed block">Buat jadwal berulang (misal: Senin–Jumat selama 1 bulan) untuk banyak staf atau seluruh cabang sekaligus.</span>
                    </div>
                </label>
            </div>
        </div>

        {{-- KARTU 1: Cabang Penugasan --}}
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-100 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                        1
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Cabang Penugasan</h2>
                        <p class="text-xs text-slate-500">Pilih cabang operasional tempat shift ini berlaku.</p>
                    </div>
                </div>
                <div id="badge-branch-sync-note" class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200/80 px-2.5 py-1 rounded-lg {{ $currentMode === 'recurring' ? 'hidden' : '' }}">
                    ⚡ Cabang otomatis mengikuti staf yang Anda pilih
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5">
                <div>
                    @php
                        $branchOpts = $branches->map(fn($b) => [
                            'value' => (string)$b->id,
                            'label' => $b->name,
                            'sublabel' => 'Kode: ' . $b->code,
                        ]);
                    @endphp
                    <x-searchable-select
                        name="branch_id"
                        id="shift-branch-select"
                        label="Cabang Penugasan *"
                        :value="old('branch_id', auth()->user()->branch_id)"
                        :options="$branchOpts"
                        placeholder="Pilih cabang operasional..."
                        searchPlaceholder="Ketik nama cabang..."
                    />
                    @error('branch_id')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- KARTU 2: Karyawan Bertugas --}}
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                    2
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Pilihan Karyawan</h2>
                    <p class="text-xs text-slate-500">Pilih karyawan yang dialokasikan ke jadwal kerja ini.</p>
                </div>
            </div>

            {{-- Tampilan Mode Tunggal --}}
            <div id="section-employee-single" class="{{ $currentMode === 'recurring' ? 'hidden' : '' }} space-y-4">
                {{-- Toggle Jadwalkan Sebagai Slot Kosong (Open Shift) --}}
                <div class="p-3.5 bg-amber-50/70 border border-amber-200/90 rounded-xl flex items-start gap-3">
                    <input
                        type="checkbox"
                        name="is_open_slot"
                        id="toggle-is-open-slot"
                        value="1"
                        class="w-4 h-4 text-amber-600 rounded border-slate-300 focus:ring-amber-500 cursor-pointer mt-0.5"
                        {{ old('is_open_slot') ? 'checked' : '' }}
                        onchange="toggleOpenSlotMode(this.checked)"
                    >
                    <div class="flex-1">
                        <label for="toggle-is-open-slot" class="text-xs font-bold text-amber-950 cursor-pointer block">
                            Jadwalkan Sebagai Slot Kosong (Open Shift / Belum Ditugaskan)
                        </label>
                        <p class="text-[11px] text-amber-800 mt-0.5 leading-relaxed">
                            Shift ini tidak terikat pada satu karyawan tertentu. Karyawan aktif level setingkat dapat mengajukan <strong>Ganti Shift</strong> atau <strong>Ganti Libur</strong> untuk mengisi slot ini dengan persetujuan Super Admin / HRD.
                        </p>
                    </div>
                </div>

                {{-- Pilihan Posisi / Level Setingkat untuk Slot Kosong --}}
                <div id="section-open-slot-position" class="{{ old('is_open_slot') ? '' : 'hidden' }} space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Level Jabatan / Posisi Setingkat</label>
                    <select name="position_id" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <option value="">-- Terbuka Untuk Semua Level Staf --</option>
                        @if(isset($positions))
                            @foreach($positions as $pos)
                                <option value="{{ $pos->id }}" @selected(old('position_id') == $pos->id)>{{ $pos->name }}</option>
                            @endforeach
                        @endif
                    </select>
                    <p class="text-[11px] text-slate-500">Pilih level posisi jika jadwal ini dikhususkan untuk jabatan tertentu.</p>
                </div>

                <div id="container-single-user-select" class="{{ old('is_open_slot') ? 'hidden' : '' }}">
                    @php
                        $peopleOpts = $people->map(fn($p) => [
                            'value' => (string)$p->id,
                            'label' => $p->name,
                            'sublabel' => ($p->username ? '@' . $p->username . ' • ' : '') . ($p->position?->name ?? 'Staf') . ' • ' . ($p->branch?->name ?? 'Semua Cabang'),
                            'branch_id' => (string)($p->branch_id ?? ''),
                        ]);
                    @endphp
                    <x-searchable-select
                        name="user_id"
                        id="shift-user-select"
                        label="Pilih Satu Karyawan *"
                        :value="old('user_id')"
                        :options="$peopleOpts"
                        placeholder="Cari atau pilih nama karyawan..."
                        searchPlaceholder="Ketik nama karyawan..."
                    />
                    @error('user_id')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Tampilan Mode Rutin / Massal --}}
            <div id="section-employee-recurring" class="{{ $currentMode === 'recurring' ? '' : 'hidden' }} space-y-5">
                {{-- Toggle Pilih Semua Karyawan Cabang --}}
                <div class="p-4 bg-emerald-50/70 border border-emerald-200/90 rounded-2xl flex items-start sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5">
                            <input
                                type="checkbox"
                                name="select_all_branch"
                                id="select_all_branch"
                                value="1"
                                class="w-4 h-4 text-emerald-600 rounded-sm border-slate-300 focus:ring-emerald-500 cursor-pointer"
                                {{ old('select_all_branch') ? 'checked' : '' }}
                                onchange="toggleSelectAllBranch(this.checked)"
                            >
                        </div>
                        <div>
                            <label for="select_all_branch" class="text-xs font-bold text-slate-900 cursor-pointer block">
                                Jadwalkan Seluruh Karyawan Aktif di Cabang Ini
                            </label>
                            <p class="text-[11px] text-emerald-800 mt-0.5">
                                Sistem otomatis mendaftarkan semua staf aktif cabang terpilih tanpa perlu memilih manual satu per satu.
                            </p>
                        </div>
                    </div>
                    <span id="badge-branch-count" class="shrink-0 px-2.5 py-1 bg-white border border-emerald-200 text-emerald-800 text-[11px] font-bold rounded-lg shadow-2xs">
                        0 Karyawan Cabang
                    </span>
                </div>

                {{-- Checklist Multi Karyawan (jika tidak pilih semua) --}}
                <div id="custom-employee-list-container" class="{{ old('select_all_branch') ? 'opacity-50 pointer-events-none' : '' }} space-y-3">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Pilih Karyawan Spesifik <span class="text-rose-500">*</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                onclick="selectAllFilteredEmployees(true)"
                                class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 cursor-pointer hover:underline"
                            >
                                Centang Semua
                            </button>
                            <span class="text-slate-300">•</span>
                            <button
                                type="button"
                                onclick="selectAllFilteredEmployees(false)"
                                class="text-[11px] font-semibold text-slate-500 hover:text-slate-700 cursor-pointer hover:underline"
                            >
                                Hapus Centang
                            </button>
                        </div>
                    </div>

                    {{-- Kotak Pencarian Karyawan Cepat --}}
                    <div class="relative">
                        <input
                            type="text"
                            id="search-employee-bulk"
                            placeholder="Ketik untuk memfilter nama karyawan..."
                            onkeyup="filterEmployeeList(this.value)"
                            class="w-full h-10 pl-9 pr-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all"
                        >
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>

                    {{-- Daftar Checkbox Karyawan --}}
                    @php
                        $selectedUserIds = (array) old('user_ids', []);
                    @endphp
                    <div
                        id="employee-checkbox-grid"
                        class="max-h-56 overflow-y-auto p-3 bg-slate-50 rounded-xl border border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-2"
                    >
                        @foreach($people as $p)
                            <label
                                class="employee-checkbox-item flex items-center gap-2.5 p-2 bg-white rounded-lg border border-slate-200/80 hover:border-emerald-300 cursor-pointer transition-colors"
                                data-branch-id="{{ $p->branch_id }}"
                                data-name="{{ strtolower($p->name) }}"
                            >
                                <input
                                    type="checkbox"
                                    name="user_ids[]"
                                    value="{{ $p->id }}"
                                    class="user-id-checkbox w-4 h-4 text-emerald-600 rounded-sm border-slate-300 focus:ring-emerald-500 cursor-pointer"
                                    {{ in_array((string)$p->id, $selectedUserIds, true) ? 'checked' : '' }}
                                    onchange="updateSelectedCount()"
                                >
                                <div class="text-xs truncate">
                                    <span class="font-bold text-slate-800 block truncate">{{ $p->name }}</span>
                                    <span class="text-[10px] text-slate-500 block truncate">
                                        {{ $p->position?->title ?? 'Staf' }} • {{ $p->branch?->name ?? 'Semua Cabang' }}
                                    </span>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-500 px-1">
                        <span id="selected-employees-count">0 karyawan dipilih</span>
                        <span>Hanya staf dari cabang terpilih yang ditampilkan</span>
                    </div>

                    @error('user_ids')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="p-3.5 bg-emerald-50/50 border border-emerald-200/70 rounded-xl flex items-start gap-2.5">
                <svg class="w-4 h-4 text-emerald-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-xs text-emerald-900 leading-relaxed">
                    <strong>Pencegahan Konflik Cerdas:</strong> Bila seorang karyawan sudah memiliki shift lain di hari/jam yang bertabrakan, sistem secara otomatis melewati jadwal tersebut tanpa membatalkan shift hari lainnya.
                </div>
            </div>
        </div>

        {{-- KARTU 3: Tanggal & Pola Jadwal --}}
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                    3
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Tanggal & Pola Hari</h2>
                    <p class="text-xs text-slate-500">Tentukan tanggal pelaksanaan atau rentang tanggal dan hari aktif kerja.</p>
                </div>
            </div>

            {{-- Mode Single: Satu Tanggal --}}
            <div id="section-date-single" class="{{ $currentMode === 'recurring' ? 'hidden' : '' }}">
                <label for="shift-date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                    Tanggal Shift <span class="text-rose-500">*</span>
                </label>
                <input
                    type="date"
                    name="date"
                    id="shift-date"
                    value="{{ old('date', now('Asia/Jakarta')->toDateString()) }}"
                    class="w-full sm:w-1/2 h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('date') border-rose-300 bg-rose-50/50 @enderror"
                >
                @error('date')
                    <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Mode Recurring: Rentang Tanggal & Hari Aktif --}}
            <div id="section-date-recurring" class="{{ $currentMode === 'recurring' ? '' : 'hidden' }} space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    {{-- Tanggal Mulai --}}
                    <div>
                        <label for="shift-start-date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Tanggal Mulai Rentang <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="start_date"
                            id="shift-start-date"
                            value="{{ old('start_date', now('Asia/Jakarta')->toDateString()) }}"
                            class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('start_date') border-rose-300 bg-rose-50/50 @enderror"
                        >
                        @error('start_date')
                            <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tanggal Selesai --}}
                    <div>
                        <label for="shift-end-date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Tanggal Selesai Rentang <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="end_date"
                            id="shift-end-date"
                            value="{{ old('end_date', now('Asia/Jakarta')->addDays(30)->toDateString()) }}"
                            class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('end_date') border-rose-300 bg-rose-50/50 @enderror"
                        >
                        <span class="text-[10px] text-slate-400 mt-1 block">Maksimal rentang penjadwalan sekaligus adalah 90 hari.</span>
                        @error('end_date')
                            <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Checklist Hari Aktif --}}
                <div class="space-y-3 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Hari Kerja Aktif dalam Sepekan <span class="text-rose-500">*</span>
                        </label>
                        <div class="flex items-center gap-1.5 text-xs">
                            <span class="text-slate-400 font-medium mr-1 text-[11px]">Pola:</span>
                            <button
                                type="button"
                                onclick="setDaysPreset([1, 2, 3, 4, 5])"
                                class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 text-[11px] font-semibold rounded-lg transition-colors cursor-pointer"
                            >
                                Sen–Jum (5 Hari)
                            </button>
                            <button
                                type="button"
                                onclick="setDaysPreset([1, 2, 3, 4, 5, 6])"
                                class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 text-[11px] font-semibold rounded-lg transition-colors cursor-pointer"
                            >
                                Sen–Sab (6 Hari)
                            </button>
                            <button
                                type="button"
                                onclick="setDaysPreset([1, 2, 3, 4, 5, 6, 7])"
                                class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 text-[11px] font-semibold rounded-lg transition-colors cursor-pointer"
                            >
                                Semua Hari
                            </button>
                        </div>
                    </div>

                    @php
                        $daysMap = [
                            1 => 'Senin',
                            2 => 'Selasa',
                            3 => 'Rabu',
                            4 => 'Kamis',
                            5 => 'Jumat',
                            6 => 'Sabtu',
                            7 => 'Minggu',
                        ];
                        $selectedDays = (array) old('days_of_week', [1, 2, 3, 4, 5]);
                    @endphp
                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2">
                        @foreach($daysMap as $dayNum => $dayName)
                            <label
                                class="day-checkbox-label flex items-center justify-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all text-xs font-bold {{ in_array($dayNum, $selectedDays) ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                                id="label-day-{{ $dayNum }}"
                            >
                                <input
                                    type="checkbox"
                                    name="days_of_week[]"
                                    value="{{ $dayNum }}"
                                    class="day-checkbox w-4 h-4 text-emerald-600 rounded-sm border-slate-300 focus:ring-emerald-500 cursor-pointer"
                                    {{ in_array($dayNum, $selectedDays) ? 'checked' : '' }}
                                    onchange="syncDayLabel({{ $dayNum }})"
                                >
                                <span>{{ $dayName }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('days_of_week')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- KARTU 4: Jam Kerja & Opsi Persetujuan --}}
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-6 sm:p-7 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm flex items-center justify-center border border-emerald-200/60">
                        4
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Jam Kerja & Validasi</h2>
                        <p class="text-xs text-slate-500">Tentukan jam masuk, jam pulang, dan status persetujuan jadwal.</p>
                    </div>
                </div>

                {{-- Preset Cepat Jam Kerja --}}
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="text-slate-400 font-semibold mr-1 text-[11px]">Preset Jam:</span>
                    <button type="button" onclick="setShiftPreset('08:00', '17:00')" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-semibold rounded-lg transition-colors cursor-pointer">
                        Pagi (08:00-17:00)
                    </button>
                    <button type="button" onclick="setShiftPreset('13:00', '21:00')" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-semibold rounded-lg transition-colors cursor-pointer">
                        Siang (13:00-21:00)
                    </button>
                    <button type="button" onclick="setShiftPreset('21:00', '05:00')" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-700 font-semibold rounded-lg transition-colors cursor-pointer">
                        Malam (21:00-05:00)
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                {{-- Jam Mulai --}}
                <div>
                    <label for="shift-start-time" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Jam Mulai <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="time"
                        name="start_time"
                        id="shift-start-time"
                        required
                        value="{{ old('start_time', '08:00') }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('start_time') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('start_time')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Jam Selesai --}}
                <div>
                    <label for="shift-end-time" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Jam Selesai <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="time"
                        name="end_time"
                        id="shift-end-time"
                        required
                        value="{{ old('end_time', '17:00') }}"
                        class="w-full h-11 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all @error('end_time') border-rose-300 bg-rose-50/50 @enderror"
                    >
                    @error('end_time')
                        <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Opsi Khusus Super Admin: Langsung Setujui Jadwal (Auto-Approve) --}}
            @if($isAdmin)
                <div id="section-auto-approve" class="p-4 bg-emerald-50/80 border border-emerald-200/90 rounded-2xl flex items-start gap-3">
                    <input
                        type="checkbox"
                        name="auto_approve"
                        id="auto_approve"
                        value="1"
                        class="w-4 h-4 text-emerald-600 rounded-sm border-slate-300 focus:ring-emerald-500 cursor-pointer mt-0.5"
                        {{ old('auto_approve', 1) ? 'checked' : '' }}
                    >
                    <div class="text-xs">
                        <label for="auto_approve" class="font-bold text-slate-900 cursor-pointer block">
                            Langsung Setujui Jadwal (Status: Approved)
                        </label>
                        <p class="text-[11px] text-emerald-800 mt-0.5 leading-relaxed">
                            Sebagai Super Admin, Anda dapat mengaktifkan jadwal langsung tanpa status draf sehingga staf dapat langsung melakukan absensi pada jadwal tersebut.
                        </p>
                    </div>
                </div>
            @endif

            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs text-slate-600 flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Pergantian hari (shift lintas tengah malam) didukung secara otomatis oleh sistem.</span>
                </span>
                <span class="text-[11px] font-mono text-slate-400">WIB (Asia/Jakarta)</span>
            </div>
        </div>

        {{-- Tombol Aksi Simpan & Batal --}}
        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-2">
            <a
                href="{{ route('shifts') }}"
                class="w-full sm:w-auto px-6 py-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs uppercase tracking-wider text-center transition-all shadow-2xs"
            >
                Batal
            </a>
            <button
                type="submit"
                id="submit-btn"
                class="w-full sm:w-auto px-7 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition-all flex items-center justify-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                </svg>
                <span id="submit-btn-text">
                    {{ $currentMode === 'recurring' ? 'Generate Jadwal Rutin Massal' : 'Simpan Draf Shift' }}
                </span>
            </button>
        </div>
    </form>
</div>

<script>
    let currentMode = '{{ $currentMode }}';

    window.toggleOpenSlotMode = function(checked) {
        const posSec = document.getElementById('section-open-slot-position');
        const userSec = document.getElementById('container-single-user-select');
        if (posSec) posSec.classList.toggle('hidden', !checked);
        if (userSec) userSec.classList.toggle('hidden', checked);
    };

    function switchMode(mode) {
        currentMode = mode;
        const radioSingle = document.getElementById('radio-mode-single');
        const radioRecurring = document.getElementById('radio-mode-recurring');
        const labelSingle = document.getElementById('label-mode-single');
        const labelRecurring = document.getElementById('label-mode-recurring');

        const secEmpSingle = document.getElementById('section-employee-single');
        const secEmpRecurring = document.getElementById('section-employee-recurring');
        const secDateSingle = document.getElementById('section-date-single');
        const secDateRecurring = document.getElementById('section-date-recurring');
        const secAutoApprove = document.getElementById('section-auto-approve');
        const submitBtnText = document.getElementById('submit-btn-text');

        const dateSingleInput = document.getElementById('shift-date');
        const startDateInput = document.getElementById('shift-start-date');
        const endDateInput = document.getElementById('shift-end-date');

        if (mode === 'single') {
            if (radioSingle) radioSingle.checked = true;
            if (labelSingle) {
                labelSingle.classList.add('border-emerald-600', 'bg-emerald-50/40', 'shadow-xs');
                labelSingle.classList.remove('border-slate-200', 'bg-slate-50/50');
            }
            if (labelRecurring) {
                labelRecurring.classList.remove('border-emerald-600', 'bg-emerald-50/40', 'shadow-xs');
                labelRecurring.classList.add('border-slate-200', 'bg-slate-50/50');
            }

            if (secEmpSingle) secEmpSingle.classList.remove('hidden');
            if (secEmpRecurring) secEmpRecurring.classList.add('hidden');
            if (secDateSingle) secDateSingle.classList.remove('hidden');
            if (secDateRecurring) secDateRecurring.classList.add('hidden');

            const badgeBranchSync = document.getElementById('badge-branch-sync-note');
            if (badgeBranchSync) badgeBranchSync.classList.remove('hidden');

            if (dateSingleInput) dateSingleInput.required = true;
            if (startDateInput) startDateInput.required = false;
            if (endDateInput) endDateInput.required = false;

            if (submitBtnText) submitBtnText.textContent = 'Simpan Draf Shift';
        } else {
            if (radioRecurring) radioRecurring.checked = true;
            if (labelRecurring) {
                labelRecurring.classList.add('border-emerald-600', 'bg-emerald-50/40', 'shadow-xs');
                labelRecurring.classList.remove('border-slate-200', 'bg-slate-50/50');
            }
            if (labelSingle) {
                labelSingle.classList.remove('border-emerald-600', 'bg-emerald-50/40', 'shadow-xs');
                labelSingle.classList.add('border-slate-200', 'bg-slate-50/50');
            }

            if (secEmpSingle) secEmpSingle.classList.add('hidden');
            if (secEmpRecurring) secEmpRecurring.classList.remove('hidden');
            if (secDateSingle) secDateSingle.classList.add('hidden');
            if (secDateRecurring) secDateRecurring.classList.remove('hidden');
            if (secAutoApprove) secAutoApprove.classList.remove('hidden');

            const badgeBranchSync = document.getElementById('badge-branch-sync-note');
            if (badgeBranchSync) badgeBranchSync.classList.add('hidden');

            if (dateSingleInput) dateSingleInput.required = false;
            if (startDateInput) startDateInput.required = true;
            if (endDateInput) endDateInput.required = true;

            if (submitBtnText) submitBtnText.textContent = 'Generate Jadwal Rutin Massal';
        }
    }

    function toggleSelectAllBranch(checked) {
        const container = document.getElementById('custom-employee-list-container');
        if (container) {
            if (checked) {
                container.classList.add('opacity-50', 'pointer-events-none');
            } else {
                container.classList.remove('opacity-50', 'pointer-events-none');
            }
        }
    }

    function setShiftPreset(start, end) {
        const startInput = document.getElementById('shift-start-time');
        const endInput = document.getElementById('shift-end-time');
        if (startInput && endInput) {
            startInput.value = start;
            endInput.value = end;
        }
    }

    function setDaysPreset(days) {
        for (let i = 1; i <= 7; i++) {
            const checkbox = document.querySelector(`input[name="days_of_week[]"][value="${i}"]`);
            if (checkbox) {
                checkbox.checked = days.includes(i);
                syncDayLabel(i);
            }
        }
    }

    function syncDayLabel(dayNum) {
        const label = document.getElementById(`label-day-${dayNum}`);
        const checkbox = document.querySelector(`input[name="days_of_week[]"][value="${dayNum}"]`);
        if (label && checkbox) {
            if (checkbox.checked) {
                label.classList.add('border-emerald-600', 'bg-emerald-50', 'text-emerald-800');
                label.classList.remove('border-slate-200', 'bg-white', 'text-slate-600');
            } else {
                label.classList.remove('border-emerald-600', 'bg-emerald-50', 'text-emerald-800');
                label.classList.add('border-slate-200', 'bg-white', 'text-slate-600');
            }
        }
    }

    function getSelectedBranchId() {
        const branchSelect = document.querySelector('select[name="branch_id"], input[name="branch_id"]');
        return branchSelect ? branchSelect.value : '';
    }

    function filterEmployeesByBranch() {
        const branchId = getSelectedBranchId();
        const items = document.querySelectorAll('.employee-checkbox-item');
        let branchCount = 0;

        items.forEach(item => {
            const itemBranch = item.getAttribute('data-branch-id');
            if (!branchId || itemBranch === branchId || !itemBranch) {
                item.style.display = 'flex';
                branchCount++;
            } else {
                item.style.display = 'none';
                const input = item.querySelector('.user-id-checkbox');
                if (input) input.checked = false;
            }
        });

        const badge = document.getElementById('badge-branch-count');
        if (badge) {
            badge.textContent = `${branchCount} Karyawan Cabang`;
        }
        updateSelectedCount();
    }

    function filterEmployeeList(query) {
        const q = query.toLowerCase().trim();
        const branchId = getSelectedBranchId();
        const items = document.querySelectorAll('.employee-checkbox-item');

        items.forEach(item => {
            const itemBranch = item.getAttribute('data-branch-id');
            const itemName = item.getAttribute('data-name') || '';
            const matchBranch = !branchId || itemBranch === branchId || !itemBranch;
            const matchQuery = !q || itemName.includes(q);

            if (matchBranch && matchQuery) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function selectAllFilteredEmployees(select) {
        const items = document.querySelectorAll('.employee-checkbox-item');
        items.forEach(item => {
            if (item.style.display !== 'none') {
                const input = item.querySelector('.user-id-checkbox');
                if (input) input.checked = select;
            }
        });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.user-id-checkbox:checked');
        const countSpan = document.getElementById('selected-employees-count');
        if (countSpan) {
            countSpan.textContent = `${checkedBoxes.length} karyawan dipilih`;
        }
    }

    function filterSingleEmployeesByBranch() {
        const branchId = getSelectedBranchId();
        const userWrapper = document.getElementById('shift-user-select-wrapper');
        if (!userWrapper) return;

        const userInput = document.getElementById('shift-user-select');
        const currentUserId = userInput ? userInput.value : '';
        let isCurrentValid = false;

        userWrapper.querySelectorAll('[data-select-option]').forEach(opt => {
            const empBranch = opt.getAttribute('data-branch-id');
            const matches = !branchId || empBranch === branchId || !empBranch;
            opt.dataset.filteredOut = matches ? 'false' : 'true';
            opt.classList.toggle('hidden', !matches);

            if (matches && opt.getAttribute('data-value') === currentUserId) {
                isCurrentValid = true;
            }
        });

        if (currentUserId && !isCurrentValid && branchId) {
            userInput.value = '';
            const labelEl = userWrapper.querySelector('[data-select-label]');
            if (labelEl) {
                labelEl.textContent = 'Cari atau pilih nama karyawan...';
                labelEl.className = 'truncate text-slate-400';
            }
            userWrapper.querySelectorAll('[data-select-option]').forEach(o => {
                o.setAttribute('aria-selected', 'false');
                o.className = 'px-3 py-2 rounded-xl flex items-center justify-between gap-2 cursor-pointer transition-colors text-slate-700 hover:bg-slate-100';
            });
        }
    }

    let isSyncingBranch = false;
    function syncBranchFromEmployee() {
        if (currentMode !== 'single' || isSyncingBranch) return;
        const userInput = document.getElementById('shift-user-select');
        if (!userInput || !userInput.value) return;

        const userWrapper = document.getElementById('shift-user-select-wrapper');
        const selectedOpt = userWrapper?.querySelector(`[data-select-option][data-value="${userInput.value}"]`);
        const branchId = selectedOpt?.getAttribute('data-branch-id');

        if (branchId) {
            const currentBranchId = getSelectedBranchId();
            if (currentBranchId !== branchId) {
                isSyncingBranch = true;
                const branchWrapper = document.getElementById('shift-branch-select-wrapper');
                const branchOpt = branchWrapper?.querySelector(`[data-select-option][data-value="${branchId}"]`);
                if (branchOpt) {
                    branchOpt.click();
                }
                isSyncingBranch = false;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        switchMode(currentMode);
        filterEmployeesByBranch();
        filterSingleEmployeesByBranch();

        const branchInput = document.getElementById('shift-branch-select');
        if (branchInput) {
            branchInput.addEventListener('change', () => {
                filterEmployeesByBranch();
                if (!isSyncingBranch) {
                    filterSingleEmployeesByBranch();
                }
            });
        }

        const userInput = document.getElementById('shift-user-select');
        if (userInput) {
            userInput.addEventListener('change', () => {
                syncBranchFromEmployee();
            });
        }
    });
</script>
@endsection
