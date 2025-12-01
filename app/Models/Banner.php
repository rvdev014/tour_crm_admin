<?php

namespace App\Models;

use App\Traits\HasLocaleFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property string $header_ru
 * @property string|null $header_en
 * @property string|null $description_ru
 * @property string|null $description_en
 * @property string|null $link
 * @property string|null $photo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read string $description
 * @property-read string $header
 */
class Banner extends Model
{
    use HasFactory, HasLocaleFields;

    protected $fillable = [
        'header_ru',
        'header_en',
        'description_ru',
        'description_en',
        'link',
        'photo',
    ];

    public function getHeaderAttribute(): string
    {
        return $this->getLocaleValue('header');
    }

    public function getDescriptionAttribute(): string
    {
        return $this->getLocaleValue('description');
    }
}
