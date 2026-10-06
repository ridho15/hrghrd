@props([
    'action' => request()->url(),
    'searchName' => 'search',
    'searchValue' => request('search'),
    'searchPlaceholder' => 'Cari data...',
    'resetUrl' => request()->url(),
])

@php
    $isFiltered = request()->filled($searchName) || 
                  request()->filled('branch_id') || 
                  request()->filled('role') || 
                  request()->filled('status') || 
                  request()->filled('position_id') || 
                  request()->filled('type') || 
                  request()->filled('decision');
@endphp

<div class="p-4 sm:p-5 border-b border-slate-100 bg-slate-50/40">
    <form method="get" action="{{ $action }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5">
        {{-- Sisi Kiri: Search Input & Dynamic Filter Selects --}}
        <div class="flex flex-wrap items-center gap-2.5 flex-1">
            {{-- Input Pencarian --}}
            <div class="relative flex-1 min-w-[200px] sm:min-w-[260px] max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input
                    type="search"
                    name="{{ $searchName }}"
                    value="{{ $searchValue }}"
                    placeholder="{{ $searchPlaceholder }}"
                    data-table-search
                    class="w-full h-9 pl-11 pr-10 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all font-medium"
                >
                <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none">
                    <kbd class="hidden sm:inline-flex px-1.5 py-0.5 text-[10px] font-mono font-bold text-slate-400 bg-slate-100 border border-slate-200 rounded shadow-2xs" title="Tekan / untuk fokus pencarian">/</kbd>
                </div>
            </div>

            {{-- Slot Filter Tambahan (Dropdown Cabang, Role, Status, dll) --}}
            @if(isset($filters))
                {{ $filters }}
            @else
                {{ $slot }}
            @endif

            {{-- Tombol Terapkan / Cari --}}
            <button
                type="submit"
                class="h-9 px-3.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer shadow-xs inline-flex items-center gap-1.5 shrink-0"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <span>Filter</span>
            </button>

            {{-- Tombol Reset Filter (Tampil jika ada filter aktif) --}}
            @if($isFiltered)
                <a
                    href="{{ $resetUrl }}"
                    class="h-9 px-3 rounded-xl bg-white hover:bg-slate-100 text-slate-600 border border-slate-200 text-xs font-semibold transition-colors cursor-pointer shadow-xs inline-flex items-center gap-1 shrink-0"
                    title="Bersihkan seluruh filter"
                >
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Reset</span>
                </a>
            @endif
        </div>

        {{-- Sisi Kanan: Tombol Aksi Tambahan (Misal: Tambah, Impor, Ekspor) --}}
        @if(isset($actions))
            <div class="flex items-center gap-2 shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                {{ $actions }}
            </div>
        @endif
    </form>
</div>
