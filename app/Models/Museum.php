<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $inn
 * @property int $country_id
 * @property int $city_id
 * @property float $price_per_person
 * @property string $created_at
 * @property string $updated_at
 * @property string $contract
 *
 * @property Country $country
 * @property City $city
 * @property Collection<MuseumItem> $children
 */
class Museum extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'inn',
        'country_id',
        'city_id',
        'price_per_person',
        'contract',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(MuseumItem::class);
    }
}
