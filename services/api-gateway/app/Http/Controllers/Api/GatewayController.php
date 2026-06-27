<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GatewayController extends Controller
{
    private const ROUTE_MAP = [
        'auth' => 'auth',
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
        'discounts' => 'order',
        'order-discounts' => 'order',
        'order_discounts' => 'order',
        'order-items' => 'order',
        'order_items' => 'order',
        'orders' => 'order',
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
        $serviceName = $this->resolveServiceName($path);

        if (!$serviceName) {
            return response()->json([
                'success' => false,
                'message' => 'Gateway khong tim thay service phu hop',
            ], 404);
        }

        $targetUrl = $this->targetUrl($serviceName, $path);
        $options = ['query' => $request->query()];

        if ($request->allFiles()) {
            $options['multipart'] = $this->multipartPayload($request);
        } elseif (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            $content = $request->getContent();

            if ($content !== '') {
                $pending = Http::withHeaders($this->forwardHeaders($request))
                    ->timeout((int) config('microservices.timeout'))
                    ->withBody($content, $request->headers->get('Content-Type', 'application/json'));

                return $this->send($pending, $request, $targetUrl, $options, $serviceName);
            }

            $options['form_params'] = $request->request->all();
        }

        $pending = Http::withHeaders($this->forwardHeaders($request))
            ->timeout((int) config('microservices.timeout'));

        return $this->send($pending, $request, $targetUrl, $options, $serviceName);
    }

    private function send($pending, Request $request, string $targetUrl, array $options, string $serviceName): Response|JsonResponse
    {
        try {
            $serviceResponse = $pending->send($request->method(), $targetUrl, $options);
        } catch (ConnectionException) {
            return response()->json([
                'success' => false,
                'message' => "Service {$serviceName} khong kha dung",
            ], 503);
        }

        return response($serviceResponse->body(), $serviceResponse->status())
            ->withHeaders($this->responseHeaders($serviceResponse));
    }

    private function resolveServiceName(string $path): ?string
    {
        $firstSegment = Str::of($path)->before('/')->toString();

        return self::ROUTE_MAP[$firstSegment] ?? null;
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
            ], true))
            ->mapWithKeys(fn (array $value, string $key) => [$key => implode(', ', $value)])
            ->all();
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
