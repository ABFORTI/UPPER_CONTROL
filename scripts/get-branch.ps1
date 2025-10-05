Param(
    [Parameter(Position=0, Mandatory=$false)]
    [string]$Branch
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

try {
    if ($PSScriptRoot) { Set-Location -Path (Join-Path $PSScriptRoot '..') }

    if (-not $Branch -or $Branch.Trim().Length -eq 0) {
        $Branch = Read-Host -Prompt 'Nombre de la rama a obtener (remote)'
    }

    Write-Host '-> Obteniendo del remoto (fetch)...' -ForegroundColor Cyan
    git fetch --prune origin

    # ¿Existe localmente?
    git rev-parse --verify $Branch 2>$null
    $existsLocal = $LASTEXITCODE -eq 0

    if ($existsLocal) {
        Write-Host "-> Cambiando a la rama '$Branch'..." -ForegroundColor Cyan
        git switch $Branch
    } else {
        Write-Host "-> Creando rama local '$Branch' rastreando origin/$Branch..." -ForegroundColor Cyan
        git switch -c $Branch --track "origin/$Branch"
    }

    Write-Host "-> Actualizando '$Branch' con rebase..." -ForegroundColor Cyan
    git pull --rebase --autostash origin $Branch

    Write-Host 'Listo ✅' -ForegroundColor Green
}
catch {
    Write-Error $_
    exit 1
}
