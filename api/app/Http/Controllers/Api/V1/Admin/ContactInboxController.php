<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ContactStatus;
use App\Http\Controllers\Controller;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * صندوق «تواصل معنا»: استفسارات زوار الموقع.
 *
 * الوعد المنشور «نردّ خلال 72 ساعة». القائمة تُرتَّب بما يقترب من إخلافه،
 * والرد يُرسل من المنصة نفسها ويُحفظ، فيعرف من يأتي بعدك ماذا قيل ومن قاله.
 */
class ContactInboxController extends Controller
{
    /** وعد الرد المنشور على صفحة «تواصل معنا» */
    private const PROMISE_HOURS = 72;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $status = $request->string('status')->toString();
        $open = $status === '' || $status === 'open' || $request->boolean('open_only');

        $items = $this->filtered($request)
            ->with('handler:id,name')
            ->withCount('replies')
            ->when($status === 'open' || $request->boolean('open_only'), fn ($q) => $q->open())
            ->when(in_array($status, ContactStatus::values(), true), fn ($q) => $q->where('status', $status))
            // المفتوحة الأقدم أولاً: هي الأقرب إلى تجاوز الوعد
            ->when($open && $status !== 'closed', fn ($q) => $q->oldest(), fn ($q) => $q->latest())
            ->paginate($this->perPage($request, 30));

        $base = $this->filtered($request);
        $counts = (clone $base)->toBase()->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);

        return response()->json([
            'data' => collect($items->items())->map(fn (ContactMessage $m) => $this->present($m)),
            'meta' => [
                'total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'status_counts' => $counts,
                'all_count' => $counts->sum(),
                'open_count' => ($counts['new'] ?? 0) + ($counts['in_progress'] ?? 0),
                'overdue_count' => (clone $base)->open()->where('created_at', '<', now()->subHours(self::PROMISE_HOURS))->count(),
                'promise_hours' => self::PROMISE_HOURS,
            ],
        ]);
    }

    public function show(Request $request, ContactMessage $contact): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $contact->load(['handler:id,name', 'replies.author:id,name']);

        return response()->json(['data' => [
            ...$this->present($contact),
            'replies' => $contact->replies->map(fn ($r) => [
                'id' => $r->id,
                'body' => $r->body,
                'author' => $r->author?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
        ]]);
    }

    /**
     * الرد من المنصة: يصل الزائر من عنوان المنظمة، ويُحفظ في الرسالة، وتنتقل
     * حالتها (قيد المعالجة، أو مغلقة إن طُلب). كان الرد بزرّ mailto يخرج من
     * بريد الموظف الشخصي بلا أثر.
     */
    public function reply(Request $request, ContactMessage $contact): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'close' => ['sometimes', 'boolean'],
        ], [
            'body.required' => 'اكتب الرد.',
            'body.min' => 'الرد أقصر من أن يجيب. وضّحه أكثر.',
        ]);

        $reply = DB::transaction(function () use ($contact, $validated, $request) {
            $reply = $contact->replies()->create(['user_id' => $request->user()->id, 'body' => $validated['body']]);

            $contact->update([
                'status' => ($validated['close'] ?? false) ? ContactStatus::Closed : ContactStatus::InProgress,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ]);

            return $reply;
        });

        // بلغة الصفحة التي كتب منها: قالب الردّ وموضوعه، والردّ نفسه بما كتبه الفريق
        Mail::to($contact->email, $contact->name)->locale($contact->locale ?: 'ar')->send(new ContactReplyMail($contact, $validated['body']));

        activity()->performedOn($contact)->causedBy($request->user())->event('replied')
            ->withProperties(['reply_id' => $reply->id, 'closed' => (bool) ($validated['close'] ?? false)])
            ->log('ردّ على رسالة تواصل');

        return response()->json([
            'message' => ($validated['close'] ?? false) ? 'أُرسل الرد وأُغلقت الرسالة.' : 'أُرسل الرد إلى بريد المرسل.',
            'data' => $this->present($contact->fresh()->load('handler:id,name')->loadCount('replies')),
        ]);
    }

    /**
     * الفلاتر المشتركة بين القائمة وعدّاداتها، عدا الحالة
     *
     * @return Builder<ContactMessage>
     */
    private function filtered(Request $request): Builder
    {
        return ContactMessage::query()
            ->when($request->filled('topic'), fn ($q) => $q->where('subject', $request->string('topic')))
            ->when($request->boolean('overdue'), fn ($q) => $q->open()->where('created_at', '<', now()->subHours(self::PROMISE_HOURS)))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('body', 'like', "%{$term}%");
            }));
    }

    /** @return array<string, mixed> */
    private function present(ContactMessage $m): array
    {
        // المرسل كافلٌ مسجَّل؟ يُرى ملفه قبل الرد
        static $sponsors = [];
        $email = mb_strtolower((string) $m->email);
        $sponsors[$email] ??= Sponsor::query()->whereHas('user', fn ($u) => $u->where('email', $email))->first(['id', 'sponsor_code']);
        $sponsor = $sponsors[$email];

        $status = $m->status->value;

        return [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'phone' => $m->phone,
            'subject' => $m->subject,
            'body' => $m->body,
            'status' => $status,
            'status_label' => $m->status->label(),
            'created_at' => $m->created_at?->toIso8601String(),
            'handled_at' => $m->handled_at?->toIso8601String(),
            'handler' => $m->relationLoaded('handler') && $m->handler ? ['id' => $m->handler->id, 'name' => $m->handler->name] : null,
            'replies_count' => $m->replies_count ?? null,
            'sponsor' => $sponsor ? ['id' => $sponsor->id, 'code' => $sponsor->sponsor_code] : null,
        ];
    }
}
