<?php

declare(strict_types=1);

use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;

/**
 * نموذجا التواصل والنشرة.
 *
 * كلاهما مفتوح للعالم بلا مصادقة، فكلاهما هدفٌ للسبام ولإساءة الاستعمال.
 * ما يُختبَر هنا هو الحماية لا السعادة: المصيدة، ومنع التكرار، ومنع استعمال
 * النموذج لإغراق بريد شخص آخر.
 */
it('يحفظ رسالة التواصل مع عنوان المُرسِل', function () {
    $response = $this->postJson('/api/v1/public/contact', [
        'name' => 'زائر',
        'email' => 'visitor@example.com',
        'subject' => 'sponsorship',
        'body' => 'سؤال عن بند المصاريف الإدارية في تقرير الربع الثالث.',
    ]);

    $response->assertCreated();

    $message = ContactMessage::sole();

    expect($message->name)->toBe('زائر')
        ->and($message->status->value)->toBe('new')
        ->and($message->ip_address)->not->toBeNull();
});

it('يرفض رسالة تواصل أقصر من أن تكون سؤالاً', function () {
    $this->postJson('/api/v1/public/contact', [
        'name' => 'زائر',
        'email' => 'visitor@example.com',
        'body' => 'مرحبا',
    ])->assertStatus(422);

    $this->assertDatabaseCount('contact_messages', 0);
});

it('لا يُنشئ صفاً ثانياً حين يشترك البريد نفسه مرتين', function () {
    $payload = ['email' => 'reader@example.com', 'source' => 'footer'];

    $this->postJson('/api/v1/public/newsletter', $payload)->assertCreated();
    $this->postJson('/api/v1/public/newsletter', $payload)->assertCreated();

    $this->assertDatabaseCount('newsletter_subscribers', 1);
});

it('لا يَعُدّ المشترك مؤكَّداً قبل ضغطه رابط بريده', function () {
    /**
     * التأكيد المزدوج ليس تفصيلاً إجرائياً: بلا هذا الشرط يستطيع أي شخص أن
     * يُدخل بريد غيره في النموذج فيغرقه برسائلنا. الاشتراك لا يكتمل إلا
     * بفعلٍ من صاحب البريد نفسه.
     */
    $this->postJson('/api/v1/public/newsletter', ['email' => 'reader@example.com']);

    $subscriber = NewsletterSubscriber::sole();

    expect($subscriber->confirmed_at)->toBeNull()
        ->and($subscriber->confirm_token)->not->toBeNull()
        ->and(NewsletterSubscriber::confirmed()->count())->toBe(0);
});

it('لا يُخرج رمز التأكيد في أي استجابة', function () {
    // الرمز مفتاح تفعيل الاشتراك؛ تسريبه يسمح لغير صاحب البريد بتأكيده
    $this->postJson('/api/v1/public/newsletter', ['email' => 'reader@example.com']);

    expect(NewsletterSubscriber::sole()->toArray())->not->toHaveKey('confirm_token');
});

it('يوحّد حالة أحرف البريد فلا يتكرر المشترك بصيغتين', function () {
    $this->postJson('/api/v1/public/newsletter', ['email' => 'Reader@Example.com']);
    $this->postJson('/api/v1/public/newsletter', ['email' => 'reader@example.com']);

    $this->assertDatabaseCount('newsletter_subscribers', 1);
});

it('يوقف السبام بعد ستّ محاولات في الدقيقة', function () {
    /**
     * النموذجان مفتوحان للعالم بلا مصادقة، فهما أول ما يُستهدف بالسبام.
     * ستّ محاولات في الدقيقة تكفي أي إنسان بفارق كبير، ولا تكفي روبوتاً.
     *
     * ونختبره هنا لا في المتصفح: تشغيل اختبارات المتصفح مرتين متتاليتين
     * من العنوان نفسه يستهلك الحدّ فيسقط الاختبار لسبب غير حقيقي.
     */
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/public/newsletter', ['email' => "reader{$i}@example.com"])
            ->assertCreated();
    }

    $this->postJson('/api/v1/public/newsletter', ['email' => 'seventh@example.com'])
        ->assertStatus(429);

    $this->assertDatabaseCount('newsletter_subscribers', 6);
});
