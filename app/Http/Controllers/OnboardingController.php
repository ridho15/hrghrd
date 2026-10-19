<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\OnboardingCase;
use App\Models\Position;
use App\Models\User;
use App\Services\OnboardingService;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    private function authorizeHR(): void
    {
        abort_unless(Access::hr(), 403, 'Akses ditolak: Anda tidak memiliki wewenang HR / Manajemen Onboarding.');
    }

    public function index(Request $request)
    {
        $this->authorizeHR();

        $status = $request->query('status');
        $branchId = $request->query('branch_id');
        $search = trim((string) $request->query('search'));

        $query = OnboardingCase::with(['user', 'branch', 'position', 'supervisor', 'hrApprover'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('user', function ($u) use ($search) {
                    $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id');

        $cases = $query->paginate(15)->withQueryString();

        $branches = Branch::orderBy('name')->get();
        $counts = [
            'total' => OnboardingCase::count(),
            'menunggu_persetujuan_hr' => OnboardingCase::where('status', 'menunggu_persetujuan_hr')->count(),
            'menunggu_tanda_tangan' => OnboardingCase::whereIn('status', ['menunggu_tanda_tangan_offer', 'menunggu_tanda_tangan_nda'])->count(),
            'perlu_verifikasi' => OnboardingCase::where('status', 'perlu_verifikasi')->count(),
            'selesai' => OnboardingCase::where('status', 'selesai')->count(),
        ];

        return view('onboarding.index', compact('cases', 'branches', 'counts'));
    }

    public function create()
    {
        $this->authorizeHR();

        $branches = Branch::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();
        $supervisors = User::where('active', true)->where('employment_status', '!=', 'candidate')->orderBy('name')->get();

        return view('onboarding.create', compact('branches', 'positions', 'supervisors'));
    }

    public function store(Request $request)
    {
        $this->authorizeHR();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'branch_id' => 'required|exists:branches,id',
            'position_id' => 'required|exists:positions,id',
            'supervisor_id' => 'nullable|exists:users,id',
            'unit' => 'nullable|string|max:100',
            'salary_offered' => 'required|integer|min:1000000',
            'start_date' => 'required|date',
            'offer_valid_until' => 'required|date|after_or_equal:today',
            'entity_name' => 'nullable|string|max:150',
            'benefits' => 'nullable|array',
            'benefits.*' => 'string|max:255',
        ], [
            'name.required' => 'Nama lengkap kandidat wajib diisi.',
            'email.required' => 'Alamat email kandidat wajib diisi.',
            'email.unique' => 'Alamat email ini sudah terdaftar dalam sistem.',
            'branch_id.required' => 'Cabang penempatan wajib dipilih.',
            'position_id.required' => 'Jabatan wajib dipilih.',
            'salary_offered.required' => 'Gaji pokok yang ditawarkan wajib diisi (minimal Rp 1.000.000).',
            'start_date.required' => 'Tanggal mulai bekerja wajib ditentukan.',
            'offer_valid_until.required' => 'Batas masa berlaku penawaran wajib diisi.',
            'offer_valid_until.after_or_equal' => 'Batas masa berlaku penawaran tidak boleh tanggal lampau.',
        ]);

        $case = $this->onboardingService->createCandidate($validated, auth()->id());

        return redirect()->route('onboarding.show', $case->id)
            ->with('ok', 'Data calon karyawan berhasil dibuat dan draf Offering Letter siap ditinjau HR.');
    }

    public function show(int $id)
    {
        $this->authorizeHR();

        $case = OnboardingCase::with([
            'user',
            'branch',
            'position',
            'supervisor',
            'hrApprover',
            'documents.signingRequests.events',
            'identitySubmissions.reviewer',
            'livenessChecks.reviewer',
        ])->findOrFail($id);

        $offeringDoc = $case->offeringDocument;
        $ndaDoc = $case->ndaDocument;
        $contractDoc = $case->contractDocument;
        $latestKtp = $case->latestIdentitySubmission;
        $latestLiveness = $case->latestLivenessCheck;

        $auditLogs = \App\Models\AuditLog::where(function ($q) use ($case) {
            $q->where('subject_type', 'onboarding_case')->where('subject_id', $case->id)
                ->orWhere('subject_type', 'user')->where('subject_id', $case->user_id)
                ->orWhere(function ($sub) use ($case) {
                    $docIds = $case->documents->pluck('id')->toArray();
                    $sub->where('subject_type', 'document_instance')->whereIn('subject_id', $docIds);
                });
        })->latest('created_at')->take(20)->get();

        return view('onboarding.show', compact(
            'case',
            'offeringDoc',
            'ndaDoc',
            'contractDoc',
            'latestKtp',
            'latestLiveness',
            'auditLogs'
        ));
    }

    public function approveOffer(int $id)
    {
        $this->authorizeHR();

        $case = OnboardingCase::findOrFail($id);
        $this->onboardingService->hrApproveOffer($case, auth()->id());

        return back()->with('ok', 'Offering Letter resmi disetujui HR. Tautan aman penandatanganan telah aktif untuk kandidat.');
    }

    public function requestRevision(Request $request, int $id)
    {
        $this->authorizeHR();

        $request->validate([
            'target' => 'required|in:ktp,liveness',
            'notes' => 'required|string|min:5|max:1000',
        ], [
            'notes.required' => 'Catatan perbaikan berkas wajib diisi.',
            'notes.min' => 'Catatan perbaikan minimal 5 karakter.',
        ]);

        $case = OnboardingCase::findOrFail($id);
        $this->onboardingService->requestRevision($case, $request->input('target'), $request->input('notes'), auth()->id());

        return back()->with('ok', 'Permintaan perbaikan berkas telah dikirimkan kepada kandidat.');
    }

    public function verifyFinal(Request $request, int $id)
    {
        $this->authorizeHR();

        $case = OnboardingCase::findOrFail($id);
        $this->onboardingService->hrVerifyAndComplete($case, auth()->id(), $request->input('notes'));

        return back()->with('ok', 'Selamat! Verifikasi akhir onboarding selesai. Akun karyawan resmi diaktifkan di struktur perusahaan.');
    }

    public function cancel(Request $request, int $id)
    {
        $this->authorizeHR();

        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Alasan pembatalan onboarding wajib dicantumkan.',
        ]);

        $case = OnboardingCase::findOrFail($id);
        $this->onboardingService->cancelCase($case, $request->input('reason'), auth()->id());

        return back()->with('ok', 'Proses onboarding telah dibatalkan.');
    }

    public function resendReminder(int $id)
    {
        $this->authorizeHR();

        $case = OnboardingCase::findOrFail($id);
        // Refresh token expiry if close to expiry
        if ($case->token_expires_at->isPast() || $case->token_expires_at->diffInDays(now()) < 2) {
            $case->update([
                'token_expires_at' => now()->addDays(5),
                'offer_valid_until' => now()->addDays(5),
            ]);
        }

        Audit::record('onboarding_case', $case->id, 'resend_reminder', 'HR mengirimkan pengingat onboarding kepada kandidat.');

        return back()->with('ok', 'Pengingat onboarding dan perpanjangan masa aktif tautan berhasil diperbarui.');
    }

    /**
     * Secure stream endpoint for sensitive files (KTP, selfie, evidence pack).
     */
    public function streamFile(int $id, string $type)
    {
        $case = OnboardingCase::findOrFail($id);

        // Security check: Only Admin, HR, Auditor, or the candidate themself
        abort_unless(Access::canViewSensitiveDocuments($case->user_id), 403, 'Akses dokumen privat dibatasi oleh kebijakan keamanan data.');

        $filePath = null;
        $fileName = 'document';

        if ($type === 'ktp') {
            $ktp = $case->latestIdentitySubmission;
            abort_unless($ktp && Storage::disk('local')->exists($ktp->file_path), 404, 'Berkas KTP tidak ditemukan.');
            $filePath = $ktp->file_path;
            $fileName = 'ktp_' . $case->user_id . '.' . pathinfo($ktp->file_path, PATHINFO_EXTENSION);
        } elseif ($type === 'liveness') {
            $liveness = $case->latestLivenessCheck;
            abort_unless($liveness && Storage::disk('local')->exists($liveness->selfie_path), 404, 'Berkas foto wajah biometrik tidak ditemukan.');
            $filePath = $liveness->selfie_path;
            $fileName = 'selfie_' . $case->user_id . '.' . pathinfo($liveness->selfie_path, PATHINFO_EXTENSION);
        } elseif ($type === 'evidence_offer') {
            $doc = $case->offeringDocument;
            $req = $doc?->signingRequests()->first();
            abort_unless($req && $req->evidence_pack_path && Storage::disk('local')->exists($req->evidence_pack_path), 404, 'Evidence pack offering letter belum tersedia.');
            $filePath = $req->evidence_pack_path;
            $fileName = 'evidence_offer_' . $case->id . '.json';
        } elseif ($type === 'evidence_nda') {
            $doc = $case->ndaDocument;
            $req = $doc?->signingRequests()->first();
            abort_unless($req && $req->evidence_pack_path && Storage::disk('local')->exists($req->evidence_pack_path), 404, 'Evidence pack NDA belum tersedia.');
            $filePath = $req->evidence_pack_path;
            $fileName = 'evidence_nda_' . $case->id . '.json';
        } else {
            abort(404);
        }

        Audit::record('onboarding_case', $case->id, 'access_sensitive_file', "Pengguna mengakses berkas privat tipe {$type}.");

        return Storage::disk('local')->response($filePath, $fileName);
    }
}
