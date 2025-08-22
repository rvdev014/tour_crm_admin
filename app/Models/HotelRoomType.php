<?php

namespace App\Models;

use App\Enums\RoomPersonType;
use App\Enums\RoomSeasonType;
use App\Services\TourService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $room_type_id
 * @property RoomSeasonType $season_type
 * @property RoomPersonType $person_type
 * @property float $price
 * @property float $price_foreign
 *
 * @property RoomType $roomType
 * @property Hotel $hotel
 */
class HotelRoomType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'price',
        'price_foreign',
        'season_type',
        'person_type',
    ];

    protected $casts = [
        'season_type' => RoomSeasonType::class,
        'person_type' => RoomPersonType::class,
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->orderBy('id');
    }

    public function getPrice($personType): int|float
    {
        $hotelPrice = $personType === RoomPersonType::Uzbek ? $this->price : $this->price_foreign;
        return $hotelPrice ?? 0;
    }

    public function getPriceWithPercent($companyId, $personType): int|float
    {
        $hotelPrice = $this->getPrice($personType);
        $addPercent = TourService::getCompanyAddPercent($companyId, $hotelPrice);
        if ($addPercent) {
            $hotelPrice += $hotelPrice * $addPercent / 100;
        }
        return $hotelPrice ?? 0;
    }
}
