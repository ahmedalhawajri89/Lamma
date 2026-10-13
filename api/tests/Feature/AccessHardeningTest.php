<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Enums\MessageStatus;
use App\Enums\OrphanStatus;
use App\Enums\SponsorshipStatus;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Family;
use App\Models\Message;
use App\Models\Orphan;
use App\Models\OrphanDocument;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * نتائج فحص QA للّوحة: صلاحيات كانت تتسع بصمت، وقرارات نهائية تُعكس،
 * وبيانات تُمسح دون أن يطلب أحد ذلك. لكل واحدة اختبار هنا كي لا تعود.
 */
function countryId(string $iso2): int
{
    return Country::where('iso2', $iso2)->value('id');
}

/* ------------------------------------------------------------ التقييد بالبلد */

it('لا يرى المشرف الميداني بلا بلد شيئاً، بدل أن يرى كل البلدان', function () {
    Orphan::factory()->count(2)->create();
    $supervisor = User::factory()->supervisor(null)->create();

    expect($supervisor->assignedCountryId())->toBe(0);
    $this->actingAs($supervisor)->getJson('/api/v1/admin/orphans')->assertOk()->assertJsonPath('meta.total', 0);
});

it('لا يمسح تعديلُ هاتف المشرف بلده', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->supervisor(countryId('PS'))->create();

    // النموذج لم يعد يرسل البلد فارغاً، والخادم يرفض مشرفاً بلا بلد
    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$supervisor->id}", ['phone' => '0599', 'assigned_country_id' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('assigned_country_id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$supervisor->id}", ['phone' => '0599'])->assertOk();
    expect($supervisor->fresh()->assignedCountryId())->toBe(countryId('PS'));
});

it('لا يُنشأ مشرف ميداني بلا بلد', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/v1/admin/users', ['name' => 'مشرف جديد', 'email' => 'new-sup@kafala.org', 'role' => 'field_supervisor'])
        ->assertUnprocessable()->assertJsonValidationErrors('assigned_country_id');
});

/* ------------------------------------------------------------ آخر مدير */

it('لا يغيّر المدير دوره بنفسه، ولا يُخفَّض آخر مدير نشط', function () {
    User::query()->get()->each(fn (User $u) => $u->hasRole('admin') && $u->update(['status' => UserStatus::Suspended]));
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$admin->id}", ['role' => 'accountant'])
        ->assertUnprocessable();

    $other = User::factory()->admin()->create();
    $other->update(['status' => UserStatus::Suspended]);

    // المدير الآخر موقوف، فـ$admin آخر مدير نشط: لا يخفّضه أحد
    $second = User::factory()->admin()->create();
    $this->actingAs($second)->patchJson("/api/v1/admin/users/{$admin->id}", ['status' => 'suspended'])->assertOk();
    $this->actingAs($admin->fresh())->patchJson("/api/v1/admin/users/{$second->id}", ['role' => 'accountant'])
        ->assertStatus(401); // $admin أُوقف للتو فخرج

    $this->actingAs($second)->patchJson("/api/v1/admin/users/{$second->id}", ['status' => 'suspended'])->assertUnprocessable();
});

it('يرفض تخفيض آخر مدير نشط حتى من مدير آخر غير نشط', function () {
    User::role('admin')->update(['status' => UserStatus::Suspended]);
    $last = User::factory()->admin()->create();
    $accountantAdmin = User::factory()->admin()->create(['status' => UserStatus::Suspended]);

    // نحاكي طلباً من مدير مخوَّل: آخر مدير نشط لا يُخفَّض
    $other = User::factory()->admin()->create();
    $this->actingAs($other)->patchJson("/api/v1/admin/users/{$last->id}", ['role' => 'accountant'])->assertOk();
    $this->actingAs($other)->patchJson("/api/v1/admin/users/{$other->id}", ['status' => 'suspended'])->assertUnprocessable();
    expect($accountantAdmin->fresh()->status)->toBe(UserStatus::Suspended);
});

it('الإيقاف يُخرج الموظف فوراً من جلسته', function () {
    $admin = User::factory()->admin()->create();
    $accountant = User::factory()->accountant()->create();

    $this->actingAs($accountant)->getJson('/api/v1/admin/payments')->assertOk();

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$accountant->id}", ['status' => 'suspended'])->assertOk();

    $this->actingAs($accountant->fresh())->getJson('/api/v1/admin/payments')->assertUnauthorized();
});

it('لا يتحوّل حساب كافل إلى موظف من شاشة المستخدمين، ولا يُقبل دور مجهول في الفلتر', function () {
    $admin = User::factory()->admin()->create();
    $sponsor = \App\Models\Sponsor::factory()->create()->user;

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$sponsor->id}", ['role' => 'admin'])->assertUnprocessable();
    expect($sponsor->fresh()->hasRole('admin'))->toBeFalse();

    $this->actingAs($admin)->getJson('/api/v1/admin/users?role=ghost')->assertUnprocessable();
});

it('تعيين كلمة المرور من رابط الدعوة يفعّل حساب الموظف الجديد', function () {
    $user = User::factory()->accountant()->create(['status' => UserStatus::Pending]);
    $token = \Illuminate\Support\Facades\Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email, 'token' => $token,
        'password' => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!',
    ])->assertOk();

    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

/* ------------------------------------------------------------ الأيتام */

it('لا يغيّر المشرف حالة الملف من نموذج التعديل، ولا ينقله لأسرة في بلد آخر', function () {
    $supervisor = User::factory()->supervisor(countryId('PS'))->create();
    $orphan = Orphan::factory()->create([
        'family_id' => Family::factory()->inCountry(countryId('PS')),
        'status' => OrphanStatus::Draft,
    ]);

    $this->actingAs($supervisor)->patchJson("/api/v1/admin/orphans/{$orphan->id}", ['status' => 'active'])->assertOk();
    expect($orphan->fresh()->status)->toBe(OrphanStatus::Draft);

    $foreign = Family::factory()->inCountry(countryId('YE'))->create();
    $this->actingAs($supervisor)->patchJson("/api/v1/admin/orphans/{$orphan->id}", ['family_id' => $foreign->id])->assertForbidden();
});

it('هوية الملف الموثّق يغيّرها المدير وحده', function () {
    $supervisor = User::factory()->supervisor(countryId('PS'))->create();
    $orphan = Orphan::factory()->create([
        'family_id' => Family::factory()->inCountry(countryId('PS')),
        'verified_at' => now(),
    ]);

    $this->actingAs($supervisor)->patchJson("/api/v1/admin/orphans/{$orphan->id}", ['first_name' => 'اسم آخر'])->assertUnprocessable();

    // إرسال الهوية دون تغيير (كما يفعل النموذج) مع تعديل حقل آخر يمرّ
    $this->actingAs($supervisor)->patchJson("/api/v1/admin/orphans/{$orphan->id}", [
        'first_name' => $orphan->first_name, 'gender' => $orphan->gender->value,
        'date_of_birth' => $orphan->date_of_birth->toDateString(), 'dream' => 'طبيب',
    ])->assertOk();
});

/* ------------------------------------------------------------ المستندات */

it('لا يتحقق أحد من مستند رفعه بنفسه، ولا يتحقق المشرف أصلاً، ولا يُعاد التحقق', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->supervisor(countryId('PS'))->create();
    $orphan = Orphan::factory()->create(['family_id' => Family::factory()->inCountry(countryId('PS'))]);

    $doc = fn (User $by) => OrphanDocument::create([
        'orphan_id' => $orphan->id, 'type' => DocumentType::cases()[0], 'path' => 'documents/x.pdf',
        'original_name' => 'x.pdf', 'mime' => 'application/pdf', 'size_bytes' => 10, 'uploaded_by' => $by->id,
    ]);

    $own = $doc($admin);
    $this->actingAs($admin)->postJson("/api/v1/admin/documents/{$own->id}/verify")->assertUnprocessable();

    $bySupervisor = $doc($supervisor);
    $this->actingAs($supervisor)->postJson("/api/v1/admin/documents/{$bySupervisor->id}/verify")->assertForbidden();
    $this->actingAs($admin)->postJson("/api/v1/admin/documents/{$bySupervisor->id}/verify")->assertOk();
    $this->actingAs($admin)->postJson("/api/v1/admin/documents/{$bySupervisor->id}/verify")->assertStatus(409);
});

/* ------------------------------------------------------------ قرارات نهائية */

it('مراجعة الرسالة قرار واحد لا يُعكس', function () {
    $admin = User::factory()->admin()->create();
    $message = Message::create(['sponsorship_id' => Sponsorship::factory()->create()->id, 'sender_type' => 'sponsor', 'body' => 'رسالة']);

    $this->actingAs($admin)->postJson("/api/v1/admin/messages/{$message->id}/review", ['decision' => 'approve'])->assertOk();
    $this->actingAs($admin)->postJson("/api/v1/admin/messages/{$message->id}/review", ['decision' => 'reject', 'reason' => 'متأخر'])
        ->assertStatus(409);

    expect($message->fresh()->status)->toBe(MessageStatus::Approved);
});

it('لا تتفعّل كفالة بانتظار الدفع من نموذج التعديل، ولا تُحيا كفالة ملغاة', function () {
    $admin = User::factory()->admin()->create();
    $pending = Sponsorship::factory()->create(['status' => SponsorshipStatus::Pending]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/sponsorships/{$pending->id}", ['status' => 'active'])->assertUnprocessable();
    $this->actingAs($admin)->patchJson("/api/v1/admin/sponsorships/{$pending->id}", ['billing_day' => 9])->assertOk();
    expect($pending->fresh())->status->toBe(SponsorshipStatus::Pending)->billing_day->toBe(9);

    $cancelled = Sponsorship::factory()->create(['status' => SponsorshipStatus::Cancelled]);
    $this->actingAs($admin)->patchJson("/api/v1/admin/sponsorships/{$cancelled->id}", ['status' => 'active'])->assertUnprocessable();
});

it('رفع مبلغ الكفالة لا يتجاوز ما تبقّى من حاجة الطفل', function () {
    $admin = User::factory()->admin()->create();
    $orphan = Orphan::factory()->create(['monthly_need' => 100, 'status' => OrphanStatus::Active]);
    Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 60, 'status' => SponsorshipStatus::Active]);
    $mine = Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 30, 'status' => SponsorshipStatus::Active]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/sponsorships/{$mine->id}", ['monthly_amount' => 50])
        ->assertUnprocessable()->assertJsonValidationErrors('monthly_amount');

    $this->actingAs($admin)->patchJson("/api/v1/admin/sponsorships/{$mine->id}", ['monthly_amount' => 40])->assertOk();
    expect($mine->fresh()->share_percent)->toBe(40);
});

/* ------------------------------------------------------------ بيانات تُمسح بصمت */

it('الإعدادات: المفاتيح المجهولة تُرفض، والقيم تُتحقق، وما لم يُرسَل يبقى', function () {
    $admin = User::factory()->admin()->create();
    $name = \App\Models\Setting::cached()['site.name'] ?? null;

    $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['settings' => [['group' => 'evil', 'key' => 'x', 'value' => 1]]])
        ->assertUnprocessable();
    $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['settings' => [['group' => 'finance', 'key' => 'overhead_target_percent', 'value' => 500]]])
        ->assertUnprocessable();

    $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['settings' => [['group' => 'policy', 'key' => 'moderate_messages', 'value' => 'false']]])
        ->assertOk();

    $settings = \App\Models\Setting::cached();
    expect($settings['policy.moderate_messages'])->toBeFalse()
        ->and($settings['site.name'] ?? null)->toBe($name);
});

it('المقال يُرجع رقم تصنيفه وتسمية حالته، والقائمة تحمل التصنيفات', function () {
    $admin = User::factory()->admin()->create();
    $category = \App\Models\PostCategory::create(['slug' => 'test-cat', 'name_ar' => 'تصنيف', 'sort_order' => 1]);
    \App\Models\Post::factory()->create(['category_id' => $category->id]);

    $res = $this->actingAs($admin)->getJson('/api/v1/admin/posts')->assertOk();
    expect($res->json('data.0'))->toHaveKeys(['category_id', 'status_label'])
        ->and($res->json('meta.categories'))->not->toBeEmpty();
});

it('رسائل التواصل لا تكشف عنوان الزائر', function () {
    \App\Models\ContactMessage::create(['name' => 'زائر', 'email' => 'v@example.com', 'subject' => 'general', 'body' => 'سؤال', 'ip_address' => '10.1.2.3']);

    $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/admin/contacts')
        ->assertOk()->assertDontSee('10.1.2.3');
});

it('الأسرة: مدينة من دولة أخرى تُرفض، ورقم هوية مسجَّل يُكشف', function () {
    $admin = User::factory()->admin()->create();
    $city = City::query()->first();
    $otherCountry = Country::where('id', '!=', $city->country_id)->value('id');

    $payload = fn (array $o = []) => array_merge([
        'guardian' => ['full_name' => 'أم سعيد', 'relation' => 'mother', 'national_id' => '4455667788'],
        'country_id' => $city->country_id, 'city_id' => $city->id,
        'members_count' => 4, 'housing_status' => 'rented',
    ], $o);

    $this->actingAs($admin)->postJson('/api/v1/admin/families', $payload(['country_id' => $otherCountry]))
        ->assertUnprocessable()->assertJsonValidationErrors('city_id');

    $this->actingAs($admin)->postJson('/api/v1/admin/families', $payload())->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/admin/families', $payload())
        ->assertUnprocessable()->assertJsonValidationErrors('guardian.national_id');
});

it('الزيارة: لا تُسجَّل لملف مغلق، ويُضبط موعدها القادم من دورية التقارير، و«متأخرة» لا تظهر على زيارة تلتها أخرى', function () {
    $admin = User::factory()->admin()->create();
    $closed = Orphan::factory()->create(['status' => OrphanStatus::Graduated]);
    $this->actingAs($admin)->postJson('/api/v1/admin/visits', [
        'orphan_id' => $closed->id, 'visited_at' => now()->toDateTimeString(), 'findings' => 'زيارة دورية للأسرة في البيت',
    ])->assertUnprocessable();

    $orphan = Orphan::factory()->create(['status' => OrphanStatus::Active]);
    $res = $this->actingAs($admin)->postJson('/api/v1/admin/visits', [
        'orphan_id' => $orphan->id, 'visited_at' => now()->subMonths(5)->toDateTimeString(), 'findings' => 'زيارة دورية للأسرة في البيت',
    ])->assertCreated();
    expect($res->json('data.next_visit_due'))->not->toBeNull();

    // زيارة أحدث تُلغي تأخّر الأقدم في الصف كما في العدّاد
    $this->actingAs($admin)->postJson('/api/v1/admin/visits', [
        'orphan_id' => $orphan->id, 'visited_at' => now()->subDay()->toDateTimeString(), 'findings' => 'زيارة متابعة بعد شهر',
    ])->assertCreated();

    $rows = collect($this->actingAs($admin)->getJson("/api/v1/admin/visits?orphan_id={$orphan->id}")->json('data'));
    expect($rows->where('is_overdue', true)->count())->toBe(0);
});
