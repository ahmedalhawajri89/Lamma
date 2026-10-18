<?php

declare(strict_types=1);

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
    foreach (['/en', '/en/orphans', '/en/login'] as $page) {
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

it('الـ API يردّ رسائل التحقق بلغة الصفحة (Accept-Language)', function () {
    $en = $this->postJson('/api/v1/auth/login', [], ['Accept-Language' => 'en'])->assertUnprocessable();
    $ar = $this->postJson('/api/v1/auth/login', [], ['Accept-Language' => 'ar'])->assertUnprocessable();

    expect($en->json('errors.email.0'))->toBe('Email is required.')
        ->and($ar->json('errors.email.0'))->not->toContain('required');
});
