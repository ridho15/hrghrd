<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DocumentInstance;
use App\Models\DocumentTemplate;
use App\Models\Position;
use App\Models\User;
use App\Services\DocumentTemplateService;
use App\Services\ESign\ESignService;
use App\Support\Access;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmploymentContractController extends Controller
{
    public function __construct(
        protected DocumentTemplateService $templateService,
        protected ESignService $esignService
    ) {}

    private function authorizeHR(): void
    {
        abort_unless(Access::hr(), 403, 'Akses ditolak: Anda tidak memiliki wewenang pengelolaan kontrak kerja.');
    }

    public function index(Request $request)
    {
        $this->authorizeHR();

        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $query = DocumentInstance::with(['user.branch', 'user.position', 'templateVersion'])
            ->where('type', 'employment_contract')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('user', function ($u) use ($search) {
                    $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id');

        $contracts = $query->paginate(15)->withQueryString();

        return view('contracts.index', compact('contracts'));
    }

    public function create(int $userId)
    {
        $this->authorizeHR();

        $employee = User::with(['branch', 'position', 'manager'])->findOrFail($userId);
        $template = DocumentTemplate::where('type', 'employment_contract')->first();
        abort_unless($template && $template->activeVersion, 404, 'Template Kontrak Kerja aktif belum dikonfigurasi di Master Settings.');

        $defaultDocNumber = 'PKWT/' . date('Y/m/') . str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT);
        $defaultFacts = [
            'candidate_name' => $employee->name,
            'candidate_nik' => $employee->nik_masked ?: '3171************',
            'candidate_nik_masked' => $employee->nik_masked ?: '3171************',
            'candidate_address' => $employee->address ?: 'Indonesia',
            'candidate_email' => $employee->email,
            'candidate_phone' => $employee->phone ?: '-',
            'position_title' => $employee->position?->name ?: 'Karyawan',
            'branch_name' => $employee->branch?->name ?: 'Kantor Pusat',
            'unit' => $employee->unit ?: 'Operasional',
            'supervisor_name' => $employee->manager?->name ?: 'Direktur Operasional',
            'base_salary' => 'Rp ' . number_format($employee->base_salary, 0, ',', '.'),
            'base_salary_words' => ucfirst(trim($this->templateService->terbilang($employee->base_salary))) . ' Rupiah',
            'start_date' => $employee->hired_at ? $employee->hired_at->format('d/m/Y') : date('d/m/Y'),
            'start_date_indonesian' => $employee->hired_at ? $employee->hired_at->translatedFormat('d F Y') : Carbon::now()->translatedFormat('d F Y'),
            'company_name' => $employee->entity_name ?: 'PT HR Group Indonesia',
            'company_address' => 'Gedung HR Group, Jl. Sudirman Kav. 52-53, Jakarta Selatan',
            'hr_signatory_name' => auth()->user()?->name ?: 'Head of People & Culture',
            'hr_signatory_title' => 'Direktur Human Capital',
            'dispute_forum' => 'Pengadilan Hubungan Industrial Jakarta Pusat',
            'effective_date_indonesian' => Carbon::now()->translatedFormat('d F Y'),
            'company_signed_at' => Carbon::now()->format('d/m/Y H:i:s T'),
            'candidate_signed_at' => 'Menunggu Tanda Tangan',
            'document_number' => $defaultDocNumber,
            'document_hash' => hash('sha256', (string) $employee->id . now()),
        ];

        $rendered = $this->templateService->renderContent($template->activeVersion->content, $defaultFacts);

        return view('contracts.create', compact('employee', 'template', 'defaultDocNumber', 'rendered', 'defaultFacts'));
    }

    public function store(Request $request)
    {
        $this->authorizeHR();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'document_number' => 'required|string|unique:document_instances,document_number|max:100',
            'rendered_content' => 'nullable|string|min:20',
            'start_date' => 'required|date',
            'expires_at' => 'nullable|date|after:start_date',
        ]);

        $employee = User::with(['branch', 'position', 'manager'])->findOrFail($validated['user_id']);
        $template = DocumentTemplate::where('type', 'employment_contract')->firstOrFail();
        $version = $template->activeVersion;

        $content = $validated['rendered_content'] ?? null;
        if (!$content && $version) {
            $facts = [
                'candidate_name' => $employee->name,
                'candidate_nik' => $employee->nik_masked ?: '3171************',
                'candidate_nik_masked' => $employee->nik_masked ?: '3171************',
                'candidate_address' => $employee->address ?: 'Indonesia',
                'candidate_email' => $employee->email,
                'candidate_phone' => $employee->phone ?: '-',
                'position_title' => $employee->position?->name ?: 'Karyawan',
                'branch_name' => $employee->branch?->name ?: 'Kantor Pusat',
                'unit' => $employee->unit ?: 'Operasional',
                'supervisor_name' => $employee->manager?->name ?: 'Direktur Operasional',
                'base_salary' => 'Rp ' . number_format($employee->base_salary, 0, ',', '.'),
                'base_salary_words' => ucfirst($this->templateService->terbilang($employee->base_salary)) . ' Rupiah',
                'start_date' => date('d/m/Y', strtotime($validated['start_date'])),
                'start_date_indonesian' => Carbon::parse($validated['start_date'])->translatedFormat('d F Y'),
                'company_name' => $employee->entity_name ?: 'PT HR Group Indonesia',
                'company_address' => 'Gedung HR Group, Jl. Sudirman Kav. 52-53, Jakarta Selatan',
                'hr_signatory_name' => auth()->user()?->name ?: 'Head of People & Culture',
                'hr_signatory_title' => 'Direktur Human Capital',
                'dispute_forum' => 'Pengadilan Hubungan Industrial Jakarta Pusat',
                'effective_date_indonesian' => Carbon::now()->translatedFormat('d F Y'),
                'company_signed_at' => Carbon::now()->format('d/m/Y H:i:s T'),
                'candidate_signed_at' => 'Menunggu Tanda Tangan',
                'document_number' => $validated['document_number'],
                'document_hash' => hash('sha256', (string) $employee->id . now()),
            ];
            $content = $this->templateService->renderContent($version->content, $facts);
        }

        $hash = hash('sha256', $content ?: $validated['document_number']);

        $contract = DocumentInstance::create([
            'user_id' => $employee->id,
            'type' => 'employment_contract',
            'document_template_version_id' => $version?->id,
            'document_number' => $validated['document_number'],
            'version' => 1,
            'status' => 'menunggu_tanda_tangan',
            'rendered_content' => $content,
            'file_hash' => $hash,
            'data_snapshot' => [
                'candidate_name' => $employee->name,
                'position_title' => $employee->position?->name,
                'branch_name' => $employee->branch?->name,
                'base_salary' => 'Rp ' . number_format($employee->base_salary, 0, ',', '.'),
                'base_salary_words' => $this->templateService->terbilang($employee->base_salary) . ' Rupiah',
                'start_date' => $validated['start_date'],
                'document_number' => $validated['document_number'],
                'document_hash' => $hash,
            ],
            'ready_for_review' => true,
        ]);

        Audit::record('document_instance', $contract->id, 'create_contract', "Draf kontrak kerja PKWT dibuat untuk {$employee->name}.");

        return redirect()->route('contracts.show', $contract->id)
            ->with('ok', 'Draf Kontrak Kerja berhasil dibuat dan siap untuk proses penandatanganan elektronik.');
    }

    public function show(int $id)
    {
        $contract = DocumentInstance::with(['user.branch', 'user.position', 'signingRequests.events', 'hrApprovedBy'])
            ->where('type', 'employment_contract')
            ->findOrFail($id);

        abort_unless(Access::canViewSensitiveDocuments($contract->user_id), 403, 'Akses terbatas untuk dokumen kontrak kerja.');

        return view('contracts.show', compact('contract'));
    }

    public function sendEsign(Request $request, int $id)
    {
        $this->authorizeHR();

        $contract = DocumentInstance::with(['user'])->findOrFail($id);
        abort_if($contract->status === 'ditandatangani', 422, 'Kontrak kerja ini sudah selesai ditandatangani.');

        $this->esignService->initiateDocumentSigning($contract, [
            [
                'signer_name' => $contract->user->name,
                'signer_email' => $contract->user->email,
                'signer_phone' => $contract->user->phone,
                'signer_role' => 'candidate',
            ],
            [
                'signer_name' => auth()->user()?->name ?: 'Perwakilan Perusahaan',
                'signer_email' => auth()->user()?->email ?: 'hr@hrgroup.id',
                'signer_role' => 'company',
            ],
        ]);

        Audit::record('document_instance', $contract->id, 'send_esign', "Kontrak kerja {$contract->document_number} dikirimkan untuk tanda tangan elektronik.");

        return back()->with('ok', 'Kontrak kerja berhasil dikirimkan untuk penandatanganan elektronik Para Pihak.');
    }
}
