<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
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

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
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
    })->create();
