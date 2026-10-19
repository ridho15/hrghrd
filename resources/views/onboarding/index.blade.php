@extends('layouts.app')
@section('title', 'Onboarding Digital & Calon Karyawan')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Manajemen Talenta & Onboarding</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Onboarding Digital Karyawan
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Kelola calon karyawan, alur penawaran kerja otomatis, tanda tangan NDA, verifikasi KTP, dan biometrik wajah.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="{{ route('master-settings.documents.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-2xs transition-colors inline-flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Master Template Dokumen</span>
            </a>
            <a href="{{ route('onboarding.create') }}" class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Input Calon Karyawan Baru</span>
            </a>
        </div>
    </div>

    {{-- Summary Metric Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <a href="{{ route('onboarding.index') }}" class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs hover:border-emerald-300 transition-all">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Kasus</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $counts['total'] }}</div>
            <div class="text-[11px] text-slate-500 mt-0.5">Semua calon karyawan</div>
        </a>
        <a href="{{ route('onboarding.index', ['status' => 'menunggu_persetujuan_hr']) }}" class="p-4 rounded-2xl bg-white border border-purple-200/80 shadow-2xs hover:border-purple-300 transition-all">
            <div class="text-[11px] font-bold text-purple-600 uppercase tracking-wider">Perlu Persetujuan HR</div>
            <div class="text-2xl font-extrabold text-purple-900 mt-1">{{ $counts['menunggu_persetujuan_hr'] }}</div>
            <div class="text-[11px] text-purple-600/80 mt-0.5">Draf offering belum dikirim</div>
        </a>
        <a href="{{ route('onboarding.index', ['status' => 'perlu_verifikasi']) }}" class="p-4 rounded-2xl bg-white border border-amber-200/80 shadow-2xs hover:border-amber-300 transition-all">
            <div class="text-[11px] font-bold text-amber-600 uppercase tracking-wider">Perlu Verifikasi Akhir</div>
            <div class="text-2xl font-extrabold text-amber-900 mt-1">{{ $counts['perlu_verifikasi'] }}</div>
            <div class="text-[11px] text-amber-600/80 mt-0.5">KTP & liveness siap ditinjau</div>
        </a>
        <a href="{{ route('onboarding.index', ['status' => 'selesai']) }}" class="p-4 rounded-2xl bg-white border border-emerald-200/80 shadow-2xs hover:border-emerald-300 transition-all">
            <div class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider">Onboarding Selesai</div>
            <div class="text-2xl font-extrabold text-emerald-900 mt-1">{{ $counts['selesai'] }}</div>
            <div class="text-[11px] text-emerald-600/80 mt-0.5">Karyawan resmi aktif</div>
        </a>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('onboarding.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau email..."
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
            </div>

            <div>
                <select name="branch_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    <option value="">Semua Cabang Penempatan</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select name="status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    <option value="">Semua Status Onboarding</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draf</option>
                    <option value="menunggu_persetujuan_hr" {{ request('status') === 'menunggu_persetujuan_hr' ? 'selected' : '' }}>Menunggu Persetujuan HR</option>
                    <option value="menunggu_tanda_tangan_offer" {{ request('status') === 'menunggu_tanda_tangan_offer' ? 'selected' : '' }}>Menunggu Tanda Tangan Offer</option>
                    <option value="menunggu_tanda_tangan_nda" {{ request('status') === 'menunggu_tanda_tangan_nda' ? 'selected' : '' }}>Menunggu Tanda Tangan NDA</option>
                    <option value="menunggu_ktp" {{ request('status') === 'menunggu_ktp' ? 'selected' : '' }}>Menunggu KTP</option>
                    <option value="menunggu_liveness" {{ request('status') === 'menunggu_liveness' ? 'selected' : '' }}>Menunggu Liveness</option>
                    <option value="perlu_verifikasi" {{ request('status') === 'perlu_verifikasi' ? 'selected' : '' }}>Perlu Verifikasi</option>
                    <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai (Aktif)</option>
                    <option value="dibatalkan" {{ request('status') === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors cursor-pointer">
                    Terapkan Filter
                </button>
                @if(request()->hasAny(['search', 'branch_id', 'status']))
                    <a href="{{ route('onboarding.index') }}" class="px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Candidates Table --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4 sm:px-6">Calon Karyawan</th>
                        <th class="py-3.5 px-4">Jabatan & Cabang</th>
                        <th class="py-3.5 px-4">Atasan Langsung</th>
                        <th class="py-3.5 px-4">Status Alur</th>
                        <th class="py-3.5 px-4">Masa Berlaku</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($cases as $case)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 font-extrabold flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($case->user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('onboarding.show', $case->id) }}" class="font-bold text-slate-900 hover:text-emerald-700 transition-colors">
                                            {{ $case->user->name }}
                                        </a>
                                        <div class="text-[11px] text-slate-400">{{ $case->user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800">{{ $case->position?->name }}</div>
                                <div class="text-[11px] text-slate-500">{{ $case->branch?->name }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($case->supervisor)
                                    <div class="font-semibold text-slate-700">{{ $case->supervisor->name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $case->supervisor->position?->name }}</div>
                                @else
                                    <span class="text-slate-400 italic">Pimpinan Puncak</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $case->status_badge_class }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $case->status === 'selesai' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ $case->status_label }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">
                                {{ $case->offer_valid_until ? $case->offer_valid_until->translatedFormat('d M Y') : '-' }}
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <a href="{{ route('onboarding.show', $case->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-700 text-[11px] font-bold transition-colors">
                                    <span>Detail Alur</span>
                                    <span>→</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                Belum ada data calon karyawan untuk kriteria pencarian ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cases->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $cases->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
