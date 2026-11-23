<?php

namespace App\Models;

use App\Enums\DestinationPlaceCategory;
use App\Traits\HasLocaleFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A "place to see" card on a destination page (photo, short title and blurb),
 * grouped by category and optionally linking to a child destination page.
 *
 * @property int $id
 * @property int $destination_id
 * @property string $category
 * @property string|null $title_ru
 * @property string|null $title_en
 * @property string|null $description_ru
 * @property string|null $description_en
 * @property string|null $photo
 * @property int|null $link_destination_id
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Destination $destination
 * @property-read Destination|null $linkDestination
 */
class DestinationPlace extends Model
{
    use HasLocaleFields;

    protected $guarded = ['id'];

    public function getTitleAttribute(): ?string
    {
        return $this->getLocaleValue('title');
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->getLocaleValue('description');
    }

    public function categoryEnum(): DestinationPlaceCategory
    {
        return DestinationPlaceCategory::fromValueOrOther($this->category);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function linkDestination(): BelongsTo
    {
        return $this->belongsTo(Destination::class, 'link_destination_id');
    }
}
