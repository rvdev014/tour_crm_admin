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
 * @property string $name_ru
 * @property string|null $name_en
 * @property string|null $description_ru
 * @property string|null $description_en
 * @property string|null $photo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $description
 * @property-read mixed $name
 */
class Service extends Model
{
    use HasFactory, HasLocaleFields;

    protected $fillable = [
        'name_ru',
        'name_en',
        'description_ru',
        'description_en',
        'photo'
    ];

    public function getNameAttribute(): string
    {
        return $this->getLocaleValue('name');
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->getLocaleValue('description');
    }
}
