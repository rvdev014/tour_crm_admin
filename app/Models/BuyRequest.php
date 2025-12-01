<?php

namespace App\Models;

use App\Enums\TourStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tour_id
 * @property string $start_date
 * @property TourStatus $status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property User $user
 * @property Tour $tour
 */
class BuyRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => TourStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
