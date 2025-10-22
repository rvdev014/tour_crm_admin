<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $passenger_capacity
 */
class TransportClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price_per_km',
        'photo',
        'passenger_capacity',
        'luggage_capacity',
        'waiting_time_included',
        'meeting_with_place',
        'non_refundable_rate',
        'vehicle_example',
    ];

    protected $casts = [
        'price_per_km' => 'decimal:2',
        'passenger_capacity' => 'integer',
        'luggage_capacity' => 'integer',
        'waiting_time_included' => 'integer',
        'meeting_with_place' => 'boolean',
        'non_refundable_rate' => 'boolean',
    ];
}
