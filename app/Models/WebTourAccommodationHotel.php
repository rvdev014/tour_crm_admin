<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $web_tour_accommodation_id
 * @property int $hotel_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Hotel $hotel
 * @property-read WebTourAccommodation $webTourAccommodation
 */
class WebTourAccommodationHotel extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function webTourAccommodation(): BelongsTo
    {
        return $this->belongsTo(WebTourAccommodation::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
