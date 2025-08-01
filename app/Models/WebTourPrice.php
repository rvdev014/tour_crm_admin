<?php

namespace App\Models;

use App\Enums\TourPriceStatus;
use App\Enums\WebTourPriceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $web_tour_id
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property Carbon|null $deadline
 * @property WebTourPriceStatus $status
 * @property float $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read WebTour $webTour
 */
class WebTourPrice extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'deadline' => 'date',
        'status' => WebTourPriceStatus::class,
    ];

    public function webTour(): BelongsTo
    {
        return $this->belongsTo(WebTour::class);
    }
}
