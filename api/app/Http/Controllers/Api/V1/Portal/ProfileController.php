<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
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
            'current_password' => ['nullable', 'string'],

            'sponsor.city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'sponsor.preferred_currency' => ['sometimes', Rule::in(\App\Support\Fx::sponsorCurrencies())],
            'sponsor.prefers_zakat' => ['sometimes', 'boolean'],
            'sponsor.is_anonymous' => ['sometimes', 'boolean'],
            // مفاتيح معروفة وحدها، بقيم منطقية
            'sponsor.communication_prefs' => ['sometimes', 'array:email,sms,whatsapp'],
            'sponsor.communication_prefs.*' => ['boolean'],
        ]);

        /*
         * تغيير البريد يشترط كلمة المرور الحالية. البريد طريق استعادة الحساب:
         * من وجد جلسةً مفتوحة على جهاز مشترك كان يغيّره ثم يطلب «نسيت كلمة
         * المرور» إلى بريده هو، فيملك الحساب. ويُبطَل التوثيق حتى يُثبَت الجديد.
         */
        $changingEmail = isset($validated['email']) && $validated['email'] !== $user->email;
        if ($changingEmail) {
            if (! Hash::check((string) $request->input('current_password'), $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => __('لتغيير البريد اكتب كلمة المرور الحالية.'),
                ]);
            }
            $user->email_verified_at = null;
        }

        $user->fill(collect($validated)->except(['sponsor', 'current_password'])->all())->save();

        if (isset($validated['sponsor']) && $user->sponsor !== null) {
            $user->sponsor->update($validated['sponsor']);
        }

        return response()->json([
            'message' => __('حُفظت التعديلات.'),
            'data' => new UserResource($user->fresh()->load(['sponsor', 'staffProfile', 'roles'])),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->uncompromised()],
        ], [
            'password.confirmed' => __('كلمتا المرور غير متطابقتين.'),
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('كلمة المرور الحالية غير صحيحة.'),
            ]);
        }

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            // «تذكّرني» على الأجهزة الأخرى يسقط مع الكلمة القديمة
            'remember_token' => Str::random(60),
        ])->save();

        /*
         * إخراج الأجهزة الأخرى فعلاً: كان التعليق يقول ذلك والكود يجدّد معرّف
         * الجلسة الحالية وحدها، فتبقى جلسة مسروقة صالحة بعد تغيير الكلمة.
         */
        $current = null;
        if ($request->hasSession()) {
            $request->session()->regenerate();
            $current = $request->session()->getId();
        }
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($current, fn ($q) => $q->where('id', '!=', $current))
            ->delete();
        $user->tokens()->delete();

        return response()->json(['message' => __('تم تغيير كلمة المرور.')]);
    }
}
