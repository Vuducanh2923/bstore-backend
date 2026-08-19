<?php

namespace App\Providers;

use App\Support\RequestMetrics;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RequestMetrics::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPerformanceInstrumentation();
    }

    // Tạo hoặc lưu hiệu năng đo lường.
    private function registerPerformanceInstrumentation(): void
    {
        DB::listen(fn () => app(RequestMetrics::class)->databaseQueryCount++);
        Http::globalRequestMiddleware(function ($request) {
            $metrics = app(RequestMetrics::class);
            $metrics->externalCallCount++;

            return $metrics->requestId !== '' ? $request->withHeader('X-Request-ID', $metrics->requestId) : $request;
        });
        Event::listen(ResponseReceived::class, fn (ResponseReceived $event) => $this->logSlowExternalCall($event));
    }

    // Thực hiện log slow external call.
    private function logSlowExternalCall(ResponseReceived $event): void
    {
        $handlerStats = method_exists($event->response, 'handlerStats')
            ? $event->response->handlerStats()
            : [];
        $durationMs = (float) data_get($handlerStats, 'total_time', 0) * 1000;
        if ($durationMs < (int) config('services.performance.slow_external_call_ms', 1000)) {
            return;
        }
        $url = $event->request->url();
        Log::warning('performance.slow_external_call', [
            'request_id' => app(RequestMetrics::class)->requestId,
            'target' => parse_url($url, PHP_URL_HOST).parse_url($url, PHP_URL_PATH),
            'status_code' => method_exists($event->response, 'status')
                ? $event->response->status()
                : $event->response->getStatusCode(),
            'duration_ms' => round($durationMs, 2),
        ]);
    }
}
