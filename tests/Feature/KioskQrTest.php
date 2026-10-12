<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskQrTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memastikan halaman Kiosk QR universal dapat diakses tanpa login.
     */
    public function test_kiosk_qr_universal_page_accessible_without_login(): void
    {
        $branch1 = $this->createBranch(['code' => 'SBY01', 'name' => 'Cabang Surabaya']);
        $branch2 = $this->createBranch(['code' => 'BDG01', 'name' => 'Cabang Bandung']);

        $response = $this->get(route('kiosk.qr'));

        $response->assertOk();
        $response->assertViewIs('kiosk-qr');
        $response->assertSee('Cabang Surabaya');
        $response->assertSee('SBY01');
        $response->assertSee('Cabang Bandung');
        $response->assertSee('BDG01');
        $response->assertSee('Layar Penuh');
    }

    /**
     * Memastikan halaman Kiosk QR khusus cabang dapat diakses via kode cabang tanpa login.
     */
    public function test_kiosk_qr_specific_branch_page_accessible_without_login(): void
    {
        $branch1 = $this->createBranch(['code' => 'SBY01', 'name' => 'Cabang Surabaya']);
        $branch2 = $this->createBranch(['code' => 'BDG01', 'name' => 'Cabang Bandung']);

        $response = $this->get(route('kiosk.qr', 'BDG01'));

        $response->assertOk();
        $response->assertViewIs('kiosk-qr');
        $response->assertSee('Cabang Bandung');
        $response->assertSee('BDG01');
    }

    /**
     * Memastikan endpoint JSON data QR dapat diakses publik dan menghasilkan kode HMAC yang valid.
     */
    public function test_kiosk_qr_code_endpoint_returns_valid_rotating_code(): void
    {
        $branch = $this->createBranch(['code' => 'DEMO01', 'name' => 'Cabang Demo', 'qr_secret' => 'super-secret-key']);
        $expectedCode = AttendanceService::qr($branch);

        $response = $this->get(route('kiosk.qr.code', 'DEMO01'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'code' => $expectedCode,
            'qr_payload' => $expectedCode,
            'branch' => [
                'id' => $branch->id,
                'code' => 'DEMO01',
                'name' => 'Cabang Demo',
            ],
        ]);
        $response->assertJsonStructure([
            'success',
            'code',
            'qr_payload',
            'expires_at',
            'expires_in',
            'branch',
        ]);

        $data = $response->json();
        $this->assertLessThanOrEqual(AttendanceService::QR_INTERVAL_SECONDS, $data['expires_in']);
        $this->assertEquals(0, $data['expires_at'] % AttendanceService::QR_INTERVAL_SECONDS);
    }

    /**
     * Memastikan kode QR berotasi sesuai interval 3 menit (180 detik).
     */
    public function test_kiosk_qr_respects_three_minute_rotation_interval(): void
    {
        $branch = $this->createBranch(['code' => 'ROT01', 'name' => 'Cabang Rotasi']);

        $slotA = 1000;
        $slotB = 1001; // slot berikutnya (+180 detik)
        $codeA = AttendanceService::qr($branch, $slotA);
        $codeB = AttendanceService::qr($branch, $slotB);

        $this->assertNotEquals($codeA, $codeB);
        $this->assertSame(AttendanceService::QR_INTERVAL_SECONDS, 180);
    }

    /**
     * Memastikan endpoint QR menghasilkan 404 untuk cabang yang tidak valid.
     */
    public function test_kiosk_qr_code_returns_404_for_invalid_branch(): void
    {
        $response = $this->get(route('kiosk.qr.code', 'NON_EXISTENT'));

        $response->assertNotFound();
    }

    /**
     * Memastikan Super Admin memiliki tautan Kiosk pada halaman master & detail cabang.
     */
    public function test_super_admin_views_contain_kiosk_links(): void
    {
        $this->actingAsAdmin();
        $branch = $this->createBranch(['code' => 'JKT01', 'name' => 'Cabang Jakarta']);

        $indexResponse = $this->get(route('branches.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee(route('kiosk.qr'));
        $indexResponse->assertSee(route('kiosk.qr', 'JKT01'));

        $showResponse = $this->get(route('branches.show', $branch->id));
        $showResponse->assertOk();
        $showResponse->assertSee(route('kiosk.qr', 'JKT01'));
        $showResponse->assertSee('Buka Kiosk QR');
        $showResponse->assertSee('Salin Link Kiosk');
    }
}
