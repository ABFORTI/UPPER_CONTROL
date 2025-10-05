Param(
    [Parameter(Position=0, Mandatory=$false)]
    [string]$From
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

try {
    if ($PSScriptRoot) { Set-Location -Path (Join-Path $PSScriptRoot '..') }

    if (-not $From -or $From.Trim().Length -eq 0) {
        $From = Read-Host -Prompt 'Nombre de la rama a mezclar (desde)'
    }

    $current = (git rev-parse --abbrev-ref HEAD).Trim()
    if (-not $current) { throw 'No se pudo detectar la rama actual' }

    Write-Host "-> Actualizando rama actual '$current'..." -ForegroundColor Cyan
    git pull --rebase --autostash origin $current

    Write-Host "-> Obteniendo últimos cambios de '$From'..." -ForegroundColor Cyan
    git fetch origin $From

    Write-Host "-> Haciendo merge de 'origin/$From' en '$current'..." -ForegroundColor Cyan
    git merge --no-ff "origin/$From"

    Write-Host '-> Empujando cambios...' -ForegroundColor Cyan
    git push origin $current

    Write-Host 'Listo ✅' -ForegroundColor Green
}
catch {
    Write-Error $_
    exit 1
}
