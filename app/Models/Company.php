<?php

namespace App\Models;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'slug', 'app_key', 'database_name', 'tenant_connection', 'is_active', 'is_platform_controller'])]
class Company extends Model
{
    /**
     * @var string
     */
    protected $connection = 'master';

    public function isPlatformController(): bool
    {
        return (bool) $this->is_platform_controller;
    }

    public function hasTenantDatabase(): bool
    {
        return ! $this->isPlatformController()
            && is_string($this->tenant_connection)
            && $this->tenant_connection !== '';
    }

    /**
     * Use this when running queries against the company's own database schema.
     */
    public function tenantDb(): Connection
    {
        abort_unless($this->hasTenantDatabase(), 500, 'This organization has no tenant database.');

        return DB::connection($this->tenant_connection);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeTenantOrganizations($query)
    {
        return $query->where('is_platform_controller', false);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopePlatformController($query)
    {
        return $query->where('is_platform_controller', true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_platform_controller' => 'boolean',
        ];
    }
}
