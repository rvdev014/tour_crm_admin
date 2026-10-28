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
 * @property string|null $email
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
        'email',
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

    /**
     * Requests an operator still has to look at. Shared by the sidebar badge, the red row
     * highlight and the live new-request alert, so all three always agree.
     */
    public function scopeNeedsAttention(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', WebTourStatus::New->value);
    }

    /** Per-row twin of scopeNeedsAttention() (query: Model::needsAttention()), used for the red row highlight. Keep the two in step. */
    public function requiresAttention(): bool
    {
        return $this->status === WebTourStatus::New;
    }
}
