<?php

namespace App\Models;

use App\Enums\TourStatus;
use App\Enums\WebTourStatus;
use App\Traits\HasLocaleFields;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property string $name_ru
 * @property string|null $name_en
 * @property string $start_date
 * @property string $end_date
 * @property string|null $deadline
 * @property TourStatus $status
 * @property string|null $description
 * @property string|null $description_ru
 * @property string|null $description_en
 * @property string|null $photo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Collection<WebTourAccommodation> $accommodations
 * @property-read Collection<WebTourDay> $days
 * @property-read Collection<WebTourPackage> $packages
 * @property-read Collection<WebTourPackage> $packagesIncluded
 * @property-read Collection<WebTourPackage> $packagesNotIncluded
 * @property-read Collection<WebTourPrice> $prices
 * @property-read WebTourPrice $currentPrice
 * @property-read Collection<WebTour> $similarTours
 * @property-read Collection<SimilarTour> $similarToursRel
 */
class WebTour extends Model
{
    use HasFactory, HasLocaleFields;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'deadline' => 'datetime',
        'status' => WebTourStatus::class,
    ];

    public function getNameAttribute(): string
    {
        return $this->getLocaleValue('name');
    }

    public function getDescriptionAttribute(): string
    {
        return $this->getLocaleValue('description');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'web_tour_packages');
    }

    public function packagesIncluded(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'web_tour_packages')
            ->wherePivot('is_include', true);
    }

    public function packagesNotIncluded(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'web_tour_packages')
            ->wherePivot('is_include', false);
    }

    public function days(): HasMany
    {
        return $this->hasMany(WebTourDay::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(WebTourPrice::class);
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(WebTourPrice::class)
            ->where('deadline', '>=', now())
            ->orderBy('price');
    }

    public function accommodations(): HasMany
    {
        return $this->hasMany(WebTourAccommodation::class);
    }

    public function similarToursRel(): HasMany
    {
        return $this->hasMany(SimilarTour::class, 'tour_id');
    }

    public function similarTours(): BelongsToMany
    {
        return $this->belongsToMany(WebTour::class, 'similar_tours', 'web_tour_id', 'similar_web_tour_id');
    }
}
