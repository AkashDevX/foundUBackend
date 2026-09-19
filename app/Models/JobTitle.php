<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'employment_type', 'award_level', 'color', 'hourly_wage', 'wage_effective_from', 'rate_kind', 'is_active'])]
class JobTitle extends Model
{
    /**
     * Curated accent colors for job titles.
     *
     * @return list<string>
     */
    public static function colorPalette(): array
    {
        return [
            '#d35400', // burnt orange
            '#c0392b', // red
            '#8e44ad', // purple
            '#6c3483', // deep purple
            '#2471a3', // blue
            '#1a5276', // navy
            '#148f77', // teal
            '#1e8449', // green
            '#b7950b', // gold
            '#7d6608', // olive
            '#003d7a', // brand
            '#0052a2', // brand light
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hourly_wage' => 'decimal:2',
            'wage_effective_from' => 'date',
        ];
    }

    public function hasAwardBand(): bool
    {
        return is_string($this->employment_type) && $this->employment_type !== ''
            && is_string($this->award_level) && $this->award_level !== '';
    }

    public function hasHourlyWage(): bool
    {
        return $this->hourly_wage !== null && (float) $this->hourly_wage > 0;
    }

    /**
     * Wage applies on this calendar day. A null effective date means the current wage is already in force.
     */
    public function wageAppliesOn(\Carbon\CarbonInterface $at): bool
    {
        if (! $this->hasHourlyWage()) {
            return false;
        }

        if ($this->wage_effective_from === null) {
            return true;
        }

        $day = $at->copy()->timezone(\App\Support\DisplayTimezone::name())->toDateString();

        return $this->wage_effective_from->toDateString() <= $day;
    }

    /**
     * Employees whose primary job title is this one.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'job_title_id');
    }

    /**
     * All employees assigned this title (primary or secondary).
     */
    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_job_title')
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }

    public function accentColor(): string
    {
        $palette = self::colorPalette();
        $stored = is_string($this->color) ? strtolower(trim($this->color)) : '';

        if ($stored !== '' && preg_match('/^#[0-9a-f]{6}$/', $stored) === 1) {
            return $stored;
        }

        $index = max(0, (int) $this->id - 1);

        return $palette[$index % count($palette)];
    }

    public function formattedWage(): string
    {
        if (! $this->hasHourlyWage()) {
            return '—';
        }

        return number_format((float) $this->hourly_wage, 2);
    }
}
