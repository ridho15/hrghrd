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
                <select name="status" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                    <option value="">Semua Status Presensi</option>
                    <option value="present" @selected(request('status') === 'present')>Hadir (Present)</option>
                    <option value="late" @selected(request('status') === 'late')>Terlambat (Late)</option>
                    <option value="absent" @selected(request('status') === 'absent')>Mangkir (Absent)</option>
                    <option value="corrected" @selected(request('status') === 'corrected')>Dikoreksi (Corrected)</option>
                </select>

                <select name="flag" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
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
                                    @if($a->flags && json_decode($a->flags))
                                        <span class="block text-[11px] font-bold text-rose-600">
                                            ⚑ Perlu Tinjauan
                                        </span>
                                    @endif

                                    {{-- Inspeksi Bukti --}}
                                    <details class="text-[11px] text-slate-500 pt-0.5">
                                        <summary class="cursor-pointer text-emerald-700 font-semibold hover:underline">Bukti Presensi</summary>
                                        <div class="mt-1 p-2 bg-slate-100 rounded border border-slate-200 text-[10px] space-y-0.5 font-mono">
                                            <div><strong>In:</strong> {{ $a->checkin_evidence ?: '—' }}</div>
                                            <div><strong>Out:</strong> {{ $a->checkout_evidence ?: '—' }}</div>
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

                            {{-- Koreksi Modal/Panel --}}
                            <td class="py-4 px-4 sm:px-6 text-right">
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
                                    <pre class="mt-1 p-2 bg-slate-50 rounded border border-slate-200 text-[10px] whitespace-pre-wrap font-mono">{{ $t->evidence }}</pre>
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
