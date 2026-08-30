<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:24'],
            'locale' => ['sometimes', Rule::in(['ar', 'en'])],
            'email' => ['sometimes', 'email:rfc', 'max:180', Rule::unique('users')->ignore($user->id)],

            'sponsor.city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'sponsor.preferred_currency' => ['sometimes', Rule::in(['USD', 'GBP', 'EUR', 'SAR', 'AED'])],
            'sponsor.prefers_zakat' => ['sometimes', 'boolean'],
            'sponsor.is_anonymous' => ['sometimes', 'boolean'],
            'sponsor.communication_prefs' => ['sometimes', 'array'],
        ]);

        // تغيير البريد يُبطل التوثيق: البريد الجديد لم يُثبت أنه للمستخدم بعد
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill(collect($validated)->except('sponsor')->all())->save();

        if (isset($validated['sponsor']) && $user->sponsor !== null) {
            $user->sponsor->update($validated['sponsor']);
        }

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new UserResource($user->fresh()->load(['sponsor', 'staffProfile', 'roles'])),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->uncompromised()],
        ], [
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'كلمة المرور الحالية غير صحيحة.',
            ]);
        }

        $request->user()->update(['password' => Hash::make($validated['password'])]);

        // إبطال بقية الجلسات: تغيير كلمة المرور يجب أن يطرد أي جهاز آخر
        $request->session()->regenerate();

        return response()->json(['message' => 'تم تغيير كلمة المرور.']);
    }
}
