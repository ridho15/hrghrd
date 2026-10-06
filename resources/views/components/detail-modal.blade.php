@props([
    'id',
    'title',
    'subtitle' => null,
    'badge' => null,
    'badgeColor' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'maxWidth' => '2xl', // md, lg, xl, 2xl, 3xl, 4xl
])

@php
    $widthClass = match($maxWidth) {
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        default => 'max-w-2xl',
    };
@endphp

<div
    id="{{ $id }}"
    data-modal-container
    class="hidden fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
>
    {{-- Backdrop Hitam Semi-Transparan dengan Efek Blur --}}
    <div
        class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity cursor-pointer"
        data-close-modal
        title="Klik di luar untuk menutup"
    ></div>

    {{-- Wrapper Vertikal Tengah --}}
    <div class="min-h-full flex items-center justify-center p-3 sm:p-6">
        <div class="relative w-full {{ $widthClass }} bg-white rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden z-10 animate-in fade-in zoom-in-95 duration-150">
            {{-- Header Modal --}}
            <div class="p-5 sm:p-6 border-b border-slate-100 flex items-start justify-between gap-4 bg-slate-50/50 shrink-0">
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5">
                        <h3 id="{{ $id }}-title" class="text-base sm:text-lg font-extrabold text-slate-900 tracking-tight m-0">
                            {{ $title }}
                        </h3>
                        @if($badge)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $badgeColor }}">
                                {{ $badge }}
                            </span>
                        @endif
                    </div>
                    @if($subtitle)
                        <p class="text-xs text-slate-500 m-0 leading-relaxed">{{ $subtitle }}</p>
                    @endif
                </div>

                {{-- Tombol Tutup Silang (X) --}}
                <button
                    type="button"
                    data-close-modal
                    class="w-8 h-8 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-200/70 flex items-center justify-center transition-colors cursor-pointer shrink-0 focus:outline-hidden"
                    aria-label="Tutup modal"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            {{-- Body Modal --}}
            <div class="p-5 sm:p-6 overflow-y-auto space-y-4 flex-1">
                {{ $slot }}
            </div>

            {{-- Footer Modal (Opsional) --}}
            @if(isset($footer))
                <div class="p-4 sm:p-5 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2.5 shrink-0">
                    {{ $footer }}
                </div>
            @else
                <div class="p-3 sm:p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end shrink-0">
                    <button
                        type="button"
                        data-close-modal
                        class="h-9 px-4 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer shadow-xs"
                    >
                        Tutup
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
