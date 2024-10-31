<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Station extends Model
{
    protected $fillable = [
        'id',
        'code',
        'name',
        'routes',
        'opposite_station_id',
        'is_direction_to_center',
    ];

    protected $casts = [
        'routes' => 'array',
        'is_direction_to_center' => 'boolean',
    ];

    public $incrementing = false;

    public function oppositeStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'opposite_station_id');
    }

   
}
