<?php

use App\Http\Middleware\AdminToken;
use App\Http\Middleware\InternalService;
use App\Http\Middleware\RequestPerformance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestPerformance::class);
        $middleware->alias([
            'admin.token' => AdminToken::class,
            'internal.service' => InternalService::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (ValidationException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Dữ liệu không hợp lệ.', 'errors' => $exception->errors()], 422)
            : null);
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Bạn chưa đăng nhập.', 'data' => null], 401)
            : null);
        $exceptions->render(fn (AuthorizationException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => $exception->getMessage() ?: 'Bạn không có quyền thực hiện chức năng này.', 'data' => null], 403)
            : null);
        $exceptions->render(fn (ModelNotFoundException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Không tìm thấy dữ liệu.', 'data' => null], 404)
            : null);
        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Không tìm thấy tài nguyên.', 'data' => null], 404)
            : null);
        $exceptions->render(fn (ThrottleRequestsException $exception, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Bạn thao tác quá nhanh, vui lòng thử lại sau.', 'data' => null], 429)
            : null);
        $exceptions->render(fn (Throwable $exception, Request $request) => $request->is('api/*') && ! $exception instanceof HttpExceptionInterface
            ? response()->json(['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống.', 'data' => null], 500)
            : null);
    })->create();
