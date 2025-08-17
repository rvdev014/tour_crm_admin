<?php

namespace App\Models;

use App\Enums\TourType;
use App\Enums\WebTourStatus;
use App\Enums\WebTourType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $web_tour_id
 * @property string $start_date
 * @property string|null $phone
 * @property string|null $citizenship
 * @property string|null $comment
 * @property int|null $travellers_count
 * @property TourType|null $tour_type
 * @property WebTourStatus $status
 */
class WebTourRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'web_tour_id',
        'phone',
        'citizenship',
        'comment',
        'travellers_count',
        'tour_type',
        'start_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'status' => WebTourStatus::class,
        'tour_type' => WebTourType::class,
    ];

    public function webTour(): BelongsTo
    {
        return $this->belongsTo(WebTour::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
