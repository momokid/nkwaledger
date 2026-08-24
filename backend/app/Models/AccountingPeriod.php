<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

#[Fillable([
    'name',
    'starts_on',
    'ends_on',
    'status',
])]
class AccountingPeriod extends Model
{
    use HasFactory, SoftDeletes;

    public const OPEN = 'open';
    public const CLOSED = 'closed';

    // matches the column default, so a fresh instance reads the same before and after saving
    protected $attributes = [
        'status' => self::OPEN,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $period) {
            // a period ending before it starts covers no days at all
            if ($period->ends_on < $period->starts_on) {
                throw new RuntimeException('A period cannot end before it starts.');
            }

            // two periods covering one day would leave a transaction with no single home
            if ($period->overlapsAnother()) {
                throw new RuntimeException('That range overlaps a period that already exists.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'starts_on'   => 'date',
            'ends_on'     => 'date',
            'closed_at'   => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    // freezing the period is what stops anything else posting into it
    public function close(User $admin): void
    {
        if ($this->status === self::CLOSED) {
            throw new RuntimeException('That period is already closed.');
        }

        $this->forceFill([
            'status'    => self::CLOSED,
            'closed_at' => now(),
            'closed_by' => $admin->id,
        ])->save();
    }

    // the closing stays on the row, so a reopened period never looks untouched
    public function reopen(User $admin): void
    {
        if ($this->status === self::OPEN) {
            throw new RuntimeException('That period is already open.');
        }

        $this->forceFill([
            'status'      => self::OPEN,
            'reopened_at' => now(),
            'reopened_by' => $admin->id,
        ])->save();
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function covers(string $date): bool
    {
        $day = \Illuminate\Support\Carbon::parse($date)->startOfDay();

        return $day->betweenIncluded($this->starts_on, $this->ends_on);
    }

    // which period a transaction belongs to is decided by its date, never by today
    public static function covering(string $date): ?self
    {
        return static::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    private function overlapsAnother(): bool
    {
        return static::query()
            ->when($this->exists, fn($query) => $query->whereKeyNot($this->getKey()))
            ->whereDate('starts_on', '<=', $this->ends_on)
            ->whereDate('ends_on', '>=', $this->starts_on)
            ->exists();
    }
}
