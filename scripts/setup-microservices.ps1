$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$services = @(
    'services\api-gateway',
    'services\auth-service',
    'services\catalog-service',
    'services\order-service',
    'services\payment-service'
)

foreach ($service in $services) {
    $path = Join-Path $root $service
    Write-Host "Setting up $service"

    Push-Location $path

    if (-not (Test-Path '.env')) {
        Copy-Item '.env.example' '.env'
    }

    composer install
    php artisan key:generate

    Pop-Location
}

Write-Host 'Microservices setup completed.'
