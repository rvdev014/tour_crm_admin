<?php

namespace App\Models;

use App\Enums\EmployeeType;
use App\Enums\ExpenseType;
use App\Observers\TourDayExpenseObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
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
 * @property array $museum_item_ids
 * @property string $museum_inn
 *
 * Guide
 * @property string $guide_name
 * @property string $guide_phone
 *
 * Transport
 * @property int $transport_type
 * @property int $transport_comfort_level
 * @property int $from_city_id
 * @property int $to_city_id
 *
 * Train
 * @property string $train_name
 * @property int $train_class_economy
 * @property int $train_class_vip
 * @property int $train_class_second
 * @property string $arrival_time
 * @property string $departure_time
 *
 * Show
 * @property int $show_id
 *
 * Conference
 * @property string $conference_name
 * @property int $coffee_break
 *
 * Restaurant
 * @property int $restaurant_id
 *
 * Other
 * @property string $other_name
 *
 * @property City $fromCity
 * @property City $toCity
 * @property Show $show
 * @property TourDay $tourDay
 * @property Hotel $hotel
 * @property HotelRoomType $hotelRoomType
 * @property Museum $museum
 * @property MuseumItem $museumItem
 * @property Collection<MuseumItem> $museumItems
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
        'museum_item_ids' => 'array',
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

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
}
