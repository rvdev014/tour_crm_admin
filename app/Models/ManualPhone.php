<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $manual_id
 * @property string $manual_type
 * @property string $phone_number
 *
 * @property Hotel|Restaurant $manual
 */
class ManualPhone extends Model
{
    use HasFactory;

    protected $fillable = [
        'manual_id',
        'manual_type',
        'phone_number',
    ];

    public function manual(): MorphTo
    {
        return $this->morphTo('manual');
    }
}
