<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $web_tour_day_id
 * @property int $facility_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Facility $facility
 * @property-read WebTourDay $webTourDay
 */
class WebTourDayFacility extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function webTourDay(): BelongsTo
    {
        return $this->belongsTo(WebTourDay::class);
    }
}
