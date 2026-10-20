<?php

declare(strict_types=1);
use App\Models\Faq;

/**
 * لغتا الموقع: العربية بلا بادئة كما كانت، والإنجليزية تحت /en، ولكلٍّ
 * اتجاهها وروابطها. وكل رابطٍ داخلي في صفحةٍ إنجليزية يبقى في الإنجليزية.
 */
it('تُرسم كل صفحة باللغتين بالاتجاه والـ Bootstrap المناسبين', function () {
    foreach (['/', '/orphans', '/programs', '/login', '/register'] as $path) {
        $ar = $this->get($path)->assertOk()->getContent();
        $en = $this->get('/en'.($path === '/' ? '' : $path))->assertOk()->getContent();

        expect($ar)->toContain('<html lang="ar" dir="rtl"')->toContain('bootstrap.rtl.min.css')
            ->and($en)->toContain('<html lang="en" dir="ltr"')->toContain('css/bootstrap.min.css');
    }
});

it('تُعلن كل صفحة نسختها الأخرى (hreflang) وتتّجه إليها canonical بلغتها', function () {
    $html = $this->get('/en/orphans')->assertOk()->getContent();

    expect($html)
        ->toContain('hreflang="ar" href="'.url('/orphans').'"')
        ->toContain('hreflang="en" href="'.url('/en/orphans').'"')
        ->toContain('hreflang="x-default" href="'.url('/orphans').'"')
        ->toContain('rel="canonical" href="'.url('/en/orphans').'"');
});

it('روابط الصفحة الإنجليزية الداخلية كلها تبدأ بـ /en', function () {
    foreach (['/en', '/en/orphans', '/en/login', '/en/programs', '/en/about', '/en/faq', '/en/contact', '/en/transparency', '/en/blog', '/en/dashboard'] as $page) {
        preg_match_all('#href="(/[^"]*)"#', $this->get($page)->getContent(), $m);
        $internal = array_filter($m[1], fn ($h) => ! preg_match('#^/(assets|storage|admin)/#', $h));

        foreach ($internal as $href) {
            expect($href)->toStartWith('/en', "{$page} → {$href}");
        }
    }
});

it('مبدّل اللغة يقصد الصفحة نفسها باللغة الأخرى', function () {
    expect($this->get('/orphans?program=education')->getContent())
        ->toContain('href="'.url('/en/orphans').'?program=education" hreflang="en"');
});

it('صفحة غير موجودة تحت /en تُرسم بالإنجليزية', function () {
    $this->get('/en/no-such-page')->assertNotFound()->assertSee('<html lang="en" dir="ltr"', false);
});

it('الـ API يردّ رسائل التحقق بلغة الصفحة (X-Locale)، لا بلغة المتصفح', function () {
    $en = $this->postJson('/api/v1/auth/login', [], ['X-Locale' => 'en'])->assertUnprocessable();
    $ar = $this->postJson('/api/v1/auth/login', [], ['Accept-Language' => 'en-US,en'])->assertUnprocessable();

    expect($en->json('errors.email.0'))->toBe('Email is required.')
        ->and($ar->json('errors.email.0'))->not->toContain('required');
});

/*
 * تغطية الترجمة في رحلة الكافل: كل نصٍّ عربي يمرّ بـ __() في قوالبها وردود
 * الـ API، وبـ t() في وحداتها، له مقابلٌ إنجليزي. نصٌّ جديد بلا ترجمة يُفشل
 * الاختبار بدل أن يظهر عربياً وسط صفحةٍ إنجليزية.
 */
dataset('journey', [
    'القوالب' => [[
        'resources/views/site/partials/{header,footer,head,lang-switch,share,search}.blade.php',
        'resources/views/site/layouts/*.blade.php',
        'resources/views/site/auth/*.blade.php',
        'resources/views/site/pages/{home,orphans,orphan,checkout,thank-you,partner,programs,about,faq,contact,transparency,blog,post}.blade.php',
        'resources/views/site/partials/post-card.blade.php',
        'resources/views/site/portal/*.blade.php',
        'resources/views/emails/*.blade.php',
        'app/Mail/*.php',
        'app/Notifications/*.php',
        'app/Models/Report.php',
        'resources/views/errors/404.blade.php',
        'app/Enums/*.php',
        'app/Http/Controllers/Api/V1/{Auth,Public,Portal}/*.php',
        'app/Http/Controllers/Site/*.php',
        'app/Http/Requests/Auth/*.php',
        'bootstrap/app.php',
    ]],
]);

/** @return array<int, string> النصوص الحرفية في __('…') أو t('…') */
function literalKeys(array $globs, string $fn): array
{
    $keys = [];
    foreach ($globs as $glob) {
        foreach (glob(base_path($glob), GLOB_BRACE) as $file) {
            $str = "'((?:[^'\\\\]|\\\\.)*)'";
            preg_match_all('/\b'.$fn.'\((?:[\w.!=?\s]+ \? )?'.$str.'(?: : '.$str.')?/u', file_get_contents($file), $m);
            array_push($keys, ...array_filter([...$m[1], ...$m[2]]));
        }
    }

    return array_values(array_unique(array_map(fn ($k) => stripcslashes($k), $keys)));
}

it('كل نصٍّ في قوالب رحلة الكافل وردودها له ترجمة إنجليزية', function (array $globs) {
    $en = json_decode(file_get_contents(lang_path('en.json')), true);
    $keys = array_filter(literalKeys($globs, '__'), fn ($k) => preg_match('/\p{Arabic}/u', $k));

    expect($keys)->not->toBeEmpty()
        ->and(array_values(array_diff($keys, array_keys($en))))->toBe([])
        // ترجمةٌ فارغة يعاملها Laravel كالغائبة فيُظهر العربي
        ->and(array_keys(array_filter($en, fn ($v) => trim((string) $v) === '')))->toBe([]);
})->with('journey');

it('كل نصٍّ في وحدات JS لرحلة الكافل له ترجمة إنجليزية', function () {
    $en = require lang_path('en/js.php');
    $keys = literalKeys(['public/assets/js/modules/{api,blog,catalog,checkout,data,faq,features,forms,home,layout,portal,profile,proof,render,search,theme,transparency,ui,utils}.js'], 't');

    expect($keys)->not->toBeEmpty()
        ->and(array_values(array_diff($keys, array_keys($en))))->toBe([]);
});

it('المحتوى المترجم يصل بلغة الطلب، والعربي حين لا ترجمة', function () {
    // الترجمة ليست في fillable: تكتبها البذرة اليوم، ولوحة الإدارة لاحقاً
    (new Faq)->forceFill([
        'question' => 'سؤال مترجم؟', 'answer' => 'جواب.', 'category' => 'عام', 'is_published' => true,
        'translations' => ['en' => ['question' => 'A translated question?', 'answer' => 'An answer.']],
    ])->save();
    Faq::create(['question' => 'سؤال بلا ترجمة؟', 'answer' => 'جواب.', 'is_published' => true]);

    $en = collect($this->getJson('/api/v1/public/bootstrap', ['X-Locale' => 'en'])->assertOk()->json('data.faqs'))->pluck('q');
    $ar = collect($this->getJson('/api/v1/public/bootstrap')->assertOk()->json('data.faqs'))->pluck('q');

    expect($en)->toContain('A translated question?')->toContain('سؤال بلا ترجمة؟')
        ->and($ar)->toContain('سؤال مترجم؟');
});
