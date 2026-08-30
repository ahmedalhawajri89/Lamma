<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'orphan_id' => ['required', 'integer', 'exists:orphans,id'],
            'period_year' => ['required', 'integer', 'min:2012', 'max:'.(now()->year + 1)],
            'period_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'academic_note' => ['nullable', 'string', 'max:1000'],
            'health_note' => ['nullable', 'string', 'max:1000'],
            'psychosocial_note' => ['nullable', 'string', 'max:1000'],
            'visit_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * القيد الفريد (orphan_id, year, quarter) في قاعدة البيانات يمنع التكرار
     * على أي حال، لكن رسالة الخطأ منه غير مفهومة للمستخدم — فنمسكه هنا أولاً
     * برسالة عربية واضحة. الطبقتان معاً: هذه للوضوح وتلك للضمان.
     */
    public function withValidator($validator): void
    {
        $validator->sometimes('period_quarter', [
            Rule::unique('reports', 'period_quarter')
                ->where('orphan_id', $this->integer('orphan_id'))
                ->where('period_year', $this->integer('period_year')),
        ], fn () => true);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'period_quarter.unique' => 'يوجد تقرير لهذا الطفل في هذا الربع. عدّله بدل إنشاء آخر.',
            'title.required' => 'عنوان التقرير مطلوب.',
        ];
    }
}
