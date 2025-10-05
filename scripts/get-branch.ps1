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
    git fetch --prune origin | Out-Null

    # ¿Existe localmente? (silencioso)
    git show-ref --verify --quiet "refs/heads/$Branch"
    $existsLocal = $LASTEXITCODE -eq 0

    # ¿Existe en remoto? (capturar salida y comprobar no vacía)
    $remoteRef = git ls-remote --heads origin -- "$Branch"
    $existsRemote = -not [string]::IsNullOrWhiteSpace(($remoteRef -join ''))

    if ($existsLocal) {
        Write-Host "-> Cambiando a la rama '$Branch'..." -ForegroundColor Cyan
        git switch $Branch | Out-Null
    }
    elseif ($existsRemote) {
        Write-Host "-> Creando rama local '$Branch' rastreando origin/$Branch..." -ForegroundColor Cyan
        git switch -c $Branch --track "origin/$Branch" | Out-Null
    }
    else {
        throw "La rama 'origin/$Branch' no existe. Verifica el nombre o créala antes de obtenerla."
    }

    Write-Host "-> Actualizando '$Branch' con rebase..." -ForegroundColor Cyan
    git pull --rebase --autostash origin $Branch | Out-Null

    Write-Host 'Listo ✅' -ForegroundColor Green
}
catch {
    Write-Error $_
    exit 1
}
