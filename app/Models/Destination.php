<?php

namespace App\Models;

use App\Traits\HasLocaleFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A public-facing "destination" — a country (parent_id null) or a place inside
 * that country (parent_id set), shown on the website's Destinations pages.
 *
 * Deliberately separate from Country/City, which are bare operational lookups
 * used internally by tours, hotels, museums, transfers and expenses.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int|null $city_id
 * @property string $slug
 * @property string $title_ru
 * @property string|null $title_en
 * @property string|null $short_description_ru
 * @property string|null $short_description_en
 * @property string|null $description_ru
 * @property string|null $description_en
 * @property string|null $photo
 * @property array|null $photos
 * @property int $order
 * @property bool $is_published
 * @property bool $is_featured
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Destination|null $parent
 * @property-read Collection<Destination> $children
 * @property-read City|null $city
 * @property-read Collection<DestinationSection> $sections
 * @property-read Collection<WebTour> $pinnedTours
 */
class Destination extends Model
{
    use HasFactory, HasLocaleFields;

    protected $guarded = ['id'];

    protected $casts = [
        'photos' => 'array',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function getTitleAttribute(): ?string
    {
        return $this->getLocaleValue('title');
    }

    public function getShortDescriptionAttribute(): ?string
    {
        return $this->getLocaleValue('short_description');
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->getLocaleValue('description');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order')->orderBy('title_ru');
    }

    public function publishedChildren(): HasMany
    {
        return $this->children()->where('is_published', true);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(DestinationSection::class)->orderBy('order')->orderBy('id');
    }

    public function pinnedTours(): BelongsToMany
    {
        return $this->belongsToMany(WebTour::class, 'destination_web_tour');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeCountries(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopePlaces(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Tours that belong to this destination: manually pinned tours, plus (for
     * a country) every tour whose itinerary visits one of its published
     * places, or (for a place) every tour whose itinerary visits its linked city.
     */
    public function toursQuery(): Builder
    {
        $cityIds = $this->parent_id === null
            ? static::query()->where('parent_id', $this->id)->whereNotNull('city_id')->pluck('city_id')
            : collect([$this->city_id])->filter();

        return WebTour::query()
            ->where(function (Builder $q) use ($cityIds) {
                $q->whereHas('pinnedDestinations', fn (Builder $sub) => $sub->where('destinations.id', $this->id));

                if ($cityIds->isNotEmpty()) {
                    $q->orWhereHas('days', fn (Builder $sub) => $sub->whereIn('city_id', $cityIds));
                }
            })
            ->with(['days.facilities', 'currentPrice', 'categories']);
    }
}
