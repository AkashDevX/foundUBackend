<?php

namespace App\Models;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'slug', 'app_key', 'database_name', 'tenant_connection', 'is_active'])]
class Company extends Model
{
    /**
     * @var string
     */
    protected $connection = 'master';

    /**
     * Use this when running queries against the company's own database schema.
     */
    public function tenantDb(): Connection
    {
        return DB::connection($this->tenant_connection);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
