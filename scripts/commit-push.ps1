Param(
    [Parameter(Position=0)]
    [string]$Message
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

try {
    # Ir a la raíz del repo (el script vive en scripts/)
    if ($PSScriptRoot) {
        Set-Location -Path (Join-Path $PSScriptRoot '..')
    }

    # Detectar rama actual
    $branch = (git rev-parse --abbrev-ref HEAD).Trim()
    if (-not $branch) { throw "No se pudo detectar la rama actual" }

    if (-not $Message -or $Message.Trim().Length -eq 0) {
        $Message = Read-Host -Prompt 'Mensaje del commit'
    }

    Write-Host '-> Añadiendo cambios...' -ForegroundColor Cyan
    git add -A

    # ¿Hay algo staged?
    git diff --cached --quiet
    $hasStaged = $LASTEXITCODE -ne 0

    if ($hasStaged) {
        Write-Host '-> Creando commit...' -ForegroundColor Cyan
        git commit -m "$Message"
    } else {
        Write-Host 'No hay cambios para commitear.' -ForegroundColor Yellow
    }

    Write-Host "-> Actualizando desde origin/$branch (rebase)..." -ForegroundColor Cyan
    git pull --rebase origin $branch

    Write-Host "-> Enviando a origin/$branch..." -ForegroundColor Cyan
    git push origin $branch

    Write-Host 'Listo ✅' -ForegroundColor Green
}
catch {
    Write-Error $_
    exit 1
}
