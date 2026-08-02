<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'company_id',
    'name',
    'email',
    'password',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class OrganizationPortalUser extends Authenticatable
{
    /**
     * @var string
     */
    protected $connection = 'master';

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->email = mb_strtolower($user->email);
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
