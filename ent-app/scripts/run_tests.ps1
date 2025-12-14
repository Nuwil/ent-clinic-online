# Run migrations and tests using local PHP CLI (PowerShell helper)
# Usage: .\run_tests.ps1 [-PhpPath "C:\\path\\to\\php.exe"] [-BaseUrl "http://localhost/ent-clinic-online/ent-app/public"]
Param(
    [string]$PhpPath,
    [string]$BaseUrl = "http://localhost/ent-clinic-online/ent-app/public"
)

function Find-PHP {
    param([string]$hint)
    if ($hint) {
        if (Test-Path $hint) { return (Resolve-Path $hint).Path }
    }

    # Try php on PATH
    try {
        $cmd = Get-Command php -ErrorAction Stop
        return $cmd.Path
    } catch {
        # continue searching
    }

    $candidates = @(
        "$env:SystemDrive\\xampp\\php\\php.exe",
        "C:\\xampp\\php\\php.exe",
        "E:\\xampp\\php\\php.exe",
        "C:\\Program Files\\PHP\\php.exe",
        "C:\\php\\php.exe"
    )

    foreach ($c in $candidates) {
        if (Test-Path $c) { return (Resolve-Path $c).Path }
    }

    return $null
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

$php = Find-PHP -hint $PhpPath
if (-not $php) {
    Write-Host "PHP CLI not found. Install PHP or provide -PhpPath to run tests." -ForegroundColor Yellow
    Write-Host "Common XAMPP path: C:\\xampp\\php\\php.exe" -ForegroundColor Gray
    exit 2
}

Write-Host "Using PHP: $php"

# Run migrations (dry run), migrations, then tests
& $php .\run_migrations.php --dry-run
if ($LASTEXITCODE -ne 0) { Write-Host "Dry-run migrations failed" -ForegroundColor Red; exit $LASTEXITCODE }

& $php .\run_migrations.php
if ($LASTEXITCODE -ne 0) { Write-Host "Migrations failed" -ForegroundColor Red; exit $LASTEXITCODE }

# Run all PHP tests found in tests directory
Get-ChildItem -Path .\tests -Filter "*.php" | ForEach-Object {
    $t = $_.FullName
    Write-Host "Running $t"
    & $php $t "$BaseUrl"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Test failed: $t" -ForegroundColor Red
        exit $LASTEXITCODE
    }
}

Write-Host "All tests passed." -ForegroundColor Green
exit 0
