<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\DocumentInstance;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\OnboardingCase;
use App\Models\Position;
use App\Models\User;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocumentTemplateService
{
    /**
     * Standard registered template variables.
     */
    public const REGISTERED_VARIABLES = [
        'candidate_name' => 'Nama Lengkap Calon Karyawan',
        'candidate_nik' => 'NIK KTP Karyawan',
        'candidate_nik_masked' => 'NIK KTP (Sensor/Masked)',
        'candidate_email' => 'Alamat Email Karyawan',
        'candidate_phone' => 'Nomor Telepon / WhatsApp',
        'candidate_address' => 'Alamat Domisili Karyawan',
        'position_title' => 'Nama Jabatan / Posisi',
        'branch_name' => 'Nama Cabang Penempatan',
        'work_location' => 'Lokasi Kerja Spesifik',
        'unit' => 'Divisi / Departemen / Unit Kerja',
        'supervisor_name' => 'Nama Atasan Langsung',
        'base_salary' => 'Gaji Pokok (Angka Terformat)',
        'base_salary_words' => 'Gaji Pokok (Terbilang Huruf)',
        'benefits_list' => 'Rincian Tunjangan & Fasilitas',
        'start_date' => 'Tanggal Efektif Mulai Bekerja',
        'start_date_indonesian' => 'Tanggal Mulai Bekerja (Format Bahasa Indonesia)',
        'probation_months' => 'Masa Percobaan (Bulan)',
        'offer_valid_until' => 'Batas Akhir Berlaku Penawaran',
        'company_name' => 'Nama Entitas Perusahaan',
        'company_address' => 'Alamat Kantor Pusat Perusahaan',
        'hr_signatory_name' => 'Nama Pejabat HR / Penandatangan Perusahaan',
        'hr_signatory_title' => 'Jabatan Pejabat Perusahaan',
        'confidentiality_duration_years' => 'Durasi Kerahasiaan (Tahun)',
        'confidentiality_duration_words' => 'Durasi Kerahasiaan (Terbilang)',
        'governing_law' => 'Hukum yang Mengatur (Republik Indonesia)',
        'dispute_forum' => 'Forum Penyelesaian Sengketa',
        'effective_date_indonesian' => 'Tanggal Pembuatan Dokumen',
        'company_signed_at' => 'Waktu Tanda Tangan Perusahaan',
        'candidate_signed_at' => 'Waktu Tanda Tangan Karyawan',
        'document_number' => 'Nomor Registrasi Dokumen',
        'document_hash' => 'Kode Hash Integritas SHA-256',
    ];

    /**
     * Ensure default system templates exist in database.
     */
    public function ensureDefaultTemplates(): void
    {
        $this->ensureOfferingLetterTemplate();
        $this->ensureNdaTemplate();
        $this->ensureEmploymentContractTemplate();
    }

    private function ensureOfferingLetterTemplate(): DocumentTemplate
    {
        $template = DocumentTemplate::firstOrCreate(
            ['type' => 'offering_letter', 'code' => 'TPL-OFFERING-STANDARD'],
            [
                'title' => 'Offering Letter Standar PT HR Group Indonesia',
                'entity_name' => 'PT HR Group Indonesia',
            ]
        );

        if (!$template->active_version_id) {
            $content = $this->getDefaultOfferingLetterContent();
            $clauses = [
                ['id' => 'cls_pos', 'title' => 'Posisi dan Penempatan Kerja', 'is_mandatory' => true],
                ['id' => 'cls_rem', 'title' => 'Remunerasi dan Paket Kompensasi', 'is_mandatory' => true],
                ['id' => 'cls_prob', 'title' => 'Masa Percobaan (Probation Period)', 'is_mandatory' => true],
                ['id' => 'cls_conf', 'title' => 'Kerahasiaan dan Kepatuhan', 'is_mandatory' => true],
                ['id' => 'cls_acc', 'title' => 'Ketentuan dan Batas Waktu Penerimaan', 'is_mandatory' => true],
            ];

            $version = DocumentTemplateVersion::create([
                'document_template_id' => $template->id,
                'version_number' => 1,
                'status' => 'published',
                'content' => $content,
                'clauses' => $clauses,
                'required_variables' => ['candidate_name', 'position_title', 'branch_name', 'base_salary', 'start_date'],
                'required_clauses' => ['cls_pos', 'cls_rem', 'cls_prob', 'cls_conf', 'cls_acc'],
                'change_summary' => 'Inisialisasi template offering letter resmi perusahaan.',
                'published_at' => now(),
            ]);

            $template->update(['active_version_id' => $version->id]);
        }

        return $template;
    }

    private function ensureNdaTemplate(): DocumentTemplate
    {
        $template = DocumentTemplate::firstOrCreate(
            ['type' => 'nda', 'code' => 'TPL-NDA-STANDARD'],
            [
                'title' => 'Perjanjian Kerahasiaan (NDA) Karyawan',
                'entity_name' => 'PT HR Group Indonesia',
            ]
        );

        if (!$template->active_version_id) {
            $path = base_path('templates/nda-karyawan-id.md');
            $content = File::exists($path) ? File::get($path) : $this->getDefaultNdaContent();

            $clauses = [
                ['id' => 'pasal_1', 'title' => 'Pasal 1: Definisi Informasi Rahasia', 'is_mandatory' => true],
                ['id' => 'pasal_2', 'title' => 'Pasal 2: Pengecualian Informasi Rahasia', 'is_mandatory' => true],
                ['id' => 'pasal_3', 'title' => 'Pasal 3: Pembatasan Penggunaan dan Larangan Pengungkapan', 'is_mandatory' => true],
                ['id' => 'pasal_4', 'title' => 'Pasal 4: Standar Perlindungan dan Keamanan Informasi', 'is_mandatory' => true],
                ['id' => 'pasal_5', 'title' => 'Pasal 5: Pengungkapan yang Diwajibkan oleh Hukum', 'is_mandatory' => true],
                ['id' => 'pasal_6', 'title' => 'Pasal 6: Kepemilikan dan Hak Kekayaan Intelektual', 'is_mandatory' => true],
                ['id' => 'pasal_7', 'title' => 'Pasal 7: Pengembalian atau Pemusnahan Dokumen', 'is_mandatory' => true],
                ['id' => 'pasal_8', 'title' => 'Pasal 8: Masa Berlaku Kerahasiaan', 'is_mandatory' => true],
                ['id' => 'pasal_9', 'title' => 'Pasal 9: Sanksi dan Ganti Rugi Pelanggaran', 'is_mandatory' => true],
                ['id' => 'pasal_10', 'title' => 'Pasal 10: Hukum yang Berlaku dan Forum Penyelesaian Sengketa', 'is_mandatory' => true],
            ];

            $version = DocumentTemplateVersion::create([
                'document_template_id' => $template->id,
                'version_number' => 1,
                'status' => 'published',
                'content' => $content,
                'clauses' => $clauses,
                'required_variables' => ['candidate_name', 'candidate_nik_masked', 'company_name', 'effective_date_indonesian'],
                'required_clauses' => array_column($clauses, 'id'),
                'change_summary' => 'Inisialisasi template NDA standar kepatuhan hukum Indonesia.',
                'published_at' => now(),
            ]);

            $template->update(['active_version_id' => $version->id]);
        }

        return $template;
    }

    private function ensureEmploymentContractTemplate(): DocumentTemplate
    {
        $template = DocumentTemplate::firstOrCreate(
            ['type' => 'employment_contract', 'code' => 'TPL-PKWT-STANDARD'],
            [
                'title' => 'Perjanjian Kerja Waktu Tertentu (PKWT) Standar',
                'entity_name' => 'PT HR Group Indonesia',
            ]
        );

        if (!$template->active_version_id) {
            $content = $this->getDefaultEmploymentContractContent();
            $clauses = [
                ['id' => 'pkwt_pasal_1', 'title' => 'Pasal 1: Hubungan Kerja dan Penempatan', 'is_mandatory' => true],
                ['id' => 'pkwt_pasal_2', 'title' => 'Pasal 2: Jangka Waktu Perjanjian', 'is_mandatory' => true],
                ['id' => 'pkwt_pasal_3', 'title' => 'Pasal 3: Waktu Kerja dan Lembur', 'is_mandatory' => true],
                ['id' => 'pkwt_pasal_4', 'title' => 'Pasal 4: Upah dan Tata Cara Pembayaran', 'is_mandatory' => true],
                ['id' => 'pkwt_pasal_5', 'title' => 'Pasal 5: BPJS dan Jaminan Sosial Ketenagakerjaan', 'is_mandatory' => true],
                ['id' => 'pkwt_pasal_6', 'title' => 'Pasal 6: Tata Tertib dan Kewajiban Karyawan', 'is_mandatory' => true],
                ['id' => 'pkwt_pasal_7', 'title' => 'Pasal 7: Berakhirnya Perjanjian Kerja', 'is_mandatory' => true],
                ['id' => 'pkwt_pasal_8', 'title' => 'Pasal 8: Penyelesaian Perselisihan Hubungan Industrial', 'is_mandatory' => true],
            ];

            $version = DocumentTemplateVersion::create([
                'document_template_id' => $template->id,
                'version_number' => 1,
                'status' => 'published',
                'content' => $content,
                'clauses' => $clauses,
                'required_variables' => ['candidate_name', 'position_title', 'branch_name', 'base_salary', 'start_date'],
                'required_clauses' => array_column($clauses, 'id'),
                'change_summary' => 'Inisialisasi template PKWT standar ketenagakerjaan Indonesia.',
                'published_at' => now(),
            ]);

            $template->update(['active_version_id' => $version->id]);
        }

        return $template;
    }

    /**
     * Validate template text for unregistered variables.
     */
    public function validateTemplateContent(string $content, array $requiredVariables = []): array
    {
        $errors = [];
        $unregisteredVariables = [];

        // Match all {variable_name}
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $content, $matches);
        $usedVariables = array_unique($matches[1] ?? []);

        foreach ($usedVariables as $var) {
            if (!array_key_exists($var, self::REGISTERED_VARIABLES)) {
                $unregisteredVariables[] = $var;
            }
        }

        if (!empty($unregisteredVariables)) {
            $errors[] = 'Ditemukan variabel tidak terdaftar: ' . implode(', ', array_map(fn ($v) => '{' . $v . '}', $unregisteredVariables));
        }

        $missingRequired = [];
        foreach ($requiredVariables as $req) {
            if (!in_array($req, $usedVariables, true)) {
                $missingRequired[] = '{' . $req . '}';
            }
        }

        if (!empty($missingRequired)) {
            $errors[] = 'Variabel wajib belum dicantumkan dalam redaksi template: ' . implode(', ', $missingRequired);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'used_variables' => $usedVariables,
            'unregistered_variables' => $unregisteredVariables,
        ];
    }

    /**
     * Render document template with fact snapshot.
     */
    public function renderContent(string $content, array $data): string
    {
        $rendered = $content;
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $valStr = (string) ($value ?? '');
                $rendered = str_replace(['{' . $key . '}', '{{' . $key . '}}'], $valStr, $rendered);
            }
        }

        return $rendered;
    }

    /**
     * Convert rendered markdown document into clean, official legal HTML.
     */
    public function renderToHtml(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '<div class="text-slate-400 italic text-center py-8">Naskah dokumen masih kosong.</div>';
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Generate facts snapshot array for a candidate/case.
     */
    public function buildFactsSnapshot(OnboardingCase $case, array $extra = []): array
    {
        $user = $case->user;
        $branch = $case->branch;
        $position = $case->position;
        $supervisor = $case->supervisor;

        $salary = (int) ($case->salary_offered ?: $user->base_salary);
        $startDate = $case->start_date ? Carbon::parse($case->start_date) : now()->addDays(7);
        $validUntil = $case->offer_valid_until ? Carbon::parse($case->offer_valid_until) : now()->addDays(3);

        $benefits = $case->benefits;
        $benefitsText = '- BPJS Ketenagakerjaan & BPJS Kesehatan sesuai ketentuan hukum.' . "\n"
            . '- Tunjangan hari raya (THR) proporsional sesuai masa kerja.' . "\n"
            . '- Hak cuti tahunan 12 hari kerja setelah masa kerja 1 tahun.';
        if (is_array($benefits) && !empty($benefits)) {
            $benefitsText = implode("\n", array_map(fn ($b) => '- ' . $b, $benefits));
        }

        $facts = [
            'candidate_name' => $user->name,
            'candidate_nik' => $user->nik_encrypted ? '3171xxxxxxxxxxxx' : ($user->nik_masked ?: '3171012345678901'),
            'candidate_nik_masked' => $user->nik_masked ?: ($user->getMaskedNik() ?: '3171************'),
            'candidate_email' => $user->email,
            'candidate_phone' => $user->phone ?: '-',
            'candidate_address' => $user->address ?: 'Indonesia',
            'position_title' => $position?->name ?: 'Staf Operasional',
            'branch_name' => $branch?->name ?: 'Kantor Pusat',
            'work_location' => $branch?->address ?: ($branch?->name ?: 'Kantor Perusahaan'),
            'unit' => $user->unit ?: 'Divisi Operasional',
            'supervisor_name' => $supervisor?->name ?: 'HR Director / Branch Manager',
            'base_salary' => 'Rp ' . number_format($salary, 0, ',', '.'),
            'base_salary_words' => ucfirst(trim($this->terbilang($salary))) . ' Rupiah',
            'benefits_list' => $benefitsText,
            'start_date' => $startDate->format('d/m/Y'),
            'start_date_indonesian' => $startDate->translatedFormat('d F Y'),
            'probation_months' => '3 (Tiga)',
            'offer_valid_until' => $validUntil->translatedFormat('d F Y'),
            'company_name' => $user->entity_name ?: 'PT HR Group Indonesia',
            'company_address' => 'Jl. Jenderal Sudirman Kav. 52-53, Jakarta Selatan, DKI Jakarta 12190',
            'hr_signatory_name' => auth()->user()?->name ?: 'Direktur SDM / Legal',
            'hr_signatory_title' => 'Head of People & Culture',
            'confidentiality_duration_years' => '3',
            'confidentiality_duration_words' => 'tiga',
            'governing_law' => 'Hukum Negara Republik Indonesia',
            'dispute_forum' => 'Pengadilan Hubungan Industrial / Pengadilan Negeri Jakarta Selatan',
            'effective_date_indonesian' => now()->translatedFormat('d F Y'),
            'company_signed_at' => now()->format('d/m/Y H:i:s T'),
            'candidate_signed_at' => 'Menunggu Tanda Tangan',
            'document_number' => 'DOC/' . date('Y/m/') . str_pad((string) $case->id, 4, '0', STR_PAD_LEFT),
            'document_hash' => hash('sha256', (string) $case->id . $user->email),
        ];

        return array_merge($facts, $extra);
    }

    /**
     * Convert integer currency to Indonesian words.
     */
    public function terbilang(int|float $angka): string
    {
        $raw = $this->rawTerbilang($angka);
        return trim(preg_replace('/\s+/', ' ', $raw));
    }

    private function rawTerbilang(int|float $angka): string
    {
        $angka = abs((int) $angka);
        $baca = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($angka < 12) {
            return ' ' . $baca[$angka];
        }
        if ($angka < 20) {
            return $this->rawTerbilang($angka - 10) . ' belas';
        }
        if ($angka < 100) {
            return $this->rawTerbilang((int) ($angka / 10)) . ' puluh' . $this->rawTerbilang($angka % 10);
        }
        if ($angka < 200) {
            return ' seratus' . $this->rawTerbilang($angka - 100);
        }
        if ($angka < 1000) {
            return $this->rawTerbilang((int) ($angka / 100)) . ' ratus' . $this->rawTerbilang($angka % 100);
        }
        if ($angka < 2000) {
            return ' seribu' . $this->rawTerbilang($angka - 1000);
        }
        if ($angka < 1000000) {
            return $this->rawTerbilang((int) ($angka / 1000)) . ' ribu' . $this->rawTerbilang($angka % 1000);
        }
        if ($angka < 1000000000) {
            return $this->rawTerbilang((int) ($angka / 1000000)) . ' juta' . $this->rawTerbilang($angka % 1000000);
        }
        if ($angka < 1000000000000) {
            return $this->rawTerbilang((int) ($angka / 1000000000)) . ' milyar' . $this->rawTerbilang(fmod($angka, 1000000000));
        }

        return (string) $angka;
    }

    private function getDefaultOfferingLetterContent(): string
    {
        return <<<'MARKDOWN'
# SURAT PENAWARAN KERJA (OFFERING LETTER)
### NOMOR: {document_number}

Tanggal: {effective_date_indonesian}  
Kepada Yth.  
**{candidate_name}**  
Alamat: {candidate_address}  
Email: {candidate_email}  

Dengan hormat,

Sehubungan dengan proses seleksi dan wawancara yang telah Anda lalui bersama kami, Manajemen **{company_name}** dengan senang hati menyampaikan penawaran kerja untuk bergabung dengan tim kami. Rincian penawaran kerja ini adalah sebagai berikut:

---

### 1. POSISI DAN PENEMPATAN KERJA
- **Jabatan / Posisi**: {position_title}
- **Unit / Divisi**: {unit}
- **Cabang Penempatan**: {branch_name}
- **Lokasi Kerja**: {work_location}
- **Atasan Langsung**: {supervisor_name}
- **Tanggal Mulai Bekerja**: {start_date_indonesian}

### 2. KOMPENSASI DAN FASILITAS
- **Gaji Pokok**: {base_salary} per bulan ({base_salary_words})
- **Tunjangan & Manfaat**:
{benefits_list}
- **Pajak & BPJS**: Pemotongan PPh 21 dan iuran BPJS Ketenagakerjaan/Kesehatan dilaksanakan sesuai regulasi pemerintah Republik Indonesia.

### 3. MASA PERCOBAAN (PROBATION)
Anda akan menjalani masa percobaan selama **{probation_months}** terhitung sejak tanggal mulai bekerja. Evaluasi performa kerja akan dilakukan oleh atasan langsung dan departemen HR menjelang berakhirnya masa percobaan untuk menentukan kelanjutan hubungan kerja.

### 4. KERAHASIAAN DAN PERLINDUNGAN DATA
Sebagai bagian yang tidak terpisahkan dari penerimaan penawaran ini, Anda diwajibkan menandatangani **Perjanjian Kerahasiaan (Non-Disclosure Agreement / NDA)** serta menyerahkan dokumen kelengkapan administrasi identitas (KTP) dan verifikasi biometrik liveness.

### 5. SYARAT PENERIMAAN PENAWARAN
Surat Penawaran Kerja ini berlaku hingga tanggal **{offer_valid_until}**. Apabila Anda menyetujui penawaran ini, mohon untuk membubuhkan tanda tangan elektronik pada dokumen ini sebelum batas waktu tersebut berakhir. Penawaran ini menjadi dasar penyusunan Perjanjian Kerja resmi.

---

Hormat kami,  
**{company_name}**

Ditandatangani secara resmi oleh:  
**{hr_signatory_name}**  
Jabatan: {hr_signatory_title}  
Tanggal: {company_signed_at}  

---

### PERNYATAAN PENERIMAAN KANDIDAT:
Saya yang bertanda tangan di bawah ini, **{candidate_name}**, menyatakan telah membaca, memahami, dan menyetujui seluruh syarat dan ketentuan penawaran kerja di atas.

Tanda Tangan Elektronik Kandidat:  
**{candidate_name}**  
NIK: {candidate_nik_masked}  
Tanggal: {candidate_signed_at}  
Integritas Hash: [{document_hash}]
MARKDOWN;
    }

    private function getDefaultNdaContent(): string
    {
        $path = base_path('templates/nda-karyawan-id.md');
        if (File::exists($path)) {
            return File::get($path);
        }

        return '# PERJANJIAN KERAHASIAAN (NDA)' . "\n\n" . 'Draf NDA Karyawan {candidate_name} - {company_name}';
    }

    private function getDefaultEmploymentContractContent(): string
    {
        return <<<'MARKDOWN'
# PERJANJIAN KERJA WAKTU TERTENTU (PKWT)
### NOMOR: {document_number}

Pada hari ini, {effective_date_indonesian}, dibuat dan disepakati Perjanjian Kerja Waktu Tertentu ("Perjanjian") antara:

1. **{company_name}**, beralamat di {company_address}, diwakili oleh **{hr_signatory_name}** selaku **{hr_signatory_title}** ("Pihak Pertama / Perusahaan"); dan
2. **{candidate_name}**, pemegang NIK {candidate_nik_masked}, beralamat di {candidate_address} ("Pihak Kedua / Karyawan").

Para Pihak sepakat mengikatkan diri dengan ketentuan sebagai berikut:

### PASAL 1: HUBUNGAN KERJA DAN PENEMPATAN
Pihak Pertama menerima Pihak Kedua bekerja sebagai **{position_title}** yang ditempatkan pada cabang **{branch_name}**, dengan atasan langsung **{supervisor_name}**.

### PASAL 2: JANGKA WAKTU PERJANJIAN
Perjanjian kerja ini berlaku untuk jangka waktu tertentu terhitung mulai tanggal **{start_date_indonesian}** sesuai ketentuan perundang-undangan ketenagakerjaan PP No. 35 Tahun 2021.

### PASAL 3: WAKTU KERJA DAN DISIPLIN
1. Waktu kerja diatur sesuai operasional cabang/unit dengan total 40 (empat puluh) jam per minggu.
2. Pihak Kedua wajib mematuhi seluruh peraturan perusahaan dan ketentuan disiplin absensi.

### PASAL 4: UPAH DAN PEMBAYARAN
Pihak Pertama membayar upah pokok kepada Pihak Kedua sebesar **{base_salary}** ({base_salary_words}) per bulan yang dibayarkan setiap akhir bulan melalui transfer rekening bank karyawan.

### PASAL 5: JAMINAN SOSIAL KETENAGAKERJAAN
Pihak Kedua diikutsertakan dalam program jaminan sosial BPJS Ketenagakerjaan (JKK, JKM, JHT, JP) dan BPJS Kesehatan sesuai ketentuan yang berlaku.

### PASAL 6: TATA TERTIB DAN KERAHASIAAN
Pihak Kedua wajib menjaga rahasia perusahaan dan mematuhi Perjanjian Kerahasiaan (NDA) yang telah ditandatangani.

### PASAL 7: BERAKHIRNYA HUBUNGAN KERJA
Hubungan kerja berakhir apabila jangka waktu perjanjian telah selesai, atau terjadi pelanggaran berat sebagaimana diatur dalam Peraturan Perusahaan dan peraturan perundang-undangan.

### PASAL 8: PENYELESAIAN PERSELISIHAN
Segala perselisihan diselesaikan secara musyawarah mufakat, mediasi ketenagakerjaan, dan melalui forum {dispute_forum}.

---

**PIHAK PERTAMA (PERUSAHAAN)**  
{company_name}  
Ditandatangani secara elektronik oleh:  
**{hr_signatory_name}** ({hr_signatory_title})  
Tanggal: {company_signed_at}  

**PIHAK KEDUA (KARYAWAN)**  
Ditandatangani secara elektronik oleh:  
**{candidate_name}**  
NIK: {candidate_nik_masked}  
Tanggal: {candidate_signed_at}  
Kode Hash: [{document_hash}]
MARKDOWN;
    }
}
