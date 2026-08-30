<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class JournalEntry extends Model
{
    use HasFactory;

    // a row here is written once, so there is nothing to stamp on update
    public const UPDATED_AT = null;

    protected $fillable = [
        'transaction_id',
        'narration',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('A journal entry cannot be changed once it is written.');
        });

        static::deleting(function () {
            throw new RuntimeException('A journal entry cannot be deleted.');
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_number');
    }

    public function totalDebitMinor(): int
    {
        return (int) $this->lines()->sum('debit_minor');
    }

    public function totalCreditMinor(): int
    {
        return (int) $this->lines()->sum('credit_minor');
    }

    // the promise the whole product rests on
    public function isBalanced(): bool
    {
        $debit = $this->totalDebitMinor();

        // an empty entry adds up to zero on both sides, which is not a real entry
        if ($debit === 0) {
            return false;
        }

        return $debit === $this->totalCreditMinor();
    }

    // the posting service calls this inside a database transaction, so a bad entry never survives
    public function assertBalanced(): void
    {
        if ($this->isBalanced()) {
            return;
        }

        throw new RuntimeException(
            "Journal entry {$this->id} does not balance. "
                . "Debits {$this->totalDebitMinor()}, credits {$this->totalCreditMinor()}."
        );
    }
}
