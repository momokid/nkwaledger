<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use RuntimeException;

class JournalLine extends Model
{
    use HasFactory;

    // a row here is written once, so there is nothing to stamp on update
    public const UPDATED_AT = null;

    protected $fillable = [
        'journal_entry_id',
        'ledger_account_id',
        'farmer_profile_id',
        'transaction_date',
        'debit_minor',
        'credit_minor',
        'line_number',
    ];

    protected $attributes = [
        'debit_minor' => 0,
        'credit_minor' => 0,
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
            'line_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $line) {
            $line->guardAgainstNegativeAmounts();
            $line->guardAgainstBothSides();
        });

        static::updating(function () {
            throw new RuntimeException('A journal line cannot be changed once it is written.');
        });

        static::deleting(function () {
            throw new RuntimeException('A journal line cannot be deleted.');
        });
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    // copied from the transaction, so every report reads one table
    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isDebit(): bool
    {
        return (int) $this->debit_minor > 0;
    }

    public function isCredit(): bool
    {
        return (int) $this->credit_minor > 0;
    }

    // whichever side holds the money, this is the figure
    public function amountMinor(): int
    {
        return $this->isDebit() ? (int) $this->debit_minor : (int) $this->credit_minor;
    }

    // the side a line sits on carries the sign, so the figure itself is never negative
    protected function guardAgainstNegativeAmounts(): void
    {
        if ((int) $this->debit_minor < 0 || (int) $this->credit_minor < 0) {
            throw new InvalidArgumentException('A journal line amount cannot be negative.');
        }
    }

    // one side holds the money and the other stays at zero, never both and never neither
    protected function guardAgainstBothSides(): void
    {
        $debit = (int) $this->debit_minor;
        $credit = (int) $this->credit_minor;

        if ($debit > 0 && $credit > 0) {
            throw new InvalidArgumentException('A journal line cannot hold money on both sides.');
        }

        if ($debit === 0 && $credit === 0) {
            throw new InvalidArgumentException('A journal line must hold money on one side.');
        }
    }
}
