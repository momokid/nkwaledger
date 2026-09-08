<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherAdvisoryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'community_id',
        'date',
        'condition',
        'headline',
    ];

    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
    ];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
