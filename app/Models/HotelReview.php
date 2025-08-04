<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $name
 * @property string $email
 * @property int $user_id
 * @property int $hotel_id
 * @property string $comment
 * @property float $rate
 *
 * @property-read User $user
 * @property-read Hotel $hotel
 */
class HotelReview extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function hotel(): HasOne
    {
        return $this->hasOne(Hotel::class);
    }
}
