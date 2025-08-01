<?php

namespace App\Models;

use App\Enums\TourStatus;
use App\Enums\WebTourStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property string $name_ru
 * @property string|null $name_en
 * @property string $start_date
 * @property string $end_date
 * @property string|null $deadline
 * @property TourStatus $status
 * @property string|null $description_ru
 * @property string|null $description_en
 * @property string|null $photo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Collection<TourAccommodation> $accommodations
 * @property-read Collection<TourDay> $days
 * @property-read Collection<TourPackage> $packages
 * @property-read Collection<TourPackage> $packagesIncluded
 * @property-read Collection<TourPackage> $packagesNotIncluded
 * @property-read Collection<TourPrice> $prices
 * @property-read Collection<Tour> $similarTours
 * @property-read Collection<SimilarTour> $similarToursRel
 */
class WebTour extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'deadline' => 'datetime',
        'status' => WebTourStatus::class,
    ];

    public function name(): Attribute
    {
        return new Attribute(function ($value) {
            return $this['name_' . app()->getLocale()];
        });
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'tour_packages');
    }

    public function packagesIncluded(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'tour_packages')
            ->wherePivot('is_include', true);
    }

    public function packagesNotIncluded(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'tour_packages')
            ->wherePivot('is_include', false);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TourDay::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TourPrice::class);
    }

    public function accommodations(): HasMany
    {
        return $this->hasMany(TourAccommodation::class);
    }

    public function similarToursRel(): HasMany
    {
        return $this->hasMany(SimilarTour::class, 'tour_id');
    }

    public function similarTours(): BelongsToMany
    {
        return $this->belongsToMany(WebTour::class, 'similar_tours', 'tour_id', 'similar_tour_id');
    }
}
