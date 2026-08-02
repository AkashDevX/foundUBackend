<?php

namespace Tests\Unit;

use App\Models\OrganizationRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizationRequestTest extends TestCase
{
    #[Test]
    public function industry_label_includes_other_detail_when_present(): void
    {
        $request = new OrganizationRequest([
            'industry' => 'Other',
            'industry_other' => 'Custom widgets',
        ]);

        $this->assertSame('Other (Custom widgets)', $request->industryLabel());
    }

    #[Test]
    public function employee_band_label_includes_other_detail_when_present(): void
    {
        $request = new OrganizationRequest([
            'employee_band' => 'Other',
            'employee_band_other' => '75',
        ]);

        $this->assertSame('Other (75)', $request->employeeBandLabel());
    }

    #[Test]
    public function labels_use_picklist_value_when_not_other(): void
    {
        $request = new OrganizationRequest([
            'industry' => 'Healthcare',
            'employee_band' => '11–50',
        ]);

        $this->assertSame('Healthcare', $request->industryLabel());
        $this->assertSame('11–50', $request->employeeBandLabel());
    }
}
