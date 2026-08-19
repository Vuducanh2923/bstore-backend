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
$phpConfig = Split-Path -Parent $phpExecutable

$services = @(
    @{ Name = 'api-gateway'; Path = 'services\api-gateway'; Port = 8000 },
    @{ Name = 'auth-service'; Path = 'services\auth-service'; Port = 8001 },
    @{ Name = 'catalog-service'; Path = 'services\catalog-service'; Port = 8002 },
    @{ Name = 'order-service'; Path = 'services\order-service'; Port = 8003 },
    @{ Name = 'payment-service'; Path = 'services\payment-service'; Port = 8004 }
)

foreach ($service in $services) {
    $servicePath = Join-Path $root $service.Path

    if (-not (Test-Path (Join-Path $servicePath 'vendor\autoload.php'))) {
        throw "$($service.Name) is missing vendor/autoload.php. Run .\scripts\setup-microservices.ps1 first."
    }

    if (-not (Test-Path (Join-Path $servicePath '.env'))) {
        throw "$($service.Name) is missing .env. Run .\scripts\setup-microservices.ps1 first."
    }

    $title = "BStore $($service.Name) :$($service.Port)"
    $command = @"
Set-Location -LiteralPath '$servicePath'
`$Host.UI.RawUI.WindowTitle = '$title'
`$env:PHPRC = '$phpConfig'
& '$phpExecutable' artisan serve --host=127.0.0.1 --port=$($service.Port)
"@
    $encodedCommand = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($command))

    Start-Process powershell.exe -ArgumentList @('-NoExit', '-EncodedCommand', $encodedCommand)
}

Write-Host 'Started all BStore microservices.'
Write-Host 'Gateway: http://127.0.0.1:8000/api'
Write-Host "PHP: $phpExecutable"
