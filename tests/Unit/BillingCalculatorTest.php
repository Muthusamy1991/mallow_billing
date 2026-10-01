<?php

namespace Tests\Unit;

use App\DTOs\CycleSegment;
use App\Services\BillingCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class BillingCalculatorTest extends TestCase
{
    private BillingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BillingCalculator;
    }

    public function test_full_cycle_with_no_usage_charges_only_base_price(): void
    {
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-01-31');

        $result = $this->calculator->calculate($start, $end, [
            $this->segment('Starter', '100.0000', 1000, '0.100000', $start, $end, 0),
        ]);

        $this->assertSame('100.0000', $result->baseAmount);
        $this->assertSame('0.0000', $result->overageAmount);
        $this->assertSame('100.0000', $result->totalAmount);
        $this->assertSame(0, $result->totalUnits);
    }

    public function test_usage_exactly_at_included_allowance_has_no_overage(): void
    {
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-01-31');

        $result = $this->calculator->calculate($start, $end, [
            $this->segment('Starter', '100.0000', 1000, '0.100000', $start, $end, 1000),
        ]);

        $this->assertSame('0.0000', $result->overageAmount);
        $this->assertSame('100.0000', $result->totalAmount);
    }

    public function test_one_unit_over_allowance_bills_one_overage_unit(): void
    {
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-01-31');

        $result = $this->calculator->calculate($start, $end, [
            $this->segment('Starter', '100.0000', 1000, '0.100000', $start, $end, 1001),
        ]);

        $this->assertSame('0.1000', $result->overageAmount);
        $this->assertSame('100.1000', $result->totalAmount);
        $this->assertSame(1, $result->lines[1]->overageUnits);
    }

    public function test_mid_cycle_start_prorates_base_and_included_units(): void
    {
        $cycleStart = Carbon::parse('2026-01-01');
        $cycleEnd = Carbon::parse('2026-01-31');
        $start = Carbon::parse('2026-01-10');

        $result = $this->calculator->calculate($cycleStart, $cycleEnd, [
            $this->segment('Starter', '100.0000', 1000, '0.100000', $start, $cycleEnd, 800),
        ]);

        $this->assertSame('70.9677', $result->baseAmount);
        $this->assertSame(709, $result->lines[0]->includedUnits);
        $this->assertSame(91, $result->lines[1]->overageUnits);
        $this->assertSame('9.1000', $result->overageAmount);
        $this->assertSame('80.0677', $result->totalAmount);
    }

    public function test_mid_cycle_plan_change_bills_each_segment_at_its_own_rate(): void
    {
        $cycleStart = Carbon::parse('2026-01-01');
        $cycleEnd = Carbon::parse('2026-01-31');

        $result = $this->calculator->calculate($cycleStart, $cycleEnd, [
            $this->segment('Starter', '100.0000', 1000, '0.100000', $cycleStart, Carbon::parse('2026-01-14'), 600, 1),
            $this->segment('Growth', '200.0000', 5000, '0.050000', Carbon::parse('2026-01-15'), $cycleEnd, 100, 2),
        ]);

        $this->assertSame('45.1612', $result->lines[0]->amount);
        $this->assertSame('14.9000', $result->lines[1]->amount);
        $this->assertSame('109.6774', $result->lines[2]->amount);
        $this->assertSame('0.0000', $result->lines[3]->amount);
        $this->assertSame('154.8386', $result->baseAmount);
        $this->assertSame('14.9000', $result->overageAmount);
        $this->assertSame('169.7386', $result->totalAmount);
        $this->assertSame(700, $result->totalUnits);
    }

    public function test_downgrade_uses_cheaper_rate_only_after_the_change(): void
    {
        $cycleStart = Carbon::parse('2026-03-01');
        $cycleEnd = Carbon::parse('2026-03-31');

        $result = $this->calculator->calculate($cycleStart, $cycleEnd, [
            $this->segment('Growth', '200.0000', 5000, '0.050000', $cycleStart, Carbon::parse('2026-03-20'), 8000, 2),
            $this->segment('Starter', '100.0000', 1000, '0.100000', Carbon::parse('2026-03-21'), $cycleEnd, 2000, 1),
        ]);

        $this->assertSame(20, $result->lines[0]->segmentDays);
        $this->assertSame(11, $result->lines[2]->segmentDays);
        $this->assertTrue(bccomp($result->lines[1]->amount, '0', 4) === 1);
        $this->assertTrue(bccomp($result->lines[3]->amount, '0', 4) === 1);
        $this->assertNotSame($result->lines[1]->amount, $result->lines[3]->amount);
    }

    private function segment(
        string $name,
        string $base,
        int $included,
        string $overage,
        Carbon $start,
        Carbon $end,
        int $usage,
        int $planId = 1,
    ): CycleSegment {
        return new CycleSegment(
            planId: $planId,
            planName: $name,
            basePrice: $base,
            includedUnits: $included,
            overageRate: $overage,
            start: $start,
            end: $end,
            usageUnits: $usage,
        );
    }
}
