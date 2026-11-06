<?php

namespace App\Models;

use App\Actions\SyncStations;
use Database\Factories\StationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bus stop. Ids and codes come from LPP (`int_id` / `ref_id`); direction and
 * opposite station are inferred by {@see SyncStations}.
 *
 * @property int $id
 * @property string|null $code
 * @property string|null $name
 * @property array<int, mixed>|null $routes
 * @property int|null $opposite_station_id
 * @property bool|null $is_direction_to_center
 */
class Station extends Model
{
    /** @use HasFactory<StationFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'routes',
        'opposite_station_id',
        'is_direction_to_center',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'routes' => 'array',
            'is_direction_to_center' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Station, $this>
     */
    public function oppositeStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'opposite_station_id');
    }
}
