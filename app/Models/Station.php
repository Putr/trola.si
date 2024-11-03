<?php

namespace App\Models;

use App\Services\LppApiService;
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

    public function getOppositeStationCodeAttribute(): int
    {
        return $this->oppositeStation->code;
    }

    public function getArrivalsAttribute(): array
    {
        $api = app(LppApiService::class);
        $data = $api->getStationArrivals($this->code);

        if (empty($data['arrivals'])) {
            return collect();
        }

        $out = [];
        foreach ($data['arrivals'] as $arrivalData) {
            $out[$arrivalData['route_name']] = [
                'routeName' => $arrivalData['route_name'],
                'routeDirectionName' => $arrivalData['stations']['arrival'],
                'route_name_numeric' => (int) preg_replace('/[^0-9]/', '', $arrivalData['route_name']),
                'etas' => array_merge($out[$arrivalData['route_name']]['etas'] ?? [], [(int) $arrivalData['eta_min']]),
            ];
        };

        // Process the ETAs for each route
        foreach ($out as $routeName => $data) {
            sort($data['etas']); // Sort ETAs in ascending order
            $out[$routeName]['etaMin'] = $data['etas'][0] <= 9 ? $data['etas'][0] : null; // Get smallest ETA if <= 5min
            $out[$routeName]['laterEtas'] = $data['etas'][0] <= 9 ? array_slice($data['etas'], 1) : $data['etas']; // Get remaining ETAs
        }
        $out = array_values($out);
        usort($out, function ($a, $b) {
            return $a['route_name_numeric'] - $b['route_name_numeric'];
        });

        return $out;
    }
}
