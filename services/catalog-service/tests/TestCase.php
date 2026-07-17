<?php

namespace Tests;

use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.token_key' => 'catalog-service-test-auth-token-key',
            'services.internal_service_token' => 'catalog-service-test-internal-token',
        ]);

        $this->withToken(app(AuthTokenService::class)->generate(
            userId: 1,
            role: 'ADMIN',
            email: 'admin@example.test',
        ));
    }

    public function tokenForRole(string $role): string
    {
        return app(AuthTokenService::class)->generate(
            userId: 1,
            role: $role,
            email: strtolower($role).'@example.test',
        );
    }

    public function withoutCatalogToken(): static
    {
        $this->defaultHeaders = [];

        return $this;
    }
}
