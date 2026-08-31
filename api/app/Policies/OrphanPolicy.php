<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrphanStatus;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\User;

/**
 * صلاحيات ملف اليتيم.
 *
 * الصلاحية الاسمية (`orphans.update`) تقول «يجوز له تعديل أيتام».
 * وهذه السياسة تقول «أيَّ أيتام»: المشرف الميداني مقيَّد ببلده المُسنَد.
 * الفصل بينهما مقصود — الأولى تُدار من جدول الأدوار، والثانية منطق أعمال.
 */
class OrphanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orphans.view');
    }

    public function view(User $user, Orphan $orphan): bool
    {
        return $user->can('orphans.view') && $this->withinScope($user, $orphan);
    }

    /**
     * الإنشاء يُفحص على الأسرة لا على اليتيم — فاليتيم لم يوجد بعد.
     *
     * والأسرة هي ما يحمل البلد، فبلا هذا الفحص يستطيع مشرف غزّة أن يُنشئ
     * ملفاً تحت أسرة في بلد آخر، ثم لا يراه بعد ذلك: `view` تحجبه عنه.
     * سجلٌّ يُنشئه من لا يملك رؤيته ليس ثغرة أمنية وحدها، بل بيانات يتيمة.
     */
    public function create(User $user, ?Family $family = null): bool
    {
        if (! $user->can('orphans.create')) {
            return false;
        }

        $assigned = $user->assignedCountryId();

        if ($assigned === null || $user->isAdmin() || $family === null) {
            return true;
        }

        return $family->country_id === $assigned;
    }

    public function update(User $user, Orphan $orphan): bool
    {
        // ملف متوفّى أو منسحب لا يُعدَّل — يُقرأ فقط، حفاظاً على السجل التاريخي
        if (in_array($orphan->status, [OrphanStatus::Deceased, OrphanStatus::Withdrawn], true)) {
            return $user->isAdmin();
        }

        return $user->can('orphans.update') && $this->withinScope($user, $orphan);
    }

    public function verify(User $user, Orphan $orphan): bool
    {
        // من يوثّق ليس من يُنشئ: فصلٌ للمهام يمنع أن يمرّر شخص واحد حالةً كاملة
        return $user->can('orphans.verify') && $this->withinScope($user, $orphan);
    }

    public function publish(User $user, Orphan $orphan): bool
    {
        // لا يُنشر ملف بلا توثيق ولا بلا مستندات — القاعدة هنا لا في الواجهة
        return $user->can('orphans.publish')
            && $orphan->verified_at !== null
            && $this->withinScope($user, $orphan);
    }

    public function delete(User $user, Orphan $orphan): bool
    {
        return $user->can('orphans.delete') && $orphan->sponsorships()->live()->doesntExist();
    }

    /**
     * المشرف الميداني يرى أيتام بلده وحده.
     * المدير والمحاسب بلا بلد مُسنَد، فيريان الجميع.
     */
    private function withinScope(User $user, Orphan $orphan): bool
    {
        $assigned = $user->assignedCountryId();

        if ($assigned === null || $user->isAdmin()) {
            return true;
        }

        return $orphan->relationLoaded('family')
            ? $orphan->family?->country_id === $assigned
            : $orphan->family()->value('country_id') === $assigned;
    }
}
