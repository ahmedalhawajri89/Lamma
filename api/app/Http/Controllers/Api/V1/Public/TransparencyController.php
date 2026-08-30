<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\TransparencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الأرقام التي يعِد بها الموقع.
 *
 * كانت `SPENDING` و `PROOF` مصفوفتين مكتوبتين بخط اليد في data.js. صارت
 * استعلامات على صرفٍ معتمَد — وهذا هو الفرق بين موقع يقول إنه شفّاف وموقع
 * يُثبته.
 */
class TransparencyController extends Controller
{
    public function __construct(private readonly TransparencyService $transparency) {}

    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->integer('year', (int) now()->year);

        return response()->json([
            'data' => [
                'spending' => $this->transparency->spendingBreakdown($year),
                'proof' => $this->transparency->proof(),
                'annual' => $this->transparency->annual($year),
            ],
        ]);
    }

    /** لوحة الثقة في الصفحة الرئيسية وحدها — أخفّ استجابة وأكثرها طلباً. */
    public function proof(): JsonResponse
    {
        return response()->json(['data' => $this->transparency->proof()]);
    }
}
