<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoanOtherUse extends Model
{
    // a row here is written once
    public const UPDATED_AT = null;

    protected $fillable = ['uuid', 'loan_id', 'amount_minor', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn(self $row) => $row->uuid ??= (string) Str::uuid7());
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
