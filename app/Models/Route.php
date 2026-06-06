<?php

namespace App\Models;

use App\Enums\TransportType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $display_name
 * @property Collection<RouteWaypoint> $waypoints
 * @property Collection<RoutePrice> $prices
 */
class Route extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function waypoints(): HasMany
    {
        return $this->hasMany(RouteWaypoint::class)->orderBy('order');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RoutePrice::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->waypoints->map(fn($w) => $w->city?->name ?? '?')->join(' → ');
    }

    public function getPriceForTransportClass(int $transportClassId): ?float
    {
        return $this->prices->first(fn($p) => (int)$p->transport_class_id === $transportClassId)?->price;
    }
}
