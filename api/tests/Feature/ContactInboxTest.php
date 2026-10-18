<?php

declare(strict_types=1);

use App\Enums\ContactStatus;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * صندوق «تواصل معنا»: الرد من المنصة، والمتأخرة عن الوعد، والكافل المعروف.
 */
function inquiry(array $extra = []): ContactMessage
{
    return ContactMessage::create(['name' => 'زائر', 'email' => 'visitor@example.com', 'subject' => 'general', 'body' => 'سؤال عن الكفالة الشهرية', 'ip_address' => '10.0.0.1', ...$extra]);
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('الرد يُرسل بريداً ويُحفظ، وينقل الحالة، و«أرسل وأغلق» يغلقها', function (): void {
    Mail::fake();
    $m = inquiry();

    $this->postJson("/api/v1/admin/contacts/{$m->id}/reply", ['body' => 'شكراً لسؤالك، الكفالة تبدأ من 35 دولاراً.'])
        ->assertOk()->assertJsonPath('data.status', 'in_progress');
    Mail::assertSent(ContactReplyMail::class, fn ($mail) => $mail->hasTo('visitor@example.com'));

    $this->postJson("/api/v1/admin/contacts/{$m->id}/reply", ['body' => 'وأرفقنا لك رابط البرامج كذلك.', 'close' => true])
        ->assertOk()->assertJsonPath('data.status', 'closed');

    $replies = $this->getJson("/api/v1/admin/contacts/{$m->id}")->assertOk()->json('data.replies');
    expect($replies)->toHaveCount(2)->and($replies[0]['author'])->not->toBeNull();

    $this->postJson("/api/v1/admin/contacts/{$m->id}/reply", ['body' => 'قصير'])->assertUnprocessable();
});

it('يعدّ الحالات والمتأخرة عن الوعد، ويعرف الكافل المسجَّل', function (): void {
    $sponsor = Sponsor::factory()->create();
    $late = inquiry(['email' => $sponsor->user->email]);
    ContactMessage::whereKey($late->id)->update(['created_at' => now()->subHours(80)]);
    inquiry(['subject' => 'complaint']);
    inquiry(['status' => ContactStatus::Closed]);

    $res = $this->getJson('/api/v1/admin/contacts?status=open')->assertOk();
    expect($res->json('meta'))
        ->open_count->toBe(2)
        ->overdue_count->toBe(1)
        ->and($res->json('data.0.id'))->toBe($late->id)
        ->and($res->json('data.0.sponsor.id'))->toBe($sponsor->id);

    expect($this->getJson('/api/v1/admin/contacts?topic=complaint')->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/v1/admin/contacts?overdue=1')->json('meta.total'))->toBe(1);
});
