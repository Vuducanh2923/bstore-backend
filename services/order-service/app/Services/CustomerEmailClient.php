<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerEmailClient
{
    public function emailForUser(int $userId): ?string
    {
        $baseUrl = rtrim((string) config('services.auth.url'), '/');

        if ($baseUrl === '' || (string) config('services.internal.token') === '') {
            return null;
        }

        try {
            $response = $this->request()
                ->get("{$baseUrl}/api/internal/users/{$userId}");
        } catch (Throwable $exception) {
            Log::warning('Could not fetch customer email from Auth Service.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $email = data_get($response->json(), 'data.email');

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? (string) $email : null;
    }

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
