<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Observers\TourDayExpenseObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tour_id
 * @property int $tour_day_id
 * @property Carbon $date
 * @property int $city_id
 * @property ExpenseType $type
 *
 * Common fields
 * @property float $price
 * @property float $price_currency
 * @property float $total_price
 * @property int $pax
 * @property ExpenseStatus $status
 * @property string $comment
 *
 * Hotel
 * @property int $hotel_id
 * @property int $hotel_room_type_id
 * @property string $hotel_checkin_time
 * @property string $hotel_checkout_time
 * @property string $hotel_checkout_date_time
 * @property int $hotel_add_percent
 * @property int $hotel_total_nights
 *
 * Museum
 * @property int $museum_id
 * @property array $museum_ids
 * @property int $museum_item_id
 * @property array $museum_item_ids
 * @property string $museum_inn
 *
 * Guide
// * @property string $guide_name
// * @property string $guide_phone
 *
 * Transport
 * @property int $transport_type
 * @property int $transport_comfort_level
 * @property int $from_city_id
 * @property int $to_city_id
 * @property string $transport_driver_ids
 * @property string $transport_time
 * @property string $transport_route
 * @property string $transport_place
 * @property string $send_username
 * @property string $plane_route
 *
 * Train
 * @property string $train_name
 * @property int $train_id
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
 * @property Train $train
 * @property City $city
 * @property City $fromCity
 * @property City $toCity
 * @property Show $show
 * @property Tour $tour
 * @property TourDay $tourDay
 * @property Hotel $hotel
 * @property HotelRoomType $hotelRoomType
 * @property Driver $transportDriver
 * @property Museum $museum
 * @property Collection<Museum> $museums
 * @property MuseumItem $museumItem
 * @property Collection<MuseumItem> $museumItems
 * @property Restaurant $restaurant
 * @property Employee $guideEmployee
 * @property Collection<ExpenseGuide> $guides
 */
#[ObservedBy([TourDayExpenseObserver::class])]
class TourDayExpense extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'type' => ExpenseType::class,
        'car_ids' => 'array',
        'transport_driver_ids' => 'array',
        'museum_ids' => 'array',
        'museum_item_ids' => 'array',
        'status' => ExpenseStatus::class,
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

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

    public function transportDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'transport_driver_id');
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

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function train(): BelongsTo
    {
        return $this->belongsTo(Train::class);
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

    public function guides(): HasMany
    {
        return $this->hasMany(ExpenseGuide::class, 'tour_day_expense_id');
    }
}
