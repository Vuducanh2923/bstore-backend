$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$php = 'C:\Users\Admin\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
$phpConfig = 'C:\Users\Admin\Desktop\VuDucANh\tools\php83'

if (-not (Test-Path -LiteralPath $php)) {
    throw "PHP 8.3 was not found at $php"
}

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
& '$php' -S 127.0.0.1:$($service.Port) -t public public/index.php
"@
    $encodedCommand = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($command))

    Start-Process powershell.exe -ArgumentList @('-NoExit', '-EncodedCommand', $encodedCommand)
}

Write-Host 'Started all BStore microservices.'
Write-Host 'Gateway: http://127.0.0.1:8000/api'
