<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrphanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('orphans.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'min:2', 'max:60'],
            'last_name' => ['sometimes', 'string', 'min:2', 'max:60'],
            'gender' => ['sometimes', Rule::in(Gender::values())],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'family_id' => ['sometimes', 'integer', 'exists:families,id'],
            'program_id' => ['sometimes', 'integer', 'exists:programs,id'],
            'monthly_need' => ['sometimes', 'numeric', 'min:5', 'max:1000'],
            'is_urgent' => ['sometimes', 'boolean'],
            'grade_level' => ['sometimes', 'nullable', 'string', 'max:60'],
            'health_status' => ['sometimes', 'nullable', 'string', 'max:160'],
            'dream' => ['sometimes', 'nullable', 'string', 'max:160'],
            'story' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'status' => ['sometimes', Rule::in(OrphanStatus::values())],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.config('kafala.uploads.max_size_kb')],
        ];
    }
}
