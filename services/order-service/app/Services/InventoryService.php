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

    public function commit(string $reference): array
    {
        return $this->action($reference, 'commit');
    }

    public function release(string $reference): array
    {
        return $this->action($reference, 'release');
    }

    public function restore(string $reference): array
    {
        return $this->action($reference, 'restore');
    }

    private function action(string $reference, string $action): array
    {
        return $this->send(
            'post',
            '/api/internal/inventory/reservations/'.rawurlencode($reference).'/'.$action,
            [],
            $reference,
        );
    }

    private function send(string $method, string $path, array $payload, string $reference): array
    {
        $baseUrl = rtrim((string) config('services.catalog.url'), '/');

        if ($baseUrl === '' || (string) config('services.internal.token') === '') {
            throw new HttpException(503, 'Dich vu ton kho chua duoc cau hinh an toan');
        }

        try {
            $response = $this->request()->{$method}($baseUrl.$path, $payload);
        } catch (ConnectionException $exception) {
            Log::error('inventory.request.connection_failed', [
                'reference' => $reference,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            throw new HttpException(503, 'Khong the ket noi dich vu ton kho', $exception);
        }

        if ($response->status() === 409 || $response->status() === 422) {
            throw ValidationException::withMessages([
                'items' => [(string) ($response->json('message') ?: 'Ton kho khong du de xu ly yeu cau')],
            ]);
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::error('inventory.request.failed', [
                'reference' => $reference,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new HttpException(503, 'Dich vu ton kho khong the xu ly yeu cau');
        }

        $data = $response->json('data');

        if (! is_array($data) || ($data['reference'] ?? null) !== $reference) {
            throw new HttpException(502, 'Dich vu ton kho tra ve du lieu khong hop le');
        }

        return $data;
    }

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
