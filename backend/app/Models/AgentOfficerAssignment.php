<?php

namespace App\Models;

use App\Enums\OfficerRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_id',
    'officer_id',
    'role',
])]
class AgentOfficerAssignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => OfficerRole::class,
        ];
    }

    public function scopeForRole(Builder $query, OfficerRole $role): Builder
    {
        return $query->where('role', $role);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }
}
