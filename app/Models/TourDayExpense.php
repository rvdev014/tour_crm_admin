<?php

namespace App\Models;

use App\Enums\EmployeeType;
use App\Enums\ExpenseType;
use App\Observers\TourDayExpenseObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * @property int $id
 * @property int $tour_day_id
 * @property ExpenseType $type
 *
 * Common fields
 * @property float $price
 * @property float $total_price
 * @property int $pax
 * @property int $status
 * @property string $comment
 *
 * Hotel
 * @property int $hotel_id
 * @property int $hotel_room_type_id
 * @property int $hotel_add_percent
 *
 * Museum
 * @property int $museum_id
 * @property int $museum_item_id
 * @property int $museum_pax
 * @property string $museum_inn
 *
 * Guide
 * @property string $guide_name
 * @property int $guide_type
 * @property int $guide_pax
 *
 * Transport
 * @property int $transport_type
 * @property int $transport_comfort_level
 * @property int $from_city_id
 * @property int $to_city_id
 * @property int $transport_pax
 *
 * Train
 * @property int $train_class
 * @property string $arrival_time
 * @property string $departure_time
 * @property int $train_pax
 *
 * Conference
 * @property string $conference_name
 * @property int $coffee_break
 * @property int $conference_pax
 *
 * Plane
 * @property int $plane_pax
 *
 * Restaurant
 * @property int $restaurant_id
 * @property int $lunch_pax
 *
 * Other
 * @property string $other_name
 *
 * @property City $fromCity
 * @property City $toCity
 * @property TourDay $tourDay
 * @property Hotel $hotel
 * @property HotelRoomType $hotelRoomType
 * @property Museum $museum
 * @property MuseumItem $museumItem
 * @property Restaurant $restaurant
 * @property Employee $guideEmployee
 */

#[ObservedBy([TourDayExpenseObserver::class])]
class TourDayExpense extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'transport_time' => 'datetime',
        'car_ids' => 'array',
        'type' => ExpenseType::class,
    ];

    public function tourDay(): BelongsTo
    {
        return $this->belongsTo(TourDay::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function hotelRoomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class);
    }

    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    public function museumItem(): BelongsTo
    {
        return $this->belongsTo(MuseumItem::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'to_city_id');
    }
}
