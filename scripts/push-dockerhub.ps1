param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $Namespace,

    [ValidateNotNullOrEmpty()]
    [string] $Tag = 'latest',

    [string[]] $Services = @(),

    [switch] $SkipBuild
)

$ErrorActionPreference = 'Stop'

$backendRoot = Split-Path -Parent $PSScriptRoot
$projectRoot = Split-Path -Parent $backendRoot
$composeFile = Join-Path $projectRoot 'docker-compose.yml'

if (-not (Test-Path $composeFile)) {
    throw "Cannot find docker-compose.yml at $composeFile."
}

$env:DOCKERHUB_NAMESPACE = $Namespace
$env:DOCKER_IMAGE_TAG = $Tag

$composeArgs = @('-f', $composeFile)
$serviceArgs = @($Services)

Write-Host "Docker Hub namespace: $Namespace"
Write-Host "Image tag: $Tag"
Write-Host "Compose file: $composeFile"

if (-not $SkipBuild) {
    Write-Host 'Building images...'
    if ($serviceArgs.Count -gt 0) {
        docker compose @composeArgs build @serviceArgs
    } else {
        docker compose @composeArgs build
    }
}

Write-Host 'Pushing images to Docker Hub...'
if ($serviceArgs.Count -gt 0) {
    docker compose @composeArgs push @serviceArgs
} else {
    docker compose @composeArgs push
}

Write-Host 'Docker Hub push completed.'
