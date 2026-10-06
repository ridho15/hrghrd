<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Services\AttendanceService;
use Tests\TestCase;

class DynamicQrTest extends TestCase
{
    public function test_dynamic_qr_generation_and_negative_mismatch(): void
    {
        $branchA = (object) ['id' => 1, 'qr_secret' => 'secret-branch-a'];
        $branchB = (object) ['id' => 2, 'qr_secret' => 'secret-branch-a'];
        $branchDiffSecret = (object) ['id' => 1, 'qr_secret' => 'secret-branch-diff'];

        $slotCurrent = intdiv(time(), 30);
        $slotExpired = $slotCurrent - 2; // 60 seconds ago

        $qrCurrentA = AttendanceService::qr($branchA, $slotCurrent);
        $qrExpiredA = AttendanceService::qr($branchA, $slotExpired);
        $qrCurrentB = AttendanceService::qr($branchB, $slotCurrent);
        $qrDiffSecret = AttendanceService::qr($branchDiffSecret, $slotCurrent);

        // QR format check: 8 uppercase hex characters
        $this->assertSame(8, strlen($qrCurrentA));
        $this->assertMatchesRegularExpression('/^[A-F0-9]{8}$/', $qrCurrentA);

        // Negative check: Expired slot must NOT match current QR
        $this->assertFalse(hash_equals($qrCurrentA, $qrExpiredA));

        // Negative check: Different branch ID with same slot must NOT match
        $this->assertFalse(hash_equals($qrCurrentA, $qrCurrentB));

        // Negative check: Different secret with same branch ID and slot must NOT match
        $this->assertFalse(hash_equals($qrCurrentA, $qrDiffSecret));

        // Negative check: Altered/tampered QR string
        $this->assertFalse(hash_equals($qrCurrentA, 'INVALID8'));
    }
}
