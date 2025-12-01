<?php

namespace App\Models;

use DateTime;
use App\Enums\TourStatus;
use App\Enums\WebTourStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $comment
 * @property int|null $user_id
 * @property TourStatus $status
 * @property int|null $status_updated_by
 * @property DateTime $created_at
 * @property DateTime $updated_at
 *
 * @property User|null $user
 * @property User|null $statusUpdatedBy
 */
class ContactRequest extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];
    
    protected $casts = [
        'status' => WebTourStatus::class,
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function statusUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }
}
