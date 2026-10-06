@extends('layouts.app')
@section('title', 'Aturan Kebijakan')

@section('content')
<div class="space-y-8">
    {{-- Header Modul --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md mb-2 border border-emerald-200/60">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Konfigurasi Kebijakan Korporat</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                Parameter & Kebijakan HR
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Perubahan aturan berlaku pada perhitungan periode berikutnya. Periode payroll yang telah dikunci tetap memakai snapshot aturan lama demi akuntabilitas audit.
            </p>
        </div>
    </div>

    {{-- Form Simpan Kebijakan --}}
    <form method="post" action="{{ route('settings.save') }}" class="space-y-6">
        @csrf

        {{-- Grid 4 Kartu Tematik --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Kartu 1: Keterlambatan & Sanksi Presensi --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                    <span class="w-8 h-8 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Keterlambatan & Sanksi</h2>
                </div>

                <div class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Toleransi Terlambat (Menit)
                        </label>
                        <input type="number" min="0" name="late_grace_minutes" value="{{ $settings['late_grace_minutes'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Waktu dispensasi hadir tanpa dikenai penalti.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Kelipatan Unit Potongan (Menit)
                        </label>
                        <input type="number" min="0" name="late_unit_minutes" value="{{ $settings['late_unit_minutes'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Contoh: Tiap 15 menit keterlambatan dihitung 1 unit.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Besaran Potongan per Unit (Rp)
                        </label>
                        <input type="number" min="0" name="late_penalty_per_unit" value="{{ $settings['late_penalty_per_unit'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Nilai nominal pemotong gaji per unit keterlambatan.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Batas Alfa / Tolak Check-in (Menit)
                        </label>
                        <input type="number" min="0" name="late_reject_minutes" value="{{ $settings['late_reject_minutes'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Batas check-in otomatis ditolak dan dihitung mangkir (Alfa).</span>
                    </div>
                </div>
            </div>

            {{-- Kartu 2: Ambang Batas Waktu & Presensi --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                    <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Ambang Batas Waktu Presensi</h2>
                </div>

                <div class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Check-in Paling Awal (Menit)
                        </label>
                        <input type="number" min="0" name="checkin_early_minutes" value="{{ $settings['checkin_early_minutes'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Berapa menit sebelum shift dimulai karyawan boleh check-in.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Batas Check-out Setelah Shift (Jam)
                        </label>
                        <input type="number" min="0" name="checkout_late_hours" value="{{ $settings['checkout_late_hours'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Toleransi maksimal melakukan check-out setelah jam shift berakhir.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Ambang Batas Lembur (Menit)
                        </label>
                        <input type="number" min="0" name="overtime_threshold_minutes" value="{{ $settings['overtime_threshold_minutes'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Menit kelebihan kerja sebelum diakui sebagai lembur resmi.</span>
                    </div>
                </div>
            </div>

            {{-- Kartu 3: Formula Upah & Pembagi Tarif --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                    <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    </span>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Formula Upah & Pembagi Tarif</h2>
                </div>

                <div class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Pembagi Tarif Harian (Daily Divisor)
                        </label>
                        <select name="daily_divisor" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer">
                            <option value="calendar" @selected($settings['daily_divisor'] === 'calendar')>Jumlah Hari Kalender Bulan Tersebut (Disetujui)</option>
                            <option value="fixed_30" @selected($settings['daily_divisor'] === 'fixed_30')>Tetap 30 Hari per Bulan</option>
                        </select>
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Dasar pembagian gaji pokok menjadi tarif harian per orang.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Pembagi Tarif per Jam (Hourly Divisor)
                        </label>
                        <input type="number" min="1" name="hourly_divisor" value="{{ $settings['hourly_divisor'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Tarif per jam = Tarif harian &divide; angka pembagi ini (misal 7 atau 8 jam kerja).</span>
                    </div>
                </div>
            </div>

            {{-- Kartu 4: Kebijakan Cuti & Surat Medis --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                    <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </span>
                    <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Kebijakan Cuti & Surat Medis</h2>
                </div>

                <div class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Tenggat Izin (Hari Sebelum Mulai / Notice Days)
                        </label>
                        <input type="number" min="0" name="leave_notice_days" value="{{ $settings['leave_notice_days'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Minimal hari pengajuan cuti sebelum tanggal mulai cuti diambil.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Sakit Dibayar per Kejadian (Hari)
                        </label>
                        <input type="number" min="0" name="sick_paid_days_per_case" value="{{ $settings['sick_paid_days_per_case'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Jumlah hari sakit pertama yang otomatis dibayar penuh (paid).</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tombol Simpan Kebijakan --}}
        <div>
            <button type="submit" class="px-6 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition-colors cursor-pointer flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Simpan Seluruh Perubahan Aturan</span>
            </button>
        </div>
    </form>

    {{-- Kartu Panduan Simulasi Rumus --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-3">
        <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
            <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center font-bold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </span>
            <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Formula Matematika yang Diterapkan</h2>
        </div>

        <p class="text-xs text-slate-600 leading-relaxed m-0">
            <strong>Tarif Harian</strong> = Gaji Pokok &divide; Jumlah Hari Kalender Bulan Aktif. &middot;
            <strong>Tarif per Jam</strong> = Tarif Harian &divide; {{ $settings['hourly_divisor'] }}. &middot;
            <strong>Prorata Mulai Kerja</strong> = Tarif Harian &times; Jumlah Hari Kalender Sejak Mulai Bekerja. &middot;
            <strong>Potongan Unpaid / Alfa</strong> = Tarif Harian &times; Jumlah Hari Unpaid Unik. &middot;
            <strong>Lembur Sah</strong> = Tarif per Jam &times; (Menit Setelah Ambang Batas &divide; 60).
        </p>

        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-700 leading-relaxed">
            <strong>💡 Simulasi Nyata:</strong> Gaji Rp3.000.000 pada bulan 30 hari menghasilkan tarif harian <strong>Rp100.000</strong> dan tarif per jam <strong>Rp{{ number_format(100000 / max(1, $settings['hourly_divisor']), 2, ',', '.') }}</strong>. Satu unit keterlambatan memotong <strong>Rp{{ number_format($settings['late_penalty_per_unit'], 0, ',', '.') }}</strong>.
        </div>
    </section>
</div>
@endsection
