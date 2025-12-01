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
 * @property string $name
 * @property string $name_ru
 * @property string|null $name_en
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Package extends Model
{
    use HasFactory, HasLocaleFields;

    protected $guarded = ['id'];

    public function getNameAttribute(): string
    {
        return $this->getLocaleValue('name');
    }
}
