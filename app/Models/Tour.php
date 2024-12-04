<?php

namespace App\Models;

use App\Enums\TourType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $company_id
 * @property string $group_number
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $arrival
 * @property string $departure
 * @property string $rooming
 * @property int $pax
 * @property int $status
 * @property int $country_id
 * @property int $price
 * @property int $expenses
 * @property int $income
 * @property int $type
 *
 * @property Company $company
 * @property Country $country
 * @property Collection<TourDay> $days
 */
class Tour extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'group_number',
        'start_date',
        'end_date',
        'arrival',
        'departure',
        'rooming',
        'pax',
        'price',
        'status',
        'country_id',
        'type'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'type' => TourType::class
    ];

    public function getIncomeAttribute(): int
    {
        return $this->price - $this->expenses;
    }

    public function getExpensesAttribute(): int
    {
        return $this->days->sum(fn (TourDay $day) => $day->expenses->sum('price'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TourDay::class);
    }
}
