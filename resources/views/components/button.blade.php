@props([
    'variant' => 'primary', // primary, secondary, success, destructive, ghost
    'size' => 'md',         // sm, md, lg
    'type' => 'button',     // button, submit, reset
    'href' => null,
    'disabled' => false,
    'icon' => null,
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium transition-colors cursor-pointer disabled:opacity-50 disabled:pointer-events-none select-none focus:outline-hidden';

    $sizeClasses = match($size) {
        'sm' => 'h-8 px-3 text-xs gap-1.5 rounded-lg',
        'lg' => 'h-12 px-6 text-sm font-bold gap-2.5 rounded-xl',
        default => 'h-10 px-4 text-xs font-bold uppercase tracking-wider gap-2 rounded-xl',
    };

    $variantClasses = match($variant) {
        'secondary' => 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200/90 shadow-xs focus:ring-2 focus:ring-slate-300/30 active:bg-slate-100',
        'success' => 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs focus:ring-2 focus:ring-emerald-500/20 active:bg-emerald-800 border border-emerald-600',
        'destructive' => 'bg-rose-600 hover:bg-rose-700 text-white shadow-xs focus:ring-2 focus:ring-rose-500/20 active:bg-rose-800 border border-rose-600',
        'ghost' => 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 active:bg-slate-200 border border-transparent',
        default => 'bg-emerald-700 hover:bg-emerald-800 text-white shadow-xs focus:ring-2 focus:ring-emerald-500/20 active:bg-emerald-900 border border-emerald-700',
    };

    $classes = "{$baseClasses} {$sizeClasses} {$variantClasses}";
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <span class="shrink-0">{!! $icon !!}</span>
        @endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <span class="shrink-0">{!! $icon !!}</span>
        @endif
        <span>{{ $slot }}</span>
    </button>
@endif
