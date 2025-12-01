<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $name
 * @property string $email
 * @property int $user_id
 * @property int $reviewable_id
 * @property string $reviewable_type
 * @property string $comment
 * @property float $rate
 * @property bool $is_active
 *
 * @property-read User $user
 * @property-read Hotel|WebTour $reviewable
 */
class Review extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function hotel(): HasOne
    {
        return $this->hasOne(Hotel::class);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}
