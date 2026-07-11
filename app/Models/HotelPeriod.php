<?php

namespace App\Models;

use Carbon\Carbon;
use App\Enums\RoomSeasonType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $hotel_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property RoomSeasonType $season_type
 */
class HotelPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'start_date',
        'end_date',
        'season_type',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'season_type' => RoomSeasonType::class,
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * All periods defined for a hotel in a given year, memoized per request
     * since it's looked up once per row/tooltip on the same page load.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function periodsForYear(int $hotelId, int $year): \Illuminate\Support\Collection
    {
        static $cache = [];
        $key = "{$hotelId}:{$year}";

        return $cache[$key] ??= static::query()
            ->where('hotel_id', $hotelId)
            ->whereYear('start_date', $year)
            ->get();
    }

    // app/Models/HotelPeriod.php

    public function getExtendedLabelAttribute(): string
    {
        $start = Carbon::parse($this->start_date)->locale('ru');
        $end = Carbon::parse($this->end_date)->locale('ru');

        $mS = mb_strtolower($start->translatedFormat('F'));
        $mE = mb_strtolower($end->translatedFormat('F'));
        $yS = $start->format('Y');
        $yE = $end->format('Y');

        if ($mS === $mE && $yS === $yE) {
            $dateRange = "{$mS} {$yS}";
        } elseif ($yS === $yE) {
            $dateRange = "{$mS}-{$mE} {$yS}";
        } else {
            $dateRange = "{$mS} {$yS}-{$mE} {$yE}";
        }

        // Используем label из Enum (убедитесь, что поле season_type скащено в Enum)
        $seasonLabel = $this->season_type->getLabel();

        return "{$seasonLabel}: {$dateRange}";
    }
}
