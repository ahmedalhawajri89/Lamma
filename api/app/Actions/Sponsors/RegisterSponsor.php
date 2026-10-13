<?php

declare(strict_types=1);

namespace App\Actions\Sponsors;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * حساب كافل جديد: مستخدم بدور «كافل» وملف كافل.
 *
 * من صفحة التسجيل بكلمة مرور يختارها، ومن صفحة الكفالة بلا كلمة مرور
 * (كلمة عشوائية لا تُعرض، ويصله رابط تعيينها). مسارٌ واحد كي لا يختلف
 * الحسابان في شيء.
 */
class RegisterSponsor
{
    public function handle(string $name, string $email, ?string $phone = null, ?string $password = null): User
    {
        return DB::transaction(function () use ($name, $email, $phone, $password): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password ?? Str::random(40)),
                'phone' => $phone,
                'status' => UserStatus::Active,
            ]);

            $user->assignRole(UserRole::Sponsor->value);

            Sponsor::create([
                'user_id' => $user->id,
                'sponsor_code' => $this->nextSponsorCode(),
                'preferred_currency' => 'USD',
                'sponsoring_since' => now()->toDateString(),
                'communication_prefs' => ['email' => true, 'sms' => false],
            ]);

            return $user;
        });
    }

    /** الأكبر رقماً لا نصّاً، كي لا يتكرر الرمز بعد SPN-99999 */
    private function nextSponsorCode(): string
    {
        $last = (int) Sponsor::query()->lockForUpdate()->max(DB::raw('CAST(SUBSTRING(sponsor_code, 5) AS UNSIGNED)'));

        return 'SPN-'.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }
}
