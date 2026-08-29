<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ملف الموظف. `assigned_country_id` هو ما تقيّد به السياسات المشرف الميداني. */
class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'employee_code', 'title', 'assigned_country_id', 'hired_at'];

    protected function casts(): array
    {
        return ['hired_at' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'assigned_country_id');
    }
}
