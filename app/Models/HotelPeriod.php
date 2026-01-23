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
 * @property string $start_date
 * @property string $end_date
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
