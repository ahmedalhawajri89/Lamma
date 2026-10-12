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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        $users = User::query()
            // getAllPermissions() في UserResource يقرأ الصلاحيات المباشرة وصلاحيات
            // الأدوار معاً، فتُحمَّل العلاقتان هنا وإلا وقع lazy load لكل صف
            ->with(['roles.permissions', 'permissions', 'staffProfile.assignedCountry'])
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->when($request->boolean('staff_only'), fn ($q) => $q->staff())
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = $request->string('q')->toString();
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
            'assigned_country_id' => ['nullable', 'integer', 'exists:countries,id'],
        ], [
            'role.in' => 'هذه الشاشة لإنشاء الموظفين. الكفلاء يسجّلون أنفسهم من الموقع.',
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

        return response()->json([
            'message' => 'أُنشئ الحساب. أرسل له رابط تعيين كلمة المرور.',
            'data' => new UserResource($user->load(['roles', 'staffProfile'])),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:24'],
            'status' => ['sometimes', Rule::in(UserStatus::values())],
            'role' => ['sometimes', Rule::in(UserRole::values())],
            'assigned_country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
        ]);

        // لا يوقف المدير حسابه بنفسه فيقفل على نفسه الباب
        if (isset($validated['status'])
            && $user->id === $request->user()->id
            && $validated['status'] !== UserStatus::Active->value) {
            abort(422, 'لا يمكنك إيقاف حسابك أنت.');
        }

        DB::transaction(function () use ($validated, $user): void {
            $user->update(collect($validated)->only(['name', 'phone', 'status'])->all());

            if (isset($validated['role'])) {
                $user->syncRoles($validated['role']);
            }

            if (array_key_exists('assigned_country_id', $validated) && $user->staffProfile !== null) {
                $user->staffProfile->update(['assigned_country_id' => $validated['assigned_country_id']]);
            }
        });

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new UserResource($user->fresh()->load(['roles', 'staffProfile'])),
        ]);
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
