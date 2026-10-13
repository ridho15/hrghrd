@extends('layouts.app')
@section('title', 'Permohonan Pergantian Shift')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-700 bg-sky-50 px-2.5 py-1 rounded-md mb-2 border border-sky-200/60">
                <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                <span>Sistem Persetujuan Bertingkat (Two-Tier Workflow)</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Permohonan Pergantian & Pertukaran Shift
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola permohonan pengalihan atau pertukaran jadwal antar karyawan. Memerlukan konfirmasi rekan kerja dan persetujuan manajerial.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a
                href="{{ route('shifts.calendar') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold uppercase tracking-wider shadow-2xs transition-all"
            >
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Buka Kalender</span>
            </a>
        </div>
    </div>

    {{-- Tab Switcher --}}
    <div class="flex items-center gap-2 border-b border-slate-200">
        <a
            href="{{ route('shifts.calendar') }}"
            class="inline-flex items-center gap-2 px-4 py-3 border-b-2 border-transparent text-slate-500 hover:text-slate-800 font-medium text-sm transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <span>Kalender Shift</span>
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
            class="inline-flex items-center gap-2 px-4 py-3 border-b-2 border-emerald-600 text-emerald-800 font-bold text-sm -mb-px"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            <span>Permohonan Pergantian Shift</span>
            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-800">{{ $exchanges->total() }}</span>
        </a>
    </div>

    {{-- Filter Toolbar --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <x-table-toolbar :action="route('shifts.exchanges')" search-placeholder="Cari pemohon, rekan, atau alasan...">
            <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                @if(auth()->user()->role === 'admin')
                    <div class="w-44 sm:w-48">
                        @php
                            $excBranchOpts = collect([['value' => '', 'label' => 'Semua Cabang', 'sublabel' => '']])
                                ->merge($branches->map(fn($b) => ['value' => (string)$b->id, 'label' => $b->name, 'sublabel' => $b->code]));
                        @endphp
                        <x-searchable-select
                            name="branch_id"
                            id="filter-exc-branch"
                            size="sm"
                            :value="request('branch_id')"
                            :options="$excBranchOpts"
                            placeholder="Semua Cabang"
                            searchPlaceholder="Cari cabang..."
                        />
                    </div>
                @endif

                <select
                    name="status"
                    class="h-8 px-2.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer shadow-2xs"
                >
                    <option value="">Semua Status</option>
                    <option value="pending_peer" @selected(request('status') === 'pending_peer')>Menunggu Rekan</option>
                    <option value="pending_manager" @selected(request('status') === 'pending_manager')>Menunggu Manajer</option>
                    <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Ditolak</option>
                </select>
            </div>
        </x-table-toolbar>

        {{-- Tabel Daftar Permohonan --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">Pemohon & Shift Asal</th>
                        <th class="py-3.5 px-4">Pengganti / Jadwal Kosong</th>
                        <th class="py-3.5 px-4">Tipe & Cabang</th>
                        <th class="py-3.5 px-4">Alasan & Info Jadwal</th>
                        <th class="py-3.5 px-4">Status & Waktu</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($exchanges as $exc)
                        @php
                            $reqShift = $exc->requesterShift;
                            $targetShift = $exc->targetShift;
                            $user = auth()->user();
                            $isPeer = $user->id === $exc->replacement_id;
                            $isManager = in_array($user->role, ['admin', 'manager']) && ($user->role === 'admin' || $user->branch_id === $exc->branch_id);
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Pemohon & Shift Asal --}}
                            <td class="py-4 px-4 sm:px-6 whitespace-nowrap">
                                <div class="font-bold text-slate-900">{{ $exc->requester?->name }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">{{ $exc->requester?->position?->name ?? 'Staf' }}</div>
                                @if($reqShift)
                                    <div class="mt-1 text-xs text-slate-600 bg-slate-100/80 px-2 py-0.5 rounded inline-block">
                                        {{ \Carbon\Carbon::parse($reqShift->start_at)->translatedFormat('d M') }} · 
                                        {{ \Carbon\Carbon::parse($reqShift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($reqShift->end_at)->format('H:i') }}
                                    </div>
                                @endif
                            </td>

                            {{-- Karyawan Pengganti / Jadwal Kosong --}}
                            <td class="py-4 px-4 whitespace-nowrap">
                                @if($exc->isOpenSlot)
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200/80 font-bold text-xs shadow-2xs">
                                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <span>Slot Jadwal Kosong</span>
                                    </div>
                                    <div class="text-[11px] text-slate-600 font-mono mt-1">
                                        Level: <span class="font-bold text-emerald-700">{{ $exc->targetPositionName }}</span>
                                    </div>
                                    @if($targetShift)
                                        <div class="mt-1 text-xs text-sky-800 bg-sky-50 border border-sky-200/80 px-2 py-0.5 rounded-md inline-block font-semibold">
                                            {{ \Carbon\Carbon::parse($targetShift->start_at)->translatedFormat('d M Y') }} · 
                                            {{ \Carbon\Carbon::parse($targetShift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($targetShift->end_at)->format('H:i') }}
                                        </div>
                                    @endif
                                @else
                                    <div class="font-bold text-slate-900">{{ $exc->replacement?->name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $exc->replacement?->position?->name ?? 'Staf' }}</div>
                                    @if($targetShift)
                                        <div class="mt-1 text-xs text-slate-600 bg-sky-50 text-sky-800 border border-sky-200 px-2 py-0.5 rounded inline-block">
                                            Tukar: {{ \Carbon\Carbon::parse($targetShift->start_at)->translatedFormat('d M') }} ({{ \Carbon\Carbon::parse($targetShift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($targetShift->end_at)->format('H:i') }})
                                        </div>
                                    @endif
                                @endif
                            </td>

                            {{-- Tipe & Cabang --}}
                            <td class="py-4 px-4 whitespace-nowrap">
                                <div class="font-semibold text-slate-800">
                                    @if($exc->type === 'change_shift')
                                        <span class="inline-flex items-center gap-1 text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-200/80 font-bold text-xs">
                                            <span>Ganti Shift (Slot Kosong)</span>
                                        </span>
                                    @elseif($exc->type === 'change_off_day')
                                        <span class="inline-flex items-center gap-1 text-indigo-800 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200/80 font-bold text-xs">
                                            <span>Ganti Libur (Slot Kosong)</span>
                                        </span>
                                    @elseif($exc->type === 'replacement')
                                        <span class="inline-flex items-center gap-1 text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200/60 font-bold text-xs">
                                            <span>Searah (Penggantian)</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-sky-700 bg-sky-50 px-2 py-0.5 rounded border border-sky-200/60 font-bold text-xs">
                                            <span>Dua Arah (Tukar)</span>
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 mt-1">{{ $exc->branch?->name }}</div>
                            </td>

                            {{-- Alasan & Informasi Jadwal Kosong --}}
                            <td class="py-4 px-4 max-w-sm">
                                <p class="text-xs text-slate-800 font-medium leading-relaxed" title="{{ $exc->reason }}">{{ $exc->reason }}</p>
                                
                                {{-- Kotak Informasi Khusus Jadwal Kosong untuk Super Admin / HRD --}}
                                @if($exc->isOpenSlot)
                                    <div class="mt-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-[11px] space-y-1 shadow-2xs">
                                        <div class="font-bold text-slate-900 flex items-center gap-1.5 pb-1 border-b border-slate-200/60">
                                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                            <span>Informasi Jadwal Kosong yang Diajukan</span>
                                        </div>
                                        <div class="text-slate-700 flex items-baseline justify-between gap-2">
                                            <span class="text-slate-500">Jadwal Kosong Dituju:</span>
                                            <span class="font-bold text-slate-900 text-right">
                                                {{ $targetShift ? \Carbon\Carbon::parse($targetShift->start_at)->translatedFormat('l, d M Y') . ' (' . \Carbon\Carbon::parse($targetShift->start_at)->format('H:i') . '-' . \Carbon\Carbon::parse($targetShift->end_at)->format('H:i') . ')' : '-' }}
                                            </span>
                                        </div>
                                        <div class="text-slate-700 flex items-baseline justify-between gap-2">
                                            <span class="text-slate-500">Kesesuaian Level/Jabatan:</span>
                                            <span class="font-bold text-emerald-700 text-right">
                                                {{ $exc->targetPositionName }} (Setingkat: {{ $exc->requester?->position?->name ?? 'Staf' }})
                                            </span>
                                        </div>
                                        @if($exc->type === 'change_shift' && $reqShift)
                                            <div class="text-slate-700 flex items-baseline justify-between gap-2">
                                                <span class="text-slate-500">Shift Asal Pemohon:</span>
                                                <span class="font-bold text-rose-700 text-right">
                                                    {{ \Carbon\Carbon::parse($reqShift->start_at)->translatedFormat('d M Y') }} ({{ \Carbon\Carbon::parse($reqShift->start_at)->format('H:i') }}-{{ \Carbon\Carbon::parse($reqShift->end_at)->format('H:i') }}) → Libur
                                                </span>
                                            </div>
                                        @elseif($exc->type === 'change_off_day')
                                            <div class="text-slate-700 flex items-baseline justify-between gap-2">
                                                <span class="text-slate-500">Tanggal Libur Terkait:</span>
                                                <span class="font-bold text-indigo-700 text-right">
                                                    {{ $exc->target_date ? \Carbon\Carbon::parse($exc->target_date)->translatedFormat('l, d M Y') : 'Hari Libur Pemohon' }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                @if($exc->replacement_note)
                                    <div class="text-[11px] text-slate-500 mt-1 italic">Catatan Rekan: {{ $exc->replacement_note }}</div>
                                @endif
                                @if($exc->review_note)
                                    <div class="text-[11px] text-emerald-700 mt-1 italic">Catatan Manajer: {{ $exc->review_note }}</div>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="py-4 px-4 whitespace-nowrap">
                                @if($exc->status === 'approved')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>Disetujui</span>
                                    </span>
                                @elseif($exc->status === 'pending_peer')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        <span>Menunggu Rekan</span>
                                    </span>
                                @elseif($exc->status === 'pending_manager')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                        <span>Menunggu Manajer</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        <span>Ditolak</span>
                                    </span>
                                @endif
                                <div class="text-[10px] text-slate-400 font-mono mt-1">
                                    {{ $exc->created_at->translatedFormat('d M Y, H:i') }}
                                </div>
                            </td>

                            {{-- Tindakan --}}
                            <td class="py-4 px-4 sm:px-6 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Persetujuan Rekan (Peer) --}}
                                    @if($isPeer && $exc->status === 'pending_peer')
                                        <form method="post" action="{{ route('shifts.exchanges.peer-respond', $exc->id) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="action" value="accept">
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors cursor-pointer"
                                                title="Terima pergantian shift ini"
                                            >
                                                Terima
                                            </button>
                                        </form>

                                        <form method="post" action="{{ route('shifts.exchanges.peer-respond', $exc->id) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="action" value="reject">
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition-colors cursor-pointer"
                                                title="Tolak pergantian shift ini"
                                            >
                                                Tolak
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Persetujuan Manajer/Admin --}}
                                    @if($isManager && in_array($exc->status, ['pending_manager', 'pending_peer']))
                                        <form method="post" action="{{ route('shifts.exchanges.review', $exc->id) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="decision" value="approve">
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition-colors cursor-pointer"
                                                title="Setujui dan alihkan shift"
                                            >
                                                Setujui
                                            </button>
                                        </form>

                                        <form method="post" action="{{ route('shifts.exchanges.review', $exc->id) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="decision" value="reject">
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition-colors cursor-pointer"
                                                title="Tolak permohonan"
                                            >
                                                Tolak
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                <span class="font-bold text-slate-600 block">Belum ada permohonan pergantian shift.</span>
                                <span class="text-xs text-slate-400">Permohonan yang diajukan oleh karyawan atau manajer akan muncul di sini.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($exchanges->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                {{ $exchanges->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
