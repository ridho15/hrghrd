@extends('layouts.app')
@section('title', 'Kalender Jadwal Shift')

@section('content')
<div class="space-y-6">
    {{-- Header & Navigasi Tab --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Visualisasi Roster & Penjadwalan Tim</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Kalender Jadwal Shift
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Pantau distribusi penugasan kerja per hari, deteksi kekurangan tenaga kerja, dan kelola pertukaran shift secara visual.
            </p>
        </div>

        @if(in_array(auth()->user()->role, ['admin', 'manager']))
            <div class="flex items-center gap-2.5 shrink-0">
                <a
                    href="{{ route('shifts.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>+ Buat Jadwal Shift Baru</span>
                </a>
            </div>
        @endif
    </div>

    {{-- Tab Switcher: Kalender vs Tabel vs Permohonan Pergantian --}}
    <div class="flex items-center gap-2 border-b border-slate-200">
        <a
            href="{{ route('shifts.calendar', request()->query()) }}"
            class="inline-flex items-center gap-2 px-4 py-3 border-b-2 border-emerald-600 text-emerald-800 font-bold text-sm -mb-px"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <span>Kalender Shift</span>
            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-800">{{ $shifts->count() }}</span>
        </a>
        <a
            href="{{ route('shifts') }}"
            class="inline-flex items-center gap-2 px-4 py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 font-medium text-sm transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            <span>Tabel Daftar</span>
        </a>
        <a
            href="{{ route('shifts.exchanges') }}"
            class="inline-flex items-center gap-2 px-4 py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 font-medium text-sm transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            <span>Permohonan Pergantian Shift</span>
        </a>
    </div>

    {{-- Toolbar Kalender: Navigasi Bulan & Filter Terpadu --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        {{-- Navigasi Bulan --}}
        <div class="flex items-center gap-2.5">
            <a
                href="{{ route('shifts.calendar', array_merge(request()->query(), ['month' => $prevMonth])) }}"
                class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                title="Bulan Sebelumnya"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
            </a>

            <div class="text-center sm:text-left min-w-[180px]">
                <h2 class="text-lg font-black text-slate-900 tracking-tight">
                    {{ $currentMonth->translatedFormat('F Y') }}
                </h2>
                <span class="text-xs text-slate-400 font-medium">
                    Total {{ $shifts->count() }} shift terjadwal
                </span>
            </div>

            <a
                href="{{ route('shifts.calendar', array_merge(request()->query(), ['month' => $nextMonth])) }}"
                class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                title="Bulan Berikutnya"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
            </a>

            <a
                href="{{ route('shifts.calendar', array_merge(request()->query(), ['month' => now('Asia/Jakarta')->format('Y-m')])) }}"
                class="px-3 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors ml-1"
            >
                Hari Ini
            </a>
        </div>

        {{-- Form Filter Kalender --}}
        <form method="get" action="{{ route('shifts.calendar') }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="month" value="{{ $monthStr }}">

            {{-- Filter Cabang (Admin) --}}
            @if(auth()->user()->role === 'admin')
                <div class="w-44 sm:w-48">
                    @php
                        $calBranchOpts = collect([['value' => '', 'label' => 'Semua Cabang', 'sublabel' => '']])
                            ->merge($branches->map(fn($b) => ['value' => (string)$b->id, 'label' => $b->name, 'sublabel' => $b->code]));
                    @endphp
                    <x-searchable-select
                        name="branch_id"
                        id="filter-cal-branch"
                        size="sm"
                        :value="request('branch_id')"
                        :options="$calBranchOpts"
                        placeholder="Semua Cabang"
                        searchPlaceholder="Cari cabang..."
                    />
                </div>
            @endif

            {{-- Filter Karyawan --}}
            <div class="w-44 sm:w-48">
                @php
                    $calPeopleOpts = collect([['value' => '', 'label' => 'Semua Karyawan', 'sublabel' => '']])
                        ->merge($people->map(fn($p) => ['value' => (string)$p->id, 'label' => $p->name, 'sublabel' => $p->email]));
                @endphp
                <x-searchable-select
                    name="user_id"
                    id="filter-cal-user"
                    size="sm"
                    :value="request('user_id')"
                    :options="$calPeopleOpts"
                    placeholder="Semua Karyawan"
                    searchPlaceholder="Cari karyawan..."
                />
            </div>

            {{-- Filter Status --}}
            <select
                name="status"
                class="h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs"
            >
                <option value="">Semua Status</option>
                <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                <option value="draft" @selected(request('status') === 'draft')>Draf</option>
            </select>

            <button
                type="submit"
                class="h-8 px-3 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition-colors cursor-pointer"
            >
                Terapkan
            </button>

            @if(request()->hasAny(['branch_id', 'user_id', 'status']))
                <a
                    href="{{ route('shifts.calendar', ['month' => $monthStr]) }}"
                    class="h-8 px-2.5 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-800 text-xs font-bold inline-flex items-center transition-colors"
                >
                    Reset
                </a>
            @endif
        </form>
    </div>

    {{-- Grid Kalender Bulanan --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        {{-- Header Nama Hari (Senin - Minggu) --}}
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/80 text-center text-xs font-black tracking-wider text-slate-600 uppercase py-3">
            <div>Senin</div>
            <div>Selasa</div>
            <div>Rabu</div>
            <div>Kamis</div>
            <div>Jumat</div>
            <div class="text-emerald-700">Sabtu</div>
            <div class="text-rose-600">Minggu</div>
        </div>

        {{-- Matriks Sel Kalender --}}
        <div class="grid grid-cols-7 auto-rows-fr divide-x divide-y divide-slate-100">
            @php
                $cursor = $gridStart->copy();
                $todayStr = now('Asia/Jakarta')->toDateString();
            @endphp

            @while($cursor->lte($gridEnd))
                @php
                    $dateStr = $cursor->toDateString();
                    $isCurrentMonth = $cursor->isSameMonth($currentMonth);
                    $isToday = $dateStr === $todayStr;
                    $dayShifts = $groupedShifts->get($dateStr, collect());
                    $dayCount = $dayShifts->count();
                @endphp

                <div class="min-h-[130px] sm:min-h-[150px] p-2 flex flex-col justify-between transition-colors {{ $isCurrentMonth ? 'bg-white' : 'bg-slate-50/40 text-slate-400' }} {{ $isToday ? 'ring-2 ring-emerald-500/80 ring-inset bg-emerald-50/10' : '' }}">
                    {{-- Header Sel Tanggal --}}
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs sm:text-sm font-black tabular-nums {{ $isToday ? 'w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center' : ($isCurrentMonth ? 'text-slate-800' : 'text-slate-400') }}">
                                {{ $cursor->day }}
                            </span>
                            @if($isToday)
                                <span class="hidden sm:inline-block px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-emerald-100 text-emerald-800 uppercase tracking-wider">Hari Ini</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-1">
                            @if($dayCount > 0)
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 tabular-nums">
                                    {{ $dayCount }}
                                </span>
                            @endif

                            @if(in_array(auth()->user()->role, ['admin', 'manager']))
                                <a
                                    href="{{ route('shifts.create', ['date' => $dateStr]) }}"
                                    class="w-5 h-5 rounded-md hover:bg-emerald-100 text-slate-400 hover:text-emerald-700 flex items-center justify-center transition-colors text-xs font-bold opacity-0 hover:opacity-100 group-hover:opacity-100"
                                    title="Tambah shift di tanggal ini"
                                >
                                    +
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Daftar Chip Shift di Tanggal Ini --}}
                    <div class="space-y-1.5 flex-1 overflow-y-auto max-h-[120px] pr-0.5">
                        @foreach($dayShifts->take(3) as $shiftItem)
                            @php
                                $startTime = \Carbon\Carbon::parse($shiftItem->start_at)->format('H:i');
                                $endTime = \Carbon\Carbon::parse($shiftItem->end_at)->format('H:i');
                                $isApproved = $shiftItem->status === 'approved';
                                $attStatus = $shiftItem->attendance?->status;

                                if ($attStatus === 'present') {
                                    $chipTheme = 'bg-sky-50 text-sky-900 border-sky-200/90 hover:bg-sky-100';
                                    $dotColor = 'bg-sky-500';
                                    $statusLabel = 'Hadir';
                                } elseif ($attStatus === 'late') {
                                    $chipTheme = 'bg-amber-50 text-amber-900 border-amber-200/90 hover:bg-amber-100';
                                    $dotColor = 'bg-amber-500';
                                    $statusLabel = 'Terlambat';
                                } elseif ($attStatus === 'absent') {
                                    $chipTheme = 'bg-rose-50 text-rose-900 border-rose-200/90 hover:bg-rose-100';
                                    $dotColor = 'bg-rose-500';
                                    $statusLabel = 'Alfa';
                                } elseif ($isApproved) {
                                    $chipTheme = 'bg-emerald-50 text-emerald-900 border-emerald-200/90 hover:bg-emerald-100';
                                    $dotColor = 'bg-emerald-500';
                                    $statusLabel = 'Disetujui';
                                } else {
                                    $chipTheme = 'bg-slate-50 text-slate-800 border-slate-200 hover:bg-slate-100';
                                    $dotColor = 'bg-slate-400';
                                    $statusLabel = 'Draf';
                                }
                            @endphp

                            <button
                                type="button"
                                data-open-modal="modal-shift-{{ $shiftItem->id }}"
                                class="w-full text-left p-1.5 rounded-lg border {{ $chipTheme }} transition-all text-[11px] block shadow-2xs group cursor-pointer"
                            >
                                <div class="flex items-center justify-between gap-1">
                                    <div class="flex items-center gap-1 truncate font-bold">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }} shrink-0"></span>
                                        <span class="truncate">{{ $shiftItem->user?->name ?? 'Karyawan' }}</span>
                                    </div>
                                    <span class="text-[10px] font-mono text-slate-500 shrink-0">{{ $startTime }}</span>
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-slate-500 mt-0.5">
                                    <span class="truncate">{{ $shiftItem->branch?->name }}</span>
                                    <span class="font-semibold">{{ $startTime }}-{{ $endTime }}</span>
                                </div>
                            </button>

                            {{-- Modal Detail & Tindakan Cepat Shift --}}
                            <div
                                id="modal-shift-{{ $shiftItem->id }}"
                                data-modal-container
                                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
                            >
                                <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4">
                                    <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                                        <div>
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-bold {{ $isApproved ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
                                                <span>{{ $statusLabel }} · v{{ $shiftItem->version }}</span>
                                            </span>
                                            <h3 class="text-lg font-black text-slate-900 mt-1">Detail Jadwal Shift Kerja</h3>
                                        </div>
                                        <button
                                            type="button"
                                            data-close-modal
                                            class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>

                                    <div class="space-y-3 text-xs sm:text-sm">
                                        <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                                            <div>
                                                <span class="text-slate-400 text-xs block">Karyawan Bertugas:</span>
                                                <span class="font-bold text-slate-900 block mt-0.5">{{ $shiftItem->user?->name }}</span>
                                                <span class="text-xs text-slate-500 font-mono">{{ $shiftItem->user?->email }}</span>
                                            </div>
                                            <div>
                                                <span class="text-slate-400 text-xs block">Lokasi Cabang:</span>
                                                <span class="font-bold text-slate-900 block mt-0.5">{{ $shiftItem->branch?->name }}</span>
                                                <span class="text-xs text-slate-500 font-mono">Kode: {{ $shiftItem->branch?->code }}</span>
                                            </div>
                                        </div>

                                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                                            <div class="flex justify-between">
                                                <span class="text-slate-500">Tanggal Shift:</span>
                                                <span class="font-bold text-slate-900">{{ \Carbon\Carbon::parse($shiftItem->start_at)->translatedFormat('l, d F Y') }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-slate-500">Waktu Kerja:</span>
                                                <span class="font-bold text-slate-900">{{ $startTime }} – {{ $endTime }} WIB</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-slate-500">Realisasi Presensi:</span>
                                                <span class="font-semibold text-slate-700">
                                                    @if($shiftItem->attendance)
                                                        Masuk: {{ $shiftItem->attendance->checkin_at ? \Carbon\Carbon::parse($shiftItem->attendance->checkin_at)->format('H:i') : '-' }} |
                                                        Keluar: {{ $shiftItem->attendance->checkout_at ? \Carbon\Carbon::parse($shiftItem->attendance->checkout_at)->format('H:i') : '-' }}
                                                    @else
                                                        Belum ada rekaman presensi
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Tombol Aksi Cepat Modal --}}
                                    <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                                        <a
                                            href="{{ route('shifts.show', $shiftItem->id) }}"
                                            class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors inline-flex items-center gap-1.5"
                                        >
                                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            <span>Lihat Detail Penuh</span>
                                        </a>

                                        <div class="flex items-center gap-2">
                                            {{-- Tombol Ajukan Pergantian / Tukar --}}
                                            @if(! $shiftItem->attendance()->exists())
                                                <button
                                                    type="button"
                                                    data-close-modal
                                                    data-open-modal="modal-exchange-shift-{{ $shiftItem->id }}"
                                                    class="px-3.5 py-2 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-800 border border-sky-200 text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                                                >
                                                    <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                                    <span>Ganti/Tukar Shift</span>
                                                </button>
                                            @endif

                                            @if(in_array(auth()->user()->role, ['admin', 'manager']) && ! $shiftItem->attendance()->exists())
                                                <button
                                                    type="button"
                                                    data-close-modal
                                                    data-open-modal="modal-reassign-shift-{{ $shiftItem->id }}"
                                                    class="px-3.5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                                                >
                                                    <span>Tugaskan Pengganti</span>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Modal Formulir Permohonan Pergantian / Pertukaran Shift --}}
                            @if(! $shiftItem->attendance()->exists())
                                <div
                                    id="modal-exchange-shift-{{ $shiftItem->id }}"
                                    data-modal-container
                                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
                                >
                                    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4">
                                        <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                                            <div>
                                                <h3 class="text-lg font-black text-slate-900">Ajukan Pergantian / Tukar Shift</h3>
                                                <p class="text-xs text-slate-500 mt-0.5">Shift: {{ \Carbon\Carbon::parse($shiftItem->start_at)->translatedFormat('d M Y') }} ({{ $startTime }}-{{ $endTime }})</p>
                                            </div>
                                            <button type="button" data-close-modal class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>

                                        <form method="post" action="{{ route('shifts.exchange.store', $shiftItem->id) }}" class="space-y-4">
                                            @csrf
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tipe Pergantian</label>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                                        <input type="radio" name="type" value="replacement" checked class="text-emerald-600 focus:ring-emerald-500" onchange="document.getElementById('exchange-swap-box-{{ $shiftItem->id }}').classList.add('hidden'); document.getElementById('exchange-rep-box-{{ $shiftItem->id }}').classList.remove('hidden');">
                                                        <span>Penggantian Searah</span>
                                                    </label>
                                                    <label class="flex items-center gap-2 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                                        <input type="radio" name="type" value="swap" class="text-emerald-600 focus:ring-emerald-500" onchange="document.getElementById('exchange-swap-box-{{ $shiftItem->id }}').classList.remove('hidden'); document.getElementById('exchange-rep-box-{{ $shiftItem->id }}').classList.add('hidden');">
                                                        <span>Tukar Shift Dua Arah</span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div id="exchange-rep-box-{{ $shiftItem->id }}">
                                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Karyawan Pengganti</label>
                                                <select name="replacement_id" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                                                    <option value="">-- Pilih Rekan Pengganti --</option>
                                                    @foreach($people->where('id', '!=', $shiftItem->user_id) as $p)
                                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->position?->name ?? 'Staf' }})</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div id="exchange-swap-box-{{ $shiftItem->id }}" class="hidden">
                                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Jadwal Shift Rekan yang Ingin Ditukar</label>
                                                <select name="target_shift_id" class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                                                    <option value="">-- Pilih Jadwal Shift Rekan --</option>
                                                    @foreach($shifts->where('user_id', '!=', $shiftItem->user_id) as $otherShift)
                                                        <option value="{{ $otherShift->id }}">
                                                            {{ $otherShift->user?->name }} · {{ \Carbon\Carbon::parse($otherShift->start_at)->format('d M') }} ({{ \Carbon\Carbon::parse($otherShift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($otherShift->end_at)->format('H:i') }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alasan Permohonan</label>
                                                <textarea
                                                    name="reason"
                                                    rows="3"
                                                    required
                                                    minlength="5"
                                                    maxlength="500"
                                                    placeholder="Jelaskan alasan pengalihan atau pertukaran shift..."
                                                    class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                                                ></textarea>
                                            </div>

                                            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                                                <button type="button" data-close-modal class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50">Batal</button>
                                                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider">Kirim Permohonan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                {{-- Modal Formulir Penugasan Langsung oleh Manajer --}}
                                @if(in_array(auth()->user()->role, ['admin', 'manager']))
                                    <div
                                        id="modal-reassign-shift-{{ $shiftItem->id }}"
                                        data-modal-container
                                        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden"
                                    >
                                        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left space-y-4">
                                            <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                                                <div>
                                                    <h3 class="text-lg font-black text-slate-900">Tugaskan Pengganti Langsung</h3>
                                                    <p class="text-xs text-slate-500 mt-0.5">Penugasan manajerial langsung tanpa alur pengajuan staf.</p>
                                                </div>
                                                <button type="button" data-close-modal class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </button>
                                            </div>

                                            <form method="post" action="{{ route('shifts.reassign', $shiftItem->id) }}" class="space-y-4">
                                                @csrf
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Karyawan Pengganti</label>
                                                    <select name="replacement_id" required class="w-full h-10 px-3 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                                                        <option value="">-- Pilih Staf Pengganti --</option>
                                                        @foreach($people->where('id', '!=', $shiftItem->user_id) as $p)
                                                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->position?->name ?? 'Staf' }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alasan Penugasan</label>
                                                    <textarea
                                                        name="reason"
                                                        rows="3"
                                                        required
                                                        minlength="5"
                                                        maxlength="500"
                                                        placeholder="Misal: Karyawan berhalangan sakit mendadak, digantikan oleh staf pengganti..."
                                                        class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                                                    ></textarea>
                                                </div>

                                                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                                                    <button type="button" data-close-modal class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50">Batal</button>
                                                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider">Tugaskan Sekarang</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        @endforeach

                        {{-- Tautan jika ada lebih dari 3 shift di tanggal ini --}}
                        @if($dayCount > 3)
                            <a
                                href="{{ route('shifts', ['date_from' => $dateStr, 'date_to' => $dateStr]) }}"
                                class="text-[10px] font-bold text-emerald-700 hover:text-emerald-800 hover:underline block text-center py-1 bg-emerald-50/50 rounded"
                            >
                                +{{ $dayCount - 3 }} shift lainnya
                            </a>
                        @endif
                    </div>
                </div>

                @php $cursor->addDay(); @endphp
            @endwhile
        </div>
    </div>
</div>
@endsection
