<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\DisbursementCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'currency' => ['required', Rule::in(config('kafala.currencies'))],

            /*
             * سعر الصرف إلزامي لكل عملة غير الأساس، ويُكتب من إيصال البنك أو
             * الصرّاف. كان يُترك فارغاً فيُحسب ١، فتُسجَّل 1000 شيكل 1000 دولار
             * في أرقام الشفافية المنشورة.
             */
            'fx_rate' => ['nullable', 'required_unless:currency,'.config('kafala.base_currency'), 'numeric', 'min:0.000001', 'max:100000'],

            // تأكيد صريح حين يشبه الصرف صرفاً سُجّل للتو (انظر المتحكم)
            'confirm_duplicate' => ['sometimes', 'boolean'],

            // لا صرف بتاريخ مستقبلي: المحاسبة تسجّل ما وقع لا ما سيقع
            'spent_on' => ['required', 'date', 'before_or_equal:today'],

            'description' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpeg,png', 'max:'.config('kafala.uploads.max_size_kb')],
        ];
    }

    /**
     * المصروف الإداري لا يخصّ طفلاً. كان اختيار طفل ثم تحويل البند إلى إداري
     * يُبقي الطفل مربوطاً، فتتشوّه نسبة «ما يصل الطفل» المنشورة.
     */
    public function after(): array
    {
        return [function ($validator): void {
            $category = DisbursementCategory::find($this->input('category_id'));
            if ($category?->is_overhead && $this->filled('orphan_id')) {
                $validator->errors()->add('orphan_id', 'المصروف الإداري عام لا يخصّ طفلاً.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category_id.required' => 'اختر بند الصرف.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'spent_on.before_or_equal' => 'لا يُسجَّل صرف بتاريخ مستقبلي.',
            'fx_rate.required_unless' => 'اكتب سعر الصرف إلى '.config('kafala.base_currency').' كما في إيصال البنك أو الصرّاف.',
            'currency.in' => 'عملة غير مدعومة.',
        ];
    }
}
