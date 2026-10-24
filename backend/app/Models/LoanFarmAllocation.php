<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoanFarmAllocation extends Model
{
    protected $fillable = ['uuid', 'loan_id', 'farm_unit_id', 'amount_minor', 'purpose_template_id', 'purpose_note'];

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

    public function purposeTemplate(): BelongsTo
    {
        return $this->belongsTo(TransactionTemplate::class, 'purpose_template_id');
    }

    public function farmUnit(): BelongsTo
    {
        return $this->belongsTo(FarmUnit::class);
    }
}
