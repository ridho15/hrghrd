@extends('layouts.app')
@section('title', 'Tinjau Presensi')

@section('content')
<div class="space-y-8">
    {{-- Header Modul --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                <span>Pusat Pengawasan Kehadiran</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Tinjau & Koreksi Presensi
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Verifikasi pengajuan pengecualian kendala teknis, pantau keterlambatan, setujui lembur resmi, dan lakukan koreksi status jika diperlukan.
            </p>
        </div>
    </div>

    {{-- Bagian 1: Pengecualian Menunggu (Pending Exceptions) --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Pengecualian Menunggu Keputusan</h2>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $exceptions->total() ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                {{ $exceptions->total() }} Tertunda
            </span>
        </div>

        <div class="space-y-3">
            @forelse($exceptions as $e)
                <article class="p-4 rounded-xl border border-amber-200/80 bg-amber-50/20 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <strong class="text-sm font-bold text-slate-900">{{ $e->name }}</strong>
                                <span class="text-slate-300">&middot;</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $e->action === 'in' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                    {{ $e->action === 'in' ? 'Check-in' : 'Check-out' }}
                                </span>
                            </div>
                            <span class="text-xs text-slate-500 block mt-0.5 tabular-nums">
                                Shift: {{ $e->start_at }} &middot; Diajukan: {{ $e->created_at }}
                            </span>
                        </div>
                    </div>

                    <p class="text-xs text-slate-700 bg-white p-3 rounded-lg border border-slate-200 leading-relaxed m-0">
                        <strong>Alasan Kendala:</strong> {{ $e->reason }}
                    </p>

                    <form method="post" action="{{ route('attendance.exceptions.review', $e->id) }}" class="flex flex-wrap items-end gap-3 pt-1">
                        @csrf
                        <div class="w-36">
                            <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Keputusan</label>
                            <select name="decision" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-900 focus:outline-hidden focus:ring-1 focus:ring-emerald-500">
                                <option value="approved">Setujui</option>
                                <option value="rejected">Tolak</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Catatan Verifikasi</label>
                            <input name="review_note" required minlength="5" placeholder="Contoh: Bukti foto dan konfirmasi supervisor valid..." class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-900 focus:outline-hidden focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer h-[34px]">
                            Simpan Keputusan
                        </button>
                    </form>
                </article>
            @empty
                <div class="text-center py-6 text-slate-400">
                    <p class="text-xs font-medium m-0">Tidak ada pengajuan pengecualian yang tertunda.</p>
                </div>
            @endforelse
        </div>

        @if($exceptions->hasPages())
            <div class="pt-2">
                <x-pagination :paginator="$exceptions" />
            </div>
        @endif
    </section>

    {{-- Bagian 2: Riwayat Presensi & Formulir Koreksi --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden space-y-0">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Riwayat Presensi & Koreksi</h2>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                {{ $attendances->total() }} Data
            </span>
        </div>

        {{-- Toolbar Filter & Pencarian Presensi --}}
        <x-table-toolbar :action="route('attendance.review')" search-placeholder="Cari nama karyawan...">
            <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                @if(isset($branches) && $branches->isNotEmpty())
                    <div class="w-44 sm:w-48">
                        @php
                            $attBranchOpts = collect([['value' => '', 'label' => 'Semua Cabang', 'sublabel' => '']])
                                ->merge($branches->map(fn($b) => ['value' => (string)$b->id, 'label' => $b->name, 'sublabel' => $b->code]));
                        @endphp
                        <x-searchable-select
                            name="branch_id"
                            id="filter-attendance-branch"
                            size="sm"
                            :value="request('branch_id')"
                            :options="$attBranchOpts"
                            placeholder="Semua Cabang"
                            searchPlaceholder="Cari cabang..."
                        />
                    </div>
                @endif

                <select name="status" class="h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs">
                    <option value="">Semua Status Presensi</option>
                    <option value="present" @selected(request('status') === 'present')>Hadir (Present)</option>
                    <option value="late" @selected(request('status') === 'late')>Terlambat (Late)</option>
                    <option value="absent" @selected(request('status') === 'absent')>Mangkir (Absent)</option>
                    <option value="corrected" @selected(request('status') === 'corrected')>Dikoreksi (Corrected)</option>
                </select>

                <select name="flag" class="h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs">
                    <option value="">Semua Tinjauan Bendera</option>
                    <option value="flagged" @selected(request('flag') === 'flagged')>Perlu Tinjauan Saja (⚑)</option>
                </select>
            </div>
        </x-table-toolbar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">Karyawan & Shift</th>
                        <th class="py-3.5 px-4">Check-in / out</th>
                        <th class="py-3.5 px-4">Status & Bukti</th>
                        <th class="py-3.5 px-4">Terlambat</th>
                        <th class="py-3.5 px-4">Lembur</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Koreksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($attendances as $a)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Karyawan & Shift --}}
                            <td class="py-4 px-4 sm:px-6">
                                <strong class="font-bold text-slate-900 block">{{ $a->name }}</strong>
                                <span class="text-xs text-slate-500 tabular-nums">{{ $a->start_at }} – {{ $a->end_at }}</span>
                            </td>

                            {{-- Waktu Masuk/Keluar --}}
                            <td class="py-4 px-4 tabular-nums">
                                <span class="font-semibold text-slate-800 block">In: {{ $a->checkin_at ?? '—' }}</span>
                                <span class="text-xs text-slate-500">Out: {{ $a->checkout_at ?? '—' }}</span>
                            </td>

                            {{-- Status & Bukti --}}
                            <td class="py-4 px-4">
                                <div class="space-y-1">
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider {{ in_array($a->status, ['present', 'corrected']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($a->status === 'absent' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200') }}">
                                        {{ $a->status }}
                                    </span>
                                    @php
                                        $hasFlags = is_array($a->flags) ? !empty($a->flags) : !empty(json_decode($a->flags ?? '', true));
                                    @endphp
                                    @if($hasFlags)
                                        <span class="block text-[11px] font-bold text-rose-600">
                                            ⚑ Perlu Tinjauan
                                        </span>
                                    @endif

                                    {{-- Inspeksi Bukti --}}
                                    <details class="text-[11px] text-slate-500 pt-0.5">
                                        <summary class="cursor-pointer text-emerald-700 font-semibold hover:underline">Bukti Presensi</summary>
                                        <div class="mt-1 p-2 bg-slate-100 rounded border border-slate-200 text-[10px] space-y-0.5 font-mono">
                                            <div><strong>In:</strong> {{ is_array($a->checkin_evidence) ? json_encode($a->checkin_evidence) : ($a->checkin_evidence ?: '—') }}</div>
                                            <div><strong>Out:</strong> {{ is_array($a->checkout_evidence) ? json_encode($a->checkout_evidence) : ($a->checkout_evidence ?: '—') }}</div>
                                        </div>
                                    </details>
                                </div>
                            </td>

                            {{-- Keterlambatan --}}
                            <td class="py-4 px-4 tabular-nums">
                                <span class="font-semibold text-slate-800">{{ $a->late_minutes }} Menit</span>
                                <span class="text-xs text-slate-500 block">({{ $a->late_units }} Unit Potongan)</span>
                            </td>

                            {{-- Lembur & Approval --}}
                            <td class="py-4 px-4 tabular-nums">
                                <span class="font-semibold text-slate-800">{{ $a->overtime_minutes }} Menit</span>
                                @if($a->overtime_approved_by)
                                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Disetujui
                                    </span>
                                @elseif($a->overtime_minutes)
                                    <form method="post" action="{{ route('attendance.overtime', $a->id) }}" class="mt-1 flex items-center gap-1.5">
                                        @csrf
                                        <input name="reason" required minlength="5" placeholder="Alasan lembur..." class="w-32 px-2 py-1 bg-white border border-slate-200 rounded text-[11px] focus:outline-hidden focus:ring-1 focus:ring-emerald-500">
                                        <button type="submit" class="px-2 py-1 rounded bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-bold transition-colors cursor-pointer">
                                            Setujui
                                        </button>
                                    </form>
                                @endif
                            </td>

                            {{-- Aksi Detail & Koreksi --}}
                            <td class="py-4 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Tombol Detail Relasi Kehadiran --}}
                                    <button
                                        type="button"
                                        data-open-modal="detail-attendance-{{ $a->id }}"
                                        class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200/80 transition-colors cursor-pointer inline-flex items-center gap-1 shadow-2xs"
                                        title="Lihat rincian forensik GPS, geofence, dan audit kehadiran"
                                    >
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        <span>Detail</span>
                                    </button>

                                    <details class="group relative">
                                        <summary class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer list-none inline-flex items-center gap-1">
                                            <span>Koreksi</span>
                                            <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </summary>

                                        <div class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl border border-slate-200 shadow-xl p-4 z-20 text-left space-y-3">
                                            <div class="pb-2 border-b border-slate-100">
                                                <strong class="text-xs font-bold text-slate-900 block">Koreksi Kehadiran</strong>
                                                <span class="text-[10px] text-slate-400">Tercatat ke jejak audit otomatis</span>
                                            </div>

                                            <form method="post" action="{{ route('attendance.correct', $a->id) }}" class="space-y-3">
                                                @csrf
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Status</label>
                                                    <select name="status" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-emerald-500">
                                                        @foreach(['present', 'late', 'absent', 'corrected'] as $st)
                                                            <option value="{{ $st }}" @selected($a->status === $st)>{{ strtoupper($st) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Waktu Masuk (Check-in)</label>
                                                    <input type="datetime-local" name="checkin_at" value="{{ $a->checkin_at ? str_replace(' ', 'T', substr($a->checkin_at, 0, 16)) : '' }}" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-emerald-500 tabular-nums">
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Waktu Keluar (Check-out)</label>
                                                    <input type="datetime-local" name="checkout_at" value="{{ $a->checkout_at ? str_replace(' ', 'T', substr($a->checkout_at, 0, 16)) : '' }}" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-emerald-500 tabular-nums">
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Alasan Koreksi (Min. 10 Karakter)</label>
                                                    <textarea name="reason" required minlength="10" placeholder="Jelaskan dasar investigasi koreksi data..." class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-emerald-500 min-h-[50px]"></textarea>
                                                </div>

                                                <button type="submit" class="w-full py-2 px-3 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                                    Simpan Koreksi
                                                </button>
                                            </form>
                                        </div>
                                    </details>
                                </div>

                                {{-- Modal Detail Forensik Kehadiran Lengkap --}}
                                @php
                                    $inEvidence = is_array($a->checkin_evidence) ? $a->checkin_evidence : json_decode($a->checkin_evidence ?? '', true);
                                    $outEvidence = is_array($a->checkout_evidence) ? $a->checkout_evidence : json_decode($a->checkout_evidence ?? '', true);
                                    $flagList = is_array($a->flags) ? $a->flags : (json_decode($a->flags ?? '', true) ?? []);
                                    $badgeClr = in_array($a->status, ['present', 'corrected']) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($a->status === 'absent' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-700 border-amber-200');
                                @endphp
                                <x-detail-modal
                                    id="detail-attendance-{{ $a->id }}"
                                    title="Forensik Presensi & Audit Lokasi"
                                    subtitle="{{ $a->user?->name ?? 'Karyawan' }} &middot; Shift #SHF-{{ $a->shift_id }}"
                                    badge="{{ strtoupper($a->status) }}"
                                    badgeColor="{{ $badgeClr }}"
                                    maxWidth="3xl"
                                >
                                    {{-- Identitas Karyawan & Relasi Shift --}}
                                    <div class="p-4 rounded-2xl bg-gradient-to-r from-slate-900 to-emerald-950 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div class="flex items-center gap-3.5">
                                            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-300 font-extrabold flex items-center justify-center text-base border border-emerald-500/30 shrink-0">
                                                {{ strtoupper(substr($a->user?->name ?? 'KR', 0, 2)) }}
                                            </div>
                                            <div>
                                                <h4 class="text-base font-bold text-white m-0">{{ $a->user?->name ?? 'Karyawan' }}</h4>
                                                <span class="text-xs text-slate-300 block font-mono">{{ $a->user?->email ?? '—' }}</span>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <span class="text-[11px] font-semibold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-md border border-emerald-500/20">
                                                        {{ $a->user?->position?->title ?? 'Staf' }}
                                                    </span>
                                                    <span class="text-[11px] text-slate-300">
                                                        Cabang Shift: {{ $a->shift?->branch?->name ?? ($a->user?->branch?->name ?? '—') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-left sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-700/60">
                                            <span class="text-[11px] uppercase tracking-wider text-slate-400 block font-bold">Presensi Record</span>
                                            <span class="text-sm font-mono font-bold text-emerald-300">#ATT-{{ str_pad($a->id, 5, '0', STR_PAD_LEFT) }}</span>
                                        </div>
                                    </div>

                                    {{-- Banner Peringatan Bendera Audit Jika Ada --}}
                                    @if(!empty($flagList))
                                        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl space-y-1">
                                            <div class="flex items-center gap-2 text-rose-800 text-xs font-bold">
                                                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                <span>Peringatan Sistem: Anomali Terdeteksi</span>
                                            </div>
                                            <ul class="text-xs text-rose-700 list-disc list-inside space-y-0.5 m-0 pl-1 font-medium">
                                                @foreach($flagList as $flag)
                                                    <li>{{ is_string($flag) ? $flag : json_encode($flag) }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    {{-- Timeline Jam Masuk vs Keluar --}}
                                    <div class="space-y-3">
                                        <h5 class="text-xs font-bold text-slate-900 uppercase tracking-wider m-0">Rekapitulasi Waktu & Deviasi</h5>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            {{-- Waktu Masuk --}}
                                            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/70 space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">Presensi Masuk</span>
                                                    <span class="text-[10px] text-slate-400 font-mono">Check-in</span>
                                                </div>
                                                <div class="text-base font-bold text-slate-900 tabular-nums">
                                                    {{ $a->checkin_at ? \Carbon\Carbon::parse($a->checkin_at)->format('H:i:s') : '—' }}
                                                </div>
                                                <div class="text-[11px] text-slate-500">
                                                    Tanggal: {{ $a->checkin_at ? \Carbon\Carbon::parse($a->checkin_at)->translatedFormat('d F Y') : '—' }}
                                                </div>
                                                <div class="pt-1 border-t border-slate-200">
                                                    @if($a->late_minutes > 0)
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">
                                                            Terlambat: {{ $a->late_minutes }} Menit ({{ $a->late_units }} Unit Potongan)
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                                            Tepat Waktu (0 Menit Terlambat)
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Waktu Keluar --}}
                                            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/70 space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[11px] font-bold text-sky-800 uppercase tracking-wider">Presensi Keluar</span>
                                                    <span class="text-[10px] text-slate-400 font-mono">Check-out</span>
                                                </div>
                                                <div class="text-base font-bold text-slate-900 tabular-nums">
                                                    {{ $a->checkout_at ? \Carbon\Carbon::parse($a->checkout_at)->format('H:i:s') : 'Belum Check-out' }}
                                                </div>
                                                <div class="text-[11px] text-slate-500">
                                                    Tanggal: {{ $a->checkout_at ? \Carbon\Carbon::parse($a->checkout_at)->translatedFormat('d F Y') : '—' }}
                                                </div>
                                                <div class="pt-1 border-t border-slate-200">
                                                    @if($a->overtime_minutes > 0)
                                                        <div class="text-[11px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 inline-block">
                                                            Lembur: {{ $a->overtime_minutes }} Menit &middot; {{ $a->overtimeApprover ? 'Disetujui oleh '.$a->overtimeApprover->name : 'Menunggu Approval' }}
                                                        </div>
                                                    @else
                                                        <span class="text-[11px] text-slate-500">Tidak ada lembur terdata</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Forensik Lokasi GPS & Perangkat Check-in --}}
                                    <div class="space-y-3">
                                        <h5 class="text-xs font-bold text-slate-900 uppercase tracking-wider m-0">Forensik Bukti Check-in (GPS & Keamanan)</h5>
                                        @if($inEvidence && !isset($inEvidence['exception_id']))
                                            <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-2xs space-y-3">
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                    <div class="p-2.5 bg-slate-50 rounded-lg">
                                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Jarak ke Cabang</span>
                                                        <strong class="text-sm font-bold text-slate-900 block tabular-nums">
                                                            {{ $inEvidence['distance_m'] ?? '0' }} meter
                                                        </strong>
                                                        <span class="text-[10px] text-slate-500">
                                                            Batas Radius: {{ $a->shift?->branch?->radius_meters ?? 100 }}m
                                                        </span>
                                                    </div>

                                                    <div class="p-2.5 bg-slate-50 rounded-lg">
                                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Koordinat GPS Masuk</span>
                                                        <span class="text-xs font-mono font-bold text-slate-800 block">
                                                            {{ isset($inEvidence['latitude']) ? number_format($inEvidence['latitude'], 6) : '—' }},
                                                            {{ isset($inEvidence['longitude']) ? number_format($inEvidence['longitude'], 6) : '—' }}
                                                        </span>
                                                        <span class="text-[10px] text-slate-500">Akurasi GPS: &plusmn;{{ $inEvidence['accuracy'] ?? '—' }}m</span>
                                                    </div>

                                                    <div class="p-2.5 bg-slate-50 rounded-lg">
                                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Verifikasi Peta</span>
                                                        @if(isset($inEvidence['latitude'], $inEvidence['longitude']))
                                                            <a
                                                                href="https://www.google.com/maps?q={{ $inEvidence['latitude'] }},{{ $inEvidence['longitude'] }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-800 underline mt-1"
                                                            >
                                                                <span>Buka Google Maps</span>
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                            </a>
                                                        @else
                                                            <span class="text-xs text-slate-400">—</span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="pt-2 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                                    <span class="text-slate-500">
                                                        Device Hash: <span class="font-mono text-slate-700 font-medium">{{ isset($inEvidence['device_hash']) ? substr($inEvidence['device_hash'], 0, 16).'...' : '—' }}</span>
                                                    </span>
                                                    <span class="text-emerald-700 font-bold inline-flex items-center gap-1">
                                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                                        <span>Dynamic QR Code Tervalidasi</span>
                                                    </span>
                                                </div>
                                            </div>
                                        @elseif($inEvidence && isset($inEvidence['exception_id']))
                                            <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800 space-y-1">
                                                <strong>Presensi Diizinkan via Pengecualian Khusus</strong>
                                                <p class="m-0 text-amber-700">Exception ID: #{{ $inEvidence['exception_id'] }} &middot; Disetujui oleh Reviewer #{{ $inEvidence['approved_by'] ?? '—' }}</p>
                                            </div>
                                        @else
                                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/70 text-xs text-slate-500">
                                                Data bukti geofence check-in belum tersimpan atau presensi dilakukan melalui penyesuaian administratif manual.
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Forensik Check-out Jika Ada --}}
                                    @if($outEvidence)
                                        <div class="space-y-3">
                                            <h5 class="text-xs font-bold text-slate-900 uppercase tracking-wider m-0">Forensik Bukti Check-out</h5>
                                            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-2xs grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                                <div>
                                                    <span class="text-slate-500 block">Jarak Check-out ke Cabang:</span>
                                                    <strong class="font-bold text-slate-900">{{ $outEvidence['distance_m'] ?? '—' }} meter</strong>
                                                </div>
                                                <div>
                                                    <span class="text-slate-500 block">Koordinat Check-out:</span>
                                                    <span class="font-mono font-medium text-slate-800">
                                                        {{ isset($outEvidence['latitude'], $outEvidence['longitude']) ? number_format($outEvidence['latitude'], 5).', '.number_format($outEvidence['longitude'], 5) : '—' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </x-detail-modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <p class="text-sm font-semibold text-slate-500 m-0">Belum ada data presensi yang tercatat atau sesuai filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$attendances" />
    </section>

    {{-- Bagian 3: Audit Percobaan 7 Hari Terakhir --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden space-y-0">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Audit Percobaan Presensi (7 Hari Terakhir)</h2>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                {{ $attempts->total() }} Percobaan
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">Waktu Server</th>
                        <th class="py-3.5 px-4">Karyawan</th>
                        <th class="py-3.5 px-4">Aksi</th>
                        <th class="py-3.5 px-4">Hasil Evaluasi</th>
                        <th class="py-3.5 px-4 sm:px-6">Alasan / Bukti Evaluasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($attempts as $t)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-4 sm:px-6 font-mono text-xs tabular-nums text-slate-600">
                                {{ $t->server_at }}
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900">
                                {{ $t->name }}
                            </td>
                            <td class="py-3.5 px-4 font-semibold uppercase text-xs text-slate-700">
                                {{ $t->action }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($t->result === 'rejected')
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        Ditolak
                                    </span>
                                @else
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Diterima
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-xs text-slate-600">
                                <span class="font-medium text-slate-800">{{ $t->reason ?? '—' }}</span>
                                <details class="text-[11px] text-slate-400 mt-0.5">
                                    <summary class="cursor-pointer text-emerald-700 font-semibold hover:underline">Rincian Evaluasi</summary>
                                    <pre class="mt-1 p-2 bg-slate-50 rounded border border-slate-200 text-[10px] whitespace-pre-wrap font-mono">{{ is_array($t->evidence) ? json_encode($t->evidence, JSON_PRETTY_PRINT) : $t->evidence }}</pre>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400">
                                <p class="text-xs font-medium m-0">Belum ada riwayat percobaan presensi.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$attempts" />
    </section>
</div>
@endsection
