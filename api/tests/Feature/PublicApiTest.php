<?php

declare(strict_types=1);

use App\Models\Orphan;
use Database\Seeders\ContentSeeder;

beforeEach(fn () => $this->seed(ContentSeeder::class));

it('يعرض الأيتام المنشورين وحدهم', function () {
    Orphan::factory()->published()->create(['first_name' => 'أمنية']);
    Orphan::factory()->pending()->create(['first_name' => 'سرّي']);

    $response = $this->getJson('/api/v1/public/orphans');

    $response->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.name'))->toBe('أمنية');
});

it('لا يُسرّب اسم العائلة ولا الوصي في الاستجابة العامة', function () {
    // نشر الاسم الكامل مع الصورة والعنوان لطفل يتيم يخلق خطراً حقيقياً عليه
    Orphan::factory()->published()->create([
        'first_name' => 'ليان',
        'last_name' => 'المصري',
    ]);

    // نفحص المصفوفة المفكوكة لا نص JSON الخام: العربية فيه مُهرَّبة بترميز
    // يونيكود، فالبحث عن نص عربي في الخام يفشل دائماً ولو كان موجوداً
    $data = $this->getJson('/api/v1/public/orphans')->json('data.0');

    expect($data['name'])->toBe('ليان')
        ->and($data)->not->toHaveKey('last_name')
        ->and($data)->not->toHaveKey('guardian')
        ->and($data)->not->toHaveKey('family');
});

it('يرفض قيمة فرز خارج القائمة البيضاء', function () {
    // تمرير sort من الطلب إلى orderBy مباشرةً ثغرة حقن كلاسيكية
    $this->getJson('/api/v1/public/orphans?sort=id;DROP')->assertStatus(422);
});

it('يفلتر بالبرنامج', function () {
    $orphan = Orphan::factory()->published()->create();
    Orphan::factory()->published()->create();

    $slug = $orphan->program->slug;

    $response = $this->getJson("/api/v1/public/orphans?program={$slug}");

    expect($response->json('meta.total'))->toBeGreaterThan(0);
});

it('يجد الحالة بمعرّفها العلني', function () {
    // فهرس FULLTEXT على الأسماء لا يغطّي KFL-1042، ولذلك للمعرّف مسار خاص
    Orphan::factory()->published()->create(['reference_code' => 'KFL-1042']);

    $response = $this->getJson('/api/v1/public/orphans?q=KFL-1042');

    expect($response->json('meta.total'))->toBe(1);
});

it('يعيد ما كان في data.js من إعدادات وبرامج ومحتوى', function () {
    $response = $this->getJson('/api/v1/public/bootstrap')->assertOk();

    expect($response->json('data.programs'))->toHaveCount(4)
        ->and($response->json('data.faqs'))->not->toBeEmpty()
        ->and($response->json('data.site.name'))->toBe('لمّة');
});

it('يمنع السبام بحقل مصيدة صامت', function () {
    // الردّ بنجاح كاذب لا بخطأ: الخطأ يعلّم الروبوت كيف يتفادى المصيدة
    $this->postJson('/api/v1/public/contact', [
        'name' => 'روبوت',
        'email' => 'bot@example.com',
        'body' => 'رسالة طويلة بما يكفي للتحقق.',
        'website' => 'http://spam.example',
    ])->assertOk();

    $this->assertDatabaseCount('contact_messages', 0);
});

it('يملأ أرقام الأسئلة الشائعة من الشفافية بدل كتابتها باليد', function () {
    \App\Models\Faq::create([
        'question' => 'سؤال برموز الشفافية',
        'answer' => 'التشغيل {overhead}٪، ويصل للطفل {toChild}٪.',
        'sort_order' => 99,
        'is_published' => true,
    ]);

    $answer = collect($this->getJson('/api/v1/public/faqs')->assertOk()->json('data'))
        ->firstWhere('q', 'سؤال برموز الشفافية')['a'];

    // بلا صرفٍ معتمد في الاختبار: ما يصل للطفل 93 والتشغيل مكمّله، ولا يبقى رمز ولا شرطة
    expect($answer)->toBe('التشغيل 7٪، ويصل للطفل 93٪.')
        ->not->toContain('{')->not->toContain('—');
});
