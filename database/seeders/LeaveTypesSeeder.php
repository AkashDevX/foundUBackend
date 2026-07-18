<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LeaveTypesSeeder extends Seeder
{
    /**
     * Default leave types offered by every organization (tenant DB).
     * Keyed by the stable `code` that employee leave records reference.
     *
     * @var list<array{code: string, name: string, is_paid: bool, default_annual_hours: float|null, requires_approval: bool, sort_order: int}>
     */
    private const TYPES = [
        [
            'code' => 'annual',
            'name' => 'Annual leave',
            'is_paid' => true,
            'default_annual_hours' => 152.00,
            'requires_approval' => true,
            'sort_order' => 1,
        ],
        [
            'code' => 'sick',
            'name' => 'Sick leave',
            'is_paid' => true,
            'default_annual_hours' => 76.00,
            'requires_approval' => false,
            'sort_order' => 2,
        ],
        [
            'code' => 'unpaid',
            'name' => 'Unpaid leave',
            'is_paid' => false,
            'default_annual_hours' => null,
            'requires_approval' => true,
            'sort_order' => 3,
        ],
    ];

    public function run(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('leave_types')) {
                continue;
            }

            foreach (self::TYPES as $type) {
                LeaveType::on($connection)->updateOrCreate(
                    ['code' => $type['code']],
                    [
                        'name' => $type['name'],
                        'is_paid' => $type['is_paid'],
                        'default_annual_hours' => $type['default_annual_hours'],
                        'requires_approval' => $type['requires_approval'],
                        'is_active' => true,
                        'sort_order' => $type['sort_order'],
                        'created_by' => 'system',
                    ]
                );
            }
        }
    }
}
