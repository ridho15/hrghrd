<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DocumentInstance;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\IdentitySubmission;
use App\Models\LivenessCheck;
use App\Models\OnboardingCase;
use App\Models\OrgAssignment;
use App\Models\Position;
use App\Models\User;
use App\Services\DocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComprehensiveOnboardingNoHappyPathUatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(DocumentTemplateService::class)->ensureDefaultTemplates();
    }

    // ==========================================
    // 1. CANDIDATE CREATION UNHAPPY PATHS
    // ==========================================

    public function test_candidate_creation_fails_when_required_fields_are_missing(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('onboarding.store'), []);

        $response->assertSessionHasErrors([
            'name',
            'email',
            'branch_id',
            'position_id',
            'salary_offered',
            'start_date',
            'offer_valid_until',
        ]);
        $this->assertEquals(0, OnboardingCase::count());
        $this->assertEquals(0, User::where('employment_status', 'candidate')->count());
    }

    public function test_candidate_creation_fails_with_duplicate_email(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition();
        $existing = $this->createUser(['email' => 'duplicate@example.com']);

        $response = $this->post(route('onboarding.store'), [
            'name' => 'Calon Duplikat',
            'email' => 'duplicate@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertEquals(0, OnboardingCase::count());
    }

    public function test_candidate_creation_fails_with_salary_below_minimum(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition();

        $response = $this->post(route('onboarding.store'), [
            'name' => 'Gaji Terlalu Rendah',
            'email' => 'gaji.rendah@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 500000, // below 1,000,000
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['salary_offered']);
        $this->assertEquals(0, OnboardingCase::count());
    }

    public function test_candidate_creation_fails_with_past_valid_until_date(): void
    {
        $this->actingAsAdmin();

        $branch = $this->createBranch();
        $position = $this->createPosition();

        $response = $this->post(route('onboarding.store'), [
            'name' => 'Expired Valid Until',
            'email' => 'expired.date@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->subDay()->format('Y-m-d'), // past date
        ]);

        $response->assertSessionHasErrors(['offer_valid_until']);
        $this->assertEquals(0, OnboardingCase::count());
    }

    public function test_candidate_creation_fails_with_invalid_foreign_keys(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('onboarding.store'), [
            'name' => 'Invalid Foreign Keys',
            'email' => 'invalid.fk@example.com',
            'branch_id' => 999999,
            'position_id' => 888888,
            'supervisor_id' => 777777,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors(['branch_id', 'position_id', 'supervisor_id']);
        $this->assertEquals(0, OnboardingCase::count());
    }

    public function test_non_hr_user_cannot_access_or_create_candidate(): void
    {
        $employee = $this->createUser(['role' => 'employee']);
        $this->actingAs($employee);

        $resIndex = $this->get(route('onboarding.index'));
        $resIndex->assertStatus(403);

        $resCreate = $this->get(route('onboarding.create'));
        $resCreate->assertStatus(403);

        $resStore = $this->post(route('onboarding.store'), [
            'name' => 'Unauthorized Attempt',
            'email' => 'unauth@example.com',
            'branch_id' => 1,
            'position_id' => 1,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);
        $resStore->assertStatus(403);
    }

    // ==========================================
    // 2. APPROVAL GATE & EXPIRATION UNHAPPY PATHS
    // ==========================================

    public function test_candidate_portal_signing_fails_without_consent_checkbox(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Tanpa Persetujuan',
            'email' => 'tanpa.persetujuan@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        // Submit offer without consent_agreed
        $response = $this->post(route('onboarding.portal.sign_offer', $case->token), [
            'consent_agreed' => 0,
        ]);

        $response->assertSessionHasErrors(['consent_agreed']);
        $case->refresh();
        $this->assertEquals('menunggu_tanda_tangan_offer', $case->status);
    }

    public function test_signing_offer_fails_when_offer_has_expired(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Kedaluwarsa',
            'email' => 'kedaluwarsa@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        // Manually simulate expired date
        $case->update([
            'offer_valid_until' => now()->subDay(),
            'token_expires_at' => now()->subDay(),
        ]);

        $response = $this->post(route('onboarding.portal.sign_offer', $case->token), [
            'consent_agreed' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_hr_cannot_approve_cancelled_or_completed_case(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Batal Case',
            'email' => 'batal@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();

        // HR cancels case
        $this->post(route('onboarding.cancel', $case->id), [
            'reason' => 'Kandidat mengundurkan diri sebelum proses dimulai.',
        ]);
        $case->refresh();
        $this->assertEquals('dibatalkan', $case->status);

        // Attempting to approve now must fail with 422
        $resApprove = $this->post(route('onboarding.approve_offer', $case->id));
        $resApprove->assertStatus(422);
    }

    // ==========================================
    // 3. STRICT SEQUENTIAL GATING UNHAPPY PATHS
    // ==========================================

    public function test_candidate_cannot_bypass_steps_or_skip_order(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Bypass',
            'email' => 'bypass@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');
        $fakeKtp = UploadedFile::fake()->create('ktp.jpg', 500, 'image/jpeg');
        $fakeSelfie = UploadedFile::fake()->create('selfie.jpg', 500, 'image/jpeg');

        // Stage 1: menunggu_tanda_tangan_offer
        // Attempting NDA directly -> 422
        $this->post(route('onboarding.portal.sign_nda', $case->token), ['consent_agreed' => 1])
            ->assertStatus(422);

        // Attempting KTP directly -> 422
        $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171012345670001',
            'full_name' => 'Kandidat Bypass',
            'ktp_file' => $fakeKtp,
        ])->assertStatus(422);

        // Attempting Liveness directly -> 422
        $this->post(route('onboarding.portal.submit_liveness', $case->token), [
            'selfie_file' => $fakeSelfie,
        ])->assertStatus(422);

        // Sign Offer
        $this->post(route('onboarding.portal.sign_offer', $case->token), ['consent_agreed' => 1]);
        $case->refresh();
        $this->assertEquals('menunggu_tanda_tangan_nda', $case->status);

        // Stage 2: menunggu_tanda_tangan_nda
        // Attempting KTP directly before NDA -> 422
        $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171012345670001',
            'full_name' => 'Kandidat Bypass',
            'ktp_file' => $fakeKtp,
        ])->assertStatus(422);

        // Attempting Liveness directly before NDA -> 422
        $this->post(route('onboarding.portal.submit_liveness', $case->token), [
            'selfie_file' => $fakeSelfie,
        ])->assertStatus(422);

        // Sign NDA
        $this->post(route('onboarding.portal.sign_nda', $case->token), ['consent_agreed' => 1]);
        $case->refresh();
        $this->assertEquals('menunggu_ktp', $case->status);

        // Stage 3: menunggu_ktp
        // Attempting Liveness directly before KTP -> 422
        $this->post(route('onboarding.portal.submit_liveness', $case->token), [
            'selfie_file' => $fakeSelfie,
        ])->assertStatus(422);
    }

    // ==========================================
    // 4. KTP UPLOAD & NIK VALIDATION UNHAPPY PATHS
    // ==========================================

    public function test_ktp_submission_fails_with_invalid_nik_lengths(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat NIK Invalid',
            'email' => 'nik.invalid@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');
        $this->post(route('onboarding.portal.sign_offer', $case->token), ['consent_agreed' => 1]);
        $this->post(route('onboarding.portal.sign_nda', $case->token), ['consent_agreed' => 1]);

        $fakeKtp = UploadedFile::fake()->create('ktp.jpg', 500, 'image/jpeg');

        // Case A: 5 digits NIK -> fails controller request validation (min:16)
        $resShort = $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '12345',
            'full_name' => 'Kandidat NIK Invalid',
            'ktp_file' => $fakeKtp,
        ]);
        $resShort->assertSessionHasErrors(['id_number']);

        // Case B: 18 chars with hyphens/letters, but only 14 numeric digits -> cleanNik !== 16 -> 422
        $resFormat = $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171-0123-4567-00AA', // >= 16 chars raw, but only 14 digits when cleaned
            'full_name' => 'Kandidat NIK Invalid',
            'ktp_file' => $fakeKtp,
        ]);
        $resFormat->assertStatus(422);

        // Case C: 25 digits NIK -> fails max:20 validation
        $resLong = $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '1234567890123456789012345',
            'full_name' => 'Kandidat NIK Invalid',
            'ktp_file' => $fakeKtp,
        ]);
        $resLong->assertSessionHasErrors(['id_number']);
    }

    public function test_ktp_submission_fails_with_invalid_file_extension(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat File Invalid',
            'email' => 'file.invalid@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');
        $this->post(route('onboarding.portal.sign_offer', $case->token), ['consent_agreed' => 1]);
        $this->post(route('onboarding.portal.sign_nda', $case->token), ['consent_agreed' => 1]);

        // Attempt upload .exe or .txt file as KTP
        $fakeTxt = UploadedFile::fake()->create('malicious.txt', 200, 'text/plain');
        $resTxt = $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171012345670001',
            'full_name' => 'Kandidat File Invalid',
            'ktp_file' => $fakeTxt,
        ]);

        $resTxt->assertSessionHasErrors(['ktp_file']);
        $this->assertEquals(0, IdentitySubmission::count());
    }

    public function test_ktp_stores_zero_plaintext_nik(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Privasi NIK',
            'email' => 'privasi.nik@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');
        $this->post(route('onboarding.portal.sign_offer', $case->token), ['consent_agreed' => 1]);
        $this->post(route('onboarding.portal.sign_nda', $case->token), ['consent_agreed' => 1]);

        $rawNik = '3578012345670009';
        $fakeKtp = UploadedFile::fake()->create('ktp.png', 800, 'image/png');

        $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => $rawNik,
            'full_name' => 'Kandidat Privasi NIK',
            'ktp_file' => $fakeKtp,
        ]);

        $submission = IdentitySubmission::where('onboarding_case_id', $case->id)->first();
        $this->assertNotNull($submission);

        // Assert plaintext NIK is nowhere in raw database columns
        $this->assertNotEquals($rawNik, $submission->id_number_encrypted);
        $this->assertEquals('3578********0009', $submission->id_number_masked);
        $this->assertEquals($rawNik, Crypt::decryptString($submission->id_number_encrypted));

        $user = $case->user;
        $this->assertNotEquals($rawNik, $user->nik_encrypted);
        $this->assertEquals('3578********0009', $user->nik_masked);
        $this->assertEquals($rawNik, Crypt::decryptString($user->nik_encrypted));
    }

    // ==========================================
    // 5. LIVENESS CHECK UNHAPPY PATHS
    // ==========================================

    public function test_liveness_submission_fails_with_invalid_file(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Selfie Invalid',
            'email' => 'selfie.invalid@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');
        $this->post(route('onboarding.portal.sign_offer', $case->token), ['consent_agreed' => 1]);
        $this->post(route('onboarding.portal.sign_nda', $case->token), ['consent_agreed' => 1]);

        $fakeKtp = UploadedFile::fake()->create('ktp.jpg', 500, 'image/jpeg');
        $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171012345670001',
            'full_name' => 'Kandidat Selfie Invalid',
            'ktp_file' => $fakeKtp,
        ]);

        // Attempt to upload invalid text file as selfie
        $fakeTxt = UploadedFile::fake()->create('selfie.txt', 200, 'text/plain');
        $resLiveness = $this->post(route('onboarding.portal.submit_liveness', $case->token), [
            'selfie_file' => $fakeTxt,
        ]);

        $resLiveness->assertSessionHasErrors(['selfie_file']);
        $this->assertEquals(0, LivenessCheck::count());
    }

    // ==========================================
    // 6. HR FINAL VERIFICATION & REVISION UNHAPPY PATHS
    // ==========================================

    public function test_hr_cannot_verify_final_when_onboarding_steps_are_incomplete(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Incomplete',
            'email' => 'incomplete@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();

        // 1. Cannot verify while in status 'menunggu_persetujuan_hr'
        $res1 = $this->post(route('onboarding.verify_final', $case->id));
        $res1->assertStatus(422);

        // Approve offer
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        // 2. Cannot verify while in status 'menunggu_tanda_tangan_offer'
        $res2 = $this->post(route('onboarding.verify_final', $case->id));
        $res2->assertStatus(422);

        // Sign offer
        $this->post(route('onboarding.portal.sign_offer', $case->token), ['consent_agreed' => 1]);
        $case->refresh();

        // 3. Cannot verify while in status 'menunggu_tanda_tangan_nda'
        $res3 = $this->post(route('onboarding.verify_final', $case->id));
        $res3->assertStatus(422);
    }

    public function test_non_hr_cannot_request_revision_or_verify_final(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Non HR Gate',
            'email' => 'nonhr.gate@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();

        $regularEmployee = $this->createUser(['role' => 'employee']);
        $this->actingAs($regularEmployee);

        $resRev = $this->post(route('onboarding.request_revision', $case->id), [
            'target' => 'ktp',
            'notes' => 'Catatan revisi tidak sah',
        ]);
        $resRev->assertStatus(403);

        $resVerify = $this->post(route('onboarding.verify_final', $case->id));
        $resVerify->assertStatus(403);
    }

    // ==========================================
    // 7. SECURITY & SENSITIVE DOCUMENTS ACCESS CONTROL
    // ==========================================

    public function test_unauthenticated_guest_is_redirected_to_login_on_stream_file(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Guest Stream Test',
            'email' => 'guest.stream@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();

        // Logout
        auth()->logout();

        $res = $this->get(route('onboarding.stream_file', [$case->id, 'ktp']));
        $res->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_other_users_sensitive_files(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Kandidat Target Dokumen',
            'email' => 'target.dokumen@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();
        $this->post(route('onboarding.approve_offer', $case->id));
        $case->refresh();

        Storage::fake('local');
        $this->post(route('onboarding.portal.sign_offer', $case->token), ['consent_agreed' => 1]);
        $this->post(route('onboarding.portal.sign_nda', $case->token), ['consent_agreed' => 1]);

        $fakeKtp = UploadedFile::fake()->create('ktp_rahasia.jpg', 500, 'image/jpeg');
        $this->post(route('onboarding.portal.upload_ktp', $case->token), [
            'id_number' => '3171012345670001',
            'full_name' => 'Kandidat Target Dokumen',
            'ktp_file' => $fakeKtp,
        ]);

        // Malicious user / third party employee trying to access another person's KTP
        $intruder = $this->createUser(['role' => 'employee']);
        $this->actingAs($intruder);

        $resKtp = $this->get(route('onboarding.stream_file', [$case->id, 'ktp']));
        $resKtp->assertStatus(403);

        $resLiveness = $this->get(route('onboarding.stream_file', [$case->id, 'liveness']));
        $resLiveness->assertStatus(403);

        $resEvidence = $this->get(route('onboarding.stream_file', [$case->id, 'evidence_offer']));
        $resEvidence->assertStatus(403);
    }

    public function test_invalid_stream_file_type_returns_404(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();

        $this->post(route('onboarding.store'), [
            'name' => 'Invalid Type Test',
            'email' => 'invalid.type@example.com',
            'branch_id' => $branch->id,
            'position_id' => $position->id,
            'salary_offered' => 7000000,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'offer_valid_until' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $case = OnboardingCase::first();

        $this->actingAs($admin);
        $res = $this->get(route('onboarding.stream_file', [$case->id, 'credit_card_or_passport']));
        $res->assertStatus(404);
    }

    // ==========================================
    // 8. ORGANIZATION CHART UNHAPPY PATHS
    // ==========================================

    public function test_org_chart_reassignment_fails_on_self_parenting(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();

        $user = $this->createUser(['branch_id' => $branch->id, 'employment_status' => 'active']);

        $res = $this->post(route('org-chart.reassign'), [
            'user_id' => $user->id,
            'supervisor_id' => $user->id, // self-parenting!
            'effective_from' => now()->format('Y-m-d'),
            'reason' => 'Mencoba menjadi atasan diri sendiri',
        ]);

        $res->assertStatus(422);
    }

    public function test_org_chart_reassignment_fails_on_two_node_cycle(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();

        // A is supervisor of B
        $userA = $this->createUser(['name' => 'Manager A', 'branch_id' => $branch->id, 'employment_status' => 'active']);
        $userB = $this->createUser(['name' => 'Staff B', 'branch_id' => $branch->id, 'manager_id' => $userA->id, 'employment_status' => 'active']);

        // Attempt: Make A report to B (creating A -> B -> A cycle)
        $res = $this->post(route('org-chart.reassign'), [
            'user_id' => $userA->id,
            'supervisor_id' => $userB->id,
            'effective_from' => now()->format('Y-m-d'),
            'reason' => 'Membalikkan hierarki langsung',
        ]);

        $res->assertStatus(422);
    }

    public function test_org_chart_reassignment_fails_on_multi_node_cycle(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();

        // Chain: CEO -> Director -> Manager -> Lead -> Staff
        $ceo = $this->createUser(['name' => 'CEO', 'branch_id' => $branch->id, 'employment_status' => 'active']);
        $director = $this->createUser(['name' => 'Director', 'branch_id' => $branch->id, 'manager_id' => $ceo->id, 'employment_status' => 'active']);
        $manager = $this->createUser(['name' => 'Manager', 'branch_id' => $branch->id, 'manager_id' => $director->id, 'employment_status' => 'active']);
        $lead = $this->createUser(['name' => 'Lead', 'branch_id' => $branch->id, 'manager_id' => $manager->id, 'employment_status' => 'active']);
        $staff = $this->createUser(['name' => 'Staff', 'branch_id' => $branch->id, 'manager_id' => $lead->id, 'employment_status' => 'active']);

        // Attempt: Make Director report to Staff (creating Director -> Manager -> Lead -> Staff -> Director cycle)
        $res = $this->post(route('org-chart.reassign'), [
            'user_id' => $director->id,
            'supervisor_id' => $staff->id,
            'effective_from' => now()->format('Y-m-d'),
            'reason' => 'Mencoba membuat siklus multi-level',
        ]);

        $res->assertStatus(422);

        // Attempt: Make CEO report to Lead
        $resCeo = $this->post(route('org-chart.reassign'), [
            'user_id' => $ceo->id,
            'supervisor_id' => $lead->id,
            'effective_from' => now()->format('Y-m-d'),
            'reason' => 'CEO melapor ke Team Lead',
        ]);

        $resCeo->assertStatus(422);
    }

    public function test_org_chart_reassignment_fails_when_supervisor_is_inactive_or_candidate(): void
    {
        $admin = $this->actingAsAdmin();
        $branch = $this->createBranch();

        $employee = $this->createUser(['branch_id' => $branch->id, 'employment_status' => 'active']);
        $inactiveUser = $this->createUser(['branch_id' => $branch->id, 'active' => false]);
        $candidateUser = $this->createUser(['branch_id' => $branch->id, 'employment_status' => 'candidate', 'active' => false]);

        // Assigning to inactive user -> 422
        $resInactive = $this->post(route('org-chart.reassign'), [
            'user_id' => $employee->id,
            'supervisor_id' => $inactiveUser->id,
            'effective_from' => now()->format('Y-m-d'),
            'reason' => 'Atasan nonaktif',
        ]);
        $resInactive->assertStatus(422);

        // Assigning to candidate -> 422
        $resCandidate = $this->post(route('org-chart.reassign'), [
            'user_id' => $employee->id,
            'supervisor_id' => $candidateUser->id,
            'effective_from' => now()->format('Y-m-d'),
            'reason' => 'Atasan calon karyawan',
        ]);
        $resCandidate->assertStatus(422);
    }

    // ==========================================
    // 9. MASTER SETTINGS DOCUMENT UNHAPPY PATHS
    // ==========================================

    public function test_save_draft_fails_when_content_is_too_short(): void
    {
        $this->actingAsAdmin();

        $res = $this->post(route('master-settings.documents.draft', 'offering_letter'), [
            'content' => 'terlalu pendek', // < 20 chars
        ]);

        $res->assertSessionHasErrors(['content']);
    }

    public function test_submit_review_fails_on_non_draft_version(): void
    {
        $this->actingAsAdmin();

        $template = DocumentTemplate::where('type', 'offering_letter')->first();
        $activeVersion = $template->activeVersion; // status: published

        // Attempting to submit review for an already published version -> 422
        $res = $this->post(route('master-settings.documents.submit_review', $activeVersion->id));
        $res->assertStatus(422);
    }

    public function test_publish_fails_if_mandatory_variables_are_missing(): void
    {
        $this->actingAsAdmin();

        $template = DocumentTemplate::where('type', 'offering_letter')->first();

        // Create draft missing {candidate_name}
        $draft = DocumentTemplateVersion::create([
            'document_template_id' => $template->id,
            'version_number' => 99,
            'status' => 'approved',
            'content' => 'Naskah resmi tanpa menyebut nama kandidat sama sekali di dalam dokumen.',
            'required_variables' => ['candidate_name', 'position_title'],
        ]);

        $resPublish = $this->post(route('master-settings.documents.publish', $draft->id));
        $resPublish->assertSessionHasErrors(['template']);

        $draft->refresh();
        $this->assertNotEquals('published', $draft->status);
    }

    // ==========================================
    // 10. EMPLOYMENT CONTRACT (PKWT) UNHAPPY PATHS
    // ==========================================

    public function test_contract_creation_fails_with_duplicate_document_number(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch();
        $position = $this->createPosition();
        $employee = $this->createUser(['branch_id' => $branch->id, 'position_id' => $position->id, 'employment_status' => 'active']);

        $docNumber = 'PKWT/DUPLICATE/001';

        // First contract creation
        $this->post(route('contracts.store'), [
            'user_id' => $employee->id,
            'document_number' => $docNumber,
            'start_date' => now()->format('Y-m-d'),
        ]);

        // Duplicate creation attempt
        $resDup = $this->post(route('contracts.store'), [
            'user_id' => $employee->id,
            'document_number' => $docNumber,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $resDup->assertSessionHasErrors(['document_number']);
    }

    public function test_contract_creation_fails_for_non_existent_user(): void
    {
        $this->actingAsAdmin();

        $res = $this->post(route('contracts.store'), [
            'user_id' => 999999,
            'document_number' => 'PKWT/INVALID_USER/001',
            'start_date' => now()->format('Y-m-d'),
        ]);

        $res->assertSessionHasErrors(['user_id']);
    }
}
