<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * تسجيل كافل جديد. الموظفون يُنشأون من لوحة الإدارة لا من هنا،
 * فلا يوجد حقل «دور» في هذا النموذج إطلاقاً — ولو أُرسل لتُجوهل قيمته،
 * لأن المتحكّم يقرأ من validated() لا من all().
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:180', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:24', 'regex:/^\+?[0-9\s\-]{7,24}$/'],
            'password' => [
                'required', 'confirmed',
                Password::min(8)->letters()->numbers()->uncompromised(),
            ],
            'terms' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'name.min' => 'الاسم قصير جداً.',
            'email.unique' => 'هذا البريد مسجَّل لدينا. جرّب تسجيل الدخول.',
            'phone.regex' => 'صيغة رقم الهاتف غير صحيحة.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
            'password.uncompromised' => 'هذه الكلمة ظهرت في تسريبات معروفة. اختر غيرها.',
            'terms.accepted' => 'يلزم الموافقة على الشروط.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }
}
