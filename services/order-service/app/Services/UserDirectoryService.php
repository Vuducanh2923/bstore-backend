<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class UserDirectoryService
{
    private array $profiles = [];

    public function profile(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        if (array_key_exists($userId, $this->profiles)) {
            return $this->profiles[$userId];
        }

        return $this->profiles[$userId] = $this->profileFromDatabase($userId)
            ?? $this->profileFromAuthService($userId)
            ?? [];
    }

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

    private function profileFromDatabase(int $userId): ?array
    {
        try {
            if (! Schema::connection('bstore_auth')->hasTable('users')) {
                return null;
            }

            $query = DB::connection('bstore_auth')
                ->table('users')
                ->where('users.id', $userId);

            if (Schema::connection('bstore_auth')->hasTable('roles')) {
                $query->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                    ->select([
                        'users.id',
                        'users.full_name',
                        'users.email',
                        'users.phone',
                        'roles.name as role',
                    ]);
            } else {
                $query->select([
                    'users.id',
                    'users.full_name',
                    'users.email',
                    'users.phone',
                ]);
            }

            $user = $query->first();

            if (! $user) {
                return null;
            }

            return $this->normalizeProfile([
                'id' => $user->id,
                'name' => $user->full_name ?? null,
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? null,
                'role' => $user->role ?? null,
            ]);
        } catch (Throwable $exception) {
            Log::debug('Could not fetch user profile from auth database.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function profileFromAuthService(int $userId): ?array
    {
        $baseUrl = rtrim((string) config('services.auth.url'), '/');

        if ($baseUrl === '') {
            return null;
        }

        try {
            $response = $this->request()->get("{$baseUrl}/api/users/{$userId}");
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

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout((int) config('services.connect_timeout', 2))
            ->timeout((int) config('services.timeout', 5))
            ->retry(2, 100, null, false);
    }
}
