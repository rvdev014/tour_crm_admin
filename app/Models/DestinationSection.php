<?php

namespace App\Models;

use App\Traits\HasLocaleFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single article block on a destination page (e.g. "History", "Things to
 * do", "Museums"), rendered as an anchor-linked section.
 *
 * @property int $id
 * @property int $destination_id
 * @property string $title_ru
 * @property string|null $title_en
 * @property string|null $anchor
 * @property string|null $content_ru
 * @property string|null $content_en
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Destination $destination
 */
class DestinationSection extends Model
{
    use HasFactory, HasLocaleFields;

    protected $guarded = ['id'];

    public function getTitleAttribute(): ?string
    {
        return $this->getLocaleValue('title');
    }

    public function getContentAttribute(): ?string
    {
        return $this->getLocaleValue('content');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
