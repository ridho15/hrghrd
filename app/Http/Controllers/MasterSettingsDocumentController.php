<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Position;
use App\Models\Setting;
use App\Services\DocumentTemplateService;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MasterSettingsDocumentController extends Controller
{
    public function __construct(
        protected DocumentTemplateService $templateService
    ) {}

    private function authorizeManage(): void
    {
        abort_unless(Access::canManageTemplates(), 403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola Master Dokumen.');
    }

    public function index()
    {
        $this->authorizeManage();
        $this->templateService->ensureDefaultTemplates();

        $templates = DocumentTemplate::with(['activeVersion', 'versions.createdBy', 'versions.approvedBy'])
            ->orderBy('type')
            ->get();

        $stats = [
            'total_templates' => $templates->count(),
            'total_versions' => DocumentTemplateVersion::count(),
            'published_versions' => DocumentTemplateVersion::where('status', 'published')->count(),
            'draft_versions' => DocumentTemplateVersion::where('status', 'draft')->count(),
        ];

        return view('settings.documents-index', compact('templates', 'stats'));
    }

    public function edit(string $type)
    {
        $this->authorizeManage();
        $this->templateService->ensureDefaultTemplates();

        $template = DocumentTemplate::with(['activeVersion', 'versions.createdBy', 'versions.approvedBy'])
            ->where('type', $type)
            ->firstOrFail();

        // Get latest version or create new draft if only published versions exist
        $latestVersion = $template->versions()->first();
        $draftVersion = $template->versions()->where('status', 'draft')->first();

        $registeredVariables = DocumentTemplateService::REGISTERED_VARIABLES;
        $branches = Branch::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();

        return view('settings.documents-edit', compact('template', 'latestVersion', 'draftVersion', 'registeredVariables', 'branches', 'positions'));
    }

    public function saveDraft(Request $request, string $type)
    {
        $this->authorizeManage();

        $request->validate([
            'content' => 'required|string|min:20',
            'change_summary' => 'nullable|string|max:500',
            'required_variables' => 'nullable|array',
            'clauses' => 'nullable|array',
        ], [
            'content.required' => 'Isi naskah dokumen template wajib diisi.',
            'content.min' => 'Isi naskah dokumen template terlalu pendek.',
        ]);

        $template = DocumentTemplate::where('type', $type)->firstOrFail();

        // Check for draft version
        $draft = $template->versions()->where('status', 'draft')->first();

        if ($draft) {
            $draft->update([
                'content' => $request->input('content'),
                'change_summary' => $request->input('change_summary') ?: 'Pembaruan redaksi draf dokumen.',
                'required_variables' => $request->input('required_variables') ?: ['candidate_name', 'position_title', 'branch_name'],
                'clauses' => $request->input('clauses'),
                'created_by_id' => auth()->id(),
            ]);
            $version = $draft;
        } else {
            $nextNumber = ($template->versions()->max('version_number') ?: 0) + 1;
            $version = DocumentTemplateVersion::create([
                'document_template_id' => $template->id,
                'version_number' => $nextNumber,
                'status' => 'draft',
                'content' => $request->input('content'),
                'clauses' => $request->input('clauses'),
                'required_variables' => $request->input('required_variables') ?: ['candidate_name', 'position_title', 'branch_name'],
                'change_summary' => $request->input('change_summary') ?: 'Pembuatan draf versi baru ' . $nextNumber,
                'created_by_id' => auth()->id(),
            ]);
        }

        Audit::record('document_template_version', $version->id, 'save_draft', "Draf template {$template->title} versi {$version->version_number} berhasil disimpan.");

        return back()->with('ok', 'Draf versi dokumen berhasil disimpan.');
    }

    public function submitReview(Request $request, int $versionId)
    {
        $this->authorizeManage();

        $version = DocumentTemplateVersion::with('template')->findOrFail($versionId);
        abort_unless($version->isDraft(), 422, 'Hanya draf yang dapat diajukan untuk tinjauan legal.');

        $validation = $this->templateService->validateTemplateContent($version->content, $version->required_variables ?: []);
        if (!$validation['valid']) {
            return back()->withErrors(['template' => implode(' | ', $validation['errors'])]);
        }

        $version->update([
            'status' => 'in_review',
            'change_summary' => $request->input('notes') ?: 'Diajukan untuk peninjauan legal & approver.',
        ]);

        Audit::record('document_template_version', $version->id, 'submit_review', "Versi {$version->version_number} diajukan untuk review legal.");

        return back()->with('ok', 'Versi dokumen berhasil diajukan untuk peninjauan legal / approver.');
    }

    public function approve(Request $request, int $versionId)
    {
        abort_unless(Access::canApproveTemplates(), 403, 'Akses ditolak: Anda tidak memiliki wewenang menyetujui template dokumen.');

        $version = DocumentTemplateVersion::with('template')->findOrFail($versionId);
        abort_unless(in_array($version->status, ['draft', 'in_review']), 422, 'Status versi tidak valid untuk disetujui.');

        $version->update([
            'status' => 'approved',
            'reviewed_by_id' => auth()->id(),
            'approved_by_id' => auth()->id(),
        ]);

        Audit::record('document_template_version', $version->id, 'approve_version', "Versi {$version->version_number} disetujui oleh approver.");

        return back()->with('ok', 'Versi template dokumen berhasil disetujui dan siap untuk diterbitkan.');
    }

    public function publish(Request $request, int $versionId)
    {
        abort_unless(Access::canPublishTemplates() || Access::admin(), 403, 'Akses ditolak: Anda tidak memiliki wewenang mempublikasikan template dokumen.');

        $version = DocumentTemplateVersion::with('template')->findOrFail($versionId);
        abort_unless(in_array($version->status, ['approved', 'in_review', 'draft']), 422, 'Status versi belum siap diterbitkan.');

        // Validate mandatory clauses and variables
        $validation = $this->templateService->validateTemplateContent($version->content, $version->required_variables ?: []);
        if (!$validation['valid']) {
            return back()->withErrors(['template' => implode(' | ', $validation['errors'])]);
        }

        // Archive previous published versions
        $template = $version->template;
        $template->versions()->where('status', 'published')->update(['status' => 'archived']);

        $version->update([
            'status' => 'published',
            'approved_by_id' => auth()->id(),
            'published_at' => now(),
        ]);

        $template->update([
            'active_version_id' => $version->id,
        ]);

        Audit::record('document_template', $template->id, 'publish_version', "Versi {$version->version_number} resmi diterbitkan sebagai versi aktif untuk dokumen baru.");

        return back()->with('ok', "Versi {$version->version_number} resmi diterbitkan dan berlaku sebagai acuan dokumen baru.");
    }

    public function rollback(Request $request, int $versionId)
    {
        abort_unless(Access::canPublishTemplates() || Access::admin(), 403, 'Akses ditolak: Wewenang rollback terbatas untuk Super Admin.');

        $version = DocumentTemplateVersion::with('template')->findOrFail($versionId);
        $template = $version->template;

        $template->versions()->where('status', 'published')->update(['status' => 'archived']);

        $version->update([
            'status' => 'published',
        ]);

        $template->update([
            'active_version_id' => $version->id,
        ]);

        Audit::record('document_template', $template->id, 'rollback_version', "Template di-rollback ke versi {$version->version_number} untuk penerbitan dokumen baru.");

        return back()->with('ok', "Template berhasil dialihkan kembali ke Versi {$version->version_number}. Dokumen yang telah terbit sebelumnya tetap utuh.");
    }

    public function preview(Request $request)
    {
        $this->authorizeManage();

        $content = $request->input('content', '');
        $sampleFacts = [
            'candidate_name' => 'Aditya Pratama, S.Kom.',
            'candidate_nik' => '3171029384750001',
            'candidate_nik_masked' => '3171********0001',
            'candidate_email' => 'aditya.pratama@example.com',
            'candidate_phone' => '0812-3456-7890',
            'candidate_address' => 'Jl. Tebet Barat Raya No. 12, Jakarta Selatan',
            'position_title' => 'Senior IT Operations Specialist',
            'branch_name' => 'Cabang Tebet Jakarta',
            'work_location' => 'Menara BTPN Lt. 15, Mega Kuningan, Jakarta Selatan',
            'unit' => 'Divisi Teknologi Informasi',
            'supervisor_name' => 'Budi Santoso (VP Engineering)',
            'base_salary' => 'Rp 14.500.000',
            'base_salary_words' => 'Empat belas juta lima ratus ribu Rupiah',
            'benefits_list' => "- Tunjangan makan & transportasi Rp 1.500.000/bulan\n- Asuransi kesehatan rawat inap dan jalan keluarga\n- BPJS Ketenagakerjaan & BPJS Kesehatan lengkap",
            'start_date' => date('d/m/Y', strtotime('+7 days')),
            'start_date_indonesian' => Carbon::now()->addDays(7)->translatedFormat('d F Y'),
            'probation_months' => '3 (Tiga)',
            'offer_valid_until' => Carbon::now()->addDays(3)->translatedFormat('d F Y'),
            'company_name' => 'PT HR Group Indonesia',
            'company_address' => 'Gedung Graha Mitra Lt. 8, Jl. Jend. Gatot Subroto, Jakarta Selatan',
            'hr_signatory_name' => auth()->user()?->name ?: 'Ratna Indrawati, S.H.',
            'hr_signatory_title' => 'Head of Human Resources & Legal',
            'confidentiality_duration_years' => '3',
            'confidentiality_duration_words' => 'tiga',
            'governing_law' => 'Hukum Negara Republik Indonesia',
            'dispute_forum' => 'Pengadilan Hubungan Industrial pada Pengadilan Negeri Jakarta Pusat',
            'effective_date_indonesian' => Carbon::now()->translatedFormat('d F Y'),
            'company_signed_at' => Carbon::now()->format('d/m/Y H:i:s T'),
            'candidate_signed_at' => '[Pratinjau: Belum Ditandatangani]',
            'document_number' => 'PREVIEW/' . date('Y/m/') . '0001',
            'document_hash' => hash('sha256', $content),
        ];

        $rendered = $this->templateService->renderContent($content, $sampleFacts);
        $validation = $this->templateService->validateTemplateContent($content);

        return response()->json([
            'rendered' => $rendered,
            'valid' => $validation['valid'],
            'errors' => $validation['errors'],
            'used_variables' => $validation['used_variables'],
        ]);
    }

    public function esignSettings()
    {
        $this->authorizeManage();

        $settings = [
            'company_signatory_name' => Setting::where('key', 'esign_company_signatory_name')->value('value') ?: 'Ratna Indrawati, S.H.',
            'company_signatory_title' => Setting::where('key', 'esign_company_signatory_title')->value('value') ?: 'Head of Human Capital',
            'esign_provider' => Setting::where('key', 'esign_provider')->value('value') ?: 'internal_esign',
            'token_expiry_days' => Setting::where('key', 'esign_token_expiry_days')->value('value') ?: '7',
            'require_otp' => Setting::where('key', 'esign_require_otp')->value('value') ?: '0',
            'webhook_secret' => Setting::where('key', 'esign_webhook_secret')->value('value') ?: 'whsec_' . substr(md5('hrghrd-secret'), 0, 16),
        ];

        return view('settings.documents-esign', compact('settings'));
    }

    public function saveEsignSettings(Request $request)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'company_signatory_name' => 'required|string|max:150',
            'company_signatory_title' => 'required|string|max:150',
            'esign_provider' => 'required|string|in:internal_esign,privy_adapter,peruri_adapter',
            'token_expiry_days' => 'required|integer|min:1|max:30',
        ]);

        foreach ($validated as $k => $v) {
            Setting::updateOrCreate(['key' => 'esign_' . $k], ['value' => (string) $v]);
        }

        Audit::record('settings', 1, 'update_esign_settings', 'Pengaturan penandatanganan elektronik diperbarui.');

        return back()->with('ok', 'Konfigurasi Tanda Tangan Elektronik berhasil disimpan.');
    }
}
