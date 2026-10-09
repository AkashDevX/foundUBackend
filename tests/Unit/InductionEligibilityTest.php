<?php

namespace Tests\Unit;

use App\Support\InductionEligibility;
use PHPUnit\Framework\TestCase;

class InductionEligibilityTest extends TestCase
{
    public function test_only_required_status_blocks_shifts_and_clock_in(): void
    {
        $this->assertTrue(InductionEligibility::blocksStatus(InductionEligibility::STATUS_REQUIRED));
        $this->assertFalse(InductionEligibility::blocksStatus(InductionEligibility::STATUS_PASSED));
        $this->assertFalse(InductionEligibility::blocksStatus(InductionEligibility::STATUS_OVERRIDDEN));
        $this->assertFalse(InductionEligibility::blocksStatus(InductionEligibility::STATUS_NOT_REQUIRED));
        $this->assertFalse(InductionEligibility::blocksStatus(null));
    }

    public function test_failed_attempts_can_be_repeated_until_the_configured_limit(): void
    {
        $this->assertTrue(InductionEligibility::allowsAnotherAttempt(false, 1, 3));
        $this->assertTrue(InductionEligibility::allowsAnotherAttempt(false, 2, 3));
        $this->assertFalse(InductionEligibility::allowsAnotherAttempt(false, 3, 3));
        $this->assertFalse(InductionEligibility::allowsAnotherAttempt(true, 1, 3));
    }

    public function test_twenty_five_question_induction_passes_at_ninety_two_percent(): void
    {
        $this->assertTrue(InductionEligibility::percentPassed(23, 25, 92));
        $this->assertTrue(InductionEligibility::percentPassed(24, 25, 92));
        $this->assertFalse(InductionEligibility::percentPassed(22, 25, 92));
        $this->assertFalse(InductionEligibility::percentPassed(0, 0, 92));
    }
};
