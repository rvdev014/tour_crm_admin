<?php

namespace App\Models;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseType;
use App\Enums\GuideType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Services\ExpenseService;
use App\Enums\TransportComfortLevel;
use App\Enums\TransportType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $company_id
 * @property string $group_number
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $pax
 * @property int $leader_pax
 * @property string $comment
 * @property int $status
 * @property int $country_id
 * @property int $created_by
 * @property int $city_id
 * @property int $hotel_expenses_total
 * @property TourType $type
 * @property GuideType $guide_type
 * @property string $requested_by
 * @property string $guide_name
 * @property string $guide_phone
 * @property string $package_name
 * @property TransportType $transport_type
 * @property PaymentType $payment_type
 * @property PaymentStatus $payment_status
 *
 * @property float $expenses_total
 * @property float $income
 * @property float $price
 * @property float $guide_price
 * @property float $transport_price
 * @property CurrencyEnum $price_currency
 * @property CurrencyEnum $guide_price_currency
 * @property CurrencyEnum $transport_price_currency
 *
 * @property TransportComfortLevel $transport_comfort_level
 * @property int $single_supplement_price
 *
 * @property float $price_result
 * @property float $guide_price_result
 * @property float $transport_price_result
 *
 * @property Company $company
 * @property User $createdBy
 * @property City $city
 * @property Country $country
 * @property Collection<TourDay> $days
 * @property Collection<TourGroup> $groups
 * @property Collection<TourDayExpense> $expenses
 * @property Collection<TourRoomType> $roomTypes
 * @property Collection<TourHotel> $hotels
 * @property Collection<TourPassenger> $passengers
 */
class Tour extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'type' => TourType::class,
        'status' => TourStatus::class,
        'guide_type' => GuideType::class,
        'transport_type' => TransportType::class,
        'transport_comfort_level' => TransportComfortLevel::class,
        'price_currency' => CurrencyEnum::class,
        'guide_price_currency' => CurrencyEnum::class,
        'transport_price_currency' => CurrencyEnum::class,
        'payment_type' => PaymentType::class,
        'payment_status' => PaymentStatus::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TourDay::class)->orderBy('date');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TourGroup::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TourDayExpense::class);
    }

    public function expensesThroughDays(): HasManyThrough
    {
        return $this->hasManyThrough(
            TourDayExpense::class,
            TourDay::class,
            'tour_id',
            'tour_day_id',
            'id',
            'id'
        );
    }

    public function getExpense(ExpenseType $expenseType): ?TourDayExpense
    {
        return $this->expenses->first(fn($expense) => $expense->type == $expenseType);
    }

    public function getExpenseByDate($date, ExpenseType $expenseType): ?TourDayExpense
    {
        return $this->expenses->first(fn($expense) => $expense->type == $expenseType && $expense->date == $date);
    }

    public function getExpenses(ExpenseType $expenseType): Collection
    {
        return $this->expenses->filter(fn($expense) => $expense->type == $expenseType);
    }

    public function getExpensesByDate($date, ExpenseType $expenseType): Collection
    {
        return $this->expenses->filter(fn($expense) => $expense->type == $expenseType && $expense->date == $date);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(TourRoomType::class);
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(TourHotel::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TourPassenger::class);
    }

    public function isCorporate(): bool
    {
        return $this->type == TourType::Corporate;
    }

    public function getTotalPax($withLeader = true): int
    {
        if ($this->isCorporate()) {
            $totalPax = 0;
            $groups = $this->groups;
            foreach ($groups as $group) {
                $totalPax += $group->passengers()->count();
            }
            return $totalPax;
        }

        if (!$withLeader) {
            return $this->pax;
        }

        return $this->pax + $this->leader_pax;
    }

    public function saveExpensesTotal(): void
    {
        if ($this->isCorporate()) {
            $expensesTotal = ExpenseService::updateExpensesPricesCorporate($this);
            $this->update(['expenses_total' => $expensesTotal]);
        } else {
            $expensesTotal = ExpenseService::updateExpensesPricesTps($this);
            $expensesTotal = $expensesTotal + $this->guide_price_result;
            $this->update([
                'expenses_total' => $expensesTotal,
                'income' => $this->price_result - $expensesTotal,
            ]);
        }
    }
}
