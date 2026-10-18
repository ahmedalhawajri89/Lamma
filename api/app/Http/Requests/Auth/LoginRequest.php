<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * تسجيل الدخول.
 *
 * تحديد المعدّل بمفتاح «البريد + عنوان IP» لا بأحدهما وحده:
 *  · بالبريد وحده يستطيع مهاجم أن يقفل حساب أي شخص بمحاولات فاشلة متعمّدة.
 *  · بالـ IP وحده تُقفل شبكة مكتب كاملة خلف NAT بسبب موظف نسي كلمته.
 * والمفتاح المركّب يمنع التخمين ولا يمنح أحداً قدرة على تعطيل غيره.
 */
class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:180'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => __('البريد الإلكتروني مطلوب.'),
            'email.email' => __('صيغة البريد الإلكتروني غير صحيحة.'),
            'password.required' => __('كلمة المرور مطلوبة.'),
        ];
    }

    /** @return array{email: string, password: string} */
    public function credentials(): array
    {
        return [
            'email' => Str::lower($this->string('email')->trim()->toString()),
            'password' => $this->string('password')->toString(),
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "محاولات كثيرة. أعد المحاولة بعد {$seconds} ثانية.",
        ])->status(429);
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    private function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('email')->toString()).'|'.$this->ip(),
        );
    }
}
