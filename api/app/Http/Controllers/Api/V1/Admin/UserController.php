<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        // دور مجهول يرمي استثناءً من Spatie (500)؛ يُرفض هنا بـ 422
        $request->validate(['role' => ['nullable', Rule::in(UserRole::values())]]);

        $users = User::query()
            // getAllPermissions() في UserResource يقرأ الصلاحيات المباشرة وصلاحيات
            // الأدوار معاً، فتُحمَّل العلاقتان هنا وإلا وقع lazy load لكل صف
            ->with(['roles.permissions', 'permissions', 'staffProfile.assignedCountry'])
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->when($request->boolean('staff_only'), fn ($q) => $q->staff())
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
            }))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => ['total' => $users->total(), 'current_page' => $users->currentPage(), 'last_page' => $users->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:24'],
            'role' => ['required', Rule::in(array_column(UserRole::staff(), 'value'))],
            'title' => ['nullable', 'string', 'max:80'],
            // المشرف الميداني بلا بلد لا يرى شيئاً، فلا يُنشأ بلا بلد
            'assigned_country_id' => ['nullable', 'required_if:role,'.UserRole::FieldSupervisor->value, 'integer', 'exists:countries,id'],
        ], [
            'role.in' => 'هذه الشاشة لإنشاء الموظفين. الكفلاء يسجّلون أنفسهم من الموقع.',
            'assigned_country_id.required_if' => 'المشرف الميداني يعمل في بلد واحد. اختر بلده.',
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,

                // كلمة عشوائية لا تُعرض لأحد: الموظف يضبطها عبر رابط الاستعادة،
                // فلا تمرّ كلمة مرور مؤقتة في بريد أو محادثة
                'password' => Hash::make(Str::random(32)),
                'status' => UserStatus::Pending,
            ]);

            $user->assignRole($validated['role']);

            StaffProfile::create([
                'user_id' => $user->id,
                'employee_code' => $this->nextEmployeeCode(),
                'title' => $validated['title'] ?? null,
                'assigned_country_id' => $validated['assigned_country_id'] ?? null,
                'hired_at' => now()->toDateString(),
            ]);

            return $user;
        });

        // الدعوة رابط تعيين كلمة المرور نفسه، وتعيينها يفعّل الحساب
        Password::sendResetLink(['email' => $user->email]);

        activity()->performedOn($user)->causedBy($request->user())->event('created')
            ->withProperties(['attributes' => ['role' => $validated['role'], 'assigned_country_id' => $validated['assigned_country_id'] ?? null]])
            ->log('أنشأ حساب موظف');

        return response()->json([
            'message' => 'أُنشئ الحساب وأُرسل للموظف رابط تعيين كلمة المرور.',
            'data' => new UserResource($user->load(['roles', 'staffProfile'])),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        // حسابات الكفلاء تُدار من بوابتهم؛ تحويل كافل إلى موظف من هنا كان
        // يمنح حساباً عاماً صلاحيات إدارية ويُسقط دور الكافل
        abort_unless($user->isStaff(), 422, 'هذا حساب كافل. لا يُعدَّل من شاشة فريق العمل.');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:24'],
            'status' => ['sometimes', Rule::in(UserStatus::values())],
            'role' => ['sometimes', Rule::in(array_column(UserRole::staff(), 'value'))],
            'title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'assigned_country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
        ]);

        $self = $user->id === $request->user()->id;
        $currentRole = $user->roles->first()?->name;
        $role = $validated['role'] ?? $currentRole;
        $status = $validated['status'] ?? $user->status->value;

        // لا يغيّر المدير دوره ولا يوقف حسابه بنفسه فيقفل على نفسه الباب
        abort_if($self && $role !== $currentRole, 422, 'لا يمكنك تغيير دورك أنت. يغيّره مدير آخر.');
        abort_if($self && $status !== UserStatus::Active->value, 422, 'لا يمكنك إيقاف حسابك أنت.');

        $country = array_key_exists('assigned_country_id', $validated)
            ? $validated['assigned_country_id']
            : $user->staffProfile?->assigned_country_id;

        if ($role === UserRole::FieldSupervisor->value && $country === null) {
            throw ValidationException::withMessages([
                'assigned_country_id' => 'المشرف الميداني يعمل في بلد واحد. اختر بلده.',
            ]);
        }

        DB::transaction(function () use ($validated, $user, $currentRole, $role, $status): void {
            // آخر مدير نشط لا يُخفَّض ولا يُوقف: بلا مدير لا أحد يدير المستخدمين.
            // العدّ داخل المعاملة مع قفل، فلا يُخفّض مديران بعضهما في اللحظة نفسها.
            $losesAdmin = $currentRole === UserRole::Admin->value
                && ($role !== UserRole::Admin->value || $status !== UserStatus::Active->value);

            if ($losesAdmin) {
                $others = User::role(UserRole::Admin->value)
                    ->where('status', UserStatus::Active)
                    ->whereKeyNot($user->id)
                    ->lockForUpdate()
                    ->count();

                abort_if($others === 0, 422, 'هذا آخر مدير نشط. أضف مديراً آخر قبل تغيير دوره أو إيقافه.');
            }

            $user->update(collect($validated)->only(['name', 'phone', 'status'])->all());

            if ($role !== $currentRole) {
                $user->syncRoles($role);
            }

            $profile = collect($validated)->only(['assigned_country_id', 'title'])->all();
            if ($profile !== []) {
                $user->staffProfile()->updateOrCreate(['user_id' => $user->id], $profile + [
                    'employee_code' => $user->staffProfile?->employee_code ?? $this->nextEmployeeCode(),
                    'hired_at' => $user->staffProfile?->hired_at ?? now()->toDateString(),
                ]);
            }

            // الإيقاف يُنهي الجلسات القائمة فوراً، لا عند انتهائها
            if ($status === UserStatus::Suspended->value) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                $user->tokens()->delete();
            }
        });

        activity()
            ->performedOn($user)
            ->causedBy($request->user())
            ->event('updated')
            ->withProperties([
                'attributes' => collect($validated)->except([])->all(),
                'old' => ['role' => $currentRole, 'status' => $user->getOriginal('status')],
            ])
            ->log('عدّل حساب موظف');

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new UserResource($user->fresh()->load(['roles', 'staffProfile.assignedCountry'])),
        ]);
    }

    /** إعادة إرسال رابط الدعوة لموظف لم يفعّل حسابه بعد. */
    public function invite(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);
        abort_unless($user->isStaff() && $user->status === UserStatus::Pending, 422, 'الدعوة لحساب موظف لم يُفعَّل بعد.');

        Password::sendResetLink(['email' => $user->email]);

        return response()->json(['message' => 'أُعيد إرسال رابط الدعوة.']);
    }

    /** قائمة الأدوار والصلاحيات — تبنيها الواجهة في شاشة إدارة المستخدمين. */
    public function roles(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        return response()->json([
            'data' => [
                'roles' => UserRole::options(),
                'statuses' => UserStatus::options(),
            ],
        ]);
    }

    private function nextEmployeeCode(): string
    {
        $last = StaffProfile::query()->lockForUpdate()->max('employee_code');
        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'EMP-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
