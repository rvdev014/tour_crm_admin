<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int $country_id
 * @property int $city_id
 * @property int $price_per_person
 * @property string $inn
 * @property string $phone
 * @property array $media
 * @property string $created_at
 * @property string $updated_at
 *
 * @property City $city
 * @property Country $country
 * @property Collection<ManualPhone> $phones
 */
class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'country_id',
        'city_id',
        'price_per_person',
        'company_name',
        'inn',
        'phone',
        'media',
    ];

    protected $casts = [
        'media' => 'array',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function phones(): MorphMany
    {
        return $this->morphMany(ManualPhone::class, 'manual');
    }
}
