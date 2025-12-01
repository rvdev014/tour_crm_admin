<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tour_day_expense_id
 * @property string $name
 * @property string $phone
 *
 * @property-read TourDayExpense $tourDayExpense
 */
class ExpenseGuide extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function tourDayExpense(): BelongsTo
    {
        return $this->belongsTo(TourDayExpense::class);
    }
}
