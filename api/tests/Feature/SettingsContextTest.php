<?php

declare(strict_types=1);

use App\Models\User;

/**
 * الإعدادات تُقرأ بسياقها: الفعلي بجانب المستهدف، وما ينتظر المراجعة،
 * ومن غيّر كل مفتاح ومتى.
 */
it('تُرجع سياق كل إعداد، ومن غيّر المفتاح آخر مرة', function () {
    $admin = User::factory()->admin()->create(['name' => 'مديرة الإعدادات']);

    $this->actingAs($admin)->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertJsonStructure(['meta' => ['overhead_actual', 'overhead_year', 'messages_pending', 'active_orphans', 'last_changed']]);

    $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
        'settings' => [['group' => 'site', 'key' => 'tagline', 'value' => 'وصف جديد']],
    ])->assertOk();

    // المفاتيح فيها نقطة («site.tagline»)، فتُقرأ من المصفوفة لا بمسار
    $last = $this->actingAs($admin)->getJson('/api/v1/admin/settings')->json('meta')['last_changed'];
    expect($last['site.tagline']['by'])->toBe('مديرة الإعدادات')
        ->and($last)->not->toHaveKey('site.name');

    // وتظهر في سجل الإعدادات بقيمتها قبل وبعد
    $this->actingAs($admin)->getJson('/api/v1/admin/activity?event=settings_updated')
        ->assertJsonPath('data.0.changes.0.label', 'الوصف المختصر')
        ->assertJsonPath('data.0.changes.0.after', 'وصف جديد');
});

it('يحرّر المدير اسم المنصة بالإنجليزية فيظهر في الموقع الإنجليزي وحده', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
        'settings' => [['group' => 'site', 'key' => 'name_en', 'value' => 'Circle Test']],
    ])->assertOk();

    expect($this->get('/en')->assertOk()->getContent())->toContain('Circle Test')
        ->and($this->get('/')->assertOk()->getContent())->not->toContain('Circle Test');

    $this->actingAs($admin)->getJson('/api/v1/admin/activity?event=settings_updated')
        ->assertJsonPath('data.0.changes.0.label', 'اسم المنصة بالإنجليزية');
});
