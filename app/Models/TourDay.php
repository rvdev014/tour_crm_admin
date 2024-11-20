<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $date
 * @property int $city_id
 * @property int $tour_id
 * @property string $status
 *
 * @property City $city
 * @property Tour $tour
 * @property Collection<TourDayExpense> $expenses
 */
class TourDay extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'date',
        'city_id',
        'tour_id',
        'status'
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TourDayExpense::class);
    }
}
