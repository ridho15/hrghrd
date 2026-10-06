@props([
    'name' => 'password',
    'id' => null,
    'label' => null,
    'placeholder' => '••••••••••••',
    'required' => false,
    'minlength' => null,
    'autocomplete' => 'current-password',
    'value' => null,
    'helper' => null,
    'size' => 'md', // sm, md, lg
])

@php
    $inputId = $id ?? $name;
    $hasError = $errors->has($name);

    $heightClass = match($size) {
        'sm' => 'h-8 text-xs pl-3 pr-10',
        'lg' => 'h-12 text-sm pl-4 pr-12',
        default => 'h-10 text-xs sm:text-sm pl-3.5 pr-11',
    };
@endphp

<div class="password-input-wrapper space-y-1">
    @if($label)
        <label for="{{ $inputId }}" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
            {{ $label }}
            @if($required)
                <span class="text-rose-500 font-bold">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        <input
            id="{{ $inputId }}"
            type="password"
            name="{{ $name }}"
            value="{{ $value ?? old($name) }}"
            placeholder="{{ $placeholder }}"
            autocomplete="{{ $autocomplete }}"
            @if($required) required @endif
            @if($minlength) minlength="{{ $minlength }}" @endif
            {{ $attributes->merge([
                'class' => "w-full rounded-xl bg-slate-50/70 text-slate-900 font-medium placeholder:text-slate-400 focus:bg-white focus:outline-hidden transition-all {$heightClass} " .
                ($hasError 
                    ? 'border border-rose-300 ring-2 ring-rose-500/10 focus:border-rose-500 focus:ring-rose-500/20' 
                    : 'border border-slate-200 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600')
            ]) }}
        >

        <button
            type="button"
            data-toggle-password
            aria-label="Tampilkan kata sandi"
            class="absolute inset-y-0 right-0 pr-3.5 flex items-center cursor-pointer group focus:outline-hidden"
            tabindex="-1"
        >
            {{-- Eye Open (Mode Sembunyi / Password) --}}
            <svg class="w-4 h-4 eye-icon-show text-slate-400 group-hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
            </svg>
            {{-- Eye Slashed (Mode Terlihat / Teks) --}}
            <svg class="w-4 h-4 eye-icon-hide hidden text-emerald-600 group-hover:text-emerald-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path>
            </svg>
        </button>
    </div>

    @if($hasError)
        <p class="text-xs text-rose-600 font-medium flex items-center gap-1 mt-1">
            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @elseif($helper)
        <p class="text-[11px] text-slate-400 mt-1 leading-normal">{{ $helper }}</p>
    @endif
</div>
