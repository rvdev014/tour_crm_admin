<?php

namespace App\Models;

use App\Enums\EmployeeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $description
 *
 * @property Company $company
 * @property Employee $employee
 * @property Employee $driverEmployee
 * @property Employee $guideEmployee
 *
 */
class Transport extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function driverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id')->where('type', EmployeeType::Driver);
    }

    public function guideEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id')->where('type', EmployeeType::Guide);
    }
}
