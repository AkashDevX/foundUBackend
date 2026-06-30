<?php

namespace Tests\Unit;

use App\Models\Company;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyPlatformControllerTest extends TestCase
{
    #[Test]
    public function platform_controller_company_has_no_tenant_database(): void
    {
        $company = new Company([
            'is_platform_controller' => true,
            'tenant_connection' => null,
            'database_name' => null,
        ]);

        $this->assertTrue($company->isPlatformController());
        $this->assertFalse($company->hasTenantDatabase());
    }

    #[Test]
    public function tenant_company_requires_connection_name(): void
    {
        $company = new Company([
            'is_platform_controller' => false,
            'tenant_connection' => 'tenant_demo',
            'database_name' => 'demodb',
        ]);

        $this->assertFalse($company->isPlatformController());
        $this->assertTrue($company->hasTenantDatabase());
    }
}
