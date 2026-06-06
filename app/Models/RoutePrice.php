<?php

namespace App\Models;

use App\Enums\TransportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $route_id
 * @property int $transport_type
 * @property float $price
 *
 * @property Route $route
 * @property TransportType $transportTypeEnum
 */
class RoutePrice extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'transport_type' => TransportType::class,
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
