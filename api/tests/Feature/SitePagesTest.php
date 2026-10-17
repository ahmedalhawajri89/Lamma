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
