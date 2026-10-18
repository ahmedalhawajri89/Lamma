<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Orphan;
use App\Models\Program;
use Illuminate\Contracts\View\View;

/**
 * صفحة البرامج، من جدول البرامج نفسه.
 *
 * كانت بطاقاتها تُبنى في المتصفح، وتحتها جدول مقارنة مكتوبٌ باليد بأسعاره
 * وبنوده: يقول إن «الصحية» تشمل دعماً نفسياً وتقريراً كل ثلاثة أشهر، وبنودها
 * في قاعدة البيانات غير ذلك. الآن السعر والبنود من مصدرٍ واحد يحرّره الفريق
 * من اللوحة، ومع كل برنامج عدد من ينتظر فيه كافلاً الآن.
 */
class ProgramsPageController extends Controller
{
    public function __invoke(): View
    {
        $programs = Program::query()
            ->active()
            ->with('features')
            ->get();

        // من يقبل كفالة الآن في كل برنامج (seekingSponsor: لا من اكتملت كفالته)
        $waiting = Orphan::query()
            ->seekingSponsor()
            ->selectRaw('program_id, count(*) as n')
            ->groupBy('program_id')
            ->pluck('n', 'program_id');

        return view('site.pages.programs', [
            'programs' => $programs,
            'waiting' => $waiting,
        ]);
    }
}
