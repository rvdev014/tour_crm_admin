<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\HotelRule
 *
 * @property int $id
 * @property int $hotel_id
 * @property string $rule_type Тип правила (early_check_in, late_check_out)
 * @property string $start_time Время начала действия (H:i:s)
 * @property string $end_time Время окончания действия (H:i:s)
 * @property string $price_impact_type Тип наценки (percentage, hourly, fixed)
 * @property float $impact_value Числовое значение наценки
 * @property bool $is_inclusive Включено ли питание/услуги
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * * @property-read Hotel $hotel
 *
 * @method static Builder|HotelRule newModelQuery()
 * @method static Builder|HotelRule newQuery()
 * @method static Builder|HotelRule query()
 */
class HotelRule extends Model
{
    use HasFactory;
    
    protected $table = 'hotel_rules';
    
    /**
     * Константы для типов правил (Best Practice)
     */
    public const TYPE_EARLY_CHECK_IN = 'early_check_in';
    public const TYPE_LATE_CHECK_OUT = 'late_check_out';
    
    public const IMPACT_PERCENTAGE = 'percentage'; // В процентах от стоимости суток
    public const IMPACT_HOURLY = 'hourly';         // Процент/сумма за каждый час
    public const IMPACT_FIXED = 'fixed';           // Фиксированная сумма/дней
    
    /**
     * Атрибуты, которые можно заполнять массово.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'hotel_id',
        'rule_type',
        'start_time',
        'end_time',
        'price_impact_type',
        'impact_value',
        'is_inclusive',
    ];
    
    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'hotel_id' => 'integer',
        'impact_value' => 'float',     // Приводим decimal из БД к float в PHP
        'is_inclusive' => 'boolean',   // 0/1 в БД -> false/true в PHP
        // start_time и end_time мы оставляем строками (H:i:s),
        // так как Carbon::parse() отлично работает со строками.
        // Если нужно, можно использовать 'start_time' => 'datetime:H:i',
    ];
    
    /**
     * Получить отель, которому принадлежит правило.
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}