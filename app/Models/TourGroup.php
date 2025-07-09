<?php

namespace App\Models;

use App\Enums\ExpenseType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order
 * @property string $name
 * @property int $type
 * @property int $tour_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Tour $tour
 * @property Collection<TourPassenger> $passengers
 * @property Collection<TourDayExpense> $expenses
 */
class TourGroup extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    public function getExpense(ExpenseType $expenseType): ?TourDayExpense
    {
        return $this->expenses->first(fn($expense) => $expense->type == $expenseType);
    }

    public function getExpenses(ExpenseType $expenseType): Collection
    {
        return $this->expenses->filter(fn($expense) => $expense->type == $expenseType);
    }
}
