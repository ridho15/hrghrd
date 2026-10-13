@php
    $routeAction = $routeAction ?? route('attendance.review');
    $viewParam = $viewParam ?? 'calendar';
@endphp

<div class="space-y-6">
    {{-- 1. Banner Metrik Ringkasan Khusus Presensi --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="p-3.5 rounded-2xl bg-white border border-slate-200 shadow-2xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Total Shift Bulan Ini</span>
            <span class="text-xl font-black text-slate-900 block mt-0.5 tabular-nums">{{ $monthSummary['total_shifts'] ?? 0 }}</span>
            <span class="text-[10px] text-slate-400">Jadwal aktif terdata</span>
        </div>
        <div class="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200 shadow-2xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800 block">Hadir Tepat Waktu</span>
            <span class="text-xl font-black text-emerald-700 block mt-0.5 tabular-nums">{{ $monthSummary['total_present'] ?? 0 }}</span>
            <span class="text-[10px] text-emerald-600 font-medium">Presensi valid tanpa denda</span>
        </div>
        <div class="p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200 shadow-2xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800 block">Terlambat Presensi</span>
            <span class="text-xl font-black text-amber-700 block mt-0.5 tabular-nums">{{ $monthSummary['total_late'] ?? 0 }}</span>
            <span class="text-[10px] text-amber-600 font-medium">Melewati batas toleransi</span>
        </div>
        <div class="p-3.5 rounded-2xl bg-rose-50/70 border border-rose-200 shadow-2xs">
            <span class="text-[11px] font-bold uppercase tracking-wider text-rose-800 block">Alfa / Mangkir</span>
            <span class="text-xl font-black text-rose-700 block mt-0.5 tabular-nums">{{ $monthSummary['total_absent'] ?? 0 }}</span>
            <span class="text-[10px] text-rose-600 font-medium">Shift lewat tanpa hadir</span>
        </div>
        <div class="p-3.5 rounded-2xl bg-purple-50/70 border border-purple-200 shadow-2xs col-span-2 sm:col-span-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-purple-800 block">Pengecualian Menunggu</span>
            <span class="text-xl font-black text-purple-700 block mt-0.5 tabular-nums">{{ $monthSummary['total_exceptions'] ?? 0 }}</span>
            <span class="text-[10px] text-purple-600 font-medium">Menunggu persetujuan</span>
        </div>
    </div>

    {{-- 2. Toolbar Kalender & Filter --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-3 sm:p-4 space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            {{-- Navigasi Bulan --}}
            <div class="flex items-center justify-between lg:justify-start gap-2 sm:gap-3">
                <div class="flex items-center gap-1">
                    <a
                        href="{{ $routeAction }}?{{ http_build_query(array_merge(request()->query(), ['view' => $viewParam, 'month' => $prevMonth])) }}"
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                        title="Bulan Sebelumnya"
                        aria-label="Bulan Sebelumnya"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                    </a>

                    <div class="px-2 sm:px-3 text-center">
                        <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight m-0">
                            {{ $currentMonth->translatedFormat('F Y') }}
                        </h2>
                        <span class="text-[10px] sm:text-[11px] text-slate-400 font-medium">
                            {{ $shifts->count() }} shift tercatat
                        </span>
                    </div>

                    <a
                        href="{{ $routeAction }}?{{ http_build_query(array_merge(request()->query(), ['view' => $viewParam, 'month' => $nextMonth])) }}"
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                        title="Bulan Berikutnya"
                        aria-label="Bulan Berikutnya"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>

                <div class="flex items-center gap-1.5">
                    <a
                        href="{{ $routeAction }}?{{ http_build_query(array_merge(request()->query(), ['view' => $viewParam, 'month' => now('Asia/Jakarta')->format('Y-m'), 'date' => now('Asia/Jakarta')->toDateString()])) }}"
                        class="px-2.5 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-colors"
                    >
                        Hari Ini
                    </a>

                    {{-- Tombol Toggle Filter di Mobile --}}
                    <button
                        type="button"
                        id="btn-toggle-mobile-filter"
                        class="lg:hidden px-2.5 py-1.5 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold inline-flex items-center gap-1.5 hover:bg-slate-50 cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        <span>Filter</span>
                        @if(request()->hasAny(['branch_id', 'user_id', 'status', 'attendance_status']))
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        @endif
                    </button>
                </div>
            </div>

            {{-- Switcher Tampilan Khusus Mobile: [ Kalender Mini ] vs [ List Agenda ] --}}
            <div class="flex lg:hidden items-center justify-between border-t border-slate-100 pt-2.5">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tampilan Mobile:</span>
                <div class="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 text-xs">
                    <button
                        type="button"
                        id="btn-mobile-view-cal"
                        class="px-3 py-1 rounded-md font-bold transition-all bg-white text-emerald-800 shadow-2xs cursor-pointer"
                    >
                        📅 Kalender Mini
                    </button>
                    <button
                        type="button"
                        id="btn-mobile-view-list"
                        class="px-3 py-1 rounded-md font-semibold text-slate-600 hover:text-slate-900 transition-all cursor-pointer"
                    >
                        📋 List Agenda
                    </button>
                </div>
            </div>

            {{-- Formulir Filter (Collapsible di mobile, Inline di Desktop) --}}
            <div id="filter-wrapper" class="hidden lg:block">
                <form method="get" action="{{ $routeAction }}" class="flex flex-wrap items-center gap-2 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                    <input type="hidden" name="view" value="{{ $viewParam }}">
                    <input type="hidden" name="month" value="{{ $monthStr }}">

                    {{-- Filter Cabang (Admin) --}}
                    @if(auth()->user()->role === 'admin')
                        <div class="w-full sm:w-44">
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
                    <div class="w-full sm:w-44">
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

                    {{-- Filter Status Presensi --}}
                    <select
                        name="attendance_status"
                        class="w-full sm:w-auto h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs"
                    >
                        <option value="">Semua Status Presensi</option>
                        <option value="present" {{ request('attendance_status') === 'present' ? 'selected' : '' }}>Hadir Tepat Waktu</option>
                        <option value="late" {{ request('attendance_status') === 'late' ? 'selected' : '' }}>Terlambat</option>
                        <option value="absent" {{ request('attendance_status') === 'absent' ? 'selected' : '' }}>Alfa / Tidak Hadir</option>
                        <option value="on_duty" {{ request('attendance_status') === 'on_duty' ? 'selected' : '' }}>Sedang Bertugas</option>
                        <option value="early_checkout" {{ request('attendance_status') === 'early_checkout' ? 'selected' : '' }}>Pulang Lebih Cepat</option>
                        <option value="pending_exception" {{ request('attendance_status') === 'pending_exception' ? 'selected' : '' }}>Ada Dispensasi Menunggu</option>
                    </select>

                    <button
                        type="submit"
                        class="w-full sm:w-auto h-8 px-3.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors"
                    >
                        Terapkan
                    </button>

                    @if(request()->hasAny(['branch_id', 'user_id', 'attendance_status']))
                        <a
                            href="{{ $routeAction }}?{{ http_build_query(['view' => $viewParam, 'month' => $monthStr]) }}"
                            class="w-full sm:w-auto h-8 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-colors inline-flex items-center justify-center"
                            title="Reset Filter"
                        >
                            Reset
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </div>

    {{-- 3. Legenda Titik Status Presensi --}}
    <div class="flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-slate-500">
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-1.5 font-medium">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <span>Hadir Tepat Waktu</span>
            </span>
            <span class="inline-flex items-center gap-1.5 font-medium">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                <span>Terlambat</span>
            </span>
            <span class="inline-flex items-center gap-1.5 font-medium">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                <span>Alfa / Mangkir</span>
            </span>
            <span class="inline-flex items-center gap-1.5 font-medium">
                <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                <span>Sedang Bertugas</span>
            </span>
        </div>
        <div class="text-[11px] text-slate-400">
            * Klik kartu shift untuk melihat forensik presensi & rincian
        </div>
    </div>

    {{-- 4. TAMPILAN MOBILE: Mini Touch Calendar + Agenda Harian (Layar < 768px) --}}
    <div id="mobile-calendar-container" class="block md:hidden space-y-4">
        {{-- Section Kalender Mini + Detail Harian --}}
        <div id="mobile-cal-section" class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-3">
                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-black uppercase tracking-wider text-slate-400 pb-2 border-b border-slate-100">
                    <span>Sen</span>
                    <span>Sel</span>
                    <span>Rab</span>
                    <span>Kam</span>
                    <span>Jum</span>
                    <span class="text-emerald-700">Sab</span>
                    <span class="text-rose-600">Min</span>
                </div>

                <div class="grid grid-cols-7 gap-1 pt-2">
                    @php
                        $mobileCursor = $gridStart->copy();
                        $todayStr = now('Asia/Jakarta')->toDateString();
                    @endphp

                    @while($mobileCursor <= $gridEnd)
                        @php
                            $dStr = $mobileCursor->toDateString();
                            $isCurrentMonth = $mobileCursor->isSameMonth($currentMonth);
                            $isToday = $dStr === $todayStr;
                            $isSelected = $dStr === $selectedDate;
                            $dayShifts = $groupedShifts->get($dStr, collect());
                            $dayCount = $dayShifts->count();
                            $summary = $attendanceSummaryByDate[$dStr] ?? null;

                            $cellBg = 'bg-white text-slate-800 hover:bg-slate-50';
                            if ($isSelected) {
                                $cellBg = 'bg-emerald-700 text-white shadow-xs font-black ring-2 ring-emerald-500';
                            } elseif ($isToday) {
                                $cellBg = 'bg-emerald-50 text-emerald-900 border border-emerald-300 font-bold';
                            } elseif (! $isCurrentMonth) {
                                $cellBg = 'bg-slate-50/50 text-slate-300';
                            }
                        @endphp

                        <button
                            type="button"
                            data-cal-date="{{ $dStr }}"
                            onclick="window.selectMobileDate('{{ $dStr }}')"
                            class="mobile-date-cell flex flex-col items-center justify-between p-1.5 rounded-xl text-xs transition-all min-h-[46px] {{ $cellBg }} cursor-pointer"
                        >
                            <span class="text-[11px] leading-tight {{ $isSelected ? 'text-white' : ($isToday ? 'text-emerald-700 font-bold' : ($isCurrentMonth ? 'text-slate-800' : 'text-slate-300')) }}">
                                {{ $mobileCursor->format('j') }}
                            </span>

                            <div class="flex items-center gap-0.5 mt-1 h-2">
                                @if($summary && $summary['present'] > 0)
                                    <span class="w-1.5 h-1.5 rounded-full {{ $isSelected ? 'bg-white' : 'bg-emerald-500' }}"></span>
                                @endif
                                @if($summary && $summary['late'] > 0)
                                    <span class="w-1.5 h-1.5 rounded-full {{ $isSelected ? 'bg-amber-200' : 'bg-amber-500' }}"></span>
                                @endif
                                @if($summary && $summary['absent'] > 0)
                                    <span class="w-1.5 h-1.5 rounded-full {{ $isSelected ? 'bg-rose-200' : 'bg-rose-500' }}"></span>
                                @endif
                                @if($dayCount === 0 && $isCurrentMonth && $mobileCursor->isPast())
                                    <span class="w-1 h-1 rounded-full {{ $isSelected ? 'bg-white/50' : 'bg-slate-300' }}"></span>
                                @endif
                            </div>
                        </button>

                        @php $mobileCursor->addDay(); @endphp
                    @endwhile
                </div>
            </div>

            {{-- Panel Kartu Detail Tanggal Terpilih (Mobile Agenda Panel) --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 space-y-3">
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Agenda Detail Harian:</span>
                        <h3 id="mobile-selected-label" class="text-sm font-black text-slate-900 tracking-tight mt-0.5">
                            {{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }}
                        </h3>
                    </div>
                </div>

                {{-- Wadah Kartu Shift untuk Tanggal Terpilih --}}
                <div id="mobile-agenda-cards-container" class="space-y-3">
                    @php
                        $activeDayShifts = $groupedShifts->get($selectedDate, collect());
                    @endphp

                    @forelse($activeDayShifts as $shiftItem)
                        @include('partials.shift-calendar-mobile-card', ['shiftItem' => $shiftItem, 'mode' => 'attendance'])
                    @empty
                        <div class="text-center py-8 text-slate-400">
                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-xs font-semibold text-slate-600 m-0">Tidak ada jadwal shift pada tanggal ini.</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Ketuk tanggal lain pada kalender mini di atas.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Section List Agenda Kronologis Mobile --}}
        <div id="mobile-list-section" class="space-y-4 hidden">
            @php
                $hasAnyShifts = $shifts->isNotEmpty();
            @endphp

            @if($hasAnyShifts)
                @foreach($groupedShifts as $listDateStr => $dayListShifts)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                            <span class="text-xs font-black text-slate-900">
                                📅 {{ \Carbon\Carbon::parse($listDateStr)->translatedFormat('l, d M Y') }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                {{ $dayListShifts->count() }} Shift
                            </span>
                        </div>

                        <div class="space-y-2.5">
                            @foreach($dayListShifts as $shiftItem)
                                @include('partials.shift-calendar-mobile-card', ['shiftItem' => $shiftItem, 'mode' => 'attendance'])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-400">
                    <p class="text-xs font-semibold text-slate-600">Tidak ada jadwal shift pada periode bulan ini.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- 5. TAMPILAN DESKTOP: Matriks 7 Kolom Penuh (Layar >= 768px) --}}
    <div class="hidden md:block bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
        {{-- Header Hari --}}
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/80 text-center text-xs font-black uppercase tracking-wider text-slate-500 py-3">
            <div>Senin</div>
            <div>Selasa</div>
            <div>Rabu</div>
            <div>Kamis</div>
            <div>Jumat</div>
            <div class="text-emerald-700">Sabtu</div>
            <div class="text-rose-600">Minggu</div>
        </div>

        {{-- Grid Matriks Hari --}}
        <div class="grid grid-cols-7 auto-rows-fr divide-x divide-y divide-slate-100">
            @php
                $cursor = $gridStart->copy();
                $today = now('Asia/Jakarta')->toDateString();
            @endphp

            @while($cursor <= $gridEnd)
                @php
                    $dateStr = $cursor->toDateString();
                    $isCurrentMonth = $cursor->isSameMonth($currentMonth);
                    $isToday = $dateStr === $today;
                    $isSunday = $cursor->isSunday();
                    $dayShifts = $groupedShifts->get($dateStr, collect());
                    $dayCount = $dayShifts->count();
                    $summary = $attendanceSummaryByDate[$dateStr] ?? null;
                @endphp

                <div class="min-h-[140px] p-2 flex flex-col justify-between transition-colors {{ ! $isCurrentMonth ? 'bg-slate-50/50' : ($isToday ? 'bg-emerald-50/20' : 'bg-white hover:bg-slate-50/40') }}">
                    {{-- Header Kotak Tanggal --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg text-xs font-bold {{ $isToday ? 'bg-emerald-700 text-white shadow-2xs font-black' : ($isSunday ? 'text-rose-600' : ($isCurrentMonth ? 'text-slate-800' : 'text-slate-300')) }}">
                                {{ $cursor->format('j') }}
                            </span>

                            @if($isToday)
                                <span class="text-[9px] font-black uppercase tracking-wider text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                                    Hari Ini
                                </span>
                            @endif
                        </div>

                        {{-- Ringkasan Badge Presensi Harian --}}
                        @if($summary && $summary['total_shifts'] > 0)
                            <div class="flex items-center gap-1 mb-1.5 flex-wrap">
                                @if($summary['present'] > 0)
                                    <span class="px-1 py-0.2 rounded text-[9px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200" title="{{ $summary['present'] }} Hadir Tepat Waktu">
                                        {{ $summary['present'] }}H
                                    </span>
                                @endif
                                @if($summary['late'] > 0)
                                    <span class="px-1 py-0.2 rounded text-[9px] font-black bg-amber-100 text-amber-800 border border-amber-200" title="{{ $summary['late'] }} Terlambat">
                                        {{ $summary['late'] }}T
                                    </span>
                                @endif
                                @if($summary['absent'] > 0)
                                    <span class="px-1 py-0.2 rounded text-[9px] font-black bg-rose-100 text-rose-800 border border-rose-200" title="{{ $summary['absent'] }} Alfa">
                                        {{ $summary['absent'] }}A
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Daftar Chip Shift --}}
                    <div class="space-y-1 my-1">
                        @if(request()->filled('user_id') && $isCurrentMonth && $dayCount === 0)
                            <div class="p-1 rounded text-[10px] font-bold text-center text-slate-400 bg-slate-50 border border-dashed border-slate-200">
                                Libur
                            </div>
                        @endif

                        @foreach($dayShifts->take(3) as $shiftItem)
                            @php
                                $startTime = \Carbon\Carbon::parse($shiftItem->start_at)->format('H:i');
                                $endTime = \Carbon\Carbon::parse($shiftItem->end_at)->format('H:i');
                                $startHour = (int) \Carbon\Carbon::parse($shiftItem->start_at)->format('H');
                                $isShift1 = $startHour < 12;
                                $shiftBadge = $isShift1 ? 'S1' : 'S2';
                                $isApproved = $shiftItem->status === 'approved';
                                $att = $shiftItem->attendance;
                                $attStatus = $att?->status;

                                $isCompleted = $att && $att->checkin_at && $att->checkout_at;
                                $isOnDuty = $att && $att->checkin_at && ! $att->checkout_at;
                                $isPast = \Carbon\Carbon::parse($shiftItem->end_at)->isPast();

                                if ($isCompleted) {
                                    // Sudah check-in dan check-out: Biru agak pekat (solid blue)
                                    $chipTheme = 'bg-blue-600 text-white border-blue-700 hover:bg-blue-700 shadow-2xs';
                                    $badgeClass = 'bg-white/20 text-white border border-white/30';
                                    $dotColor = 'bg-emerald-300';
                                    $timeColor = 'text-blue-100';
                                    $branchColor = 'text-blue-100/90';
                                    $statusLabel = $attStatus === 'late' ? 'Terlambat (Selesai)' : 'Hadir';
                                } elseif ($isOnDuty) {
                                    // Sudah check-in, belum check-out: Biru aktif (sedang berdinas)
                                    $chipTheme = 'bg-sky-100 text-sky-950 border-sky-400 hover:bg-sky-200';
                                    $badgeClass = $isShift1 ? 'bg-sky-200 text-sky-900 border border-sky-300' : 'bg-amber-100 text-amber-800 border border-amber-300';
                                    $dotColor = 'bg-sky-500 animate-pulse';
                                    $timeColor = 'text-sky-800 font-bold';
                                    $branchColor = 'text-slate-500';
                                    $statusLabel = 'Sedang Berdinas';
                                } elseif ($attStatus === 'absent' || (! $att && $isPast)) {
                                    // Alfa / mangkir
                                    $chipTheme = 'bg-rose-50 text-rose-950 border-rose-300 hover:bg-rose-100';
                                    $badgeClass = $isShift1 ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-300';
                                    $dotColor = 'bg-rose-500';
                                    $timeColor = 'text-slate-500';
                                    $branchColor = 'text-slate-500';
                                    $statusLabel = 'Alfa';
                                } elseif ($isApproved) {
                                    // Belum check-in / jadwal sah: Biru muda
                                    $chipTheme = 'bg-sky-50 text-sky-950 border-sky-300 hover:bg-sky-100';
                                    $badgeClass = $isShift1 ? 'bg-sky-100 text-sky-800 border border-sky-300/80' : 'bg-amber-100 text-amber-800 border border-amber-300/80';
                                    $dotColor = 'bg-sky-500';
                                    $timeColor = 'text-slate-500';
                                    $branchColor = 'text-slate-500';
                                    $statusLabel = 'Jadwal Sah';
                                } else {
                                    // Draf
                                    $chipTheme = 'bg-slate-50 text-slate-800 border-slate-200 hover:bg-slate-100';
                                    $badgeClass = 'bg-slate-100 text-slate-700 border border-slate-200';
                                    $dotColor = 'bg-slate-400';
                                    $timeColor = 'text-slate-500';
                                    $branchColor = 'text-slate-500';
                                    $statusLabel = 'Draf';
                                }
                            @endphp

                            <button
                                type="button"
                                data-open-modal="modal-shift-{{ $shiftItem->id }}"
                                title="{{ $statusLabel }}"
                                class="w-full text-left p-1.5 rounded-lg border {{ $chipTheme }} transition-all text-[11px] block shadow-2xs group cursor-pointer"
                            >
                                <div class="flex items-center justify-between gap-1">
                                    <div class="flex items-center gap-1.5 truncate font-bold">
                                        <span class="px-1 py-0.2 rounded text-[9px] font-black uppercase tracking-wider shrink-0 {{ $badgeClass }}">
                                            {{ $shiftBadge }}
                                        </span>
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }} shrink-0"></span>
                                        <span class="truncate">{{ $shiftItem->user?->name ?? 'Karyawan' }}</span>
                                    </div>
                                    <span class="text-[10px] font-mono {{ $timeColor }} shrink-0">{{ $startTime }}</span>
                                </div>
                                <div class="flex items-center justify-between text-[10px] {{ $branchColor }} mt-0.5">
                                    <span class="truncate">{{ $shiftItem->branch?->name }}</span>
                                    @if($isCompleted && $att)
                                        <span class="font-bold text-blue-100">
                                            {{ $att->checkin_at ? \Carbon\Carbon::parse($att->checkin_at)->format('H:i') : '-' }}-{{ $att->checkout_at ? \Carbon\Carbon::parse($att->checkout_at)->format('H:i') : '-' }}
                                        </span>
                                    @elseif($isOnDuty && $att)
                                        <span class="font-bold text-sky-800">
                                            Masuk: {{ \Carbon\Carbon::parse($att->checkin_at)->format('H:i') }}
                                        </span>
                                    @else
                                        <span class="font-semibold">{{ $startTime }}-{{ $endTime }}</span>
                                    @endif
                                </div>
                            </button>
                        @endforeach

                        @if($dayCount > 3)
                            @if(in_array(auth()->user()->role, ['admin', 'manager']))
                                <a
                                    href="{{ route('shifts', ['date_from' => $dateStr, 'date_to' => $dateStr]) }}"
                                    class="text-[10px] font-bold text-emerald-700 hover:text-emerald-800 hover:underline block text-center py-1 bg-emerald-50/50 rounded"
                                >
                                    +{{ $dayCount - 3 }} shift lainnya
                                </a>
                            @else
                                <a
                                    href="{{ $routeAction }}?{{ http_build_query(array_merge(request()->query(), ['view' => $viewParam, 'date' => $dateStr])) }}"
                                    class="text-[10px] font-bold text-emerald-700 hover:text-emerald-800 hover:underline block text-center py-1 bg-emerald-50/50 rounded"
                                >
                                    +{{ $dayCount - 3 }} shift lainnya
                                </a>
                            @endif
                        @endif
                    </div>
                </div>

                @php $cursor->addDay(); @endphp
            @endwhile
        </div>
    </div>

    {{-- 6. Detail Modals untuk Setiap Shift pada Kalender --}}
    @foreach($shifts as $shiftItem)
        @php
            $startTime = \Carbon\Carbon::parse($shiftItem->start_at)->format('H:i');
            $endTime = \Carbon\Carbon::parse($shiftItem->end_at)->format('H:i');
            $startHour = (int) \Carbon\Carbon::parse($shiftItem->start_at)->format('H');
            $isShift1 = $startHour < 12;
            $shiftTypeLabel = $isShift1 ? 'Shift 1 (Pagi)' : 'Shift 2 (Siang)';
            $isApproved = $shiftItem->status === 'approved';
            $att = $shiftItem->attendance;
        @endphp

        <div
            id="modal-shift-{{ $shiftItem->id }}"
            class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200"
            role="dialog"
            aria-modal="true"
        >
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg p-5 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $isShift1 ? 'bg-sky-100 text-sky-800 border border-sky-300' : 'bg-amber-100 text-amber-800 border border-amber-300' }}">
                            {{ $isShift1 ? 'S1' : 'S2' }} · {{ $shiftTypeLabel }}
                        </span>
                        <span class="text-xs text-slate-400">&middot;</span>
                        <span class="text-xs font-bold {{ $isApproved ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ $isApproved ? 'Shift Disetujui' : 'Draf Shift' }} · v{{ $shiftItem->version }}
                        </span>
                    </div>
                    <button
                        type="button"
                        data-close-modal
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                        aria-label="Tutup"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                {{-- Konten Detail Shift --}}
                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Karyawan</span>
                            <span class="font-bold text-slate-900 block mt-0.5">{{ $shiftItem->user?->name }}</span>
                            <span class="text-xs text-slate-500 font-mono">{{ $shiftItem->user?->email }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Cabang Penugasan</span>
                            <span class="font-bold text-slate-900 block mt-0.5">{{ $shiftItem->branch?->name }}</span>
                            <span class="text-xs text-slate-500 font-mono">Kode: {{ $shiftItem->branch?->code }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Tanggal Shift</span>
                            <span class="font-bold text-slate-900">{{ \Carbon\Carbon::parse($shiftItem->start_at)->translatedFormat('l, d F Y') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Jam Dinas Resmi</span>
                            <span class="font-mono font-bold text-slate-900">{{ $startTime }} - {{ $endTime }} WIB</span>
                        </div>
                    </div>

                    {{-- Forensik Kehadiran --}}
                    @if($att)
                        <div class="p-3 bg-emerald-50/60 rounded-xl border border-emerald-200/80 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-emerald-950 uppercase tracking-wider text-[10px]">Realisasi Kehadiran:</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $att->status === 'present' ? 'bg-emerald-100 text-emerald-800' : ($att->status === 'late' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                    {{ $att->status === 'present' ? 'Hadir Tepat Waktu' : ($att->status === 'late' ? 'Terlambat (' . ($att->late_units * 15) . 'm)' : 'Alfa') }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[11px]">
                                <div>
                                    <span class="text-slate-500">Scan Check-in:</span>
                                    <span class="font-mono font-bold text-slate-800 block">{{ $att->checkin_at ? \Carbon\Carbon::parse($att->checkin_at)->format('H:i:s') : '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Scan Check-out:</span>
                                    <span class="font-mono font-bold text-slate-800 block">{{ $att->checkout_at ? \Carbon\Carbon::parse($att->checkout_at)->format('H:i:s') : '-' }}</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="p-3 bg-slate-100 rounded-xl text-center text-slate-500">
                            {{ \Carbon\Carbon::parse($shiftItem->end_at)->isPast() ? 'Karyawan tidak hadir / mangkir pada jadwal ini.' : 'Shift belum berlangsung atau karyawan belum melakukan scan presensi.' }}
                        </div>
                    @endif
                </div>

                {{-- Aksi --}}
                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    @if(in_array(auth()->user()->role, ['admin', 'manager']))
                        <a
                            href="{{ route('shifts.show', $shiftItem->id) }}"
                            class="px-3 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50 transition-colors"
                        >
                            Lihat Detail Jadwal
                        </a>
                    @endif
                    <button
                        type="button"
                        data-close-modal
                        class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endforeach
</div>

@push('scripts')
<script>
    // 1. Toggle Filter Drawer di Mobile
    const btnToggleFilter = document.getElementById('btn-toggle-mobile-filter');
    const filterWrapper = document.getElementById('filter-wrapper');
    if (btnToggleFilter && filterWrapper) {
        btnToggleFilter.addEventListener('click', () => {
            filterWrapper.classList.toggle('hidden');
        });
    }

    // 2. Switcher Antara Kalender Mini vs List Agenda di Mobile
    const btnCal = document.getElementById('btn-mobile-view-cal');
    const btnList = document.getElementById('btn-mobile-view-list');
    const secCal = document.getElementById('mobile-cal-section');
    const secList = document.getElementById('mobile-list-section');

    if (btnCal && btnList && secCal && secList) {
        btnCal.addEventListener('click', () => {
            secCal.classList.remove('hidden');
            secList.classList.add('hidden');
            btnCal.classList.add('bg-white', 'text-emerald-800', 'shadow-2xs');
            btnCal.classList.remove('text-slate-600');
            btnList.classList.remove('bg-white', 'text-emerald-800', 'shadow-2xs');
            btnList.classList.add('text-slate-600');
        });

        btnList.addEventListener('click', () => {
            secCal.classList.add('hidden');
            secList.classList.remove('hidden');
            btnList.classList.add('bg-white', 'text-emerald-800', 'shadow-2xs');
            btnList.classList.remove('text-slate-600');
            btnCal.classList.remove('bg-white', 'text-emerald-800', 'shadow-2xs');
            btnCal.classList.add('text-slate-600');
        });
    }

    // 3. Fast Tap Mobile Date Switching
    const shiftDataByDate = @json($groupedShifts);

    window.selectMobileDate = function(dateStr) {
        document.querySelectorAll('.mobile-date-cell').forEach(cell => {
            if (cell.dataset.calDate === dateStr) {
                cell.classList.add('bg-emerald-700', 'text-white', 'shadow-xs', 'font-black', 'ring-2', 'ring-emerald-500');
                cell.classList.remove('text-slate-800', 'hover:bg-slate-50', 'bg-emerald-50', 'text-emerald-900');
            } else {
                cell.classList.remove('bg-emerald-700', 'text-white', 'shadow-xs', 'font-black', 'ring-2', 'ring-emerald-500');
                if (cell.dataset.calDate === "{{ now('Asia/Jakarta')->toDateString() }}") {
                    cell.classList.add('bg-emerald-50', 'text-emerald-900', 'border', 'border-emerald-300');
                } else {
                    cell.classList.add('text-slate-800', 'hover:bg-slate-50');
                }
            }
        });

        const dateObj = new Date(dateStr + 'T00:00:00');
        const formattedDate = dateObj.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        const labelEl = document.getElementById('mobile-selected-label');
        if (labelEl) labelEl.textContent = formattedDate;

        const cardsContainer = document.getElementById('mobile-agenda-cards-container');
        const shifts = shiftDataByDate[dateStr] || [];

        if (shifts.length === 0) {
            cardsContainer.innerHTML = `
                <div class="text-center py-8 text-slate-400">
                    <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <p class="text-xs font-semibold text-slate-600 m-0">Tidak ada jadwal shift pada tanggal ini.</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Ketuk tanggal lain pada kalender mini di atas.</p>
                </div>
            `;
            return;
        }

        let html = '';
        shifts.forEach(s => {
            const startHour = parseInt((s.start_at || '').substring(11, 13) || '0');
            const isS1 = startHour < 12;
            const shiftBadge = isS1 ? 'S1' : 'S2';
            const shiftTypeLabel = isS1 ? 'Shift 1 (Pagi)' : 'Shift 2 (Siang)';
            const startTime = (s.start_at || '').substring(11, 16);
            const endTime = (s.end_at || '').substring(11, 16);
            const att = s.attendance;
            const userName = s.user ? s.user.name : 'Karyawan';
            const branchName = s.branch ? s.branch.name : '-';
            const posName = (s.user && s.user.position) ? s.user.position.name : 'Staf';

            let attBadgeHtml = '';
            let attCardBorder = 'border-slate-200';
            if (att) {
                if (att.status === 'present') {
                    attBadgeHtml = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Hadir Tepat Waktu</span>`;
                    attCardBorder = 'border-emerald-200 bg-emerald-50/20';
                } else if (att.status === 'late') {
                    attBadgeHtml = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Terlambat</span>`;
                    attCardBorder = 'border-amber-200 bg-amber-50/20';
                } else if (att.status === 'absent') {
                    attBadgeHtml = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">Alfa</span>`;
                    attCardBorder = 'border-rose-200 bg-rose-50/20';
                }
            } else {
                attBadgeHtml = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">Belum Ada Presensi</span>`;
            }

            html += `
                <div class="p-3.5 rounded-xl border ${attCardBorder} shadow-2xs space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase ${isS1 ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800'}">
                                    ${shiftBadge} · ${shiftTypeLabel}
                                </span>
                                ${attBadgeHtml}
                            </div>
                            <h4 class="text-sm font-extrabold text-slate-900 mt-1">${userName}</h4>
                            <span class="text-[11px] text-slate-500">${posName} · ${branchName}</span>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-xs font-mono font-bold text-slate-900 block">${startTime} – ${endTime}</span>
                            <span class="text-[10px] text-slate-400">WIB</span>
                        </div>
                    </div>

                    ${att ? `
                        <div class="p-2.5 rounded-lg bg-white border border-slate-100 text-xs space-y-1">
                            <div class="flex justify-between text-[11px]">
                                <span class="text-slate-500">Scan Check-in:</span>
                                <span class="font-mono font-bold text-slate-800">${(att.checkin_at || '').substring(11, 16) || '-'}</span>
                            </div>
                            <div class="flex justify-between text-[11px]">
                                <span class="text-slate-500">Scan Check-out:</span>
                                <span class="font-mono font-bold text-slate-800">${(att.checkout_at || '').substring(11, 16) || '-'}</span>
                            </div>
                        </div>
                    ` : ''}

                    <div class="pt-2 border-t border-slate-100/80 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            data-open-modal="modal-shift-${s.id}"
                            class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors cursor-pointer"
                        >
                            Detail Penuh
                        </button>
                    </div>
                </div>
            `;
        });

        cardsContainer.innerHTML = html;
    };
</script>
@endpush
