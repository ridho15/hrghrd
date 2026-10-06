@extends('layouts.app')
@section('title', 'Izin & Sakit')

@section('content')
<div class="space-y-6">
    {{-- Header Modul & Info Kebijakan --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Ketentuan Cuti & Sakit</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Pengajuan Izin & Sakit
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Izin biasa diajukan minimal H-{{ \App\Support\Rules::int('leave_notice_days') }} sebelum tanggal mulai. Pengajuan sakit wajib menyertakan surat keterangan medis.
            </p>
        </div>
    </div>

    {{-- Grid: Form Pengajuan (5 Cols) & Riwayat / Review (7 Cols) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Sisi Kiri: Form Pengajuan (5 Cols) --}}
        <section class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-5">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </span>
                <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Formulir Pengajuan</h2>
            </div>

            <form method="post" action="{{ route('leave.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                {{-- Khusus Admin & Manager: Pilih Karyawan --}}
                @if(in_array(auth()->user()->role, ['admin', 'manager']))
                    <div>
                        <label for="leave-user" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Karyawan Pemohon
                        </label>
                        <select 
                            id="leave-user" 
                            name="user_id" 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer"
                        >
                            <option value="{{ auth()->id() }}">Saya sendiri ({{ auth()->user()->name }})</option>
                            @foreach($people as $p)
                                @if($p->id !== auth()->id())
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Jenis Pengajuan (Wajib ID leave-type untuk JS) --}}
                <div>
                    <label for="leave-type" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Jenis Pengajuan
                    </label>
                    <select 
                        id="leave-type" 
                        name="type" 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-semibold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer"
                    >
                        <option value="leave">Izin Biasa / Cuti</option>
                        <option value="sick">Sakit (Wajib Surat Dokter)</option>
                    </select>
                </div>

                {{-- Rentang Tanggal Mulai & Selesai --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="start_date" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tanggal Mulai
                        </label>
                        <input 
                            id="start_date" 
                            type="date" 
                            name="start_date" 
                            required 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                        >
                    </div>
                    <div>
                        <label for="end_date" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tanggal Selesai
                        </label>
                        <input 
                            id="end_date" 
                            type="date" 
                            name="end_date" 
                            required 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                        >
                    </div>
                </div>

                {{-- Alasan Pengajuan --}}
                <div>
                    <label for="reason" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Alasan Lengkap
                    </label>
                    <textarea 
                        id="reason" 
                        name="reason" 
                        minlength="10" 
                        maxlength="1000" 
                        required 
                        placeholder="Jelaskan keperluan izin atau kondisi kesehatan yang dialami..."
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 min-h-[90px]"
                    ></textarea>
                </div>

                {{-- Unggah Surat Keterangan Dokter --}}
                <div class="p-4 bg-slate-50/70 rounded-xl border border-dashed border-slate-300 space-y-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Surat Keterangan Sakit
                    </label>
                    <input 
                        type="file" 
                        name="certificate" 
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer"
                    >
                    <p class="text-[11px] text-slate-400 m-0">Format didukung: PDF, JPG, PNG (Maksimal 5 MB). Wajib untuk jenis sakit.</p>
                </div>

                <button type="submit" class="w-full py-3 px-5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm tracking-wide shadow-xs transition-colors cursor-pointer flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    <span>Kirim Pengajuan</span>
                </button>
            </form>
        </section>

        {{-- Sisi Kanan: Daftar Pengajuan & Approval Matrix (7 Cols) --}}
        <section class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </span>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Daftar Pengajuan</h2>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                    {{ $requests->total() }} Pengajuan
                </span>
            </div>

            {{-- Toolbar Filter & Pencarian Pengajuan --}}
            <x-table-toolbar :action="route('leave')" search-placeholder="Cari nama karyawan...">
                <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                    <select name="type" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                        <option value="">Semua Jenis</option>
                        <option value="leave" @selected(request('type') === 'leave')>Izin / Cuti</option>
                        <option value="sick" @selected(request('type') === 'sick')>Sakit</option>
                    </select>

                    <select name="status" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                        <option value="">Semua Status</option>
                        <option value="pending" @selected(request('status') === 'pending')>Menunggu</option>
                        <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                        <option value="partial" @selected(request('status') === 'partial')>Sebagian</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Ditolak</option>
                    </select>
                </div>
            </x-table-toolbar>

            <div class="space-y-4">
                @forelse($requests as $r)
                    <article class="p-4 sm:p-5 rounded-xl border border-slate-200/80 bg-slate-50/30 space-y-3.5 hover:border-slate-300 transition-colors">
                        {{-- Baris Atas: Pemohon, Jenis & Status --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
                            <div>
                                <div class="flex items-center gap-2">
                                    <strong class="text-sm font-bold text-slate-900">{{ $r->employee_name }}</strong>
                                    <span class="text-slate-300">&middot;</span>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ $r->type === 'sick' ? 'Surat Sakit' : 'Izin Kerja' }}
                                    </span>
                                </div>
                                <span class="text-[11px] text-slate-400 block mt-0.5 tabular-nums">
                                    Periode: {{ $r->start_date }} s.d. {{ $r->end_date }} &middot; Diajukan {{ substr($r->created_at, 0, 16) }}
                                </span>
                            </div>

                            @php
                                $statusBadge = [
                                    'pending' => ['label' => 'Menunggu', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                                    'approved' => ['label' => 'Disetujui', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                    'partial' => ['label' => 'Sebagian', 'class' => 'bg-sky-50 text-sky-700 border-sky-200'],
                                    'rejected' => ['label' => 'Ditolak', 'class' => 'bg-rose-50 text-rose-700 border-rose-200']
                                ][$r->status] ?? ['label' => $r->status, 'class' => 'bg-slate-100 text-slate-600 border-slate-200'];
                            @endphp
                            <span class="self-start sm:self-center px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $statusBadge['class'] }}">
                                {{ $statusBadge['label'] }}
                            </span>
                        </div>

                        {{-- Isi Alasan --}}
                        <p class="text-xs text-slate-600 leading-relaxed m-0 bg-white p-3 rounded-lg border border-slate-100">
                            {{ $r->reason }}
                        </p>

                        {{-- Tautan Surat Dokter Jika Ada --}}
                        @if($r->certificate_path)
                            <div class="flex items-center gap-2">
                                <a href="{{ route('leave.certificate', $r->id) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                    <span>Lihat Surat Keterangan Dokter ↗</span>
                                </a>
                            </div>
                        @endif

                        {{-- Chips Matrix Status Harian --}}
                        <div class="day-chips flex flex-wrap gap-1.5 pt-1">
                            @foreach($days[$r->id] ?? [] as $d)
                                @php
                                    $dayClass = $d->status === 'approved' 
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                                        : ($d->status === 'rejected' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-100 text-slate-600 border-slate-200');
                                    $dayText = $d->status === 'approved' 
                                        ? ($d->paid ? 'Dibayar' : 'Unpaid') 
                                        : ($d->status === 'rejected' ? 'Ditolak' : 'Menunggu');
                                @endphp
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border tabular-nums {{ $dayClass }}">
                                    {{ $d->date }}: {{ $dayText }}
                                </span>
                            @endforeach
                        </div>

                        {{-- Catatan Peninjau --}}
                        @if($r->review_note)
                            <div class="text-[11px] text-slate-500 bg-slate-100/70 p-2.5 rounded-lg border border-slate-200/60">
                                <strong class="text-slate-700">Catatan Peninjau:</strong> {{ $r->review_note }}
                            </div>
                        @endif

                        {{-- Formulir Keputusan Manajer (Khusus Status Pending dan Bukan Diri Sendiri) --}}
                        @if(in_array(auth()->user()->role, ['admin', 'manager']) && $r->status === 'pending' && $r->created_by !== auth()->id())
                            <div class="mt-3 pt-3 border-t border-slate-200">
                                <details class="group">
                                    <summary class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 hover:text-emerald-800 cursor-pointer list-none">
                                        <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                        <span>Putuskan Persetujuan per Tanggal</span>
                                    </summary>

                                    <form method="post" action="{{ route('leave.review', $r->id) }}" class="mt-3 p-4 bg-white rounded-xl border border-slate-200 shadow-xs space-y-4">
                                        @csrf
                                        <div class="p-3 bg-amber-50/60 rounded-lg border border-amber-200/80 text-[11px] text-amber-900 leading-relaxed">
                                            💡 Centang tanggal yang disetujui. Tanggal yang tidak dicentang otomatis ditolak. Untuk pengajuan sakit, 2 hari pertama yang disetujui dibayar otomatis.
                                        </div>

                                        <div class="space-y-2">
                                            @foreach($days[$r->id] ?? [] as $d)
                                                <div class="flex items-center gap-4 p-2 rounded-lg bg-slate-50 border border-slate-100 text-xs">
                                                    <label class="flex items-center gap-2 font-semibold text-slate-800 cursor-pointer">
                                                        <input type="checkbox" name="approved_dates[]" value="{{ $d->date }}" class="rounded text-emerald-600 focus:ring-emerald-500">
                                                        <span>Setujui {{ $d->date }}</span>
                                                    </label>
                                                    @if($r->type === 'leave')
                                                        <label class="flex items-center gap-1.5 font-medium text-slate-600 cursor-pointer ml-auto">
                                                            <input type="checkbox" name="paid_dates[]" value="{{ $d->date }}" class="rounded text-emerald-600 focus:ring-emerald-500">
                                                            <span>Dibayar (Paid)</span>
                                                        </label>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                                Catatan Keputusan Peninjau
                                            </label>
                                            <textarea 
                                                name="review_note" 
                                                required 
                                                minlength="5" 
                                                placeholder="Tuliskan pertimbangan atau alasan keputusan..."
                                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 min-h-[60px]"
                                            ></textarea>
                                        </div>

                                        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                                            Simpan Keputusan Pengajuan
                                        </button>
                                    </form>
                                </details>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="text-center py-10 px-4 rounded-xl border border-dashed border-slate-200">
                        <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <p class="text-xs sm:text-sm font-semibold text-slate-500 m-0">Belum ada riwayat perizinan atau sakit yang tercatat atau sesuai filter.</p>
                    </div>
                @endforelse
            </div>

            <x-pagination :paginator="$requests" />
        </section>
    </div>
</div>
@endsection
