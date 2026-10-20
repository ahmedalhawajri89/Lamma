<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\Disbursement;
use App\Models\Family;
use App\Models\Message;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * سجل نشاط تجريبي: ستة أسابيع من عمل الفريق على سجلات حقيقية.
 *
 * البذر يُنشئ البيانات دون أن يمرّ بالشاشات، فيبقى السجل فارغاً ولا يُرى
 * شكله ولا تُجرَّب فلاتره. هنا أفعالٌ بصياغة الكود نفسها («سجّل دفعة يدوية»،
 * «اعتمد صرفاً»…)، كلٌّ من الدور الذي يملك صلاحيته، في ساعات الدوام.
 * للعرض والتطوير وحدهما: لا يُستدعى خارج DemoSeeder.
 */
class ActivityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role(UserRole::Admin->value)->where('email', 'admin@lamma.org')->first();
        $accountant = User::role(UserRole::Accountant->value)->first();
        $supervisors = User::role(UserRole::FieldSupervisor->value)->get();
        if (! $admin || ! $accountant || $supervisors->isEmpty()) {
            return;
        }

        $orphans = Orphan::inRandomOrder()->limit(80)->get();
        $families = Family::inRandomOrder()->limit(40)->get();
        $sponsorships = Sponsorship::inRandomOrder()->limit(40)->get();
        $payments = Payment::inRandomOrder()->limit(60)->get();
        $disbursements = Disbursement::inRandomOrder()->limit(40)->get();
        $reports = Report::inRandomOrder()->limit(30)->get();
        $messages = Message::inRandomOrder()->limit(40)->get();
        $contacts = ContactMessage::inRandomOrder()->limit(10)->get();

        $tz = config('kafala.display_timezone');

        // كل فعل: [وزنه، من يفعله، ما يُسجَّل]
        $actions = [
            [14, fn () => $supervisors->random(), function (User $by) use ($orphans) {
                $o = $orphans->random();
                $old = (float) $o->monthly_need;

                return [$o, 'updated', 'updated', ['old' => ['monthly_need' => $old], 'attributes' => ['monthly_need' => $old + [10, 15, 20, 25][array_rand([0, 1, 2, 3])]]]];
            }],
            [8, fn () => $supervisors->random(), fn () => [$families->random(), 'updated', 'updated', ['old' => ['members_count' => 5], 'attributes' => ['members_count' => 6]]]],
            [6, fn () => $supervisors->random(), function () use ($orphans) {
                return [$orphans->random(), 'updated', 'updated', ['old' => ['is_urgent' => false], 'attributes' => ['is_urgent' => true]]];
            }],
            [9, fn () => $admin, fn () => [$orphans->random(), 'document_verified', 'تحقّق من مستند', ['note' => 'شهادة ميلاد']]],
            [2, fn () => $admin, fn () => [$orphans->random(), 'document_rejected', 'رفض مستنداً', ['reason' => 'الصورة غير واضحة، يُعاد التصوير']]],
            [3, fn () => $admin, fn () => [$orphans->random(), 'status_changed', 'غيّر مسار الملف', ['old' => ['status' => 'pending_verification'], 'attributes' => ['status' => 'active']]]],
            [12, fn () => $admin, fn () => [$messages->random(), 'reviewed', 'اعتمد رسالة', ['decision' => 'approved']]],
            [2, fn () => $admin, fn () => [$messages->random(), 'reviewed', 'رفض رسالة', ['decision' => 'rejected', 'reason' => 'فيها رقم هاتف الكافل']]],
            [5, fn () => $admin, fn () => [$messages->random(), 'delivered', 'سلّم رسالة للطفل', []]],
            [10, fn () => $accountant, fn () => [$payments->random(), 'recorded', 'سجّل دفعة يدوية', ['attributes' => ['method' => 'bank_transfer']]]],
            [1, fn () => $accountant, fn () => [$payments->random(), 'refunded', 'استردّ دفعة', ['reason' => 'خُصمت مرتين من بطاقة الكافل']]],
            [8, fn () => $accountant, fn () => [$disbursements->random(), 'recorded', 'سجّل صرفاً', []]],
            [6, fn () => $admin, fn () => [$disbursements->random(), 'approved', 'اعتمد صرفاً', []]],
            [3, fn () => $accountant, fn () => [$sponsorships->random(), 'updated', 'updated', ['old' => ['status' => 'active'], 'attributes' => ['status' => 'paused']]]],
            [5, fn () => $admin, fn () => [$reports->random(), 'approved', 'اعتمد تقريراً', []]],
            [2, fn () => $admin, fn () => [$reports->random(), 'returned', 'أرجع تقريراً للتعديل', ['reason' => 'ينقصه ملخص الصحة']]],
            [3, fn () => $admin, fn () => [$contacts->isNotEmpty() ? $contacts->random() : null, 'replied', 'ردّ على رسالة تواصل', []]],
            [2, fn () => $accountant, fn () => [null, null, 'صدّر المدفوعات', ['rows' => 412]]],
            [1, fn () => $admin, fn () => [null, null, 'صدّر قائمة الأيتام', ['rows' => 150]]],
            [2, fn () => $admin, fn () => [$orphans->random(), null, 'نزّل مستند تحقق', []]],
        ];
        /** @var list<array{int, callable(): User, callable(User): array{Model|null, string|null, string, array<string, mixed>}}> $bag */
        $bag = collect($actions)->flatMap(fn ($a) => array_fill(0, $a[0], $a))->all();

        for ($ago = 41; $ago >= 0; $ago--) {
            $day = Carbon::now($tz)->subDays($ago)->startOfDay();
            if ($day->isFriday()) {
                continue;
            }
            // السبت أخفّ، واليوم حتى الساعة الحالية
            $count = $day->isSaturday() ? random_int(1, 4) : random_int(5, 14);

            for ($i = 0; $i < $count; $i++) {
                $at = $day->copy()->addHours(random_int(8, 16))->addMinutes(random_int(0, 59));
                if ($at->isFuture()) {
                    continue;
                }

                [, $who, $make] = $bag[array_rand($bag)];
                $by = $who();
                [$subject, $event, $description, $props] = $make($by);
                if ($subject === null && $event === 'replied') {
                    continue;
                }

                $log = activity()->causedBy($by)->withProperties($props)->createdAt($at->copy()->utc());
                if ($subject) {
                    $log->performedOn($subject);
                }
                if ($event) {
                    $log->event($event);
                }
                $log->log($description);
            }
        }

        // تعديل إعدادات واحد قبل أسبوعين
        activity()->causedBy($admin)->event('settings_updated')
            ->withProperties(['old' => ['site.phone' => '+970 599 000 001'], 'attributes' => ['site.phone' => '+970 599 000 000']])
            ->createdAt(Carbon::now($tz)->subDays(14)->setTime(11, 20)->utc())
            ->log('عدّل الإعدادات');
    }
}
