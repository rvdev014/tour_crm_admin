<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $name
 * @property int $country_id
 * @property int $city_id
 * @property float $price_per_person
 *
 * @property Country $country
 * @property City $city
 */
class Show extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_id',
        'city_id',
        'price_per_person',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
