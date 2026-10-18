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
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->uncompromised()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.confirmed' => __('كلمتا المرور غير متطابقتين.'),
            // كما في التسجيل: كلمةٌ في تسريبٍ معروف تُجرَّب أولاً على أي حساب
            'password.uncompromised' => __('هذه الكلمة ظهرت في تسريبات معروفة. اختر غيرها.'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => __('البريد الإلكتروني'),
            'password' => __('كلمة المرور'),
        ];
    }
}
