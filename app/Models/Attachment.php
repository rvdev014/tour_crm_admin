<?php

namespace App\Models;

use App\Enums\AttachmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * App\Models\Attachment
 *
 * @property int $id
 * @property string $file_name
 * @property string $file_path
 * @property string $file_type
 * @property string $file_size
 * @property integer $attachable_id
 * @property string $attachable_type
 *
 * @property-read Model $attachable
 */
class Attachment extends Model
{
    use HasFactory;

    protected $table = 'attachments';

    public $timestamps = false;

    protected $fillable = [
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::deleted(function (Attachment $attachment) {
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        });
    }

    public function getUrl(): ?string
    {
        if (Storage::disk('public')->exists($this->file_path)) {
            return asset('storage/' . $this->file_path);
        }

        return null;
    }
}
