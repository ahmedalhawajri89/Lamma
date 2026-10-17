<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

it('ترسم الرئيسية الترويسة والفوتر من الخادم بإعدادات الموقع', function () {
    Setting::query()->updateOrCreate(['group' => 'site', 'key' => 'name'], ['value' => 'كفالة تجريبية', 'type' => 'string']);
    Setting::query()->updateOrCreate(['group' => 'site', 'key' => 'phone'], ['value' => '+970 599 111 222', 'type' => 'string']);
    Cache::forget('settings');

    $this->get('/')
        ->assertOk()
        ->assertSee('id="siteHeader"', false)
        ->assertSee('class="kf-footer"', false)
        ->assertSee('كفالة تجريبية')
        ->assertSee('href="tel:+970599111222"', false)
        // لا قالب يُملأ في المتصفح: الترويسة والفوتر جاهزان في الاستجابة
        ->assertDontSee('data-layout=', false);
});

it('تحمل الرئيسية عنوانها ووسوم مشاركتها بروابط مطلقة', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>كفالة | اكفل يتيماً وتابع أثرك شهراً بشهر</title>')
        ->toContain('property="og:title" content="كفالة | اكفل يتيماً')
        ->toContain('property="og:image" content="'.url('/assets/img/og-image.jpg').'"')
        ->toContain('rel="canonical" href="'.url('/').'"');
});

it('يحوّل الرابط القديم بامتداد html إلى العنوان النظيف مع معاملاته', function () {
    $this->get('/index.html')->assertRedirect('/')->assertStatus(301);
    $this->get('/orphan.html?ref=K-12')->assertRedirect('/orphan?ref=K-12');
});

it('يُبرز في التنقل القسم الذي تقف فيه الصفحة العميقة', function () {
    $nav = collect(App\Support\SiteProfile::nav('orphan'))->keyBy('href');

    expect($nav['/orphans']['current'])->toBe('true')
        ->and($nav['/blog']['current'])->toBeNull()
        ->and(collect(App\Support\SiteProfile::nav('blog'))->firstWhere('href', '/blog')['current'])->toBe('page');
});

/** طفلٌ منشور موثّق، بحاجة 50 في الشهر. */
function profileChild(array $attrs = []): App\Models\Orphan
{
    return App\Models\Orphan::factory()->create([
        'status' => App\Enums\OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now(),
        'first_name' => 'سارة', 'last_name' => 'المصري', 'gender' => 'female', 'monthly_need' => 50,
        'dream' => 'أن تكتب قصة', ...$attrs,
    ]);
}

it('ترسم صفحة الطفل من الخادم: الاسم والحلم في العنوان ووسوم المشاركة، بلا اسم العائلة', function () {
    $kid = profileChild();

    $html = $this->get("/orphan?id={$kid->reference_code}")->assertOk()->getContent();

    expect($html)
        ->toContain('<title>سارة، ')
        ->toContain('حلمها: أن تكتب قصة')
        ->toContain('property="og:title" content="ساعد سارة')
        ->toContain('<meta name="robots" content="noindex">')
        ->toContain('href="/checkout?id='.$kid->reference_code.'"')
        ->toContain('لم يكفلها أحد بعد')
        ->not->toContain('المصري');
});

it('تقول صفحة الطفل المكفول جزئياً كم يكفله وكم بقي، وزرّها «أكمل كفالة»', function () {
    $kid = profileChild();
    App\Models\Sponsorship::factory()->for($kid)->create(['monthly_amount' => 20]);
    App\Models\Sponsorship::factory()->for($kid)->create(['monthly_amount' => 10]);

    $this->get("/orphan?id={$kid->reference_code}")
        ->assertOk()
        ->assertSee('يكفلها شخصان')
        ->assertSee('$20')
        ->assertSee('أكمل كفالة سارة');
});

it('تشكر صفحة من اكتملت كفالته كافليه بلا زرّ كفالة', function () {
    $kid = profileChild();
    App\Models\Sponsorship::factory()->for($kid)->create(['monthly_amount' => 50]);

    $this->get("/orphan?id={$kid->reference_code}")
        ->assertOk()
        ->assertSee('اكتملت كفالتها')
        ->assertSee('property="og:title" content="اكتملت كفالة سارة"', false)
        ->assertDontSee('/checkout?id=', false);
});

it('رمزٌ لا طفل له يعطي 404 بصفحة تقول ذلك، وبلا رمز إلى الفهرس', function () {
    App\Models\Orphan::factory()->create(['status' => App\Enums\OrphanStatus::Draft, 'reference_code' => 'KFL-9999']);

    $this->get('/orphan?id=KFL-9999')->assertNotFound()->assertSee('لم نجد هذا الطفل');
    $this->get('/orphan?id=NOPE')->assertNotFound();
    $this->get('/orphan')->assertRedirect('/orphans');
});

it('ترسم صفحة الكفالة ملخص الطفل من الخادم، وحقول البطاقة بلا name فلا تُرسل', function () {
    $kid = profileChild();
    App\Models\Sponsorship::factory()->for($kid)->create(['monthly_amount' => 45.5]);

    $html = $this->get("/checkout?id={$kid->reference_code}")->assertOk()->getContent();

    expect($html)
        ->toContain('أكمل كفالة سارة')
        ->toContain('المتبقي $4.50 كاملاً')
        ->toContain('data-checkout="remaining">$4.50<')
        ->not->toContain('المصري');

    // رقم البطاقة ورمزها وتاريخها حقولٌ بلا name: لا تدخل FormData أبداً
    foreach (['cardNumber', 'cardCvc', 'cardExpiry', 'cardHolder'] as $id) {
        expect($html)->toMatch('/<input[^>]*id="'.$id.'"(?![^>]*\sname=)[^>]*>/');
    }
});

it('صفحة الكفالة لطفلٍ مكتمل بلا نموذج دفع، ولرمزٍ مجهول 404', function () {
    $kid = profileChild();
    App\Models\Sponsorship::factory()->for($kid)->create(['monthly_amount' => 50]);

    $this->get("/checkout?id={$kid->reference_code}")->assertOk()
        ->assertSee('اكتملت كفالة سارة')
        ->assertDontSee('id="checkoutForm"', false);
    $this->get('/checkout?id=NOPE')->assertNotFound();
    $this->get('/checkout')->assertRedirect('/orphans');
});

it('ترسم صفحة البرامج أسعارها وبنودها من جدولها، وتعدّ من ينتظر في كلٍّ', function () {
    $this->seed(Database\Seeders\ContentSeeder::class);
    $program = App\Models\Program::active()->with('features')->first();
    $program->update(['monthly_price' => 77]);

    // منتظران، ومكفولٌ بالكامل لا يُعدّ
    profileChild(['program_id' => $program->id]);
    profileChild(['program_id' => $program->id]);
    $full = profileChild(['program_id' => $program->id]);
    App\Models\Sponsorship::factory()->for($full)->create(['monthly_amount' => 50]);

    $html = $this->get('/programs')->assertOk()->getContent();

    expect($html)
        ->toContain('$77')
        ->toContain($program->features->first()->label_ar)
        ->toContain('<b class="num">2</b> طفلان ينتظران')
        ->toContain('href="/orphans?program='.$program->slug.'"');
});
