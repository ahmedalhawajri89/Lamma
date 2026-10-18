<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;

/**
 * لا يخرج من الـ API نصّ استثناء داخلي.
 *
 * فشلُ حفظ الجلسة حين توقفت MySQL أرسل للمتصفح نوع قاعدة البيانات وعنوانها
 * ومنفذها واسمها، والاستعلام بقيمه، ومنها معرّف الجلسة.
 */
beforeEach(function () {
    Route::middleware('api')->get('/api/v1/__test/db-down', function () {
        throw new QueryException(
            'mysql',
            'update `sessions` set `payload` = ? where `id` = ?',
            ['secret-payload', 'SESSIONID123'],
            new PDOException('SQLSTATE[HY000] [2002] Connection refused (Host: 127.0.0.1, Port: 3306, Database: kafala)'),
        );
    });

    Route::middleware('api')->get('/api/v1/__test/boom', fn () => throw new RuntimeException('internal path C:\secret\file.php'));
});

it('يردّ 503 برسالة عامة ورقم مرجع حين لا تردّ قاعدة البيانات', function () {
    $response = $this->getJson('/api/v1/__test/db-down')->assertStatus(503);

    expect($response->json('message'))->toBe('الخدمة غير متاحة مؤقتاً. حاول بعد قليل.')
        ->and($response->json('reference'))->toMatch('/^[A-Z0-9]{8}$/');

    foreach (['SQLSTATE', '127.0.0.1', '3306', 'kafala', 'sessions', 'SESSIONID123', 'secret-payload', 'exception', 'trace', 'file'] as $leak) {
        expect($response->getContent())->not->toContain($leak);
    }
});

it('يخفي نصّ أي خطأ غير متوقع ولو كان وضع التطوير مفعّلاً', function () {
    config(['app.debug' => true]);

    $response = $this->getJson('/api/v1/__test/boom')->assertStatus(500);

    expect($response->getContent())->not->toContain('secret')->not->toContain('RuntimeException')
        ->and($response->json('reference'))->not->toBeNull();
});

it('يُبقي رسائل الأخطاء المكتوبة للمستخدم كما هي', function () {
    $this->getJson('/api/v1/admin/dashboard')->assertStatus(401)->assertJsonPath('message', 'يلزم تسجيل الدخول للمتابعة.');
    $this->getJson('/api/v1/public/orphans/999999')->assertStatus(404)->assertJsonPath('message', 'العنصر المطلوب غير موجود.');
});
