@props([
    'name',
    'id' => null,
    'label' => null,
    'type' => 'text',
    'required' => false,
    'placeholder' => '',
    'value' => null,
    'helper' => null,
    'size' => 'md', // sm, md, lg
    'min' => null,
    'max' => null,
    'step' => null,
    'icon' => null,
])

@php
    $inputId = $id ?? $name;
    $hasError = $errors->has($name);

    $heightClass = match($size) {
        'sm' => 'h-8 text-xs ' . ($icon ? 'pl-9 pr-2.5' : 'px-2.5'),
        'lg' => 'h-12 text-sm ' . ($icon ? 'pl-12 pr-4' : 'px-4'),
        default => 'h-10 text-xs sm:text-sm ' . ($icon ? 'pl-11 pr-3.5' : 'px-3.5'),
    };
@endphp

<div class="space-y-1">
    @if($label)
        <label for="{{ $inputId }}" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
            {{ $label }}
            @if($required)
                <span class="text-rose-500 font-bold">*</span>
            @endif
        </label>
    @endif

    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        <div class="relative">
            @if($icon)
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    {!! $icon !!}
                </div>
            @endif
            <input
                id="{{ $inputId }}"
                type="{{ $type }}"
                name="{{ $name }}"
                value="{{ $value ?? old($name) }}"
                placeholder="{{ $placeholder }}"
                @if($required) required @endif
                @if(!is_null($min)) min="{{ $min }}" @endif
                @if(!is_null($max)) max="{{ $max }}" @endif
                @if(!is_null($step)) step="{{ $step }}" @endif
                {{ $attributes->merge([
                    'class' => "w-full rounded-xl bg-slate-50/70 text-slate-900 font-medium placeholder:text-slate-400 focus:bg-white focus:outline-hidden transition-all {$heightClass} " .
                    ($hasError 
                        ? 'border border-rose-300 ring-2 ring-rose-500/10 focus:border-rose-500 focus:ring-rose-500/20' 
                        : 'border border-slate-200 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600')
                ]) }}
            >
        </div>
    @endif

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
