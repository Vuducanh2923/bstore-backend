<?php

namespace App\Http\Middleware;

use App\Support\RequestMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestPerformance
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly RequestMetrics $metrics) {}

    // Xử lý dữ liệu theo nghiệp vụ của hàm.
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) ($request->header('X-Request-ID') ?: Str::uuid());
        $this->metrics->reset($requestId);
        $request->headers->set('X-Request-ID', $requestId);
        $startedAt = hrtime(true);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        Log::info('performance.request', [
            'request_id' => $requestId, 'service' => (string) config('app.name'),
            'endpoint' => '/'.$request->path(), 'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            'database_query_count' => $this->metrics->databaseQueryCount,
            'external_call_count' => $this->metrics->externalCallCount,
            'response_size_bytes' => strlen((string) $response->getContent()),
        ]);

        return $response;
    }
}
