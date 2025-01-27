<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tour_id
 * @property string $name
 *
 * @property Tour $tour
 */
class TourPassenger extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tour_id',
        'name',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
