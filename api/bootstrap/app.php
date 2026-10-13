<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * مصادقة Sanctum بنمط SPA: كوكي جلسة HttpOnly لا توكن في localStorage.
         * التوكن هناك يُقرأ بأي ثغرة XSS؛ الكوكي HttpOnly لا يُقرأ من JS إطلاقاً.
         */
        $middleware->statefulApi();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // تحديد المعدّل يُفعَّل لكل مسارات api عبر مجموعات معرّفة في routes/api.php
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * استجابة JSON موحّدة لكل خطأ.
         *
         * بلا هذا يرد Laravel بصفحة HTML على أخطاء معيّنة، فتنكسر الواجهة
         * بأثر «Unexpected token < in JSON» الغامض بدل رسالة مفهومة.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'تعذّر قبول البيانات المُرسلة.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'يلزم تسجيل الدخول للمتابعة.'], 401);
        });

        /**
         * صفحة ٤٠٤ الخاصة بالمشروع لكل طلب متصفح.
         *
         * لماذا هنا لا في `Route::fallback` وحده؟ لأن الـ 404 يأتي من مصدرين:
         * مسار غير مطابق (يلتقطه fallback)، و`abort(404)` من داخل متحكّم —
         * وهذا الثاني يتخطى الموجّه كلياً فيصل إلى صفحة لارافل الافتراضية.
         * المعالج هنا يغطّي المصدرين معاً.
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 404
                && ! $request->is('api/*')
                && ! $request->expectsJson()
                && is_file(public_path('404.html'))) {
                // نقرأ الملف لا نبثّه: BinaryFileResponse يُسقط الجسم مع
                // أي حالة غير ناجحة، فتخرج ٤٠٤ بيضاء (انظر routes/web.php)
                return response(file_get_contents(public_path('404.html')), 404, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]);
            }

            if (! $request->is('api/*')) {
                return null;
            }

            $messages = [
                403 => 'لا تملك صلاحية تنفيذ هذا الإجراء.',
                404 => 'العنصر المطلوب غير موجود.',
                429 => 'محاولات كثيرة. أعد المحاولة بعد قليل.',
            ];

            return response()->json([
                'message' => $messages[$e->getStatusCode()] ?? ($e->getMessage() ?: 'حدث خطأ غير متوقع.'),
            ], $e->getStatusCode());
        });

        /**
         * كل خطأ غير متوقَّع في الـ API يصل العميل برسالة عامة ورقم مرجع، لا بنصّه.
         *
         * نصّ الاستثناء الخام يكشف ما لا يجوز أن يخرج من الخادم: نوع قاعدة
         * البيانات وعنوانها ومنفذها واسمها، وبنية الجداول، وقيم الاستعلام نفسها
         * (ومنها معرّف الجلسة حين يفشل حفظها). وهذا لا يتبع APP_DEBUG: وضع
         * التطوير قد يُترك مفعّلاً خطأً على خادم حقيقي، والتفاصيل مكانها السجل.
         * رقم المرجع يربط ما يراه المستخدم بالسطر في السجل.
         */
        $exceptions->respond(function (SymfonyResponse $response, \Throwable $e, Request $request) {
            // ما رفعناه بأنفسنا (abort) رسالته مكتوبة للمستخدم، وما دون 500 ليس عطلاً
            if (! $request->is('api/*') || $response->getStatusCode() < 500 || $e instanceof HttpExceptionInterface) {
                return $response;
            }

            $reference = Str::upper(Str::random(8));
            Log::error("[{$reference}] {$e->getMessage()}", ['exception' => $e, 'url' => $request->fullUrl()]);

            // قاعدة البيانات لا تردّ: عطل مؤقت في الخدمة، لا خطأ من المستخدم
            $unavailable = $e instanceof QueryException || $e instanceof \PDOException;

            return response()->json([
                'message' => $unavailable
                    ? 'الخدمة غير متاحة مؤقتاً. حاول بعد قليل.'
                    : 'حدث خطأ غير متوقع. إن تكرّر فأبلغ الدعم برقم المرجع.',
                'reference' => $reference,
            ], $unavailable ? 503 : 500);
        });
    })->create();
