<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class CatalogCache
{
    private const VERSION_KEY = 'catalog:cache:version';

    // Thực hiện remember.
    public function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        if (app()->runningUnitTests()) {
            return $callback();
        }

        return Cache::remember($this->key($key), $ttlSeconds, $callback);
    }

    // Thực hiện bump.
    public function bump(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 1);

            return;
        }

        Cache::increment(self::VERSION_KEY);
    }

    // Thực hiện khóa.
    public function key(string $key): string
    {
        return 'catalog:v'.$this->version().':'.$key;
    }

    // Thực hiện version.
    private function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }
}
