<?php

namespace App\Support;

class RequestMetrics
{
    public string $requestId = '';

    public int $databaseQueryCount = 0;

    public int $externalCallCount = 0;

    public function reset(string $requestId): void
    {
        $this->requestId = $requestId;
        $this->databaseQueryCount = 0;
        $this->externalCallCount = 0;
    }
}
