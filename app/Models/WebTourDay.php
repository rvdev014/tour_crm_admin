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
 * @property int $day_number
 * @property string $place_name
 * @property string $place_name_ru
 * @property string|null $place_name_en
 * @property string $description
 * @property string $description_ru
 * @property string|null $description_en
 * @property string|null $photo
 * @property string|null $date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read WebTour $webTour
 * @property-read Collection<Facility> $facilities
 */
class WebTourDay extends Model
{
    use HasFactory, HasLocaleFields;

    protected $guarded = ['id'];

    public function webTour(): BelongsTo
    {
        return $this->belongsTo(WebTour::class);
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'web_tour_day_facilities');
    }
    
    public function getPlaceNameAttribute(): ?string
    {
        return $this->getLocaleValue('place_name');
    }
    
    public function getDescriptionAttribute(): ?string
    {
        return $this->getLocaleValue('description');
    }
}
