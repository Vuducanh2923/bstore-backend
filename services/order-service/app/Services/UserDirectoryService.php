<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserDirectoryService
{
    private array $profiles = [];

    // Thực hiện hồ sơ.
    public function profile(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        if (array_key_exists($userId, $this->profiles)) {
            return $this->profiles[$userId];
        }

        return $this->profiles[$userId] = $this->profileFromAuthService($userId) ?? [];
    }

    // Thực hiện người thực hiện.
    public function actor(array $actor): array
    {
        $profile = $this->profile((int) ($actor['id'] ?? 0));

        return [
            'id' => (int) ($actor['id'] ?? 0),
            'role' => strtoupper((string) ($actor['role'] ?? '')),
            'email' => $actor['email'] ?? ($profile['email'] ?? null),
            'name' => $profile['name'] ?? $actor['email'] ?? ('User #'.((int) ($actor['id'] ?? 0))),
            'phone' => $profile['phone'] ?? null,
        ];
    }

    // Thực hiện hồ sơ từ xác thực service.
    private function profileFromAuthService(int $userId): ?array
    {
        $baseUrl = rtrim((string) config('services.auth.url'), '/');

        if ($baseUrl === '' || (string) config('services.internal.token') === '') {
            return null;
        }

        try {
            $response = $this->request()->get("{$baseUrl}/api/internal/users/{$userId}");
        } catch (Throwable $exception) {
            Log::warning('Could not fetch user profile from Auth Service.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $this->normalizeProfile([
            'id' => $data['id'] ?? null,
            'name' => $data['full_name'] ?? $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => data_get($data, 'role.name'),
        ]) : null;
    }

    // Chuẩn hóa hồ sơ.
    private function normalizeProfile(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);

        return [
            'id' => $id,
            'name' => $data['name'] ?: ($data['email'] ?? "User #{$id}"),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => isset($data['role']) ? strtoupper((string) $data['role']) : null,
        ];
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
            ->timeout((int) config('services.timeout', 5))
            ->retry(2, 100, null, false);
    }
}
