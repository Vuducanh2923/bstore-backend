<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GatewayController extends Controller
{
    private const PUBLIC_GET_PATH_PATTERNS = [
        '#^products(?:/(?:sale|new|[0-9]+|[^/]+))?$#',
        '#^categories$#',
        '#^brands$#',
        '#^banners(?:/(?:home|[0-9]+))?$#',
        '#^home/banners$#',
        '#^payments/vnpay/(?:return|ipn)$#',
        '#^docs/openapi\.json$#',
        '#^gateway/health$#',
    ];

    private const PUBLIC_POST_PATH_PATTERNS = [
        '#^auth/(?:register|login|refresh|verify-register-otp|resend-register-otp|forgot-password|verify-forgot-password-otp|reset-password)$#',
    ];

    private const AUTH_ADMIN_PATH_PATTERNS = [
        '#^admin/staff(?:/[0-9]+)?(?:/status)?$#',
        '#^admin/customers(?:/[0-9]+)?(?:/status)?$#',
        '#^admin/users/[0-9]+/role$#',
    ];

    private const ORDER_ADMIN_PATH_PATTERNS = [
        '#^admin/orders(?:/[0-9]+)?(?:/(?:assign|status|payment-status|cancel/(?:approve|reject)|return/(?:approved|received|completed|rejected)))?$#',
        '#^admin/warranty-requests(?:/[0-9]+)?(?:/(?:approve|reject|processing|complete))?$#',
        '#^admin/discount-codes(?:/[0-9]+)?(?:/deactivate)?$#',
    ];

    private const ROUTE_MAP = [
        'auth' => 'auth',
        'profile' => 'auth',
        'roles' => 'auth',
        'users' => 'auth',

        'admin' => 'catalog',
        'banners' => 'catalog',
        'brands' => 'catalog',
        'categories' => 'catalog',
        'home' => 'catalog',
        'inventories' => 'catalog',
        'inventory-transactions' => 'catalog',
        'inventory_transactions' => 'catalog',
        'product-images' => 'catalog',
        'product_images' => 'catalog',
        'product-variants' => 'catalog',
        'product_variants' => 'catalog',
        'products' => 'catalog',
        'uploads' => 'catalog',
        'warranty-policies' => 'catalog',
        'warranty_policies' => 'catalog',

        'cart-items' => 'order',
        'cart_items' => 'order',
        'carts' => 'order',
        'customer' => 'order',
        'discounts' => 'order',
        'order-discounts' => 'order',
        'order_discounts' => 'order',
        'order-histories' => 'order',
        'order_histories' => 'order',
        'order-items' => 'order',
        'order_items' => 'order',
        'orders' => 'order',
        'complaints' => 'order',
        'notifications' => 'order',
        'refunds' => 'order',
        'refund-requests' => 'order',
        'refund_requests' => 'order',
        'warranty-requests' => 'order',
        'warranty_requests' => 'order',

        'invoices' => 'payment',
        'payment-transactions' => 'payment',
        'payment_transactions' => 'payment',
        'payments' => 'payment',
    ];

    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'api-gateway',
        ]);
    }

    public function forward(Request $request, string $path): Response|JsonResponse
    {
        $normalizedPath = trim($path, '/');

        if ($normalizedPath === 'internal' || str_starts_with($normalizedPath, 'internal/')) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tài nguyên.',
            ], 404);
        }

        $isPublic = $this->isPublicRequest($request, $normalizedPath);

        Log::debug('gateway.request.resolved', [
            'method' => $request->method(),
            'path' => $normalizedPath,
            'public' => $isPublic,
            'has_authorization' => $request->hasHeader('Authorization'),
        ]);

        if (! $isPublic) {
            $token = $request->bearerToken();

            if (! $token) {
                return $this->tokenInvalidResponse();
            }

            $accessToken = $this->introspectAccessToken($token);

            if ($accessToken === null) {
                Log::warning('gateway.auth.unavailable', [
                    'method' => $request->method(),
                    'path' => $normalizedPath,
                ]);

                return response()->json([
                    'message' => 'Dịch vụ xác thực hiện không khả dụng.',
                    'code' => 'AUTH_SERVICE_UNAVAILABLE',
                ], 503);
            }

            if ($accessToken === false) {
                Log::notice('gateway.auth.rejected', [
                    'method' => $request->method(),
                    'path' => $normalizedPath,
                    'reason' => 'inactive_or_invalid_access_token',
                ]);

                return $this->tokenInvalidResponse();
            }

            if (str_starts_with($normalizedPath, 'admin/')
                && strtoupper((string) ($accessToken['role'] ?? '')) === 'CUSTOMER') {
                return response()->json([
                    'message' => 'Bạn không có quyền thực hiện chức năng này.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        }

        $serviceName = $this->resolveServiceName($path);

        if (! $serviceName) {
            return response()->json([
                'success' => false,
                'message' => 'Gateway khong tim thay service phu hop',
            ], 404);
        }

        $targetUrl = $this->targetUrl($serviceName, $path);

        Log::debug('gateway.request.forwarding', [
            'method' => $request->method(),
            'path' => $normalizedPath,
            'service' => $serviceName,
            'has_authorization' => $request->hasHeader('Authorization'),
        ]);
        $options = ['query' => $request->query()];

        if ($request->allFiles()) {
            $options['multipart'] = $this->multipartPayload($request);
        } elseif (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            $content = $request->getContent();

            if ($content !== '') {
                $pending = $this->pendingRequest($request)
                    ->withBody($content, $request->headers->get('Content-Type', 'application/json'));

                return $this->send($pending, $request, $targetUrl, $options, $serviceName);
            }

            $options['form_params'] = $request->request->all();
        }

        $pending = $this->pendingRequest($request);

        return $this->send($pending, $request, $targetUrl, $options, $serviceName);
    }

    private function pendingRequest(Request $request): PendingRequest
    {
        $pending = Http::withHeaders($this->forwardHeaders($request))
            ->connectTimeout((int) config('microservices.connect_timeout', 2))
            ->timeout((int) config('microservices.timeout', 5));

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $pending->retry(2, 100, null, false);
        }

        return $pending;
    }

    private function send($pending, Request $request, string $targetUrl, array $options, string $serviceName): Response|JsonResponse
    {
        try {
            $serviceResponse = $pending->send($request->method(), $targetUrl, $options);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Dịch vụ hiện không khả dụng.',
                'code' => 'SERVICE_UNAVAILABLE',
            ], 503);
        }

        return response($serviceResponse->body(), $serviceResponse->status())
            ->withHeaders($this->responseHeaders($serviceResponse));
    }

    private function resolveServiceName(string $path): ?string
    {
        $normalizedPath = trim($path, '/');

        if ($this->isAuthAdminPath($normalizedPath)) {
            return 'auth';
        }

        if ($this->matchesAny($normalizedPath, self::ORDER_ADMIN_PATH_PATTERNS)) {
            return 'order';
        }

        $firstSegment = Str::of($normalizedPath)->before('/')->toString();

        return self::ROUTE_MAP[$firstSegment] ?? null;
    }

    private function isAuthAdminPath(string $path): bool
    {
        foreach (self::AUTH_ADMIN_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isPublicRequest(Request $request, string $path): bool
    {
        $patterns = match ($request->method()) {
            'GET', 'HEAD' => self::PUBLIC_GET_PATH_PATTERNS,
            'POST' => self::PUBLIC_POST_PATH_PATTERNS,
            default => [],
        };

        return $this->matchesAny($path, $patterns);
    }

    private function targetUrl(string $serviceName, string $path): string
    {
        $baseUrl = rtrim((string) config("microservices.services.{$serviceName}.url"), '/');

        return $baseUrl.'/api/'.ltrim($path, '/');
    }

    private function forwardHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->reject(fn (array $value, string $key) => in_array(strtolower($key), [
                'content-length',
                'content-type',
                'host',
                'x-internal-service-token',
            ], true))
            ->mapWithKeys(fn (array $value, string $key) => [$key => implode(', ', $value)])
            ->all();
    }

    /**
     * @return array<string, mixed>|false|null Active token data, invalid token, or unavailable auth service.
     */
    private function introspectAccessToken(string $token): array|false|null
    {
        $authUrl = rtrim((string) config('microservices.services.auth.url'), '/');
        $internalToken = (string) config('microservices.internal_token');

        if ($authUrl === '' || $internalToken === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withHeader('X-Internal-Service-Token', $internalToken)
                ->connectTimeout((int) config('microservices.connect_timeout', 2))
                ->timeout((int) config('microservices.timeout', 5))
                ->post($authUrl.'/api/internal/auth/introspect', ['token' => $token]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->serverError()) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if (! (bool) data_get($response->json(), 'data.active', false)) {
            return false;
        }

        return (array) data_get($response->json(), 'data', []);
    }

    private function tokenInvalidResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Bạn chưa đăng nhập.',
            'code' => 'TOKEN_INVALID',
        ], 401);
    }

    private function multipartPayload(Request $request): array
    {
        $multipart = [];

        foreach ($request->request->all() as $name => $value) {
            $this->appendMultipartValue($multipart, $name, $value);
        }

        foreach ($request->allFiles() as $name => $file) {
            $this->appendMultipartFile($multipart, $name, $file);
        }

        return $multipart;
    }

    private function appendMultipartValue(array &$multipart, string $name, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $childName => $childValue) {
                $this->appendMultipartValue($multipart, "{$name}[{$childName}]", $childValue);
            }

            return;
        }

        $multipart[] = [
            'name' => $name,
            'contents' => (string) $value,
        ];
    }

    private function appendMultipartFile(array &$multipart, string $name, UploadedFile|array $file): void
    {
        if (is_array($file)) {
            foreach ($file as $childName => $childFile) {
                $this->appendMultipartFile($multipart, "{$name}[{$childName}]", $childFile);
            }

            return;
        }

        $multipart[] = [
            'name' => $name,
            'contents' => fopen($file->getRealPath(), 'r'),
            'filename' => $file->getClientOriginalName(),
        ];
    }

    private function responseHeaders(ClientResponse $response): array
    {
        return collect($response->headers())
            ->reject(fn (array $value, string $key) => in_array(strtolower($key), [
                'connection',
                'content-encoding',
                'transfer-encoding',
            ], true))
            ->mapWithKeys(fn (array $value, string $key) => [$key => implode(', ', $value)])
            ->all();
    }
}
