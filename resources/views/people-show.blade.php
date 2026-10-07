@extends('layouts.app')
@section('title', 'Profil ' . $person->name)

@section('content')
<div class="space-y-6">
    {{-- Header Navigasi & Aksi --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('people') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Kembali ke Direktori Karyawan</span>
                </a>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Dossier Karyawan
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Informasi profil lengkap, penempatan kerja, kompensasi, dan jejak riwayat operasional.
            </p>
        </div>

        @if(\App\Support\Access::admin())
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('people.edit', $person->id) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    <span>Ubah Data Karyawan</span>
                </a>

                @if($person->id !== auth()->id())
                    <button 
                        type="button" 
                        data-confirm="Apakah Anda yakin ingin {{ $person->active ? 'menonaktifkan' : 'mengaktifkan kembali' }} akun karyawan {{ $person->name }}?"
                        data-confirm-title="{{ $person->active ? 'Nonaktifkan Akun Karyawan?' : 'Aktifkan Akun Karyawan?' }}"
                        data-confirm-variant="{{ $person->active ? 'warning' : 'primary' }}"
                        data-confirm-btn="{{ $person->active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                        data-confirm-action="{{ route('people.toggle_status', $person->id) }}"
                        class="px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition-colors cursor-pointer"
                    >
                        <span>{{ $person->active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}</span>
                    </button>

                    <button 
                        type="button" 
                        data-confirm="Akun karyawan '{{ $person->name }}' yang dihapus akan dinonaktifkan secara aman jika memiliki riwayat absensi atau dihapus permanen jika belum ada data transaksi."
                        data-confirm-title="Hapus Karyawan {{ $person->name }}?"
                        data-confirm-variant="danger"
                        data-confirm-btn="Ya, Hapus Data"
                        data-confirm-action="{{ route('people.destroy', $person->id) }}"
                        class="px-3.5 py-2.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold shadow-xs transition-colors cursor-pointer"
                    >
                        <span>Hapus Data</span>
                    </button>
                @endif
            </div>
        @endif
    </div>

    {{-- Profil Header Card --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
            <div class="flex items-start sm:items-center gap-5">
                <div class="w-20 h-20 rounded-2xl bg-emerald-700 text-white font-extrabold text-2xl flex items-center justify-center uppercase shadow-xs shrink-0 border-2 border-white ring-4 ring-emerald-50">
                    {{ Str::substr($person->name, 0, 2) }}
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">{{ $person->name }}</h2>
                        @if($person->active)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Aktif Bekerja
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                Nonaktif
                            </span>
                        @endif
                    </div>
                    <p class="text-sm font-medium text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                        <span>{{ $person->email }}</span>
                        <span>&middot;</span>
                        <span class="font-bold text-slate-700">{{ $person->position?->name ?? 'Belum Ditentukan' }}</span>
                        <span>&middot;</span>
                        <span class="text-emerald-700 font-semibold">{{ $person->branch?->name ?? 'Kantor Pusat / Semua Cabang' }}</span>
                    </p>
                    <div class="flex items-center gap-2 mt-2">
                        @php
                            $roleBadge = [
                                'admin' => ['label' => 'Super Admin', 'class' => 'bg-purple-50 text-purple-700 border-purple-200'],
                                'manager' => ['label' => 'Manager Cabang', 'class' => 'bg-sky-50 text-sky-700 border-sky-200'],
                                'employee' => ['label' => 'Karyawan', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
                            ][$person->role] ?? ['label' => $person->role, 'class' => 'bg-slate-100 text-slate-700 border-slate-200'];
                        @endphp
                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold border {{ $roleBadge['class'] }}">
                            {{ $roleBadge['label'] }}
                        </span>
                        <span class="text-xs text-slate-400">ID Akun #{{ $person->id }}</span>
                    </div>
                </div>
            </div>

            {{-- Kartu Status HP Karyawan (Device Binding) --}}
            <div class="bg-slate-50 rounded-xl border border-slate-200/80 p-4 shrink-0 sm:max-w-xs w-full">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kunci HP Karyawan</span>
                    @if($person->device_hash)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Terkunci</span>
                    @else
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-700">Belum Terikat</span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mb-2 leading-relaxed">
                    {{ $person->device_hash ? 'Karyawan hanya bisa absen dari smartphone yang telah terdaftar.' : 'Belum ada smartphone yang dikunci. Karyawan bebas absen dari perangkat pertama.' }}
                </p>
                @if($person->device_hash && \App\Support\Access::admin())
                    <form method="post" action="{{ route('people.update', $person->id) }}">
                        @csrf
                        <input type="hidden" name="name" value="{{ $person->name }}">
                        <input type="hidden" name="email" value="{{ $person->email }}">
                        <input type="hidden" name="role" value="{{ $person->role }}">
                        <input type="hidden" name="branch_id" value="{{ $person->branch_id }}">
                        <input type="hidden" name="position_id" value="{{ $person->position_id }}">
                        <input type="hidden" name="hired_at" value="{{ $person->hired_at }}">
                        <input type="hidden" name="base_salary" value="{{ $person->base_salary }}">
                        <input type="hidden" name="reset_device" value="1">
                        <button type="submit" class="w-full py-1.5 px-3 rounded-lg bg-white hover:bg-slate-100 text-slate-800 text-xs font-bold border border-slate-300 transition-colors shadow-2xs cursor-pointer">
                            Reset Kunci HP Karyawan
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Grid 4 Metrik Detail --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Gaji Pokok Bulanan</span>
            <strong class="text-xl font-extrabold text-slate-900 mt-1 block tabular-nums">
                Rp {{ number_format($person->base_salary, 0, ',', '.') }}
            </strong>
            <span class="text-[11px] text-slate-400 mt-0.5 block">Kompensasi dasar</span>
        </div>

        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Tanggal Mulai Bekerja</span>
            <strong class="text-xl font-extrabold text-slate-900 mt-1 block">
                {{ \Carbon\Carbon::parse($person->hired_at)->translatedFormat('d M Y') }}
            </strong>
            <span class="text-[11px] text-slate-400 mt-0.5 block">Masa kerja: {{ \Carbon\Carbon::parse($person->hired_at)->diffForHumans(null, true) }}</span>
        </div>

        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Cabang Penempatan</span>
            <strong class="text-xl font-extrabold text-emerald-800 mt-1 block truncate">
                {{ $person->branch?->name ?? 'Pusat' }}
            </strong>
            <span class="text-[11px] text-slate-400 mt-0.5 block font-mono">Kode: {{ $person->branch?->code ?? '-' }}</span>
        </div>

        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Jabatan Terdaftar</span>
            <strong class="text-xl font-extrabold text-slate-900 mt-1 block truncate">
                {{ $person->position?->name ?? 'Belum Ada' }}
            </strong>
            <span class="text-[11px] text-slate-400 mt-0.5 block">Struktur resmi</span>
        </div>
    </div>

    {{-- Riwayat Transaksi Operasional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        {{-- Riwayat 10 Presensi Terakhir --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <h3 class="text-base font-bold text-slate-900 tracking-tight m-0">Riwayat Presensi Terbaru</h3>
                </div>
                <span class="text-xs text-slate-400 font-semibold">{{ $person->attendances->count() }} Data</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                            <th class="py-2 px-2.5">Tanggal</th>
                            <th class="py-2 px-2.5">Masuk</th>
                            <th class="py-2 px-2.5">Pulang</th>
                            <th class="py-2 px-2.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($person->attendances as $att)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-2.5 px-2.5 font-bold text-slate-900">
                                    {{ $att->checkin_at ? \Carbon\Carbon::parse($att->checkin_at)->translatedFormat('d M Y') : '-' }}
                                </td>
                                <td class="py-2.5 px-2.5 font-mono text-slate-700">
                                    {{ $att->checkin_at ? \Carbon\Carbon::parse($att->checkin_at)->format('H:i') : '-' }}
                                </td>
                                <td class="py-2.5 px-2.5 font-mono text-slate-700">
                                    {{ $att->checkout_at ? \Carbon\Carbon::parse($att->checkout_at)->format('H:i') : '-' }}
                                </td>
                                <td class="py-2.5 px-2.5 text-right">
                                    @if($att->late_minutes > 0)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                            Telat {{ $att->late_minutes }}m
                                        </span>
                                    @elseif($att->status === 'absent')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            Alfa
                                        </span>
                                    @elseif($att->checkin_at)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Tepat Waktu
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                            -
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-400 italic">
                                    Belum ada catatan presensi untuk karyawan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Riwayat 10 Pengajuan Izin/Sakit --}}
        <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </span>
                    <h3 class="text-base font-bold text-slate-900 tracking-tight m-0">Riwayat Pengajuan Izin & Sakit</h3>
                </div>
                <span class="text-xs text-slate-400 font-semibold">{{ $person->leaveRequests->count() }} Data</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 text-[11px] uppercase tracking-wider font-bold">
                            <th class="py-2 px-2.5">Jenis</th>
                            <th class="py-2 px-2.5">Rentang Tanggal</th>
                            <th class="py-2 px-2.5">Alasan</th>
                            <th class="py-2 px-2.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($person->leaveRequests as $lr)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-2.5 px-2.5 font-bold text-slate-900">
                                    {{ $lr->type === 'sick' ? 'Sakit' : 'Izin' }}
                                </td>
                                <td class="py-2.5 px-2.5 text-slate-600 tabular-nums">
                                    {{ $lr->start_date }} s.d. {{ $lr->end_date }}
                                </td>
                                <td class="py-2.5 px-2.5 text-slate-600 truncate max-w-[150px]">
                                    {{ $lr->reason }}
                                </td>
                                <td class="py-2.5 px-2.5 text-right">
                                    @php
                                        $lrBadge = [
                                            'pending' => ['label' => 'Menunggu', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                                            'approved' => ['label' => 'Disetujui', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                            'rejected' => ['label' => 'Ditolak', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
                                        ][$lr->status] ?? ['label' => $lr->status, 'class' => 'bg-slate-100 text-slate-600 border-slate-200'];
                                    @endphp
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $lrBadge['class'] }}">
                                        {{ $lrBadge['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-400 italic">
                                    Belum ada catatan pengajuan izin untuk karyawan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
