@extends('layouts.app')
@section('title', 'Jadwal Shift')

@section('content')
<div class="space-y-6">
    {{-- Header & Aturan Penjadwalan --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Siklus Penjadwalan Terstruktur</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Jadwal Shift Kerja
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Shift baru dibuat sebagai draf. Setiap revisi pada shift yang telah disetujui akan kembali menjadi draf dengan nomor versi meningkat untuk diapprove ulang.
            </p>
        </div>
    </div>

    {{-- Formulir Pembuatan Shift Baru (Khusus Admin) --}}
    @if(auth()->user()->role === 'admin')
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Buat Draf Shift Baru</h2>
            </div>

            <form method="post" action="{{ route('shifts.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                @csrf
                <div>
                    <label for="shift-user" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Karyawan
                    </label>
                    <select 
                        id="shift-user" 
                        name="user_id" 
                        required 
                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer"
                    >
                        @foreach($people as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="shift-branch" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Cabang
                    </label>
                    <select 
                        id="shift-branch" 
                        name="branch_id" 
                        required 
                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer"
                    >
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="shift-date" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Tanggal Shift
                    </label>
                    <input 
                        id="shift-date" 
                        type="date" 
                        name="date" 
                        value="{{ now('Asia/Jakarta')->toDateString() }}" 
                        required 
                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                    >
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="shift-start" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Mulai
                        </label>
                        <input 
                            id="shift-start" 
                            type="time" 
                            name="start_time" 
                            value="09:00" 
                            required 
                            class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 tabular-nums"
                        >
                    </div>
                    <div>
                        <label for="shift-end" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Selesai
                        </label>
                        <input 
                            id="shift-end" 
                            type="time" 
                            name="end_time" 
                            value="17:00" 
                            required 
                            class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 tabular-nums"
                        >
                    </div>
                </div>

                <div>
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-xs transition-colors cursor-pointer flex items-center justify-center gap-1.5 h-[42px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        <span>Buat Draf</span>
                    </button>
                </div>
            </form>
            <p class="text-[11px] text-slate-400 m-0">💡 Catatan: Jam selesai yang lebih awal dari jam mulai secara otomatis dihitung sebagai shift lintas tengah malam (keesokan harinya).</p>
        </section>
    @endif

    {{-- Tabel Daftar Shift --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden space-y-0">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Daftar Jadwal Shift</h2>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                {{ $shifts->total() }} Jadwal Terdaftar
            </span>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <x-table-toolbar :action="route('shifts')" search-placeholder="Cari nama karyawan...">
            <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                <select name="branch_id" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                    <option value="">Semua Cabang</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>

                <select name="status" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                    <option value="">Semua Status</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draf</option>
                    <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                </select>

                <div class="flex items-center gap-1.5">
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="px-2.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" title="Dari Tanggal">
                    <span class="text-xs text-slate-400">s/d</span>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="px-2.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" title="Sampai Tanggal">
                </div>
            </div>
        </x-table-toolbar>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                        <th class="py-3.5 px-4 sm:px-6">Karyawan</th>
                        <th class="py-3.5 px-4">Cabang</th>
                        <th class="py-3.5 px-4">Jadwal Jam Kerja</th>
                        <th class="py-3.5 px-4">Status & Versi</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($shifts as $s)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            {{-- Nama Karyawan --}}
                            <td class="py-4 px-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 font-bold flex items-center justify-center shrink-0 border border-emerald-200/60 text-xs">
                                        {{ strtoupper(substr($s->employee_name, 0, 2)) }}
                                    </div>
                                    <strong class="font-bold text-slate-900 block">{{ $s->employee_name }}</strong>
                                </div>
                            </td>

                            {{-- Cabang --}}
                            <td class="py-4 px-4 font-medium text-slate-700">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                    <span>{{ $s->branch_name }}</span>
                                </span>
                            </td>

                            {{-- Waktu Kerja --}}
                            <td class="py-4 px-4">
                                <span class="font-bold text-slate-900 block tabular-nums">
                                    {{ \Carbon\Carbon::parse($s->start_at)->translatedFormat('d M Y') }}
                                </span>
                                <span class="text-xs text-slate-500 tabular-nums">
                                    {{ \Carbon\Carbon::parse($s->start_at)->format('H:i') }} – {{ \Carbon\Carbon::parse($s->end_at)->format('H:i') }}
                                </span>
                            </td>

                            {{-- Status & Versi --}}
                            <td class="py-4 px-4">
                                @if($s->status === 'approved')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>Disetujui &middot; v{{ $s->version }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        <span>Draf &middot; v{{ $s->version }}</span>
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi Persetujuan & Revisi --}}
                            <td class="py-4 px-4 sm:px-6 text-right">
                                @if(auth()->user()->role === 'admin')
                                    <div class="flex items-center justify-end gap-2">
                                        @if($s->status === 'draft')
                                            <form method="post" action="{{ route('shifts.approve', $s->id) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors shadow-xs cursor-pointer">
                                                    Setujui
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Panel Ubah / Revisi Jadwal --}}
                                        <details class="group relative">
                                            <summary class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer list-none flex items-center gap-1">
                                                <span>Revisi</span>
                                                <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </summary>

                                            <div class="absolute right-0 top-full mt-2 w-72 sm:w-80 bg-white rounded-2xl border border-slate-200 shadow-xl p-4 z-20 text-left space-y-3">
                                                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                                    <strong class="text-xs font-bold text-slate-900">Revisi Jadwal Shift</strong>
                                                    <span class="text-[10px] text-amber-600 font-semibold bg-amber-50 px-1.5 py-0.5 rounded">Revisi = Kembali ke Draf</span>
                                                </div>

                                                <form method="post" action="{{ route('shifts.update', $s->id) }}" class="space-y-3">
                                                    @csrf
                                                    <div>
                                                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Tanggal</label>
                                                        <input type="date" name="date" value="{{ substr($s->start_at, 0, 10) }}" required class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-emerald-500">
                                                    </div>

                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Mulai</label>
                                                            <input type="time" name="start_time" value="{{ substr($s->start_at, 11, 5) }}" required class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-emerald-500 tabular-nums">
                                                        </div>
                                                        <div>
                                                            <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Selesai</label>
                                                            <input type="time" name="end_time" value="{{ substr($s->end_at, 11, 5) }}" required class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-emerald-500 tabular-nums">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Alasan Perubahan (Min. 5 Karakter)</label>
                                                        <textarea name="reason" required minlength="5" placeholder="Contoh: Permintaan tukar jadwal kerja..." class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-emerald-500 min-h-[50px]"></textarea>
                                                    </div>

                                                    <button type="submit" class="w-full py-2 px-3 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                                        Simpan Revisi Shift
                                                    </button>
                                                </form>
                                            </div>
                                        </details>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400 italic">Lihat saja</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <p class="text-sm font-semibold text-slate-500 m-0">Belum ada shift kerja yang dibuat.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$shifts" />
    </section>
</div>
@endsection
