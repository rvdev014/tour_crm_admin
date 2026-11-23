<?php

namespace App\Models;

use App\Traits\HasLocaleFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A local dish card ("Beshparmak", "Laghman", …) in the cuisine block of a destination page.
 *
 * @property int $id
 * @property int $destination_id
 * @property string|null $name_ru
 * @property string|null $name_en
 * @property string|null $description_ru
 * @property string|null $description_en
 * @property string|null $photo
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Destination $destination
 */
class DestinationDish extends Model
{
    use HasLocaleFields;

    protected $guarded = ['id'];

    public function getNameAttribute(): ?string
    {
        return $this->getLocaleValue('name');
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->getLocaleValue('description');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
