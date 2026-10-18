<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * استعادة كلمة المرور.
 *
 * كانت الشاشة تقول «أرسلنا لك رابط إعادة التعيين» ولا تُرسل شيئاً — نموذج
 * بلا وجهة يمرّ بفرع المحاكاة في forms.js. هذه الاختبارات تحرس أن يبقى
 * الوعد مطابقاً للفعل.
 */
it('يرسل رابط الاستعادة لبريد مسجّل', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

/**
 * أهمّ اختبار هنا.
 *
 * ردٌّ مختلف للبريد غير المسجّل يحوّل الشاشة إلى أداة تعداد حسابات: يجرّب
 * المهاجم قائمة بُرد ويعرف أيّها لدينا. وهذا يخصّ كفلاءنا لا حساباتنا.
 */
it('لا يكشف أي البُرد مسجّلة لدينا', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@nowhere.test']);

    expect($unknown->status())->toBe($known->status())
        ->and($unknown->json('message'))->toBe($known->json('message'));

    Notification::assertCount(1);
});

it('يغيّر كلمة المرور برمز صالح', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Jadeed1234',
        'password_confirmation' => 'Jadeed1234',
    ])->assertOk();

    expect(Hash::check('Jadeed1234', $user->fresh()->password))->toBeTrue();
});

it('يرفض رمزاً غير صالح', function (): void {
    $user = User::factory()->create(['password' => Hash::make('AlQadeema1')]);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'ليس-رمزاً',
        'email' => $user->email,
        'password' => 'Jadeed1234',
        'password_confirmation' => 'Jadeed1234',
    ])->assertStatus(422);

    expect(Hash::check('AlQadeema1', $user->fresh()->password))->toBeTrue();
});

/**
 * الرمز يُستهلك مرة واحدة.
 *
 * رابطٌ يبقى صالحاً بعد استعماله يعني أن من يصل إلى بريد قديم — صندوق
 * مشترك، أو جهاز بيع — يملك الحساب متى شاء.
 */
it('لا يقبل الرمز نفسه مرتين', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $payload = [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Jadeed1234',
        'password_confirmation' => 'Jadeed1234',
    ];

    $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
    $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(422);
});

it('يرفض كلمة قصيرة أو غير مؤكّدة', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'قصيرة',
        'password_confirmation' => 'مختلفة',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

/* ------------------------------------------------------------ الصفحة */

/**
 * الصفحة تفحص الرابط عند فتحها: كان الزائر يكتب كلمته مرتين ثم يُقال له
 * إن الرابط انتهى.
 */
it('رابط البريد يقصد الصفحة النظيفة، وتفتح بنموذجها لرمزٍ صالح', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use ($user): bool {
        $url = $n->toMail($user)->actionUrl;
        expect($url)->toContain('/reset-password?')->not->toContain('.html');

        $page = $this->get(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))->assertOk();
        $page->assertSee('كلمة مرور جديدة')->assertSee('data-action="reset"', false)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        expect($page->headers->get('Cache-Control'))->toContain('no-store');

        return true;
    });
});

it('رمزٌ منتهٍ أو خاطئ يُقال عند الفتح، بلا نموذج ولا كشفٍ للبريد', function (): void {
    $user = User::factory()->create();

    foreach ([$user->email, 'nobody@nowhere.test'] as $email) {
        $this->get('/reset-password?'.http_build_query(['token' => 'wrong', 'email' => $email]))->assertOk()
            ->assertSee('انتهت صلاحية الرابط')
            ->assertDontSee('data-action="reset"', false);
    }

    $this->get('/reset-password')->assertOk()->assertSee('الرابط غير مكتمل');
});

it('فتح الصفحة لا يستهلك الرمز', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get('/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]))->assertSee('data-action="reset"', false);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => $user->email, 'password' => 'Jadeed1234', 'password_confirmation' => 'Jadeed1234',
    ])->assertOk();
});
