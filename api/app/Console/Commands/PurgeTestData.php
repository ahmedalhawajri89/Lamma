<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Api\V1\Public\CatalogController;
use App\Models\ContactMessage;
use App\Models\Disbursement;
use App\Models\Family;
use App\Models\FieldVisit;
use App\Models\Guardian;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Sponsorship;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تنظيف قاعدة التطوير مما تركته اختبارات E2E قبل أن تنتقل إلى قاعدتها.
 *
 * كانت الاختبارات تكتب في قاعدة التطوير: أيتام «ليان12345»، وأسر «أم خالد
 * 12345»، و«موظف اختباري»، و«مقال اختباري» منشور على الموقع العام، ودفعات
 * يدوية قدّمت موعد قسطِ كفالةٍ سنةً كاملة. يُعرَف كلٌّ منها بعلامته التي
 * يكتبها الاختبار، ولا يُمسّ شيء غيرها. `--dry` يعرض ولا يحذف.
 *
 * و`--seed-fixes` يصحّح ما أدخله الـ seeder القديم: صرفٌ بتاريخ مستقبلي.
 */
class PurgeTestData extends Command
{
    protected $signature = 'kafala:purge-test-data
                            {--dry : اعرض ما سيُحذف بلا حذف}
                            {--seed-fixes : واحذف أيضاً الصرف المؤرَّخ في المستقبل من الـ seeder القديم}';

    protected $description = 'حذف بيانات اختبارات E2E المتراكمة في قاعدة التطوير';

    public function handle(MetricsService $metrics): int
    {
        abort_if(app()->environment('production'), 1, 'لا يعمل على الإنتاج.');

        $dry = (bool) $this->option('dry');

        $orphans = Orphan::withTrashed()->whereRaw("first_name REGEXP '^ليان[0-9]+$'");
        $families = Family::whereHas('guardian', fn ($g) => $g->whereRaw("full_name REGEXP '^أم خالد [0-9]+'"));
        // النطاق القديم (قبل اسم «لمّة») والجديد: بقايا تشغيلاتٍ سابقة تُنظَّف أيضاً
        $users = User::where('name', 'موظف اختباري')
            ->where(fn ($q) => $q->where('email', 'like', 'staff%@lamma.org')->orWhere('email', 'like', 'staff%@kafala.org'));
        $posts = Post::where('title', 'like', 'مقال اختباري%');
        $disbursements = Disbursement::where('description', 'like', 'اختبار %');
        $reports = Report::where('title', 'like', 'تقرير اختباري%');
        $visits = FieldVisit::where('findings', 'like', 'زرت البيت صباحاً. الأسرة بخير%');
        $contacts = ContactMessage::where('email', 'like', 'visitor%@example.com');

        // الدفعات اليدوية من الاختبار: بوابة «manual» بلا بطاقة، ومرجعها TRX- أو بلا مرجع
        // (قبل حفظ المرجع). دفعات الـ seeder كلها من «demo-gateway».
        $payments = Payment::where('gateway', 'manual')
            ->whereNull('card_last4')
            ->where(fn ($q) => $q->whereNull('gateway_reference')->orWhere('gateway_reference', 'like', 'TRX-%'));

        $future = Disbursement::whereDate('spent_on', '>', now()->toDateString());

        $rows = [
            ['أيتام اختباريون', (clone $orphans)->count()],
            ['أسر اختبارية', (clone $families)->count()],
            ['حسابات «موظف اختباري»', (clone $users)->count()],
            ['مقالات اختبارية', (clone $posts)->count()],
            ['صرف اختباري', (clone $disbursements)->count()],
            ['تقارير اختبارية', (clone $reports)->count()],
            ['زيارات اختبارية', (clone $visits)->count()],
            ['رسائل تواصل اختبارية', (clone $contacts)->count()],
            ['دفعات يدوية اختبارية', (clone $payments)->count()],
        ];
        if ($this->option('seed-fixes')) {
            $rows[] = ['صرف بتاريخ مستقبلي (seeder قديم)', (clone $future)->count()];
        }

        $this->table(['البيان', 'العدد'], $rows);

        if ($dry) {
            $this->info('تجربة: لم يُحذف شيء.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphans, $families, $users, $posts, $disbursements, $reports, $visits, $contacts, $payments, $future): void {
            /*
             * كل دفعة قدّمت موعد قسط كفالتها شهراً، فحذفها يعيده بعدد ما حُذف
             * منها. كفالة واحدة سُجّلت عليها تسع دفعات اختبار صار موعدها بعد سنة.
             */
            $perSponsorship = (clone $payments)->selectRaw('sponsorship_id, COUNT(*) AS n')->groupBy('sponsorship_id')->pluck('n', 'sponsorship_id');
            (clone $payments)->delete();
            foreach ($perSponsorship as $id => $n) {
                $s = Sponsorship::find($id);
                if ($s?->next_due_at) {
                    $s->next_due_at = $s->next_due_at->copy()->subMonthsNoOverflow((int) $n);
                    $s->save();
                }
            }

            $reports->delete();
            $visits->delete();
            $disbursements->delete();
            $contacts->delete();
            $posts->delete();

            // الأيتام بمستنداتهم وتقاريرهم وزياراتهم، ثم الأسر الفارغة وأوصياؤها
            foreach ((clone $orphans)->get() as $orphan) {
                $orphan->documents()->delete();
                $orphan->reports()->delete();
                $orphan->fieldVisits()->delete();
                $orphan->forceDelete();
            }
            foreach ((clone $families)->withCount('orphans')->get() as $family) {
                if ($family->orphans_count === 0) {
                    $guardian = $family->guardian_id;
                    $family->delete();
                    Guardian::whereKey($guardian)->delete();
                }
            }

            foreach ((clone $users)->get() as $user) {
                StaffProfile::where('user_id', $user->id)->delete();
                $user->syncRoles([]);
                $user->forceDelete();
            }

            if ($this->option('seed-fixes')) {
                $future->delete();
            }
        });

        CatalogController::forgetBootstrap();
        Cache::forget('transparency.proof');
        Cache::forget('transparency.spending.'.now()->year);
        $metrics->flushCache();

        $this->info('نُظّفت بيانات الاختبار. شغّل metrics:rollup --all لتحديث المؤشرات.');

        return self::SUCCESS;
    }
}
