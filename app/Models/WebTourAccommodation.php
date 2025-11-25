<?php

namespace App\Models;

use App\Traits\HasLocaleFields;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $web_tour_id
 * @property string $header
 * @property string $header_ru
 * @property string|null $header_en
 * @property string|null $description
 * @property string|null $description_ru
 * @property string|null $description_en
 * @property int|null $days
 * @property int|null $city_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read City $city
 * @property-read Collection<Hotel> $hotels
 * @property-read WebTour $webTour
 */
class WebTourAccommodation extends Model
{
    use HasFactory, HasLocaleFields;

    protected $guarded = ['id'];

    public function getHeaderAttribute(): string
    {
        return $this->getLocaleValue('header');
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->getLocaleValue('description');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function webTour(): BelongsTo
    {
        return $this->belongsTo(WebTour::class);
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class, 'web_tour_accommodation_hotels');
    }
}
