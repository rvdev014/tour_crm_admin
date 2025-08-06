<?php

namespace App\Models;

use App\Enums\RoomSeasonType;
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
 * @property string $description
 * @property string $comment
 *
 * @property City $city
 * @property Country $country
 * @property Collection<HotelRoomType> $roomTypes
 * @property Collection<HotelPeriod> $periods
 * @property Collection<ManualPhone> $phones
 * @property Collection<Facility> $facilities
 * @property Collection<Attachment> $attachments
 * @property Collection<RecommendedHotel> $recommendedHotels
 * @property Collection<Review> $reviews
 */
class Hotel extends Model
{
    use HasFactory;

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
        'description',
    ];

    protected $casts = [
        'contract_date' => 'date',
    ];

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class)
            ->orderByRaw("
            CASE season_type
                WHEN 3 THEN 1
                WHEN 4 THEN 2
                WHEN 5 THEN 3
                WHEN 2 THEN 4
                ELSE 5
            END
        ");
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
}
