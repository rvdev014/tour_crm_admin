<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $route_id
 * @property int $city_id
 * @property int $order
 *
 * @property Route $route
 * @property City $city
 */
class RouteWaypoint extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
