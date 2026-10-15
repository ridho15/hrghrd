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

            {{-- Kartu 2: Ambang Batas Waktu Presensi --}}
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
                            Durasi Kerja Minimal Sah (Menit)
                        </label>
                        <input type="number" min="1" name="min_work_duration_minutes" value="{{ $settings['min_work_duration_minutes'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Bila check-out kurang dari durasi ini, ditandai sebagai check-out terlalu dini.</span>
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
                        <input type="number" min="1" name="hourly_divisor" id="setting_hourly_divisor" value="{{ $settings['hourly_divisor'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Tarif per jam = Tarif harian &divide; angka pembagi ini (misal 24 atau 173 sesuai UU).</span>
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

            {{-- Kartu 5: Kebijakan Lembur & Pembayaran Fleksibel (Fitur Baru) --}}
            <div class="col-span-1 md:col-span-2 bg-white rounded-2xl border-2 border-emerald-500/30 shadow-md p-5 sm:p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold shadow-2xs">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </span>
                        <div>
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 mb-0.5">
                                Fleksibel & Terkustomisasi
                            </div>
                            <h2 class="text-lg font-black text-slate-900 tracking-tight m-0">Kebijakan Lembur & Pembayaran</h2>
                        </div>
                    </div>
                    <span class="text-xs text-slate-500">Atur metode perhitungan per jam atau per menit serta nominal pembayarannya.</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {{-- 1. Basis Perhitungan --}}
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            1. Basis Perhitungan Lembur *
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors {{ $settings['overtime_calculation_basis'] === 'per_hour' ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-600' : '' }}">
                                <input type="radio" name="overtime_calculation_basis" value="per_hour" @checked($settings['overtime_calculation_basis'] === 'per_hour') class="mt-0.5 text-emerald-600 focus:ring-emerald-500" onchange="syncOvertimeUI()">
                                <div>
                                    <span class="text-xs font-bold text-slate-900 block">Per Jam (Hourly)</span>
                                    <span class="text-[11px] text-slate-500 block">Dihitung berdasarkan satuan jam kerja (atau kelipatan 60 menit).</span>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors {{ $settings['overtime_calculation_basis'] === 'per_minute' ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-600' : '' }}">
                                <input type="radio" name="overtime_calculation_basis" value="per_minute" @checked($settings['overtime_calculation_basis'] === 'per_minute') class="mt-0.5 text-emerald-600 focus:ring-emerald-500" onchange="syncOvertimeUI()">
                                <div>
                                    <span class="text-xs font-bold text-slate-900 block">Per Menit (Minute Units)</span>
                                    <span class="text-[11px] text-slate-500 block">Dihitung dalam kelipatan menit khusus (misal per 15, 30 menit).</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- 2. Skema Tarif Pembayaran --}}
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            2. Skema Tarif Pembayaran *
                        </label>
                        <select name="overtime_rate_scheme" id="overtime_rate_scheme" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer" onchange="syncOvertimeUI()">
                            <option value="fixed_amount" @selected($settings['overtime_rate_scheme'] === 'fixed_amount')>Nominal Tetap per Unit (Fixed Rate)</option>
                            <option value="hourly_prorata" @selected($settings['overtime_rate_scheme'] === 'hourly_prorata')>Proporsional Upah per Jam (Salary Pro-rata)</option>
                            <option value="statutory_pp35" @selected($settings['overtime_rate_scheme'] === 'statutory_pp35')>Standar Regulasi PP 35/2021 (1.5x / 2.0x)</option>
                        </select>

                        <div id="box_scheme_desc" class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-[11px] text-slate-600 leading-relaxed">
                            Pilih skema untuk menentukan bagaimana upah lembur dikalkulasi.
                        </div>
                    </div>

                    {{-- 3. Rasio Pembayaran: Per Berapa Menit untuk Berapa Rupiah --}}
                    <div class="space-y-3.5 bg-emerald-50/50 p-4 rounded-xl border border-emerald-200/80">
                        <div class="flex items-center justify-between pb-1.5 border-b border-emerald-200/60">
                            <span class="text-xs font-black uppercase tracking-wider text-emerald-900">Rasio Pembayaran</span>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded">Rumus Kustom</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Setiap Berapa Menit (Unit Durasi)
                            </label>
                            <div class="relative">
                                <input type="number" min="1" max="480" name="overtime_unit_minutes" id="overtime_unit_minutes" value="{{ $settings['overtime_unit_minutes'] }}" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-bold tabular-nums text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-emerald-500" oninput="runOvertimeSimulator()">
                                <span class="absolute right-3 top-2 text-xs font-semibold text-slate-400">Menit</span>
                            </div>
                            <span class="text-[11px] text-slate-500 mt-0.5 block">Contoh: Diisi 15 untuk hitungan per 15 menit, atau 60 untuk per jam.</span>
                        </div>

                        <div id="box_rate_per_unit">
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Dibayar Sebesar Berapa (Rp per Unit)
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-xs font-bold text-slate-500">Rp</span>
                                <input type="number" min="0" step="1000" name="overtime_rate_per_unit" id="overtime_rate_per_unit" value="{{ $settings['overtime_rate_per_unit'] }}" required class="w-full pl-9 pr-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-bold tabular-nums text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-emerald-500" oninput="runOvertimeSimulator()">
                            </div>
                            <span class="text-[11px] text-slate-500 mt-0.5 block">Nominal rupiah pasti yang dibayarkan untuk setiap 1 unit durasi lembur.</span>
                        </div>
                    </div>
                </div>

                {{-- Baris Pengaturan Tambahan: Batas & Pembulatan --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Ambang Batas Mulai Lembur (Menit)
                        </label>
                        <input type="number" min="0" max="480" name="overtime_threshold_minutes" id="overtime_threshold_minutes" value="{{ $settings['overtime_threshold_minutes'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" oninput="runOvertimeSimulator()">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Kelebihan waktu kerja minimal sebelum diakui lembur resmi.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Batas Maksimal Lembur Harian (Menit)
                        </label>
                        <input type="number" min="15" max="720" name="overtime_max_daily_minutes" id="overtime_max_daily_minutes" value="{{ $settings['overtime_max_daily_minutes'] }}" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold tabular-nums text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" oninput="runOvertimeSimulator()">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Batas maksimal lembur legal per hari yang dapat dibayar (default 240 mnt / 4 jam).</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Mode Pembulatan Menit Lembur
                        </label>
                        <select name="overtime_rounding_mode" id="overtime_rounding_mode" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer" onchange="runOvertimeSimulator()">
                            <option value="floor" @selected($settings['overtime_rounding_mode'] === 'floor')>Dibulatkan ke Bawah (Floor - Default)</option>
                            <option value="round" @selected($settings['overtime_rounding_mode'] === 'round')>Dibulatkan ke Terdekat (Round)</option>
                            <option value="ceil" @selected($settings['overtime_rounding_mode'] === 'ceil')>Dibulatkan ke Atas (Ceil)</option>
                        </select>
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Contoh: 40 menit @ 15 mnt dihitung 2 unit (floor) atau 3 unit (ceil).</span>
                    </div>
                </div>

                {{-- Live Simulator Interaktif Box --}}
                <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-5 shadow-inner space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-slate-700">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="text-xs font-black uppercase tracking-wider text-emerald-400">Simulator Interaktif Real-Time</span>
                        </div>
                        <span class="text-[11px] text-slate-400">Uji skenario sebelum menyimpan perubahan</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-wider mb-1">
                                Contoh Durasi Lembur (Menit)
                            </label>
                            <input type="number" id="sim_minutes" value="75" min="0" step="5" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-hidden focus:border-emerald-400 tabular-nums" oninput="runOvertimeSimulator()">
                            <span class="text-[10px] text-slate-400 mt-0.5 block">Ubah menit untuk melihat perubahan nominal.</span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-wider mb-1">
                                Contoh Gaji Pokok (Rp)
                            </label>
                            <input type="number" id="sim_salary" value="3000000" min="0" step="500000" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-hidden focus:border-emerald-400 tabular-nums" oninput="runOvertimeSimulator()">
                            <span class="text-[10px] text-slate-400 mt-0.5 block">Digunakan jika memilih skema proporsional/PP 35.</span>
                        </div>

                        <div class="bg-slate-800/80 p-3.5 rounded-xl border border-emerald-500/30 flex flex-col justify-between">
                            <div class="text-[11px] text-slate-400 font-semibold">Hasil Upah Lembur Diterima:</div>
                            <div class="text-xl sm:text-2xl font-black text-emerald-400 tracking-tight tabular-nums" id="sim_result_pay">
                                Rp 0
                            </div>
                            <div class="text-[11px] text-slate-300 font-medium" id="sim_result_desc">
                                Memuat simulasi...
                            </div>
                        </div>
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
            <strong>Lembur Fleksibel</strong> = Disesuaikan dengan Skema Pilihan (Nominal Tetap per Unit Menit, Proporsional Upah per Jam, atau Standar PP 35/2021).
        </p>

        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-700 leading-relaxed flex items-start gap-2.5">
            <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <strong>Simulasi Nyata:</strong> Gaji Rp3.000.000 pada bulan 30 hari menghasilkan tarif harian <strong>Rp100.000</strong> dan tarif per jam <strong>Rp{{ number_format(100000 / max(1, $settings['hourly_divisor']), 2, ',', '.') }}</strong>. Satu unit keterlambatan memotong <strong>Rp{{ number_format($settings['late_penalty_per_unit'], 0, ',', '.') }}</strong>.
            </div>
        </div>
    </section>
</div>

<script>
function syncOvertimeUI() {
    const basis = document.querySelector('input[name="overtime_calculation_basis"]:checked')?.value || 'per_hour';
    const scheme = document.getElementById('overtime_rate_scheme')?.value || 'fixed_amount';
    const unitInput = document.getElementById('overtime_unit_minutes');
    const rateBox = document.getElementById('box_rate_per_unit');
    const descBox = document.getElementById('box_scheme_desc');

    if (basis === 'per_hour') {
        if (!unitInput.value || unitInput.value === '15' || unitInput.value === '30') {
            unitInput.value = '60';
        }
    } else {
        if (unitInput.value === '60') {
            unitInput.value = '15';
        }
    }

    if (scheme === 'fixed_amount') {
        rateBox.classList.remove('opacity-50', 'pointer-events-none');
        descBox.innerHTML = '<strong>Nominal Tetap:</strong> Setiap kelipatan menit lembur akan dikalikan dengan nominal rupiah yang Anda tentukan di samping.';
    } else if (scheme === 'hourly_prorata') {
        rateBox.classList.add('opacity-50', 'pointer-events-none');
        descBox.innerHTML = '<strong>Proporsional Upah:</strong> Tarif dihitung dari upah per jam karyawan (Gaji Pokok / Pembagi Jam Kerja) dibagi durasi menit lembur.';
    } else {
        rateBox.classList.add('opacity-50', 'pointer-events-none');
        descBox.innerHTML = '<strong>Regulasi Standar PP 35/2021:</strong> Upah jam ke-1 dibayar 1.5x upah per jam, dan jam ke-2 seterusnya dibayar 2.0x (1/173 gaji pokok).';
    }

    runOvertimeSimulator();
}

function runOvertimeSimulator() {
    const simMinutesInput = parseInt(document.getElementById('sim_minutes')?.value || '0', 10);
    const simSalary = parseFloat(document.getElementById('sim_salary')?.value || '0');
    const scheme = document.getElementById('overtime_rate_scheme')?.value || 'fixed_amount';
    const unitMinutes = Math.max(1, parseInt(document.getElementById('overtime_unit_minutes')?.value || '60', 10));
    const ratePerUnit = Math.max(0, parseInt(document.getElementById('overtime_rate_per_unit')?.value || '0', 10));
    const roundMode = document.getElementById('overtime_rounding_mode')?.value || 'floor';
    const maxDaily = Math.max(1, parseInt(document.getElementById('overtime_max_daily_minutes')?.value || '240', 10));

    // Cap minutes
    const effectiveMinutes = Math.min(maxDaily, Math.max(0, simMinutesInput));

    // Calculate units
    let units = 0;
    if (roundMode === 'ceil') {
        units = Math.ceil(effectiveMinutes / unitMinutes);
    } else if (roundMode === 'round') {
        units = Math.round(effectiveMinutes / unitMinutes);
    } else {
        units = Math.floor(effectiveMinutes / unitMinutes);
    }

    let totalPay = 0;
    let desc = '';

    const hourlyRate = simSalary > 0 ? (simSalary / 173) : 0;

    if (scheme === 'fixed_amount') {
        totalPay = units * ratePerUnit;
        desc = `${units} unit (${effectiveMinutes} mnt @ ${unitMinutes} mnt) × Rp ${ratePerUnit.toLocaleString('id-ID')}`;
    } else if (scheme === 'hourly_prorata') {
        const minuteRate = hourlyRate / 60.0;
        totalPay = Math.round(units * (minuteRate * unitMinutes));
        desc = `${units} unit (${effectiveMinutes} mnt @ ${unitMinutes} mnt) × Rp ${Math.round(minuteRate * unitMinutes).toLocaleString('id-ID')} (proporsional upah)`;
    } else {
        const hours = effectiveMinutes / 60.0;
        let weighted = 0.0;
        if (hours <= 1.0) {
            weighted = hours * 1.5;
        } else {
            weighted = (1.0 * 1.5) + ((hours - 1.0) * 2.0);
        }
        totalPay = Math.round(hourlyRate * weighted);
        desc = `PP 35/2021: ${hours.toFixed(2)} jam (${weighted.toFixed(2)} jam terbobot @ Rp ${Math.round(hourlyRate).toLocaleString('id-ID')}/jam)`;
    }

    const payEl = document.getElementById('sim_result_pay');
    const descEl = document.getElementById('sim_result_desc');
    if (payEl) payEl.innerText = 'Rp ' + totalPay.toLocaleString('id-ID');
    if (descEl) descEl.innerText = desc;
}

document.addEventListener('DOMContentLoaded', function () {
    syncOvertimeUI();
});
</script>
@endsection
