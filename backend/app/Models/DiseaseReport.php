<?php

namespace App\Models;

use App\Enums\ContactMethod;
use App\Enums\DiseaseReportStatus;
use App\Enums\OfficerRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'farm_unit_id',
    'farmer_profile_id',
    'reported_by',
    'category',
    'routed_role',
    'assigned_officer_id',
    'status',
    'photo_path',
    'description',
    'contact_method',
    'response_note',
])]
class DiseaseReport extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'routed_role' => OfficerRole::class,
            'status' => DiseaseReportStatus::class,
            'contact_method' => ContactMethod::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DiseaseReport $report) {
            $report->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isAssigned(): bool
    {
        return $this->assigned_officer_id !== null;
    }

    public function respond(DiseaseReportStatus $status, ContactMethod $contactMethod, string $note): void
    {
        $this->forceFill([
            'status' => $status,
            'contact_method' => $contactMethod,
            'response_note' => $note,
        ])->save();
    }

    public function scopeAwaitingOfficer(Builder $query): Builder
    {
        return $query->whereNull('assigned_officer_id');
    }

    // the farm unit's earlier reports, read directly rather than via a reverse
    // relation on FarmUnit — this is the only place that ever needs the history
    public function scopeHistoryFor(Builder $query, int $farmUnitId, ?int $excludingReportId = null): Builder
    {
        return $query
            ->where('farm_unit_id', $farmUnitId)
            ->when($excludingReportId, fn(Builder $q) => $q->where('id', '!=', $excludingReportId))
            ->orderByDesc('created_at');
    }

    public function farmUnit(): BelongsTo
    {
        return $this->belongsTo(FarmUnit::class);
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }
}
