<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tour_id
 * @property int $tour_group_id
 * @property string $name
 *
 * @property Tour $tour
 * @property TourGroup $tourGroup
 */
class TourPassenger extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'tour_id',
        'tour_group_id',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function tourGroup(): BelongsTo
    {
        return $this->belongsTo(TourGroup::class);
    }
}
