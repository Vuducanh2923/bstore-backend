param(
    [string] $EnvFile = (Join-Path $PSScriptRoot '..\.env'),
    [switch] $IncludeDatabasePasswords
)

$ErrorActionPreference = 'Stop'

function New-Secret([int] $Bytes = 48) {
    $buffer = [byte[]]::new($Bytes)
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($buffer)
    } finally {
        $generator.Dispose()
    }
    return [Convert]::ToBase64String($buffer)
}

function Set-EnvValue([string] $Content, [string] $Name, [string] $Value) {
    $line = "${Name}=${Value}"
    $pattern = "(?m)^$([Regex]::Escape($Name))=.*$"

    if ([Regex]::IsMatch($Content, $pattern)) {
        return [Regex]::Replace($Content, $pattern, $line)
    }

    return $Content.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
}

$resolved = [IO.Path]::GetFullPath($EnvFile)
$content = if (Test-Path -LiteralPath $resolved) { [IO.File]::ReadAllText($resolved) } else { '' }
$content = Set-EnvValue $content 'AUTH_TOKEN_KEY' ('base64:' + (New-Secret))
$content = Set-EnvValue $content 'INTERNAL_SERVICE_TOKEN' (New-Secret)

if ($IncludeDatabasePasswords) {
    $content = Set-EnvValue $content 'DB_PASSWORD' (New-Secret 36)
    $content = Set-EnvValue $content 'MYSQL_ROOT_PASSWORD' (New-Secret 36)
}

[IO.File]::WriteAllText($resolved, $content, [Text.UTF8Encoding]::new($false))
Write-Host 'Secrets rotated. Existing access and refresh sessions are now invalid.'
