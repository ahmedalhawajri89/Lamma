<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GuardianRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * الوصي.
 *
 * رقم الهوية لا يُخزَّن — تُخزَّن تجزئته فقط، فيمكن كشف التكرار
 * دون الاحتفاظ برقم قابل للتسريب.
 */
class Guardian extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name', 'relation', 'phone', 'date_of_birth',
        'occupation', 'monthly_income', 'notes',
    ];

    protected $hidden = ['national_id_hash'];

    protected function casts(): array
    {
        return [
            'relation' => GuardianRelation::class,
            'date_of_birth' => 'date',
            'monthly_income' => 'decimal:2',
        ];
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }

    /** التجزئة تتم هنا لا في المتحكّم، فلا يمكن نسيانها في مسار آخر. */
    public function setNationalId(?string $nationalId): void
    {
        $this->national_id_hash = $nationalId
            ? hash('sha256', $nationalId.config('kafala.guardian_id_pepper'))
            : null;
    }
}
