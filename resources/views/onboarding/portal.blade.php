<!doctype html>
<html lang="id" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Portal Onboarding Karyawan · {{ $case->user->name }} · {{ $case->user->entity_name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
    </style>
</head>
<body class="min-h-full bg-linear-to-b from-slate-50 to-slate-100/70 text-slate-800 font-sans selection:bg-emerald-500 selection:text-white">

    {{-- Top App Bar --}}
    <header class="bg-white/90 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-30">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-700 text-white flex items-center justify-center font-extrabold text-sm shadow-xs">
                    HR
                </div>
                <div>
                    <h1 class="text-sm font-extrabold text-slate-900 tracking-tight m-0 leading-tight">
                        {{ $case->user->entity_name ?: 'PT HR Group Indonesia' }}
                    </h1>
                    <span class="text-[11px] text-slate-500 font-medium">Digital Onboarding & E-Sign Portal</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold border {{ $case->status_badge_class }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ in_array($case->status, ['selesai', 'menunggu_tanda_tangan_offer', 'menunggu_tanda_tangan_nda']) ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                    {{ $case->status_label }}
                </span>
            </div>
        </div>
    </header>

    {{-- Main Container --}}
    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-8 space-y-6">

        {{-- Toast / Alerts --}}
        @if(session('ok'))
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-950 flex items-start gap-3 shadow-xs">
                <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0 font-bold">✓</span>
                <div class="text-xs font-semibold leading-relaxed">{{ session('ok') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-950 space-y-1 shadow-xs">
                <div class="text-xs font-bold uppercase tracking-wider text-rose-800">Mohon Periksa Kembali:</div>
                <ul class="text-xs list-disc pl-4 space-y-0.5">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Welcome & Candidate Info Card --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
                <div>
                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-md uppercase tracking-wider border border-emerald-200/70 inline-block mb-1.5">
                        Calon Karyawan
                    </span>
                    <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">{{ $case->user->name }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Penawaran Posisi: <strong class="text-slate-800">{{ $case->position?->name }}</strong> · Penempatan: <strong class="text-slate-800">{{ $case->branch?->name }}</strong>
                    </p>
                </div>

                <div class="sm:text-right">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Masa Berlaku Penawaran</span>
                    <span class="text-xs font-bold text-slate-700">
                        {{ $case->offer_valid_until ? $case->offer_valid_until->translatedFormat('d F Y') : '-' }}
                    </span>
                </div>
            </div>

            {{-- Sequential Step Bar --}}
            @php
                $steps = [
                    'offer' => ['title' => '1. Offering Letter', 'done' => $offeringDoc && $offeringDoc->isSigned()],
                    'nda' => ['title' => '2. Dokumen NDA', 'done' => $ndaDoc && $ndaDoc->isSigned()],
                    'ktp' => ['title' => '3. Unggah KTP', 'done' => $case->latestIdentitySubmission !== null],
                    'liveness' => ['title' => '4. Foto Liveness', 'done' => $case->latestLivenessCheck !== null],
                    'complete' => ['title' => '5. Selesai', 'done' => $case->status === 'selesai'],
                ];
            @endphp
            <div class="pt-6">
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center">
                    @foreach($steps as $key => $s)
                        <div class="p-2.5 rounded-xl border text-[11px] font-bold transition-all {{ $s['done'] ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                            <div class="flex items-center justify-center gap-1.5">
                                @if($s['done'])
                                    <span class="w-4 h-4 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px]">✓</span>
                                @else
                                    <span class="w-4 h-4 rounded-full bg-slate-300 text-slate-700 flex items-center justify-center text-[10px]">•</span>
                                @endif
                                <span>{{ $s['title'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Conditional Wizard Step View --}}

        {{-- CASE 1: Offering Letter Signing --}}
        @if($case->status === 'menunggu_tanda_tangan_offer' && $offeringDoc)
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8 space-y-6">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-200">
                    <div>
                        <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Tahap 1 dari 4</span>
                        <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mt-2">Penandatanganan Surat Penawaran Kerja (Offering Letter)</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Mohon membaca rincian jabatan, kompensasi, dan ketentuan di bawah ini dengan teliti sebelum menandatangani secara elektronik.
                        </p>
                    </div>
                </div>

                {{-- Document Reader View (Official Legal Paper Canvas) --}}
                <div class="p-6 sm:p-10 rounded-2xl bg-white border border-slate-200/90 shadow-sm max-h-[30rem] overflow-y-auto custom-scrollbar select-text">
                    <div class="document-legal-body">
                        {!! $offeringDoc->rendered_html !!}
                    </div>
                </div>

                {{-- Signing Form --}}
                <form method="POST" action="{{ route('onboarding.portal.sign_offer', $case->token) }}" class="space-y-5 pt-2">
                    @csrf
                    <div class="p-4 rounded-xl bg-emerald-50/70 border border-emerald-200/80 space-y-2">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="consent_agreed" value="1" required class="mt-0.5 w-4 h-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-xs font-medium text-slate-700 leading-relaxed">
                                Saya, <strong>{{ $case->user->name }}</strong>, menyatakan telah membaca, memahami, dan menyetujui seluruh ketentuan penawaran kerja ini, serta memberikan persetujuan eksplisit untuk menandatangani dokumen ini secara elektronik yang sah dan berkekuatan hukum penuh sesuai UU ITE.
                            </span>
                        </label>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2">
                        <div class="text-[11px] text-slate-400">
                            Tanda tangan elektronik aman diverifikasi dengan IP {{ request()->ip() }} dan Hash SHA-256.
                        </div>
                        <button type="submit" class="px-6 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-md shadow-emerald-700/20 transition-all cursor-pointer">
                            Tandatangani Offering Letter Secara Elektronik →
                        </button>
                    </div>
                </form>
            </div>

        {{-- CASE 2: NDA Signing --}}
        @elseif($case->status === 'menunggu_tanda_tangan_nda' && $ndaDoc)
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8 space-y-6">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-200">
                    <div>
                        <span class="text-xs font-bold text-blue-700 uppercase tracking-wider bg-blue-50 px-2 py-0.5 rounded border border-blue-200">Tahap 2 dari 4</span>
                        <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mt-2">Penandatanganan Perjanjian Kerahasiaan (NDA)</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Perjanjian ini mengatur perlindungan rahasia dagang, data perusahaan, dan kepatuhan hukum ketenagakerjaan Indonesia.
                        </p>
                    </div>
                </div>

                {{-- Document Reader View (Official Legal Paper Canvas) --}}
                <div class="p-6 sm:p-10 rounded-2xl bg-white border border-slate-200/90 shadow-sm max-h-[30rem] overflow-y-auto custom-scrollbar select-text">
                    <div class="document-legal-body">
                        {!! $ndaDoc->rendered_html !!}
                    </div>
                </div>

                {{-- Signing Form --}}
                <form method="POST" action="{{ route('onboarding.portal.sign_nda', $case->token) }}" class="space-y-5 pt-2">
                    @csrf
                    <div class="p-4 rounded-xl bg-blue-50/70 border border-blue-200/80 space-y-2">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="consent_agreed" value="1" required class="mt-0.5 w-4 h-4 rounded border-blue-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-xs font-medium text-slate-700 leading-relaxed">
                                Saya, <strong>{{ $case->user->name }}</strong>, menyatakan bersedia terikat pada seluruh klausul kerahasiaan dan perlindungan data yang diatur dalam Perjanjian Kerahasiaan (NDA) ini serta menyetujui penandatanganan elektronik resmi.
                            </span>
                        </label>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2">
                        <div class="text-[11px] text-slate-400">
                            Integritas dokumen terenkripsi dan dicatat pada audit trail perusahaan.
                        </div>
                        <button type="submit" class="px-6 py-3 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold shadow-md shadow-blue-700/20 transition-all cursor-pointer">
                            Tandatangani NDA Secara Elektronik →
                        </button>
                    </div>
                </form>
            </div>

        {{-- CASE 3: KTP Upload --}}
        @elseif($case->status === 'menunggu_ktp')
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8 space-y-6">
                <div>
                    <span class="text-xs font-bold text-indigo-700 uppercase tracking-wider bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">Tahap 3 dari 4</span>
                    <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mt-2">Unggah Foto Kartu Tanda Penduduk (KTP)</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Sesuai regulasi UU Pelindungan Data Pribadi (UU PDP), data KTP Anda disimpan di server privat terenkripsi dan NIK Anda akan disensor secara aman.
                    </p>
                </div>

                @if($case->correction_notes)
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs">
                        <strong>Catatan Permintaan Perbaikan HR:</strong> {{ $case->correction_notes }}
                    </div>
                @endif

                <form method="POST" action="{{ route('onboarding.portal.upload_ktp', $case->token) }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nomor Induk Kependudukan (NIK) <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="id_number" required maxlength="16" minlength="16" placeholder="Contoh: 3171012345678901"
                            class="w-full sm:max-w-md px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                        <p class="text-[11px] text-slate-400 mt-1">Wajib 16 digit angka sesuai KTP resmi Republik Indonesia.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nama Lengkap Sesuai KTP <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="full_name" required value="{{ $case->user->name }}"
                            class="w-full sm:max-w-md px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Berkas Foto KTP Asli <span class="text-rose-500">*</span>
                        </label>
                        <input type="file" name="ktp_file" required accept="image/jpeg,image/png,application/pdf"
                            class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                        <p class="text-[11px] text-slate-400 mt-1">Format: JPG, PNG, atau PDF. Maksimal 5 MB. Pastikan foto jelas, tidak terpotong, dan dapat terbaca.</p>
                    </div>

                    <div class="pt-3">
                        <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-bold shadow-md shadow-indigo-700/20 transition-all cursor-pointer">
                            Simpan & Lanjutkan ke Pemeriksaan Foto Wajah →
                        </button>
                    </div>
                </form>
            </div>

        {{-- CASE 4: Liveness Photo --}}
        @elseif($case->status === 'menunggu_liveness')
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8 space-y-6">
                <div>
                    <span class="text-xs font-bold text-purple-700 uppercase tracking-wider bg-purple-50 px-2 py-0.5 rounded border border-purple-200">Tahap 4 dari 4</span>
                    <h3 class="text-xl font-extrabold text-slate-900 tracking-tight mt-2">Pemeriksaan Foto Wajah (Liveness)</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Ambil foto <strong>wajah saja</strong> dengan panduan pencahayaan yang cukup, tanpa kacamata hitam/masker, dan hanya satu orang dalam frame.
                    </p>
                </div>

                @if($case->correction_notes)
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs">
                        <strong>Catatan Permintaan Perbaikan HR:</strong> {{ $case->correction_notes }}
                    </div>
                @endif

                <form method="POST" action="{{ route('onboarding.portal.submit_liveness', $case->token) }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    
                    {{-- Guidelines Box --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-center space-y-1">
                            <span class="text-lg">👤</span>
                            <div class="text-[11px] font-bold text-slate-800">Wajah Jelas & Tegak</div>
                            <div class="text-[10px] text-slate-500">Posisikan wajah tepat di tengah bingkai kamera.</div>
                        </div>
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-center space-y-1">
                            <span class="text-lg">💡</span>
                            <div class="text-[11px] font-bold text-slate-800">Cahaya Cukup</div>
                            <div class="text-[10px] text-slate-500">Hindari backlight atau ruangan terlalu redup.</div>
                        </div>
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-center space-y-1">
                            <span class="text-lg">🚫</span>
                            <div class="text-[11px] font-bold text-slate-800">Tanpa Penutup</div>
                            <div class="text-[10px] text-slate-500">Lepaskan topi, masker, atau kacamata hitam.</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                            Pilih / Ambil Foto Wajah Kamera <span class="text-rose-500">*</span>
                        </label>
                        <input type="file" name="selfie_file" required accept="image/*" capture="user"
                            class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 cursor-pointer">
                        <p class="text-[11px] text-slate-400 mt-1">Pada ponsel, Anda dapat langsung mengaktifkan kamera depan.</p>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="manual_alternative" value="1" class="rounded border-slate-300 text-purple-600 focus:ring-purple-500">
                            <span class="text-xs text-slate-600">Perangkat saya mengalami kendala deteksi liveness otomatis; ajukan verifikasi manual oleh HR.</span>
                        </label>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="px-6 py-3 rounded-xl bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold shadow-md shadow-purple-700/20 transition-all cursor-pointer">
                            Kirim Foto Wajah untuk Verifikasi Liveness →
                        </button>
                    </div>
                </form>
            </div>

        {{-- CASE 5: Verification Pending --}}
        @elseif($case->status === 'perlu_verifikasi')
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-8 text-center space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center mx-auto text-2xl font-bold border border-amber-200">
                    ⏳
                </div>
                <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Seluruh Berkas Telah Berhasil Diserahkan</h3>
                <p class="text-xs text-slate-600 max-w-md mx-auto leading-relaxed">
                    Terima kasih, <strong>{{ $case->user->name }}</strong>! Anda telah menyelesaikan seluruh rangkaian dokumen offering, NDA, identitas KTP, dan biometrik wajah. Saat ini berkas Anda sedang dalam proses tinjauan akhir oleh Departemen HR.
                </p>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 max-w-md mx-auto text-left text-xs space-y-2">
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Offering Letter:</span>
                        <strong class="text-emerald-700">✓ Ditandatangani</strong>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Perjanjian Kerahasiaan (NDA):</span>
                        <strong class="text-emerald-700">✓ Ditandatangani</strong>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Dokumen KTP:</span>
                        <strong class="text-emerald-700">✓ Diterima (NIK: {{ $case->user->nik_masked }})</strong>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Foto Liveness Wajah:</span>
                        <strong class="text-emerald-700">✓ Berhasil Dinilai</strong>
                    </div>
                </div>
            </div>

        {{-- CASE 6: Completed --}}
        @elseif($case->status === 'selesai')
            <div class="bg-white rounded-2xl border border-emerald-200/90 shadow-xs p-8 text-center space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center mx-auto text-2xl font-bold border border-emerald-300">
                    🎉
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">Selamat Datang di {{ $case->user->entity_name }}!</h3>
                <p class="text-xs text-slate-600 max-w-md mx-auto leading-relaxed">
                    Proses onboarding digital Anda telah <strong>resmi disetujui dan diselesaikan</strong> oleh tim HR. Status kepegawaian Anda telah aktif.
                </p>
                <div class="pt-2">
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-md shadow-emerald-700/20 transition-all">
                        <span>Masuk ke Akun Karyawan HRIS</span>
                        <span>→</span>
                    </a>
                </div>
            </div>

        {{-- CASE 7: Waiting HR initial approval --}}
        @else
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-8 text-center space-y-3">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto text-xl font-bold">
                    ℹ️
                </div>
                <h3 class="text-lg font-bold text-slate-900">Dokumen Sedang Dipersiapkan oleh HR</h3>
                <p class="text-xs text-slate-500 max-w-md mx-auto">
                    Draf dokumen penawaran kerja Anda saat ini sedang dalam peninjauan internal tim HR. Anda akan menerima notifikasi segera setelah dokumen resmi siap ditandatangani.
                </p>
            </div>
        @endif

    </main>

    <footer class="py-6 text-center text-xs text-slate-400 border-t border-slate-200/60 mt-12">
        &copy; {{ date('Y') }} {{ $case->user->entity_name ?: 'PT HR Group Indonesia' }}. Dilindungi oleh ketentuan kerahasiaan dan privasi data.
    </footer>

</body>
</html>
