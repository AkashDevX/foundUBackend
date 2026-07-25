<?php

namespace Tests\Unit;

use App\Support\ShiftBreaks;
use PHPUnit\Framework\TestCase;

class ShiftBreaksTest extends TestCase
{
    public function test_normalize_keeps_paid_and_unpaid_breaks(): void
    {
        $breaks = ShiftBreaks::normalize([
            ['label' => 'Morning tea', 'minutes' => 15, 'paid' => '1'],
            ['label' => 'Lunch', 'minutes' => 30, 'paid' => '0'],
            ['label' => '', 'minutes' => '', 'paid' => '0'],
        ]);

        $this->assertSame([
            ['label' => 'Morning tea', 'minutes' => 15, 'paid' => true],
            ['label' => 'Lunch', 'minutes' => 30, 'paid' => false],
        ], $breaks);
    }

    public function test_summary_and_unpaid_total(): void
    {
        $breaks = [
            ['label' => 'Tea', 'minutes' => 15, 'paid' => true],
            ['label' => 'Lunch', 'minutes' => 30, 'paid' => false],
            ['label' => 'Afternoon tea', 'minutes' => 15, 'paid' => true],
        ];

        $this->assertSame(
            '15m paid Tea, 30m unpaid Lunch, 15m paid Afternoon tea',
            ShiftBreaks::summaryFrom($breaks)
        );
        $this->assertSame(30, ShiftBreaks::unpaidMinutesTotal($breaks));
        $this->assertSame(30, ShiftBreaks::paidMinutesTotal($breaks));
    }

    public function test_break_pay_adjustment_keeps_paid_and_deducts_unpaid_plus_excess(): void
    {
        $breaks = [
            ['label' => 'Tea', 'minutes' => 15, 'paid' => true],
            ['label' => 'Lunch', 'minutes' => 30, 'paid' => false],
        ];

        // 45m taken exactly matches allocation: keep 15 paid, deduct 30 unpaid.
        $exact = ShiftBreaks::breakPayAdjustment(45 * 60, $breaks);
        $this->assertSame(15 * 60, $exact['paid_kept_seconds']);
        $this->assertSame(30 * 60, $exact['unpaid_taken_seconds']);
        $this->assertSame(0, $exact['excess_seconds']);
        $this->assertSame(30 * 60, $exact['deducted_seconds']);

        // 60m taken: 15 paid kept, 30 unpaid + 15 excess deducted.
        $over = ShiftBreaks::breakPayAdjustment(60 * 60, $breaks);
        $this->assertSame(15 * 60, $over['paid_kept_seconds']);
        $this->assertSame(30 * 60, $over['unpaid_taken_seconds']);
        $this->assertSame(15 * 60, $over['excess_seconds']);
        $this->assertSame(45 * 60, $over['deducted_seconds']);
    }
}
