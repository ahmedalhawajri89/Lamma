<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrphanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('orphans.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:60'],
            'last_name' => ['required', 'string', 'min:2', 'max:60'],
            'gender' => ['required', Rule::in(Gender::values())],

            // التاريخ لا العمر: العمر يتغيّر كل عام، والتاريخ لا.
            // والسقف ١٨ سنة لأن اليتيم يتخرّج من البرنامج عندها.
            'date_of_birth' => ['required', 'date', 'before:today', 'after:'.now()->subYears(18)->toDateString()],

            'family_id' => ['required', 'integer', 'exists:families,id'],
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'monthly_need' => ['required', 'numeric', 'min:5', 'max:1000'],
            'is_urgent' => ['sometimes', 'boolean'],
            'grade_level' => ['nullable', 'string', 'max:60'],
            'health_status' => ['nullable', 'string', 'max:160'],
            'dream' => ['nullable', 'string', 'max:160'],
            'story' => ['nullable', 'string', 'max:4000'],

            // لا يُقبل إنشاء ملف بحالة منشورة مباشرةً: النشر يمرّ بالتوثيق
            'status' => ['sometimes', Rule::in([
                OrphanStatus::Draft->value,
                OrphanStatus::PendingVerification->value,
            ])],

            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.config('kafala.uploads.max_size_kb')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.required' => 'الاسم الأول مطلوب.',
            'last_name.required' => 'اسم العائلة مطلوب.',
            'date_of_birth.required' => 'تاريخ الميلاد مطلوب — لا نخزّن العمر لأنه يتغيّر كل عام.',
            'date_of_birth.after' => 'تجاوز الطفل سنّ البرنامج (١٨ عاماً).',
            'family_id.required' => 'اختر الأسرة أولاً، فمنها تُقرأ الدولة والمدينة والوصي.',
            'monthly_need.min' => 'أقل حاجة شهرية ٥ دولارات.',
            'status.in' => 'الملف الجديد يبدأ مسوّدةً أو بانتظار التحقق — والنشر يمرّ بالتوثيق.',
        ];
    }
}
