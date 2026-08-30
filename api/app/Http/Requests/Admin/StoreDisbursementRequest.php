<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('disbursements.record');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // NULL مسموح ويعني مصروفاً إدارياً عاماً لا يخصّ طفلاً بعينه
            'orphan_id' => ['nullable', 'integer', 'exists:orphans,id'],

            'category_id' => ['required', 'integer', 'exists:disbursement_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3'],
            'fx_rate' => ['nullable', 'numeric', 'min:0.000001'],

            // لا صرف بتاريخ مستقبلي: المحاسبة تسجّل ما وقع لا ما سيقع
            'spent_on' => ['required', 'date', 'before_or_equal:today'],

            'description' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpeg,png', 'max:'.config('kafala.uploads.max_size_kb')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category_id.required' => 'اختر بند الصرف.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'spent_on.before_or_equal' => 'لا يُسجَّل صرف بتاريخ مستقبلي.',
        ];
    }
}
