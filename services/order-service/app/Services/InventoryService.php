<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InventoryService
{

    // Thực hiện reserve.
    public function reserve(string $reference, array $items): array
    {
        return $this->send('post', '/api/internal/inventory/reservations', [
            'reference' => $reference,
            'items' => collect($items)->map(fn (array $item): array => [
                'product_variant_id' => (int) $item['product_variant_id'],
                'quantity' => (int) $item['quantity'],
            ])->values()->all(),
        ], $reference);
    }

    // Thực hiện commit.
    public function commit(string $reference): array
    {
        return $this->action($reference, 'commit');
    }

    // Thực hiện release.
    public function release(string $reference): array
    {
        return $this->action($reference, 'release');
    }

    // Thực hiện restore.
    public function restore(string $reference): array
    {
        return $this->action($reference, 'restore');
    }

    // Thực hiện action.
    private function action(string $reference, string $action): array
    {
        return $this->send(
            'post',
            '/api/internal/inventory/reservations/'.rawurlencode($reference).'/'.$action,
            [],
            $reference,
        );
    }

    // Gửi hoặc phát dữ liệu theo nghiệp vụ của hàm.
    private function send(string $method, string $path, array $payload, string $reference): array
    {
        $baseUrl = rtrim((string) config('services.catalog.url'), '/');

        if ($baseUrl === '' || (string) config('services.internal.token') === '') {
            throw new HttpException(503, 'Dịch vụ tồn kho chưa được cấu hình an toàn');
        }

        try {
            $response = $this->request()->{$method}($baseUrl.$path, $payload);
        } catch (ConnectionException $exception) {
            Log::error('inventory.request.connection_failed', [
                'reference' => $reference,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            throw new HttpException(503, 'Không thể kết nối dịch vụ tồn kho', $exception);
        }

        if ($response->status() === 409 || $response->status() === 422) {
            throw ValidationException::withMessages([
                'items' => [(string) ($response->json('message') ?: 'Tồn kho không đủ để xử lý yêu cầu')],
            ]);
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::error('inventory.request.failed', [
                'reference' => $reference,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new HttpException(503, 'Dịch vụ tồn kho không thể xử lý yêu cầu');
        }

        $data = $response->json('data');

        if (! is_array($data) || ($data['reference'] ?? null) !== $reference) {
            throw new HttpException(502, 'Dịch vụ tồn kho trả về dữ liệu không hợp lệ');
        }

        return $data;
    }

    // Thực hiện yêu cầu.
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeader(
                (string) config('services.internal.header', 'X-Internal-Service-Token'),
                (string) config('services.internal.token'),
            )
            ->connectTimeout((int) config('services.connect_timeout', 2))
            ->timeout((int) config('services.timeout', 5));
    }
}
