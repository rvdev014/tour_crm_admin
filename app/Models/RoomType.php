<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $picture
 * @property string|null $description
 */
class RoomType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['name', 'picture', 'description'];

    public function hotelRoomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class);
    }

    public function tourRoomTypes(): HasMany
    {
        return $this->hasMany(TourRoomType::class);
    }

    public function tourDayExpenseRoomTypes(): HasMany
    {
        return $this->hasMany(TourDayExpenseRoomType::class);
    }

    public function canBeDeleted(): bool
    {
        return !$this->hotelRoomTypes()->exists() 
            && !$this->tourRoomTypes()->exists() 
            && !$this->tourDayExpenseRoomTypes()->exists();
    }

    public function getDeleteErrorMessage(): string
    {
        $relations = [];
        
        if ($this->hotelRoomTypes()->exists()) {
            $count = $this->hotelRoomTypes()->count();
            $relations[] = "{$count} hotel room type" . ($count > 1 ? 's' : '');
        }
        
        if ($this->tourRoomTypes()->exists()) {
            $count = $this->tourRoomTypes()->count();
            $relations[] = "{$count} tour room type" . ($count > 1 ? 's' : '');
        }
        
        if ($this->tourDayExpenseRoomTypes()->exists()) {
            $count = $this->tourDayExpenseRoomTypes()->count();
            $relations[] = "{$count} tour day expense room type" . ($count > 1 ? 's' : '');
        }
        
        if (empty($relations)) {
            return '';
        }
        
        return "Cannot delete '{$this->name}' because it is being used by: " . implode(', ', $relations) . '.';
    }
}
