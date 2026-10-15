@php
    $shiftId = $shiftItem?->id ?? 'standalone';
    $isStandalone = $isStandalone ?? false;
    $actionUrl = $shiftItem ? route('shifts.exchange.store', $shiftItem->id) : '#';
    $shiftDateFormatted = $shiftItem ? \Carbon\Carbon::parse($shiftItem->start_at)->translatedFormat('l, d F Y') : '';
    $shiftTimeFormatted = $shiftItem ? (\Carbon\Carbon::parse($shiftItem->start_at)->format('H:i') . ' - ' . \Carbon\Carbon::parse($shiftItem->end_at)->format('H:i') . ' WIB') : '';
    $shiftPosName = $shiftItem ? ($shiftItem->position?->name ?? $shiftItem->user?->position?->name ?? 'Staf') : '';
    $shiftBranchName = $shiftItem ? ($shiftItem->branch?->name ?? 'Cabang') : '';
@endphp

<div
    id="{{ $modalId }}"
    data-modal-container
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs hidden transition-all duration-200"
>
    <div class="bg-white rounded-2xl max-w-xl w-full p-5 sm:p-6 shadow-2xl border border-slate-200 text-left space-y-4 max-h-[92vh] overflow-y-auto exchange-modal-box">
        {{-- Header Modal --}}
        <div class="flex items-start justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
                    Ajukan Pergantian / Tukar Shift
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Permohonan memerlukan persetujuan rekan kerja dan pengesahan manajer.
                </p>
            </div>
            <button
                type="button"
                data-close-modal
                class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer shrink-0"
                title="Tutup Modal"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <form
            method="post"
            action="{{ $actionUrl }}"
            class="space-y-4 form-exchange-container"
            data-is-standalone="{{ $isStandalone ? '1' : '0' }}"
            data-default-action="{{ $actionUrl }}"
        >
            @csrf

            {{-- Jika Standalone: Dropdown Memilih Jadwal Kerja Pemohon Terlebih Dahulu --}}
            @if($isStandalone)
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                    <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                        Pilih Jadwal Kerja Anda yang Ingin Diganti / Ditukar <span class="text-rose-600">*</span>
                    </label>
                    @if(isset($myUpcomingShifts) && $myUpcomingShifts->isNotEmpty())
                        <select
                            name="requester_shift_id"
                            required
                            class="select-standalone-shift w-full h-10 px-3 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                        >
                            <option value="">-- Pilih Salah Satu Jadwal Shift Anda --</option>
                            @foreach($myUpcomingShifts as $myShift)
                                <option
                                    value="{{ $myShift->id }}"
                                    data-date="{{ \Carbon\Carbon::parse($myShift->start_at)->translatedFormat('l, d M Y') }}"
                                    data-time="{{ \Carbon\Carbon::parse($myShift->start_at)->format('H:i') }} - {{ \Carbon\Carbon::parse($myShift->end_at)->format('H:i') }} WIB"
                                    data-position="{{ $myShift->position?->name ?? 'Staf' }}"
                                    data-branch="{{ $myShift->branch?->name ?? 'Cabang' }}"
                                >
                                    {{ \Carbon\Carbon::parse($myShift->start_at)->translatedFormat('l, d M Y') }} ({{ \Carbon\Carbon::parse($myShift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($myShift->end_at)->format('H:i') }}) · {{ $myShift->position?->name ?? 'Staf' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-500">Pilih shift kerja milik Anda yang berhalangan atau ingin dipindahkan.</p>
                    @else
                        <div class="p-3 bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Anda belum memiliki jadwal shift mendatang yang aktif di sistem.</span>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Kartu Info Shift Terpilih --}}
            <div class="info-shift-card p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs flex items-center justify-between gap-3 {{ $isStandalone ? 'hidden' : '' }}">
                <div class="space-y-0.5">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Jadwal Shift yang Diajukan</span>
                    <div class="font-bold text-slate-900 text-xs sm:text-sm shift-display-date">
                        {{ $shiftDateFormatted }}
                    </div>
                    <div class="text-[11px] text-slate-600 flex items-center gap-2 flex-wrap">
                        <span class="font-medium text-slate-800 bg-white border border-slate-200 px-1.5 py-0.5 rounded text-[10px] shift-display-time">{{ $shiftTimeFormatted }}</span>
                        <span class="text-slate-300">·</span>
                        <span class="shift-display-pos">{{ $shiftPosName }}</span>
                        <span class="text-slate-300">·</span>
                        <span class="text-slate-500 shift-display-branch">{{ $shiftBranchName }}</span>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-500 shrink-0 shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>

            {{-- LANGKAH 1: PILIH KEBUTUHAN UTAMA (2 INTENT CARDS TANPA EMOJI SLOP) --}}
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    1. Keperluan Pengajuan <span class="text-rose-600">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    {{-- Tombol Intent A: Ingin Libur / Berhalangan --}}
                    <button
                        type="button"
                        class="btn-intent btn-intent-off group text-left p-3 rounded-xl border-2 border-emerald-600 bg-emerald-50/30 hover:bg-emerald-50/50 transition-all cursor-pointer relative shadow-2xs"
                        data-intent="off"
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100/80 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l4 4m0-4l-4 4"></path></svg>
                            </div>
                            <div class="space-y-0.5 min-w-0 flex-1">
                                <div class="text-xs font-bold text-slate-900 flex items-center justify-between">
                                    <span>Saya Ingin Libur</span>
                                    <span class="badge-intent-check w-4 h-4 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px]">
                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 leading-tight">
                                    Berhalangan hadir dan mengajukan pengganti.
                                </p>
                            </div>
                        </div>
                    </button>

                    {{-- Tombol Intent B: Sedang Libur / Ambil Slot Kosong --}}
                    <button
                        type="button"
                        class="btn-intent btn-intent-work group text-left p-3 rounded-xl border border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50 transition-all cursor-pointer relative"
                        data-intent="work"
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 mt-0.5 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div class="space-y-0.5 min-w-0 flex-1">
                                <div class="text-xs font-semibold text-slate-700 flex items-center justify-between">
                                    <span>Saya Ingin Masuk Kerja</span>
                                    <span class="badge-intent-check w-4 h-4 rounded-full bg-emerald-600 text-white hidden items-center justify-center text-[10px]">
                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 leading-tight">
                                    Mengisi slot kerja kosong atau menukar hari libur.
                                </p>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            {{-- LANGKAH 2: PILIHAN METODE --}}
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    2. Metode Pergantian <span class="text-rose-600">*</span>
                </label>

                {{-- Opsi untuk Intent: "off" (Ingin Libur) --}}
                <div class="methods-off-container grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="method-option-card flex items-start gap-2.5 p-2.5 rounded-xl border-2 border-emerald-500 bg-emerald-50/40 cursor-pointer hover:bg-emerald-50/60 transition-colors">
                        <input
                            type="radio"
                            name="type"
                            value="replacement"
                            checked
                            class="radio-method mt-0.5 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                        >
                        <div class="space-y-0.5">
                            <div class="text-xs font-bold text-slate-900">Minta Rekan Gantikan (Searah)</div>
                            <div class="text-[11px] text-slate-500 leading-tight">Rekan bertugas menggantikan shift Anda.</div>
                        </div>
                    </label>

                    <label class="method-option-card flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:bg-slate-50 transition-colors">
                        <input
                            type="radio"
                            name="type"
                            value="swap"
                            class="radio-method mt-0.5 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                        >
                        <div class="space-y-0.5">
                            <div class="text-xs font-bold text-slate-900">Saling Bertukar Hari (2 Arah)</div>
                            <div class="text-[11px] text-slate-500 leading-tight">Bertukar tanggal shift dengan rekan.</div>
                        </div>
                    </label>
                </div>

                {{-- Opsi untuk Intent: "work" (Ingin Masuk) --}}
                <div class="methods-work-container grid grid-cols-1 sm:grid-cols-2 gap-2 hidden">
                    <label class="method-option-card flex items-start gap-2.5 p-2.5 rounded-xl border border-amber-300 bg-amber-50/40 cursor-pointer hover:bg-amber-50 transition-colors">
                        <input
                            type="radio"
                            name="type"
                            value="change_shift"
                            class="radio-method mt-0.5 text-amber-600 focus:ring-amber-500 cursor-pointer"
                        >
                        <div class="space-y-0.5">
                            <div class="text-xs font-bold text-slate-900">Ambil Slot Kosong & Liburkan Shift Ini</div>
                            <div class="text-[11px] text-slate-500 leading-tight">Masuk di jadwal kosong cabang, dan liburkan jadwal ini.</div>
                        </div>
                    </label>

                    <label class="method-option-card flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:bg-slate-50 transition-colors">
                        <input
                            type="radio"
                            name="type"
                            value="change_off_day"
                            class="radio-method mt-0.5 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                        >
                        <div class="space-y-0.5">
                            <div class="text-xs font-bold text-slate-900">Ambil Slot & Ganti Tanggal Libur</div>
                            <div class="text-[11px] text-slate-500 leading-tight">Pilih tanggal baru sebagai pengganti hari libur resmi.</div>
                        </div>
                    </label>
                </div>
            </div>

            {{-- LANGKAH 3: FORM INPUT DINAMIS / SMART CASCADING --}}
            <div class="space-y-3 pt-1">
                {{-- Box A: Pilih Rekan Pengganti (type === replacement) --}}
                <div class="box-step-input box-replacement space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        3. Rekan Kerja Pengganti <span class="text-rose-600">*</span>
                    </label>
                    <select
                        name="replacement_id"
                        class="select-replacement-user w-full h-10 px-3 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                        <option value="">-- Pilih Rekan Pengganti di Cabang Ini --</option>
                        @php
                            $colleagueList = $colleagues ?? ($people ?? collect());
                            $currentUserId = $shiftItem ? $shiftItem->user_id : auth()->id();
                        @endphp
                        @foreach($colleagueList->where('id', '!=', $currentUserId) as $p)
                            <option
                                value="{{ $p->id }}"
                                data-name="{{ $p->name }}"
                                data-pos="{{ $p->position?->name ?? 'Staf' }}"
                            >
                                {{ $p->name }} ({{ $p->position?->name ?? 'Staf' }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500">Rekan yang dipilih akan menerima notifikasi konfirmasi pergantian shift.</p>
                </div>

                {{-- Box B: Smart Cascading Swap (type === swap) --}}
                <div class="box-step-input box-swap space-y-2.5 hidden">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            3A. Pilih Rekan yang Ingin Diajak Bertukar <span class="text-rose-600">*</span>
                        </label>
                        <select
                            class="select-swap-colleague w-full h-10 px-3 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                        >
                            <option value="">-- Pilih Rekan Kerja --</option>
                            @foreach($colleagueList->where('id', '!=', $currentUserId) as $p)
                                <option
                                    value="{{ $p->id }}"
                                    data-name="{{ $p->name }}"
                                >
                                    {{ $p->name }} ({{ $p->position?->name ?? 'Staf' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center justify-between">
                            <span>3B. Pilih Jadwal Kerja Rekan Tersebut <span class="text-rose-600">*</span></span>
                            <span class="text-[10px] text-emerald-700 font-semibold">(Hanya jadwal rekan yang terpilih)</span>
                        </label>
                        <select
                            name="target_shift_id"
                            class="select-swap-shift w-full h-10 px-3 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                            disabled
                        >
                            <option value="">-- Pilih rekan pada langkah 3A terlebih dahulu --</option>
                            @php
                                $cShifts = $candidateShifts ?? ($shifts ?? collect());
                            @endphp
                            @foreach($cShifts->where('user_id', '!=', $currentUserId)->whereNotNull('user_id') as $otherShift)
                                <option
                                    value="{{ $otherShift->id }}"
                                    data-user-id="{{ $otherShift->user_id }}"
                                    data-user-name="{{ $otherShift->user?->name }}"
                                    data-start="{{ $otherShift->start_at }}"
                                    data-end="{{ $otherShift->end_at }}"
                                    data-label="{{ \Carbon\Carbon::parse($otherShift->start_at)->translatedFormat('l, d M Y') }} ({{ \Carbon\Carbon::parse($otherShift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($otherShift->end_at)->format('H:i') }})"
                                    class="hidden"
                                >
                                    {{ \Carbon\Carbon::parse($otherShift->start_at)->translatedFormat('l, d M Y') }} ({{ \Carbon\Carbon::parse($otherShift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($otherShift->end_at)->format('H:i') }})
                                </option>
                            @endforeach
                        </select>
                        <p class="swap-hint-text text-[11px] text-slate-500">Pilih jadwal rekan yang akan Anda ambil sebagai pengganti hari kerja.</p>
                    </div>
                </div>

                {{-- Box C: Slot Kosong (type === change_shift / change_off_day) --}}
                <div class="box-step-input box-open-slot space-y-1.5 hidden">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        3. Slot Jadwal Kosong Cabang Setingkat <span class="text-rose-600">*</span>
                    </label>
                    @if(isset($openSlots) && $openSlots->isNotEmpty())
                        <select
                            name="target_shift_id"
                            class="select-open-slot w-full h-10 px-3 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                        >
                            <option value="">-- Pilih Salah Satu Jadwal Kosong --</option>
                            @foreach($openSlots as $slot)
                                <option
                                    value="{{ $slot->id }}"
                                    data-date="{{ \Carbon\Carbon::parse($slot->start_at)->translatedFormat('l, d M Y') }}"
                                    data-time="{{ \Carbon\Carbon::parse($slot->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($slot->end_at)->format('H:i') }}"
                                    data-pos="{{ $slot->positionName ?? ($slot->position?->name ?? 'Level Setingkat') }}"
                                >
                                    {{ \Carbon\Carbon::parse($slot->start_at)->translatedFormat('l, d M Y') }} ({{ \Carbon\Carbon::parse($slot->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($slot->end_at)->format('H:i') }}) · {{ $slot->positionName ?? ($slot->position?->name ?? 'Staf') }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-500">Pengajuan jadwal kosong langsung diteruskan ke Super Admin / HRD.</p>
                    @else
                        <div class="p-3 bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs">
                            Saat ini belum ada slot jadwal kosong yang tersedia untuk posisi Anda di cabang ini.
                        </div>
                    @endif
                </div>

                {{-- Box D: Tanggal Libur Resmi Pengganti (type === change_off_day) --}}
                <div class="box-step-input box-off-date space-y-1.5 hidden">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Tanggal Libur Resmi Pengganti <span class="text-rose-600">*</span>
                    </label>
                    <input
                        type="date"
                        name="target_date"
                        value="{{ $shiftItem ? $shiftItem->start_at->toDateString() : now('Asia/Jakarta')->toDateString() }}"
                        class="input-off-date w-full h-10 px-3 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                    <p class="text-[11px] text-slate-500">Tentukan tanggal libur pengganti setelah mengambil slot kerja kosong.</p>
                </div>
            </div>

            {{-- LANGKAH 4: PRATINJAU DAMPAK REAL-TIME (LIVE IMPACT PREVIEW ELEGAN, CLEAN, NO AI SLOP) --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 space-y-3 preview-container">
                <div class="flex items-center justify-between text-xs font-bold text-slate-800 border-b border-slate-200/80 pb-2">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Pratinjau Hasil Perubahan Jadwal</span>
                    </div>
                    <span class="preview-badge px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 text-[10px] font-bold uppercase tracking-wider">
                        Searah: Saya Libur
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    {{-- Status Pemohon --}}
                    <div class="p-2.5 bg-white rounded-xl border border-slate-200/90 shadow-2xs space-y-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Jadwal Anda</span>
                        <div class="preview-my-shift text-xs font-bold text-slate-800">
                            {{ $shiftItem ? (\Carbon\Carbon::parse($shiftItem->start_at)->translatedFormat('d M Y') . ' (' . \Carbon\Carbon::parse($shiftItem->start_at)->format('H:i') . '-' . \Carbon\Carbon::parse($shiftItem->end_at)->format('H:i') . ')') : 'Pilih jadwal Anda' }}
                        </div>
                        <div class="preview-my-status text-xs font-semibold pt-0.5">
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-bold text-rose-700 bg-rose-50 border border-rose-200/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Menjadi Libur (Off)
                            </span>
                        </div>
                    </div>

                    {{-- Status Rekan / Target --}}
                    <div class="p-2.5 bg-white rounded-xl border border-slate-200/90 shadow-2xs space-y-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Rekan / Jadwal Target</span>
                        <div class="preview-target-name text-xs font-bold text-slate-800">
                            (Belum Memilih Rekan)
                        </div>
                        <div class="preview-target-status text-xs font-semibold pt-0.5">
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                Masuk Menggantikan
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Stepper Alur Persetujuan (Two-Tier Approval Gate) --}}
                <div class="pt-2 border-t border-slate-200/70 space-y-1.5">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                        Alur Persetujuan (Two-Tier Approval Gate)
                    </span>
                    <div class="preview-stepper flex items-center gap-1.5 text-[11px] font-medium flex-wrap">
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 font-semibold text-slate-700 shadow-2xs">
                            1. Rekan Setuju
                        </span>
                        <svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-600">
                            2. Review Manajer
                        </span>
                        <svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        <span class="px-2 py-0.5 rounded-md bg-slate-900 text-white font-semibold">
                            3. Jadwal Berpindah
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-400 leading-snug">
                        Jadwal kerja Anda tetap berlaku normal sampai seluruh tahapan persetujuan selesai.
                    </p>
                </div>
            </div>

            {{-- LANGKAH 5: ALASAN PERMOHONAN --}}
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        4. Alasan Pengajuan <span class="text-rose-600">*</span>
                    </label>
                    <span class="reason-char-count text-[10px] font-mono text-slate-400">0 / 500</span>
                </div>
                <textarea
                    name="reason"
                    rows="2"
                    required
                    minlength="5"
                    maxlength="500"
                    placeholder="Tuliskan keterangan permohonan pergantian jadwal..."
                    class="input-reason w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-colors"
                ></textarea>
            </div>

            {{-- TOMBOL AKSI MODAL --}}
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button
                    type="button"
                    data-close-modal
                    class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-50 transition-colors cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="btn-submit-exchange px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider shadow-xs hover:shadow transition-all inline-flex items-center gap-2 cursor-pointer"
                >
                    <span>Kirim Permohonan</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            </div>
        </form>
    </div>
</div>
