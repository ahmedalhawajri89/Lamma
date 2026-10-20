<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Support\Fx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * أسعار الصرف في اللوحة: ما يُحسب به ما يعادل دفعات الكفلاء بعملاتهم
 * والصرف بالعملات المحلية، ومنذ متى لم يُحدَّث. والتحديث اليدوي يشغّل
 * الأمر اليومي نفسه (kafala:rates-refresh) لا مساراً آخر.
 */
class ExchangeRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('payments.view') || $request->user()->can('settings.manage'), 403);

        $staleAfter = now()->subDays((int) config('kafala.fx.stale_after_days', 3));
        $sponsor = Fx::sponsorCurrencies();

        return response()->json([
            'data' => ExchangeRate::query()->orderBy('currency')->get()->map(fn (ExchangeRate $r) => [
                'currency' => $r->currency,
                'rate' => (float) $r->rate_to_base,
                'per_base' => $r->rate_to_base > 0 ? round(1 / (float) $r->rate_to_base, 4) : null,
                'source' => $r->source,
                'fetched_at' => $r->fetched_at?->toIso8601String(),
                'stale' => $r->currency !== Fx::base() && ($r->fetched_at === null || $r->fetched_at->lt($staleAfter)),
                'for_sponsors' => in_array($r->currency, $sponsor, true),
            ])->values(),
            'meta' => ['base' => Fx::base()],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $code = Artisan::call('kafala:rates-refresh');
        $output = trim(Artisan::output());

        activity('settings')->causedBy($request->user())->event('settings_updated')
            ->withProperties(['result' => $output])->log('حدّث أسعار الصرف يدوياً');

        return response()->json(['message' => $output ?: 'حُدّثت الأسعار.'], $code === 0 ? 200 : 502);
    }
}
