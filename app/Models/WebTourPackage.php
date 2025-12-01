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
 * @property int $web_tour_id
 * @property int $package_id
 * @property bool $is_include
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read WebTour $webTour
 * @property-read Package $package
 */
class WebTourPackage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function webTour(): BelongsTo
    {
        return $this->belongsTo(WebTour::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
