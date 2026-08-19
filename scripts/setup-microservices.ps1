$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot

function Resolve-PhpExecutable {
    $command = Get-Command php.exe -ErrorAction SilentlyContinue

    if ($command) {
        return $command.Source
    }

    $searchRoots = @(
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'),
        'C:\laragon\bin\php',
        'C:\php',
        'C:\xampp\php'
    ) | Where-Object { $_ -and (Test-Path -LiteralPath $_) }

    foreach ($searchRoot in $searchRoots) {
        $candidate = Get-ChildItem -LiteralPath $searchRoot -Filter php.exe -File -Recurse -ErrorAction SilentlyContinue |
            Sort-Object FullName -Descending |
            Select-Object -First 1

        if ($candidate) {
            return $candidate.FullName
        }
    }

    throw 'PHP CLI was not found. Install PHP 8.2 or newer and add php.exe to PATH.'
}

$phpExecutable = Resolve-PhpExecutable
$env:Path = "$(Split-Path -Parent $phpExecutable);$env:Path"
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
    & $phpExecutable artisan key:generate

    Pop-Location
}

Write-Host 'Microservices setup completed.'
