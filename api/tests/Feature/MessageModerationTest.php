<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Message;
use App\Models\Sponsorship;
use App\Models\User;
use App\Services\MessageSafety;

/**
 * مراجعة الرسائل: تنبيهات حماية الطفل، والسياق، والطابور الأقدم أولاً،
 * وتسجيل وصول الرسالة للطفل.
 */
function msg(Sponsorship $s, string $body, string $from = 'sponsor', ?MessageStatus $status = null, string $ago = '1 hour'): Message
{
    $m = Message::create(['sponsorship_id' => $s->id, 'sender_type' => $from, 'body' => $body]);
    $m->forceFill(['status' => $status ?? MessageStatus::PendingReview, 'created_at' => now()->sub(\DateInterval::createFromDateString($ago))])->save();

    return $m;
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('يكتشف وسائل التواصل والمال والسؤال عن المسكن، ولا ينبّه على رسالة عادية', function (): void {
    $safety = app(MessageSafety::class);

    $flags = collect($safety->flags('كلّمني على الواتساب 0599 123 456 وأرسل لي حوالة، وين ساكنين؟'))->pluck('type')->all();
    expect($flags)->toContain('phone', 'social', 'money', 'address');

    expect($safety->flags('أتمنى لك عاماً دراسياً موفقاً، وسلّم على أمك.'))->toBe([]);
});

it('الطابور الأقدم أولاً، وفلتر «فيها تنبيه» يجد ما يستدعي النظر', function (): void {
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);
    $old = msg($s, 'رسالة قديمة بلا شيء', ago: '5 days');
    $flagged = msg($s, 'هذا رقمي 0599123456', ago: '1 day');
    msg($s, 'رسالة معتمدة', status: MessageStatus::Approved);

    $res = $this->getJson('/api/v1/admin/messages?status=pending_review')->assertOk();
    expect($res->json('data.0.id'))->toBe($old->id)
        ->and($res->json('meta.status_counts.pending_review'))->toBe(2)
        ->and($res->json('meta.flagged_pending'))->toBe(1)
        ->and($res->json('data.1.flags.0.type'))->toBe('phone');

    $only = $this->getJson('/api/v1/admin/messages?status=pending_review&flagged=1')->json('data');
    expect($only)->toHaveCount(1)->and($only[0]['id'])->toBe($flagged->id);
});

it('السياق: ما سبق الرسالة في الكفالة نفسها بترتيبه', function (): void {
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);
    $first = msg($s, 'كيف حالك؟', 'sponsor', MessageStatus::Delivered, '3 days');
    $reply = msg($s, 'بخير، شكراً', 'orphan', null, '1 day');
    msg(Sponsorship::factory()->create(), 'كفالة أخرى', ago: '2 days');

    $ids = collect($this->getJson("/api/v1/admin/messages/{$reply->id}/thread")->assertOk()->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$first->id, $reply->id]);
});

it('«وصلت للطفل» للمعتمدة وحدها، ومرة واحدة', function (): void {
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);
    $approved = msg($s, 'رسالة معتمدة', status: MessageStatus::Approved);
    $pending = msg($s, 'لم تُراجع بعد');

    $this->postJson("/api/v1/admin/messages/{$approved->id}/deliver")->assertOk();
    expect($approved->fresh()->status)->toBe(MessageStatus::Delivered);

    $this->postJson("/api/v1/admin/messages/{$approved->id}/deliver")->assertStatus(409);
    $this->postJson("/api/v1/admin/messages/{$pending->id}/deliver")->assertStatus(409);
});
