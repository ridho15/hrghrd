@extends('layouts.app')
@section('title', 'Daftar Kontrak Kerja Karyawan')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>Hubungan Industrial & Perjanjian</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Kontrak Kerja (PKWT / PKWTT)
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola naskah kontrak kerja resmi karyawan, proses tanda tangan elektronik multi-pihak, masa berlaku, dan arsip digital.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('people') }}" class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Pilih Karyawan untuk Buat Kontrak</span>
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('contracts.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama karyawan atau nomor kontrak..."
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
            </div>

            <div>
                <select name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    <option value="">Semua Status Kontrak</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draf</option>
                    <option value="menunggu_tanda_tangan" {{ request('status') === 'menunggu_tanda_tangan' ? 'selected' : '' }}>Menunggu Tanda Tangan</option>
                    <option value="ditandatangani" {{ request('status') === 'ditandatangani' ? 'selected' : '' }}>Selesai Ditandatangani</option>
                    <option value="dibatalkan" {{ request('status') === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors cursor-pointer">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('contracts.index') }}" class="px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition-colors">Reset</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Contracts Table --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4 sm:px-6">Nomor Dokumen</th>
                        <th class="py-3.5 px-4">Karyawan</th>
                        <th class="py-3.5 px-4">Jabatan & Cabang</th>
                        <th class="py-3.5 px-4">Status Kontrak</th>
                        <th class="py-3.5 px-4">Tanggal Mulai</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($contracts as $c)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-4 sm:px-6 font-mono font-bold text-slate-900">
                                {{ $c->document_number }}
                            </td>
                            <td class="py-3.5 px-4">
                                <a href="{{ route('people.show', $c->user_id) }}" class="font-bold text-slate-900 hover:text-emerald-700">
                                    {{ $c->user->name }}
                                </a>
                                <div class="text-[11px] text-slate-400">{{ $c->user->email }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800">{{ $c->user->position?->name ?: '-' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $c->user->branch?->name ?: '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($c->isSigned())
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        ✓ Ditandatangani
                                    </span>
                                @elseif($c->isPendingSignature())
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                                        Menunggu E-Sign
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        Draf
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">
                                {{ $c->user->hired_at ? $c->user->hired_at->translatedFormat('d M Y') : '-' }}
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <a href="{{ route('contracts.show', $c->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-700 text-[11px] font-bold transition-colors">
                                    <span>Buka Dokumen</span>
                                    <span>→</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                Belum ada naskah kontrak kerja yang terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($contracts->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $contracts->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
