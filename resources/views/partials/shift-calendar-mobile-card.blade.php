@php
    $startTime = \Carbon\Carbon::parse($shiftItem->start_at)->format('H:i');
    $endTime = \Carbon\Carbon::parse($shiftItem->end_at)->format('H:i');
    $startHour = (int) \Carbon\Carbon::parse($shiftItem->start_at)->format('H');
    $isShift1 = $startHour < 12;
    $shiftTypeLabel = $isShift1 ? 'Shift 1 (Pagi)' : 'Shift 2 (Siang)';
    $shiftBadge = $isShift1 ? 'S1' : 'S2';
    $isApproved = $shiftItem->status === 'approved';

    $att = $shiftItem->attendance;
    $attStatus = $att?->status;
    $isPast = \Carbon\Carbon::parse($shiftItem->end_at, 'Asia/Jakarta')->isPast();
    $hasPendingException = $shiftItem->exceptions->where('status', 'pending')->isNotEmpty();

    if ($attStatus === 'present') {
        $cardBorder = 'border-emerald-200 bg-emerald-50/20';
        $statusBadgeBg = 'bg-emerald-100 text-emerald-800 border-emerald-300';
        $statusText = 'Hadir Tepat Waktu';
    } elseif ($attStatus === 'late') {
        $cardBorder = 'border-amber-200 bg-amber-50/20';
        $statusBadgeBg = 'bg-amber-100 text-amber-800 border-amber-300';
        $statusText = 'Terlambat (' . ($att->late_units * 15) . 'm)';
    } elseif ($attStatus === 'early_checkout') {
        $cardBorder = 'border-amber-200 bg-amber-50/20';
        $statusBadgeBg = 'bg-amber-100 text-amber-800 border-amber-300';
        $statusText = 'Pulang Lebih Cepat';
    } elseif ($attStatus === 'absent' || (! $att && $isPast)) {
        $cardBorder = 'border-rose-200 bg-rose-50/20';
        $statusBadgeBg = 'bg-rose-100 text-rose-800 border-rose-300';
        $statusText = 'Alfa / Tidak Hadir';
    } elseif ($att && $att->checkin_at && ! $att->checkout_at) {
        $cardBorder = 'border-sky-200 bg-sky-50/20';
        $statusBadgeBg = 'bg-sky-100 text-sky-800 border-sky-300';
        $statusText = 'Sedang Berdinas';
    } elseif ($isApproved) {
        $cardBorder = 'border-slate-200 bg-white';
        $statusBadgeBg = 'bg-sky-50 text-sky-800 border-sky-200';
        $statusText = 'Jadwal Sah';
    } else {
        $cardBorder = 'border-slate-200 bg-white';
        $statusBadgeBg = 'bg-slate-100 text-slate-700 border-slate-200';
        $statusText = 'Draf Shift';
    }
@endphp

<div class="p-3.5 rounded-xl border {{ $cardBorder }} shadow-2xs space-y-2.5 transition-all">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider border {{ $isShift1 ? 'bg-sky-100 text-sky-800 border-sky-300/80' : 'bg-amber-100 text-amber-800 border-amber-300/80' }}">
                    {{ $shiftBadge }} · {{ $shiftTypeLabel }}
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $statusBadgeBg }}">
                    {{ $statusText }}
                </span>
                @if($hasPendingException)
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                        ⏳ Dispensasi Pending
                    </span>
                @endif
            </div>

            <h4 class="text-sm font-extrabold text-slate-900 mt-1 truncate">
                {{ $shiftItem->user?->name ?? 'Karyawan' }}
            </h4>
            <div class="flex items-center gap-1.5 text-[11px] text-slate-500 truncate">
                <span>{{ $shiftItem->user?->position?->name ?? 'Staf' }}</span>
                <span>•</span>
                <span class="truncate">{{ $shiftItem->branch?->name }}</span>
            </div>
        </div>

        <div class="text-right shrink-0">
            <span class="text-xs font-mono font-bold text-slate-900 block">{{ $startTime }} – {{ $endTime }}</span>
            <span class="text-[10px] text-slate-400">WIB</span>
        </div>
    </div>

    @if($att)
        <div class="p-2.5 rounded-lg bg-white/90 border border-slate-200/80 text-xs space-y-1">
            <div class="flex justify-between items-center text-[11px]">
                <span class="text-slate-500">Scan Masuk:</span>
                <span class="font-mono font-bold text-slate-800">
                    {{ $att->checkin_at ? \Carbon\Carbon::parse($att->checkin_at)->format('H:i:s') : '-' }}
                </span>
            </div>
            <div class="flex justify-between items-center text-[11px]">
                <span class="text-slate-500">Scan Keluar:</span>
                <span class="font-mono font-bold text-slate-800">
                    {{ $att->checkout_at ? \Carbon\Carbon::parse($att->checkout_at)->format('H:i:s') : '-' }}
                </span>
            </div>
            @if($att->late_minutes > 0)
                <div class="flex justify-between items-center text-[11px] text-amber-700 font-semibold pt-1 border-t border-slate-100">
                    <span>Keterlambatan:</span>
                    <span>{{ $att->late_minutes }} menit ({{ $att->late_units }} unit potong)</span>
                </div>
            @endif
        </div>
    @endif

    <div class="pt-2 border-t border-slate-100/90 flex items-center justify-between gap-2">
        <div class="text-[10px] font-mono text-slate-400">
            v{{ $shiftItem->version }}
        </div>

        <div class="flex items-center gap-1.5">
            @if(in_array(auth()->user()->role, ['admin', 'manager']) && ! $shiftItem->attendance)
                <button
                    type="button"
                    data-open-modal="modal-exchange-shift-{{ $shiftItem->id }}"
                    class="px-2 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-[11px] font-bold border border-emerald-200 transition-colors cursor-pointer"
                    title="Tukar Jadwal Shift"
                >
                    Tukar
                </button>
            @endif

            <button
                type="button"
                data-open-modal="modal-shift-{{ $shiftItem->id }}"
                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-[11px] font-bold transition-colors cursor-pointer"
            >
                Detail Penuh
            </button>
        </div>
    </div>
</div>
