<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function getIncomeAttribute()
    {
        return $this->price - $this->expenses;
    }

    public function getExpensesAttribute()
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
