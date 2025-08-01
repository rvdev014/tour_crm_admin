<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $web_tour_id
 * @property int $similar_web_tour_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property WebTour $webTour
 * @property WebTour $similarWebTour
 */
class SimilarTour extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
}
