<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],

            // `confirmed` تطلب `password_confirmation` — والتأكيد هنا ليس
            // زخرفة: من يخطئ في كتابة كلمةٍ لا يراها يُقفل على نفسه الباب
            // الذي فتحه للتوّ.
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
        ];
    }
}
