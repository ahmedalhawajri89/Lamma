<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ملف الموظف. `assigned_country_id` هو ما تقيّد به السياسات المشرف الميداني.
 *
 * @property int $id
 * @property int $user_id
 * @property string $employee_code
 * @property string|null $title
 * @property int|null $assigned_country_id
 * @property int|null $association_id
 * @property Carbon|null $hired_at
 */
class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'employee_code', 'title', 'assigned_country_id', 'association_id', 'hired_at'];

    protected function casts(): array
    {
        return ['hired_at' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * الجمعية التي يعمل لها الموظف؛ فارغة لموظف المنصة.
     *
     * @return BelongsTo<Association, $this>
     */
    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function assignedCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'assigned_country_id');
    }
}
