<?php

namespace Tests\Unit;

use App\Support\RegistrationDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationDisplayVisaTest extends TestCase
{
    #[Test]
    #[DataProvider('statusesThatNeedAVisaDocument')]
    public function other_statuses_can_carry_a_visa_document(string $status): void
    {
        $this->assertTrue(RegistrationDisplay::requiresVisaDocument($status));
    }

    #[Test]
    #[DataProvider('statusesWithoutAVisaDocument')]
    public function citizens_and_permanent_residents_do_not_need_a_visa_document(?string $status): void
    {
        $this->assertFalse(RegistrationDisplay::requiresVisaDocument($status));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function statusesThatNeedAVisaDocument(): array
    {
        return [
            ['Temporary Visa - Working'],
            ['Temporary Visa - Student'],
            ['Working Holiday Visa'],
            ['Other'],
            ['  bridging visa  '],
        ];
    }

    /**
     * @return list<array{0: string|null}>
     */
    public static function statusesWithoutAVisaDocument(): array
    {
        return [
            ['Australian Citizen'],
            ['Permanent Resident'],
            [''],
            [null],
        ];
    }
}
