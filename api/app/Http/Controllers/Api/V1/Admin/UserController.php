<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrphanStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Association;
use App\Models\FieldVisit;
use App\Models\Orphan;
use App\Models\Report;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class UserController extends Controller
{
    /**
     * فريق العمل.
     *
     * الكفلاء لا يظهرون هنا: لهم تبويبهم، وحساباتهم تُدار من بوابتهم. وكان
     * خلطهم بالموظفين يُغرق خمسة موظفين بين مئات الكفلاء خلف مربّع «الموظفون فقط».
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        $staffRoles = array_column(UserRole::staff(), 'value');

        // دور مجهول يرمي استثناءً من Spatie (500)؛ يُرفض هنا بـ 422
        $request->validate([
            'role' => ['nullable', Rule::in($staffRoles)],
            'status' => ['nullable', Rule::in(UserStatus::values())],
        ]);

        $users = $this->filtered($request)
            // getAllPermissions() في UserResource يقرأ الصلاحيات المباشرة وصلاحيات
            // الأدوار معاً، فتُحمَّل العلاقتان هنا وإلا وقع lazy load لكل صف
            ->with(['roles.permissions', 'permissions', 'staffProfile.assignedCountry', 'staffProfile.association:id,name_ar'])
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            // من ينتظر التفعيل أولاً (عليه إجراء)، ثم الأحدث دخولاً
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('last_login_at')
            ->latest('id')
            ->paginate($this->perPage($request, 20));

        // أعداد التبويبات: كلٌّ تحت الفلاتر الأخرى لا تحت نفسه
        $roleCounts = $this->filtered($request)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->join('model_has_roles as mr', fn ($j) => $j->on('mr.model_id', '=', 'users.id')->where('mr.model_type', User::class))
            ->join('roles', 'roles.id', '=', 'mr.role_id')
            ->whereIn('roles.name', $staffRoles)
            ->selectRaw('roles.name as role, count(distinct users.id) as n')
            ->groupBy('roles.name')
            ->pluck('n', 'role');

        $statusCounts = $this->filtered($request)
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'total' => $users->total(), 'current_page' => $users->currentPage(), 'last_page' => $users->lastPage(),
                'role_counts' => $roleCounts,
                'status_counts' => $statusCounts,
                'all_count' => $roleCounts->sum(),
            ],
        ]);
    }

    /**
     * الموظف كاملاً: ما يقدر عليه، وحمل عمله، ودعوته، وآخر ما فعل.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);
        abort_unless($user->isStaff(), 404);

        $user->load(['roles.permissions', 'permissions', 'staffProfile.assignedCountry', 'staffProfile.association:id,name_ar']);
        $role = $user->getRoleNames()->first();
        $country = $user->staffProfile?->assigned_country_id;

        $actions = Activity::query()->where('causer_type', User::class)->where('causer_id', $user->id);

        $stats = [
            'actions_30d' => (clone $actions)->where('created_at', '>=', now()->subDays(30))->count(),
            'last_action_at' => (clone $actions)->max('created_at'),
        ];

        // المشرف: بلده وما عليه فيه
        if ($role === UserRole::FieldSupervisor->value) {
            $stats += [
                'orphans_in_country' => $country
                    ? Orphan::whereIn('status', [OrphanStatus::Active, OrphanStatus::FullyFunded, OrphanStatus::PendingVerification])
                        ->whereHas('family', fn ($q) => $q->where('country_id', $country))->count()
                    : 0,
                'visits_90d' => FieldVisit::where('supervisor_id', $user->id)->where('visited_at', '>=', now()->subDays(90))->count(),
                'reports_open' => Report::where('supervisor_id', $user->id)->where('status', ReportStatus::Draft)->count(),
            ];
        }

        $invite = $user->status === UserStatus::Pending
            ? Activity::query()->where('subject_type', User::class)->where('subject_id', $user->id)
                ->whereIn('event', ['created', 'invited'])->latest('id')->value('created_at')
            : null;

        return response()->json([
            'data' => [
                ...(new UserResource($user))->resolve($request),
                'hired_at' => $user->staffProfile?->hired_at?->toDateString(),
                'created_at' => $user->created_at?->toIso8601String(),
                'invited_at' => $invite ? Carbon::parse($invite)->toIso8601String() : null,
                'is_self' => $user->id === $request->user()->id,
                'abilities' => Permissions::describe($user->getAllPermissions()->pluck('name')->all()),
                'stats' => [
                    ...$stats,
                    'last_action_at' => $stats['last_action_at'] ? Carbon::parse($stats['last_action_at'])->toIso8601String() : null,
                ],
            ],
        ]);
    }

    /**
     * الموظفون، بالبحث: أساس القائمة وأعدادها
     *
     * @return Builder<User>
     */
    private function filtered(Request $request): Builder
    {
        return User::query()
            ->staff()
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                $w->where('users.name', 'like', "%{$term}%")->orWhere('users.email', 'like', "%{$term}%");
            }));
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
            // موظف الجمعية الشريكة: نطاقه أسر جمعيته، وبلده بلدها (StaffScope)
            'association_id' => ['nullable', 'integer', 'exists:associations,id'],
            // المشرف الميداني بلا بلد ولا جمعية لا يرى شيئاً، فلا يُنشأ بلا أحدهما
            'assigned_country_id' => [
                'nullable', 'integer', 'exists:countries,id',
                Rule::requiredIf(fn () => $request->input('role') === UserRole::FieldSupervisor->value && ! $request->filled('association_id')),
            ],
        ], [
            'role.in' => 'هذه الشاشة لإنشاء الموظفين. الكفلاء يسجّلون أنفسهم من الموقع.',
            'assigned_country_id.required' => 'المشرف الميداني يعمل في بلد واحد أو لجمعية. اختر أحدهما.',
        ]);

        // بلد موظف الجمعية بلدها، لا ما كُتب
        if (! empty($validated['association_id'])) {
            $validated['assigned_country_id'] = Association::whereKey($validated['association_id'])->value('country_id');
        }

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
                'association_id' => $validated['association_id'] ?? null,
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
            'association_id' => ['sometimes', 'nullable', 'integer', 'exists:associations,id'],
        ]);

        // بلد موظف الجمعية بلدها
        if (! empty($validated['association_id'])) {
            $validated['assigned_country_id'] = Association::whereKey($validated['association_id'])->value('country_id');
        }

        $self = $user->id === $request->user()->id;
        $currentRole = $user->getRoleNames()->first();
        $role = $validated['role'] ?? $currentRole;
        $status = $validated['status'] ?? $user->status->value;

        // لا يغيّر المدير دوره ولا يوقف حسابه بنفسه فيقفل على نفسه الباب
        abort_if($self && $role !== $currentRole, 422, 'لا يمكنك تغيير دورك أنت. يغيّره مدير آخر.');
        abort_if($self && $status !== UserStatus::Active->value, 422, 'لا يمكنك إيقاف حسابك أنت.');

        $country = array_key_exists('assigned_country_id', $validated)
            ? $validated['assigned_country_id']
            : $user->staffProfile?->assigned_country_id;

        $association = array_key_exists('association_id', $validated)
            ? $validated['association_id']
            : $user->staffProfile?->association_id;

        if ($role === UserRole::FieldSupervisor->value && $country === null && $association === null) {
            throw ValidationException::withMessages([
                'assigned_country_id' => 'المشرف الميداني يعمل في بلد واحد أو لجمعية. اختر أحدهما.',
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

            $profile = collect($validated)->only(['assigned_country_id', 'association_id', 'title'])->all();
            if ($profile !== []) {
                $user->staffProfile()->updateOrCreate(['user_id' => $user->id], $profile + [
                    'employee_code' => $user->staffProfile->employee_code ?? $this->nextEmployeeCode(),
                    'hired_at' => $user->staffProfile->hired_at ?? now()->toDateString(),
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
            'data' => new UserResource($user->fresh()->load(['roles', 'staffProfile.assignedCountry', 'staffProfile.association:id,name_ar'])),
        ]);
    }

    /** إعادة إرسال رابط الدعوة لموظف لم يفعّل حسابه بعد. */
    public function invite(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);
        abort_unless($user->isStaff() && $user->status === UserStatus::Pending, 422, 'الدعوة لحساب موظف لم يُفعَّل بعد.');

        Password::sendResetLink(['email' => $user->email]);

        // يُسجَّل: الدرج يعرض «أُرسلت قبل ساعتين» فلا تُرسل مرة ثالثة بلا داعٍ
        activity()->performedOn($user)->causedBy($request->user())->event('invited')->log('أعاد إرسال دعوة موظف');

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
