<?php

namespace Tests\Unit;

use App\Support\Rules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RulesTest extends TestCase
{
    use RefreshDatabase;
    public function test_late_units_boundary_conditions(): void
    {
        // 0 to 15 minutes should be within grace period (0 units)
        $this->assertSame(0, Rules::lateUnits(0));
        $this->assertSame(0, Rules::lateUnits(5));
        $this->assertSame(0, Rules::lateUnits(15));

        // Negative minutes should return 0 units
        $this->assertSame(0, Rules::lateUnits(-1));
        $this->assertSame(0, Rules::lateUnits(-30));

        // 16 to 30 minutes should be 1 unit
        $this->assertSame(1, Rules::lateUnits(16));
        $this->assertSame(1, Rules::lateUnits(20));
        $this->assertSame(1, Rules::lateUnits(30));

        // 31 to 45 minutes should be 2 units
        $this->assertSame(2, Rules::lateUnits(31));
        $this->assertSame(2, Rules::lateUnits(40));
        $this->assertSame(2, Rules::lateUnits(45));

        // 46 to 60 minutes should be 3 units
        $this->assertSame(3, Rules::lateUnits(46));
        $this->assertSame(3, Rules::lateUnits(59));
        $this->assertSame(3, Rules::lateUnits(60));

        // 61 minutes should be 4 units
        $this->assertSame(4, Rules::lateUnits(61));

        // Extremely large duration
        $this->assertSame(95, Rules::lateUnits(1440));
    }

    public function test_rules_defaults_fallback_when_key_not_in_settings(): void
    {
        $this->assertSame('15', Rules::get('late_grace_minutes'));
        $this->assertSame(15, Rules::int('late_grace_minutes'));
        $this->assertSame(10000, Rules::int('late_penalty_per_unit'));
        $this->assertSame(60, Rules::int('late_reject_minutes'));
        $this->assertSame(90, Rules::int('overtime_threshold_minutes'));
        $this->assertSame(7, Rules::int('leave_notice_days'));
        $this->assertSame(2, Rules::int('sick_paid_days_per_case'));
    }
}
