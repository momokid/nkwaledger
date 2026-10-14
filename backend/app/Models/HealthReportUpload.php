<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthReportUpload extends Model
{
    protected $fillable = ['uuid', 'disease_report_id', 'user_id', 'kind', 'total_bytes', 'sha256', 'part_path', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'total_bytes' => 'integer'];
    }

    public function diseaseReport(): BelongsTo
    {
        return $this->belongsTo(DiseaseReport::class)->withoutGlobalScopes();
    }
}
