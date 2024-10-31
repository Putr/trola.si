<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Collection;

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

    public function getArrivalsAttribute(): Collection
    {
        $api = app(LppApiService::class);
        $arrivals = $api->getStationArrivals($this->code);

        if (!$arrivals) {
            return collect();
        }

        return collect($arrivals)->map(function ($arrivalData) {
            return Arrival::fromArray($arrivalData);
        });
    }
}
