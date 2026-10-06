@props([
    'name',
    'id' => null,
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => 'Pilih opsi...',
    'searchPlaceholder' => 'Ketik untuk mencari...',
    'required' => false,
    'disabled' => false,
    'size' => 'md',
])

@php
    $inputId = $id ?? $name;
    $selectedValue = $value ?? old($name);
    
    // Normalisasi opsi ke format terstandar: [['value' => ..., 'label' => ..., 'sublabel' => ...]]
    $normalizedOptions = [];
    foreach ($options as $key => $opt) {
        if (is_array($opt) || is_object($opt)) {
            $opt = (array) $opt;
            $normalizedOptions[] = [
                'value' => (string) ($opt['value'] ?? $key),
                'label' => (string) ($opt['label'] ?? $opt['name'] ?? ''),
                'sublabel' => (string) ($opt['sublabel'] ?? $opt['code'] ?? ''),
            ];
        } else {
            $normalizedOptions[] = [
                'value' => (string) $key,
                'label' => (string) $opt,
                'sublabel' => '',
            ];
        }
    }

    $selectedOption = collect($normalizedOptions)->first(fn($o) => (string)$o['value'] === (string)$selectedValue);
    $displayLabel = $selectedOption ? $selectedOption['label'] : $placeholder;
    $hasSelected = !is_null($selectedOption);

    $heightClass = match($size) {
        'sm' => 'h-8 text-xs px-2.5',
        'lg' => 'h-12 text-sm px-4',
        default => 'h-10 text-xs sm:text-sm px-3.5',
    };
@endphp

<div class="searchable-select-wrapper space-y-1 relative" data-searchable-select id="{{ $inputId }}-wrapper">
    @if($label)
        <label for="{{ $inputId }}-trigger" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
            {{ $label }}
            @if($required)
                <span class="text-rose-500 font-bold">*</span>
            @endif
        </label>
    @endif

    {{-- Hidden Input yang dikirim saat Form Submit --}}
    <input
        type="hidden"
        name="{{ $name }}"
        id="{{ $inputId }}"
        value="{{ $selectedValue }}"
        data-select-hidden-input
        @if($required) required @endif
    >

    {{-- Tombol Pemicu Dropdown --}}
    <button
        type="button"
        id="{{ $inputId }}-trigger"
        data-select-trigger
        aria-haspopup="listbox"
        aria-expanded="false"
        @if($disabled) disabled @endif
        class="w-full flex items-center justify-between gap-2 rounded-xl bg-white border border-slate-200 text-left font-medium transition-all cursor-pointer focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed shadow-2xs {{ $heightClass }}"
    >
        <span data-select-label class="truncate {{ $hasSelected ? 'text-slate-900 font-semibold' : 'text-slate-400' }}">
            {{ $displayLabel }}
        </span>

        <span class="flex items-center gap-1 shrink-0 text-slate-400 pointer-events-none">
            <svg class="w-4 h-4 transition-transform duration-200 chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </span>
    </button>

    {{-- Menu Popover Dropdown (Floating) --}}
    <div
        data-select-dropdown
        class="hidden absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200/90 rounded-2xl shadow-xl shadow-slate-950/10 overflow-hidden animate-in fade-in zoom-in-95 duration-100"
    >
        {{-- Kolom Input Pencarian Cepat --}}
        <div class="p-2 border-b border-slate-100 bg-slate-50/60">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input
                    type="search"
                    data-select-search-input
                    placeholder="{{ $searchPlaceholder }}"
                    class="w-full h-8 pl-8 pr-2.5 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:outline-hidden focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition-all"
                    autocomplete="off"
                >
            </div>
        </div>

        {{-- Daftar Opsi --}}
        <ul
            data-select-options-list
            role="listbox"
            class="max-h-56 overflow-y-auto p-1 text-xs space-y-0.5 divide-y divide-transparent focus:outline-hidden"
        >
            @forelse($normalizedOptions as $opt)
                @php
                    $isSelected = (string)$opt['value'] === (string)$selectedValue;
                @endphp
                <li
                    role="option"
                    aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                    data-select-option
                    data-value="{{ $opt['value'] }}"
                    data-label="{{ $opt['label'] }}"
                    class="px-3 py-2 rounded-xl flex items-center justify-between gap-2 cursor-pointer transition-colors {{ $isSelected ? 'bg-emerald-50 text-emerald-900 font-bold' : 'text-slate-700 hover:bg-slate-100' }}"
                >
                    <div class="truncate">
                        <span class="block truncate">{{ $opt['label'] }}</span>
                        @if(!empty($opt['sublabel']))
                            <span class="block text-[10px] text-slate-400 font-normal truncate">{{ $opt['sublabel'] }}</span>
                        @endif
                    </div>

                    @if($isSelected)
                        <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    @endif
                </li>
            @empty
                <li class="px-3 py-4 text-center text-xs text-slate-400 italic">
                    Tidak ada opsi tersedia
                </li>
            @endforelse
        </ul>

        {{-- State Pencarian Tidak Ditemukan --}}
        <div data-select-no-results class="hidden p-4 text-center text-xs text-slate-400 italic">
            Tidak ada data yang sesuai pencarian
        </div>
    </div>
</div>
