<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $hotel_id
 * @property string $phone_number
 *
 * @property Hotel $hotel
 */
class HotelPhone extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'phone_number',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
