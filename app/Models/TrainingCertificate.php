<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'training_assignment_id',
    'training_attempt_id',
    'employee_id',
    'reference_number',
    'employee_name',
    'training_name',
    'company_name',
    'completed_on',
    'expires_on',
    'signature_width',
    'signature_height',
    'signature_strokes',
])]
class TrainingCertificate extends Model
{
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TrainingAssignment::class, 'training_assignment_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TrainingAttempt::class, 'training_attempt_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return array{width: int, height: int, strokes: list<list<array{x: float, y: float}>>}|null
     */
    public function signatureDrawing(): ?array
    {
        $strokes = $this->signature_strokes;
        if (! is_array($strokes) || $strokes === []) {
            return null;
        }

        return [
            'width' => max(1, (int) ($this->signature_width ?: 320)),
            'height' => max(1, (int) ($this->signature_height ?: 160)),
            'strokes' => $strokes,
        ];
    }

    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
            'expires_on' => 'date',
            'signature_width' => 'integer',
            'signature_height' => 'integer',
            'signature_strokes' => 'array',
        ];
    }
}
