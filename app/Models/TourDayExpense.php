<?php

namespace App\Models;

use App\Enums\EmployeeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $num_people
 * @property string $transport_time
 * @property float $price
 * @property string $comment
 * @property string $location
 * @property string $transport_route
 * @property int $tour_day_id
 * @property int $transport_status
 * @property string $car_ids
 * @property int $driver_employee_id
 * @property string $ticket_type
 * @property int $type
 * @property string $ticket_time
 * @property int $hotel_id
 * @property int $hotel_room_type_id
 * @property int $guide_employee_id
 * @property string $ticket_route
 *
 * @property TourDay $tourDay
 * @property Employee $driverEmployee
 * @property Hotel $hotel
 * @property HotelRoomType $hotelRoomType
 * @property Employee $guideEmployee
 */
class TourDayExpense extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'transport_time' => 'datetime',
        'car_ids' => 'array',
    ];

    public function tourDay(): BelongsTo
    {
        return $this->belongsTo(TourDay::class);
    }

    public function driverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->where('type', EmployeeType::Driver);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function hotelRoomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class);
    }

    public function guideEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->where('type', EmployeeType::Guide);
    }
}
