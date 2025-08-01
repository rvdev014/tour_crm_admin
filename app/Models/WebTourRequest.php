<?php

namespace App\Models;

use App\Enums\TourType;
use App\Enums\TourStatus;
use App\Enums\WebTourStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tour_id
 * @property string $start_date
 * @property string $phone
 * @property string $citizenship
 * @property string $comment
 * @property int $travellers_count
 * @property TourType $tour_type
 * @property TourStatus $status
 */
class WebTourRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'status' => WebTourStatus::class,
        'tour_type' => TourType::class,
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
