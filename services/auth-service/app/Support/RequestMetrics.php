<?php

namespace App\Support;

class RequestMetrics
{
    public string $requestId = '';

    public int $databaseQueryCount = 0;

    public int $externalCallCount = 0;

    // Làm mới hoặc đặt lại dữ liệu theo nghiệp vụ của hàm.
    public function reset(string $requestId): void
    {
        $this->requestId = $requestId;
        $this->databaseQueryCount = 0;
        $this->externalCallCount = 0;
    }
}
