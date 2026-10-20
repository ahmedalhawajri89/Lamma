<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** سجل مدفوعات الكافل — كان أربعة صفوف ثابتة في dashboard.html. */
class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sponsor = $request->user()->sponsor;
        abort_if($sponsor === null, 403);

        $payments = Payment::query()
            ->where('sponsor_id', $sponsor->id)
            ->with(['sponsorship', 'sponsorship.orphan:id,reference_code,first_name,translations,gender,photo_path,date_of_birth,funded_percent'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('year'), fn ($q) => $q->whereYear('paid_at', $request->integer('year')))
            // الترتيب بتاريخ الحدث لا الإدخال. و`ORDER BY paid_at DESC` وحده
            // يُنزل كل الدفعات الفاشلة إلى القاع لأن paid_at فيها NULL،
            // فتضيع من سياقها الزمني — و COALESCE يبقيها في شهرها.
            ->orderByRaw('COALESCE(paid_at, created_at) DESC')
            ->paginate($this->perPage($request, 20));

        return response()->json([
            'data' => PaymentResource::collection($payments->items()),
            'meta' => [
                'total' => $payments->total(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total_given' => round((float) Payment::where('sponsor_id', $sponsor->id)->settled()->sum('amount_base'), 2),
            ],
        ]);
    }
}
