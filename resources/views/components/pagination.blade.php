@props(['paginator'])

@if($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $paginator->total() > 0)
    <nav role="navigation" aria-label="Navigasi Paginasi" class="p-4 sm:p-5 border-t border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        {{-- Kiri: Teks Counter Ringkasan Data --}}
        <div class="text-xs text-slate-500 font-medium">
            <span>Menampilkan</span>
            <strong class="font-bold text-slate-900 tabular-nums">{{ $paginator->firstItem() ?? 0 }}</strong>
            <span>sampai</span>
            <strong class="font-bold text-slate-900 tabular-nums">{{ $paginator->lastItem() ?? 0 }}</strong>
            <span>dari total</span>
            <strong class="font-bold text-slate-900 tabular-nums">{{ $paginator->total() }}</strong>
            <span>data (10 data per halaman)</span>
        </div>

        {{-- Kanan: Kontrol Navigasi Halaman --}}
        @if($paginator->hasPages())
            <div class="inline-flex items-center gap-1.5 self-center sm:self-auto">
                {{-- Tombol Halaman Sebelumnya --}}
                @if($paginator->onFirstPage())
                    <span class="h-8 px-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-400 text-xs font-semibold inline-flex items-center gap-1 cursor-not-allowed opacity-60 select-none">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        <span class="hidden sm:inline">Sebelumnya</span>
                    </span>
                @else
                    <a
                        href="{{ $paginator->previousPageUrl() }}"
                        rel="prev"
                        class="h-8 px-2.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold inline-flex items-center gap-1 transition-colors shadow-2xs select-none"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        <span class="hidden sm:inline">Sebelumnya</span>
                    </a>
                @endif

                {{-- Nomor-Nomor Halaman --}}
                <div class="hidden sm:inline-flex items-center gap-1">
                    @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                        @if($page == $paginator->currentPage())
                            <span class="w-8 h-8 rounded-lg bg-emerald-700 text-white font-bold text-xs inline-flex items-center justify-center border border-emerald-700 tabular-nums shadow-xs">
                                {{ $page }}
                            </span>
                        @else
                            <a
                                href="{{ $url }}"
                                class="w-8 h-8 rounded-lg bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs inline-flex items-center justify-center border border-slate-200 transition-colors tabular-nums shadow-2xs"
                            >
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                </div>

                {{-- Tombol Halaman Berikutnya --}}
                @if($paginator->hasMorePages())
                    <a
                        href="{{ $paginator->nextPageUrl() }}"
                        rel="next"
                        class="h-8 px-2.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold inline-flex items-center gap-1 transition-colors shadow-2xs select-none"
                    >
                        <span class="hidden sm:inline">Berikutnya</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                @else
                    <span class="h-8 px-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-400 text-xs font-semibold inline-flex items-center gap-1 cursor-not-allowed opacity-60 select-none">
                        <span class="hidden sm:inline">Berikutnya</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </span>
                @endif
            </div>
        @endif
    </nav>
@endif
