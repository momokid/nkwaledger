<?php

namespace App\Models;

use App\Enums\KioskReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'kiosk_id',
    'farmer_profile_id',
    'reason',
    'details',
    'status',
    'supplier_due_at',
    'admin_due_at',
    'supplier_answer',
    'supplier_answered_at',
    'admin_window_alerted_at',
    'resolved_by',
    'resolved_at',
])]
class KioskReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => KioskReportStatus::Open->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (KioskReport $report) {
            $report->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => KioskReportStatus::class,
            'supplier_due_at' => 'datetime',
            'admin_due_at' => 'datetime',
            'supplier_answered_at' => 'datetime',
            'admin_window_alerted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // urls carry the uuid, so a report cannot be found by counting upward
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpenForAction(): bool
    {
        return ! in_array($this->status, [KioskReportStatus::Resolved, KioskReportStatus::Suspended], true);
    }
}
