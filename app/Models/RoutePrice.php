<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $route_id
 * @property int $transport_class_id
 * @property float $price
 *
 * @property Route $route
 * @property TransportClass $transportClass
 */
class RoutePrice extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function transportClass(): BelongsTo
    {
        return $this->belongsTo(TransportClass::class);
    }
}
