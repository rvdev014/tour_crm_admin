<?php

namespace App\Models;

use App\Enums\TourType;
use App\Enums\WebTourStatus;
use App\Enums\WebTourType;
use Illuminate\Support\Carbon;
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
 * @property int|null $status_updated_by
 *
 * @property WebTour $webTour
 * @property User $user
 * @property User $statusUpdatedBy
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
        'status_updated_by',
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
    
    public function statusUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }
}
