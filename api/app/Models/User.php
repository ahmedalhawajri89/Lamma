<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * المستخدم — كل فاعل بشري في النظام.
 *
 * ما يخصّ الكافل وحده في `sponsors`، وما يخصّ الموظف في `staff_profiles`،
 * فلا يحمل هذا الجدول أعمدةً فارغة لأغلب صفوفه.
 *
 * @property-read Sponsor|null $sponsor
 * @property-read StaffProfile|null $staffProfile
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property string $locale
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $two_factor_confirmed_at
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar_path',
        'locale', 'timezone', 'status', 'email_verified_at',
    ];

    /**
     * ما لا يخرج في أي استجابة JSON مهما حدث.
     * خط الدفاع الأخير خلف API Resources.
     */
    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,

            // السرّ لا يُخزَّن خاماً — التشفير من مسؤولية الموديل لا المتحكّم
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    /* ---------------------------------------------------------------- العلاقات */

    /** @return HasOne<Sponsor, $this> */
    public function sponsor(): HasOne
    {
        return $this->hasOne(Sponsor::class);
    }

    /** @return HasOne<StaffProfile, $this> */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /* ------------------------------------------------------------------ النطاقات */

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereHas(
            'roles',
            fn ($q) => $q->whereIn('name', array_column(UserRole::staff(), 'value')),
        );
    }

    /* -------------------------------------------------------------------- مساعدات */

    public function isStaff(): bool
    {
        return $this->hasAnyRole(array_column(UserRole::staff(), 'value'));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin->value);
    }

    /**
     * البلد المُسنَد للمشرف الميداني — تستعمله السياسات لتقييد ما يراه.
     * المدير لا بلد له، فيرى كل شيء.
     */
    public function assignedCountryId(): ?int
    {
        return $this->staffProfile?->assigned_country_id;
    }
}
