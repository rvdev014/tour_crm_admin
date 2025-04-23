<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order
 * @property string $name
 * @property int $type
 * @property int $tour_id
 *
 * @property Tour $tour
 * @property Collection<TourPassenger> $passengers
 * @property Collection<TourDayExpense> $expenses
 */
class TourGroup extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TourPassenger::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TourDayExpense::class);
    }
}
