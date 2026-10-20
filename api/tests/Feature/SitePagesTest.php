<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Models\Faq;
use App\Models\Orphan;
use App\Models\Post;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Sponsorship;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\SiteProfile;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
        ->toContain('<title>اكفل يتيماً وهو في أسرته، وتابع أثرك شهراً بشهر | لمّة</title>')
        ->toContain('property="og:title" content="اكفل يتيماً وهو في أسرته')
        ->toContain('property="og:site_name" content="لمّة"')
        ->toContain('property="og:image" content="'.url('/assets/img/og-image.jpg').'"')
        ->toContain('rel="canonical" href="'.url('/').'"');
});

it('يحوّل الرابط القديم بامتداد html إلى العنوان النظيف مع معاملاته', function () {
    $this->get('/index.html')->assertRedirect('/')->assertStatus(301);
    $this->get('/orphan.html?ref=K-12')->assertRedirect('/orphan?ref=K-12');
});

it('يُبرز في التنقل القسم الذي تقف فيه الصفحة العميقة', function () {
    $nav = collect(SiteProfile::flatNav('orphan'))->keyBy('href');

    expect($nav['/orphans']['current'])->toBe('true')
        ->and($nav['/blog']['current'])->toBeNull()
        ->and(collect(SiteProfile::flatNav('blog'))->firstWhere('href', '/blog')['current'])->toBe('page');

    // المجموعة «عن لمّة» تُعلَّم حين تكون في أحد فروعها
    expect(collect(SiteProfile::nav('post'))->firstWhere('children')['current'])->toBe('true')
        ->and(collect(SiteProfile::nav('orphans'))->firstWhere('children')['current'])->toBeNull();
});

/** طفلٌ منشور موثّق، بحاجة 50 في الشهر. */
function profileChild(array $attrs = []): Orphan
{
    return Orphan::factory()->create([
        'status' => OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now(),
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
        ->toContain('لم ينضمّ أحدٌ إلى لمّتها بعد')
        ->not->toContain('المصري');
});

it('تقول صفحة الطفل المكفول جزئياً كم يكفله وكم بقي، وزرّها «أكمل كفالة»', function () {
    $kid = profileChild();
    Sponsorship::factory()->for($kid)->create(['monthly_amount' => 20]);
    Sponsorship::factory()->for($kid)->create(['monthly_amount' => 10]);

    $this->get("/orphan?id={$kid->reference_code}")
        ->assertOk()
        ->assertSee('في لمّتها شخصان')
        ->assertSee('$20')
        ->assertSee('أكمل كفالة سارة');
});

it('تشكر صفحة من اكتملت كفالته كافليه بلا زرّ كفالة', function () {
    $kid = profileChild();
    Sponsorship::factory()->for($kid)->create(['monthly_amount' => 50]);

    $this->get("/orphan?id={$kid->reference_code}")
        ->assertOk()
        ->assertSee('اكتملت لمّتها، شكراً لمن وقف معها.')
        ->assertSee('property="og:title" content="اكتملت كفالة سارة"', false)
        ->assertDontSee('/checkout?id=', false);
});

it('رمزٌ لا طفل له يعطي 404 بصفحة تقول ذلك، وبلا رمز إلى الفهرس', function () {
    Orphan::factory()->create(['status' => OrphanStatus::Draft, 'reference_code' => 'KFL-9999']);

    $this->get('/orphan?id=KFL-9999')->assertNotFound()->assertSee('لم نجد هذا الطفل');
    $this->get('/orphan?id=NOPE')->assertNotFound();
    $this->get('/orphan')->assertRedirect('/orphans');
});

it('ترسم صفحة الكفالة ملخص الطفل من الخادم، وحقول البطاقة بلا name فلا تُرسل', function () {
    $kid = profileChild();
    Sponsorship::factory()->for($kid)->create(['monthly_amount' => 45.5]);

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
    Sponsorship::factory()->for($kid)->create(['monthly_amount' => 50]);

    $this->get("/checkout?id={$kid->reference_code}")->assertOk()
        ->assertSee('اكتملت كفالة سارة')
        ->assertDontSee('id="checkoutForm"', false);
    $this->get('/checkout?id=NOPE')->assertNotFound();
    $this->get('/checkout')->assertRedirect('/orphans');
});

it('ترسم صفحة البرامج أسعارها وبنودها من جدولها، وتعدّ من ينتظر في كلٍّ', function () {
    $this->seed(ContentSeeder::class);
    $program = Program::active()->with('features')->first();
    $program->update(['monthly_price' => 77]);

    // منتظران، ومكفولٌ بالكامل لا يُعدّ
    profileChild(['program_id' => $program->id]);
    profileChild(['program_id' => $program->id]);
    $full = profileChild(['program_id' => $program->id]);
    Sponsorship::factory()->for($full)->create(['monthly_amount' => 50]);

    $html = $this->get('/programs')->assertOk()->getContent();

    expect($html)
        ->toContain('$77')
        ->toContain($program->features->first()->label_ar)
        ->toContain('<b class="num">2</b> طفلان ينتظران')
        ->toContain('href="/orphans?program='.$program->slug.'"');
});

it('ترسم صفحة المقال من الخادم بعنوانها ووسوم مشاركتها ووصفٍ منظَّم، وتعدّ القراءة', function () {
    $post = Post::factory()->create([
        'title' => 'كيف نتحقق من كل حالة', 'excerpt' => 'ثلاث مراحل قبل النشر.',
        'body' => '<p>الأولى.</p><p>الثانية.</p>', 'cover_path' => 'posts/cover.webp',
    ]);

    $html = $this->get('/post?slug='.urlencode($post->slug))->assertOk()->getContent();

    expect($html)
        ->toContain('<title>كيف نتحقق من كل حالة | لمّة</title>')
        ->toContain('<h1 class="post-title" id="postTitle">كيف نتحقق من كل حالة</h1>')
        ->toContain('property="og:type" content="article"')
        ->toContain('property="og:image" content="'.asset('storage/posts/cover.webp').'"')
        ->toContain('"@type":"Article"')
        ->toContain('<p>الثانية.</p>')
        ->and($post->fresh()->views_count)->toBe($post->views_count + 1);
});

it('تُنظَّف نصوص المقال عند العرض ولو كُتبت في القاعدة من غير المحرّر', function () {
    // صفٌّ لم يمرّ بـ RichText عند الحفظ (استيراد، أو بيانات قديمة)
    $post = Post::factory()->create(['body' => '<p>نصّ <script>alert(1)</script><a href="javascript:x" onclick="y()">رابط</a></p><p>ثانية</p>']);
    Post::whereKey($post->id)->update(['body' => '<p>نصّ <script>alert(1)</script><img src=x onerror="y()"><a href="javascript:x" onclick="y()">رابط</a></p><p>ثانية</p>']);

    $html = $this->get('/post?slug='.urlencode($post->slug))->assertOk()->getContent();
    $body = Str::between($html, '<div class="post-body">', '</div>');

    // الصورة مسموحة في المحرّر (src وalt وحدهما)، والسكربت والأحداث وروابط javascript لا
    expect($body)->toContain('ثانية')->toContain('رابط')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');
});

it('«اقرأ أيضاً» من المنشور وحده بلا المقال نفسه، والمجهول والمسودة 404', function () {
    $post = Post::factory()->create(['title' => 'المقال']);
    Post::factory()->create(['title' => 'منشور آخر']);
    $draft = Post::factory()->draft()->create(['title' => 'مسودة سرّية']);

    $related = Str::after($this->get('/post?slug='.urlencode($post->slug))->getContent(), 'id="relatedTitle"');
    expect($related)->toContain('منشور آخر')->not->toContain('مسودة سرّية')->not->toContain('>المقال<');

    $this->get('/post?slug='.urlencode($draft->slug))->assertNotFound()->assertSee('لم نجد هذا المقال');
    $this->get('/post?slug=nope')->assertNotFound();
    $this->get('/post')->assertRedirect('/blog');
});

it('يبني فهرس المقال من عناوينه الفرعية بمراسٍ يولّدها الخادم', function () {
    $post = Post::factory()->create([
        'body' => '<p>مقدمة</p><h2 id="evil" onclick="x()">المرحلة <em>الأولى</em></h2><p>نص</p><h2>المرحلة الثانية</h2><p>نص</p>',
    ]);

    $html = $this->get('/post?slug='.urlencode($post->slug))->assertOk()->getContent();

    expect($html)
        ->toContain('<h2 id="section-1">المرحلة <em>الأولى</em></h2>')
        ->toContain('<a href="#section-2">المرحلة الثانية</a>')
        ->toContain('في هذا المقال')
        ->not->toContain('id="evil"')
        ->not->toContain('onclick');
});

it('أزرار المشاركة روابط المنصات برابط الصفحة المطلق مرمَّزاً، بلا سكربت من خارج الموقع', function () {
    $post = Post::factory()->create(['title' => 'عنوان & تجربة']);
    $url = rawurlencode(url('/post?slug='.urlencode($post->slug)));

    $html = $this->get('/post?slug='.urlencode($post->slug))->assertOk()->getContent();

    expect($html)
        ->toContain('href="https://wa.me/?text='.rawurlencode('عنوان & تجربة').'%20'.$url.'"')
        ->toContain('href="https://www.facebook.com/sharer/sharer.php?u='.$url.'"')
        ->toContain('rel="noopener noreferrer"')
        ->not->toMatch('#<script[^>]+src="https?://#');
});

it('تأخذ صفحة التواصل الهاتف والبريد من الإعدادات، وتعرض أكثر الأسئلة قبل النموذج', function () {
    $this->seed(ContentSeeder::class);
    Setting::query()->updateOrCreate(['group' => 'site', 'key' => 'phone'], ['value' => '+970 599 777 888', 'type' => 'string']);
    Setting::query()->updateOrCreate(['group' => 'site', 'key' => 'email'], ['value' => 'help@kafala.test', 'type' => 'string']);
    Cache::forget('settings');

    $html = $this->get('/contact')->assertOk()->getContent();

    expect($html)
        ->toContain('href="https://wa.me/970599777888"')
        ->toContain('href="tel:+970599777888"')
        ->toContain('href="mailto:help@kafala.test"')
        ->toContain('لعلّ جوابك هنا')
        ->toContain(Faq::published()->first()->question)
        // المصيدة باقية للروبوتات
        ->toContain('name="website"');
});

it('ترسم صفحة الأسئلة أجوبتها من الخادم بمراسٍ ثابتة ووصفٍ منظَّم وأرقامٍ حيّة', function () {
    $this->seed(ContentSeeder::class);

    $html = $this->get('/faq')->assertOk()->getContent();

    expect($html)
        // صفحة الكفالة تربط /faq#zakat
        ->toContain('id="zakat"')
        ->toContain('"@type":"FAQPage"')
        ->toContain(Faq::published()->first()->question)
        // رموز الشفافية تُملأ، لا تُطبع كما هي
        ->not->toContain('{overhead}')
        ->not->toContain('{toChild}');
});

it('صفحة الدخول: مدير كلمات المرور يملأ حقليها، ولا شرط طولٍ على كلمة المرور، ولا أرقام بلا مصدر', function () {
    $html = $this->get('/login')->assertOk()->getContent();

    expect($html)
        ->toContain('autocomplete="username"')
        ->toContain('autocomplete="current-password"')
        ->toMatch('/id="password"[^>]*data-validate="required"/')
        ->not->toContain('minlength:8')
        // كانت تعرض «+1,500 كافل» مكتوبةً باليد
        ->not->toContain('1,500')
        // الاستعادة في البطاقة نفسها لا في نافذة
        ->toContain('data-acc-view="forgot"')
        ->not->toContain('onpaste');
});

/**
 * الاسم من مكانٍ واحد: إعداد site.name.
 *
 * كان «كفالة» مكتوباً في نحو أربعين موضعاً (عناوين الصفحات، وكتل الهوية،
 * والبريد، وJSON-LD)، فتغيير الاسم من الإعدادات يترك الصفحات على القديم.
 */
it('اسم المنصة يأتي من الإعدادات وحدها في كل صفحة وبريد', function () {
    Setting::query()->updateOrCreate(['group' => 'site', 'key' => 'name'], ['value' => 'علامةاختبار', 'type' => 'string']);
    Cache::forget('settings');

    $post = Post::factory()->create(['title' => 'مقال', 'body' => '<p>نص.</p>']);
    $pages = ['/', '/orphans', '/programs', '/transparency', '/blog', '/about', '/contact', '/faq', '/login', '/register',
        '/reset-password', '/thank-you', '/dashboard', '/post?slug='.urlencode($post->slug), '/no-such-page'];

    foreach ($pages as $path) {
        $html = $this->get($path)->getContent();
        preg_match('#<title>(.*?)</title>#su', $html, $title);

        expect($title[1] ?? '')->toEndWith('| علامةاختبار', $path)
            // «كفالة» علامةً: بعد الفاصل، أو اسماً لفريقٍ أو منصة
            ->and($html)->not->toMatch('/\| كفالة|فريق كفالة|منصة كفالة|على كفالة|"name":"كفالة"/u');
    }

    $user = User::factory()->create();
    $mail = (new ResetPasswordNotification('t'))->toMail($user);
    expect($mail->subject)->toContain('علامةاختبار')->and($mail->salutation)->toContain('علامةاختبار');
});
