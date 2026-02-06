<?php

namespace App\Models;

use App\Enums\RoomSeasonType;
use App\Traits\HasLocaleFields;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $contract_number
 * @property Carbon $contract_date
 * @property int $country_id
 * @property int $city_id
 * @property int $booking_cancellation_days
 * @property string $inn
 * @property string $company_name
 * @property string $address
 * @property float $rate
 * @property float $website_price
 * @property string $phone
 * @property string $photo
 * @property string $description_en
 * @property string $description_ru
 * @property string $description
 * @property string $comment
 * @property bool $nds_included
 * @property int $tour_sbor
 *
 * @property City $city
 * @property Country $country
 * @property Collection<HotelRoomType> $roomTypes
 * @property Collection<HotelPeriod> $periods
 * @property Collection<HotelPeriod> $currentYearPeriods
 * @property Collection<ManualPhone> $phones
 * @property Collection<Facility> $facilities
 * @property Collection<Attachment> $attachments
 * @property Collection<RecommendedHotel> $recommendedHotels
 * @property Collection<Review> $reviews
 * @property Collection<Review> $activeReviews
 * @property Collection<HotelRule> $rules
 */
class Hotel extends Model
{
    use HasFactory, HasLocaleFields;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'contract_number',
        'contract_date',
        'email',
        'country_id',
        'city_id',
        'booking_cancellation_days',
        'inn',
        'company_name',
        'address',
        'rate',
        'photo',
        'website_price',
        'phone',
        'comment',
        'description_en',
        'description_ru',
        'latitude',
        'longitude',
        'nds_included',
        'tour_sbor',
    ];

    protected $casts = [
        'contract_date' => 'date',
    ];

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class)->orderBy('price_foreign', 'asc');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(HotelPeriod::class)->orderBy('id');
    }
    
    public function currentYearPeriods(): HasMany
    {
        return $this->hasMany(HotelPeriod::class)->whereYear('start_date', now()->year);
    }

    public function phones(): MorphMany
    {
        return $this->morphMany(ManualPhone::class, 'manual');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'hotel_facilities');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function recommendedHotels(): HasMany
    {
        return $this->hasMany(RecommendedHotel::class);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function activeReviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable')->where('is_active', true);
    }
    
    public function rules(): HasMany
    {
        return $this->hasMany(HotelRule::class);
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->getLocaleValue('description');
    }
}
