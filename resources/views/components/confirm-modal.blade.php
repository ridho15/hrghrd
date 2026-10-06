@props([
    'id' => 'global-confirm-modal',
    'title' => 'Konfirmasi Tindakan',
    'message' => 'Apakah Anda yakin ingin melanjutkan tindakan ini? Tindakan ini mungkin tidak dapat dibatalkan.',
    'confirmText' => 'Ya, Lanjutkan',
    'cancelText' => 'Batal',
    'variant' => 'danger', // danger, primary, warning
])

<div
    id="{{ $id }}"
    data-confirm-modal
    class="hidden fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
>
    {{-- Backdrop Hitam Semi-Transparan dengan Efek Blur --}}
    <div
        class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity cursor-pointer"
        data-close-confirm
        title="Klik di luar untuk menutup"
    ></div>

    {{-- Wrapper Vertikal Tengah --}}
    <div class="min-h-full flex items-center justify-center p-4 sm:p-6">
        <div class="relative w-full max-w-md bg-white rounded-3xl border border-slate-200/90 shadow-2xl flex flex-col overflow-hidden z-10 animate-in fade-in zoom-in-95 duration-150">
            
            {{-- Konten Utama Modal Konfirmasi --}}
            <div class="p-6 text-center space-y-4">
                {{-- Icon Badge Dinamis --}}
                <div class="mx-auto flex items-center justify-center">
                    {{-- Icon Danger (Merah/Rose) --}}
                    <span data-icon-danger class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center shadow-xs">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </span>

                    {{-- Icon Primary (Emerald) --}}
                    <span data-icon-primary class="hidden w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shadow-xs">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </span>

                    {{-- Icon Warning (Amber) --}}
                    <span data-icon-warning class="hidden w-12 h-12 rounded-2xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center shadow-xs">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </span>
                </div>

                <div class="space-y-1.5">
                    <h3 id="{{ $id }}-title" data-confirm-title class="text-lg font-extrabold text-slate-900 tracking-tight m-0">
                        {{ $title }}
                    </h3>
                    <p data-confirm-message class="text-xs sm:text-sm text-slate-500 m-0 leading-relaxed">
                        {{ $message }}
                    </p>
                </div>
            </div>

            {{-- Footer Aksi --}}
            <div class="p-4 sm:p-5 border-t border-slate-100 bg-slate-50/60 flex items-center justify-end gap-3 shrink-0">
                <button
                    type="button"
                    data-close-confirm
                    class="h-10 px-4 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer shadow-xs"
                >
                    {{ $cancelText }}
                </button>

                <button
                    type="button"
                    data-confirm-submit
                    class="h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer shadow-xs inline-flex items-center gap-1.5"
                >
                    <span data-confirm-btn-text>{{ $confirmText }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
