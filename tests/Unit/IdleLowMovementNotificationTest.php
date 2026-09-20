<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\TimeClockIdleAlert;
use App\Support\AdminDashboardNotifications;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class IdleLowMovementNotificationTest extends TestCase
{
    public function test_idle_low_movement_items_include_open_alerts(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-idle-1',
            'full_legal_name' => 'Idle Worker',
            'email' => 'idle@example.com',
        ]);
        $employee->id = 91;

        $alert = new TimeClockIdleAlert([
            'employee_id' => 91,
            'clock_in_entry_id' => 501,
            'idle_minutes' => 32,
            'status' => TimeClockIdleAlert::STATUS_OPEN,
            'detected_at' => now('UTC'),
        ]);
        $alert->id = 7;
        $alert->detected_at = now('UTC');

        $method = new ReflectionMethod(AdminDashboardNotifications::class, 'idleLowMovementItems');
        $method->setAccessible(true);

        $items = $method->invoke(
            null,
            new Collection([$employee]),
            new Collection([$alert]),
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
        );

        $this->assertCount(1, $items);
        $this->assertStringContainsString('Idle Worker', $items[0]['message']);
        $this->assertStringContainsString('32', $items[0]['message']);
        $this->assertSame('urgent', $items[0]['severity']);
        $this->assertStringContainsString('location-tracking', (string) $items[0]['url']);
    }
}
