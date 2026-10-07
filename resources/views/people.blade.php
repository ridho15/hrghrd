@extends('layouts.app')
@section('title', 'Direktori Karyawan')

@section('content')
<div class="space-y-6">
    {{-- Header Administrasi & Aksi Cepat --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span>Administrasi Organisasi</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Direktori Karyawan
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola data personel, penugasan cabang, status keaktifan akun, dan kompensasi kerja.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('people.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                <span>+ Tambah Karyawan Baru</span>
            </a>
            <a href="{{ route('branches.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 shadow-xs transition-colors">
                <span>Cabang →</span>
            </a>
            <a href="{{ route('positions.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 shadow-xs transition-colors">
                <span>Jabatan →</span>
            </a>
            <a href="{{ route('import') }}" class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold border border-slate-200 transition-colors shadow-xs">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                <span>Impor Data</span>
            </a>
        </div>
    </div>

    {{-- Toolbar Filter & Pencarian Karyawan --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-4">
        <form method="get" action="{{ route('people') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                {{-- Pencarian Kata Kunci --}}
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Pencarian Nama / Email
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input 
                            name="search" 
                            value="{{ request('search') }}" 
                            placeholder="Ketik nama staf atau alamat email..." 
                            class="w-full pl-9 pr-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                        >
                    </div>
                </div>

                {{-- Filter Cabang --}}
                <div>
                    <x-searchable-select
                        name="branch_id"
                        id="filter-people-branch"
                        label="Filter Cabang"
                        :options="collect([['value' => '', 'label' => 'Semua Cabang', 'sublabel' => 'Semua Lokasi']])->concat(
                            $branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name, 'sublabel' => $b->code])
                        )"
                        :selected="request('branch_id', '')"
                        placeholder="Semua Cabang"
                        searchPlaceholder="Cari cabang..."
                    />
                </div>

                {{-- Filter Jabatan --}}
                <div>
                    <x-searchable-select
                        name="position_id"
                        id="filter-people-position"
                        label="Filter Jabatan"
                        :options="collect([['value' => '', 'label' => 'Semua Jabatan', 'sublabel' => 'Semua Posisi']])->concat(
                            $positions->map(fn($p) => ['value' => $p->id, 'label' => $p->name, 'sublabel' => 'ID #'.$p->id])
                        )"
                        :selected="request('position_id', '')"
                        placeholder="Semua Jabatan"
                        searchPlaceholder="Cari jabatan..."
                    />
                </div>

                {{-- Filter Peran & Status --}}
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Peran
                        </label>
                        <select name="role" class="w-full px-2.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-semibold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                            <option value="">Semua</option>
                            <option value="employee" @selected(request('role') === 'employee')>Karyawan</option>
                            <option value="manager" @selected(request('role') === 'manager')>Manager</option>
                            <option value="admin" @selected(request('role') === 'admin')>Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Status
                        </label>
                        <select name="status" class="w-full px-2.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs font-semibold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                            <option value="">Semua</option>
                            <option value="1" @selected(request('status') === '1')>Aktif</option>
                            <option value="0" @selected(request('status') === '0')>Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                <span class="text-xs text-slate-500 font-medium">
                    Menampilkan <strong>{{ $people->total() }}</strong> total data karyawan
                </span>
                <div class="flex items-center gap-2">
                    @if(request()->hasAny(['search', 'branch_id', 'position_id', 'role', 'status']))
                        <a href="{{ route('people') }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold transition-colors">
                            Reset Filter
                        </a>
                    @endif
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition-colors shadow-2xs cursor-pointer">
                        Terapkan Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Tabel Direktori Karyawan --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden space-y-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-slate-500 text-[11px] uppercase tracking-wider font-bold">
                        <th class="py-3 px-4">Nama Karyawan</th>
                        <th class="py-3 px-4">Cabang & Posisi</th>
                        <th class="py-3 px-4">Peran Akun</th>
                        <th class="py-3 px-4">Gaji Pokok</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($people as $p)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            {{-- Nama Karyawan & Avatar --}}
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 font-bold text-xs flex items-center justify-center uppercase shrink-0 border border-emerald-200/60">
                                        {{ Str::substr($p->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('people.show', $p->id) }}" class="font-bold text-slate-900 hover:text-emerald-700 transition-colors block text-xs sm:text-sm">
                                            {{ $p->name }}
                                        </a>
                                        <span class="text-[11px] text-slate-400 block">{{ $p->email }}</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Cabang & Posisi --}}
                            <td class="py-3.5 px-4">
                                <span class="font-semibold text-slate-800 block text-xs">
                                    {{ $p->branch?->name ?? 'Semua Cabang / Pusat' }}
                                </span>
                                <span class="text-[11px] text-slate-400 block">
                                    {{ $p->position?->name ?? 'Belum Ditentukan' }}
                                </span>
                            </td>

                            {{-- Peran Akun --}}
                            <td class="py-3.5 px-4">
                                @php
                                    $roleBadge = [
                                        'admin' => ['label' => 'Super Admin', 'class' => 'bg-purple-50 text-purple-700 border-purple-200'],
                                        'manager' => ['label' => 'Manager', 'class' => 'bg-sky-50 text-sky-700 border-sky-200'],
                                        'employee' => ['label' => 'Karyawan', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
                                    ][$p->role] ?? ['label' => $p->role, 'class' => 'bg-slate-100 text-slate-700 border-slate-200'];
                                @endphp
                                <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold border {{ $roleBadge['class'] }}">
                                    {{ $roleBadge['label'] }}
                                </span>
                            </td>

                            {{-- Gaji Pokok --}}
                            <td class="py-3.5 px-4 font-mono font-bold text-xs text-slate-800 tabular-nums">
                                Rp {{ number_format($p->base_salary, 0, ',', '.') }}
                            </td>

                            {{-- Status Keaktifan --}}
                            <td class="py-3.5 px-4">
                                @if($p->active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Nonaktif
                                    </span>
                                @endif
                            </td>

                            {{-- Tombol Tindakan Mandiri --}}
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <a href="{{ route('people.show', $p->id) }}" class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition-colors border border-emerald-200/60" title="Buka Profil Lengkap">
                                        Lihat Profil
                                    </a>

                                    <a href="{{ route('people.edit', $p->id) }}" class="px-2.5 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors border border-slate-200" title="Ubah Data">
                                        Ubah
                                    </a>

                                    @if($p->id !== auth()->id())
                                        <button 
                                            type="button" 
                                            data-confirm="Apakah Anda yakin ingin {{ $p->active ? 'menonaktifkan' : 'mengaktifkan kembali' }} akun {{ $p->name }}?"
                                            data-confirm-title="{{ $p->active ? 'Nonaktifkan Akun?' : 'Aktifkan Akun?' }}"
                                            data-confirm-variant="{{ $p->active ? 'warning' : 'primary' }}"
                                            data-confirm-btn="{{ $p->active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                                            data-confirm-action="{{ route('people.toggle_status', $p->id) }}"
                                            class="p-1.5 rounded-lg hover:bg-slate-100 {{ $p->active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }} transition-colors cursor-pointer"
                                            title="{{ $p->active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}"
                                        >
                                            @if($p->active)
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            @endif
                                        </button>

                                        <button 
                                            type="button" 
                                            data-confirm="Akun karyawan '{{ $p->name }}' yang dihapus akan dinonaktifkan secara aman jika memiliki riwayat absensi atau dihapus permanen jika belum ada data transaksi."
                                            data-confirm-title="Hapus Karyawan {{ $p->name }}?"
                                            data-confirm-variant="danger"
                                            data-confirm-btn="Ya, Hapus Data"
                                            data-confirm-action="{{ route('people.destroy', $p->id) }}"
                                            class="p-1.5 rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition-colors cursor-pointer"
                                            title="Hapus Karyawan"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400 text-xs">
                                <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <p class="font-semibold text-slate-500 m-0">Tidak ada data karyawan yang cocok dengan kriteria pencarian.</p>
                                <p class="text-slate-400 mt-1">Coba ubah kata kunci atau hapus filter untuk melihat semua data.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($people->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $people->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
