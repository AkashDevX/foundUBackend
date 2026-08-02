<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'platform_company_id',
    'company_name',
    'industry',
    'industry_other',
    'employee_band',
    'employee_band_other',
    'postcode',
    'contact_full_name',
    'contact_email',
    'contact_telephone',
    'status',
    'source',
])]
class OrganizationRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    protected $connection = 'master';

    /**
     * @return BelongsTo<Company, $this>
     */
    public function platformCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'platform_company_id');
    }

    public function industryLabel(): string
    {
        if ($this->industry === 'Other' && filled($this->industry_other)) {
            return 'Other ('.$this->industry_other.')';
        }

        return $this->industry;
    }

    public function employeeBandLabel(): string
    {
        if ($this->employee_band === 'Other' && filled($this->employee_band_other)) {
            return 'Other ('.$this->employee_band_other.')';
        }

        return $this->employee_band;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
