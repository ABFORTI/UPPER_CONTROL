# Script simple para exportar diagramas Mermaid
# Uso: .\exportar-simple.ps1

Write-Host "Exportando diagramas Mermaid..." -ForegroundColor Cyan
Write-Host ""

# Crear directorio de salida
$outputDir = "diagramas-exportados"
if (!(Test-Path $outputDir)) {
    New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
}

# Archivos a procesar
$archivos = @("DIAGRAMAS.md", "DIAGRAMAS_TECNICOS.md", "DIAGRAMAS_SECUENCIA.md")

$totalDiagramas = 0

foreach ($archivo in $archivos) {
    if (!(Test-Path $archivo)) {
        continue
    }
    
    Write-Host "Procesando: $archivo" -ForegroundColor Yellow
    
    # Leer contenido
    $content = Get-Content $archivo -Raw -Encoding UTF8
    
    # Extraer bloques mermaid
    $pattern = '(?s)```mermaid\r?\n(.*?)\r?\n```'
    $matches = [regex]::Matches($content, $pattern)
    
    $counter = 1
    $baseName = $archivo -replace '\.md$', ''
    
    foreach ($match in $matches) {
        $mermaidCode = $match.Groups[1].Value
        
        # Detectar tipo de diagrama
        $diagramType = "diagrama"
        if ($mermaidCode -match '^\s*graph') { $diagramType = "grafo" }
        elseif ($mermaidCode -match '^\s*flowchart') { $diagramType = "flujo" }
        elseif ($mermaidCode -match '^\s*sequenceDiagram') { $diagramType = "secuencia" }
        elseif ($mermaidCode -match '^\s*erDiagram') { $diagramType = "er" }
        elseif ($mermaidCode -match '^\s*mindmap') { $diagramType = "mindmap" }
        
        $fileName = "$outputDir\$baseName-$diagramType-$counter.mmd"
        
        # Guardar
        Set-Content -Path $fileName -Value $mermaidCode -Encoding UTF8
        
        Write-Host "  - Creado: $baseName-$diagramType-$counter.mmd" -ForegroundColor Green
        
        $counter++
        $totalDiagramas++
    }
}

Write-Host ""
Write-Host "Total de diagramas extraidos: $totalDiagramas" -ForegroundColor Green
Write-Host "Ubicacion: .\$outputDir\" -ForegroundColor Green
Write-Host ""

# Verificar Mermaid CLI
$mmdcInstalled = $null -ne (Get-Command mmdc -ErrorAction SilentlyContinue)

if ($mmdcInstalled) {
    Write-Host "Mermaid CLI detectado!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Para convertir a PNG:" -ForegroundColor Yellow
    Write-Host "  Get-ChildItem .\$outputDir\*.mmd | ForEach-Object {" -ForegroundColor Gray
    Write-Host "      mmdc -i `$_.FullName -o (`$_.FullName -replace '.mmd','.png') -b transparent" -ForegroundColor Gray
    Write-Host "  }" -ForegroundColor Gray
} else {
    Write-Host "Para exportar a imagenes:" -ForegroundColor Yellow
    Write-Host "  1. Abre https://mermaid.live/" -ForegroundColor White
    Write-Host "  2. Copia el contenido de cualquier archivo .mmd" -ForegroundColor White
    Write-Host "  3. Pega en el editor" -ForegroundColor White
    Write-Host "  4. Click 'Actions' -> 'PNG image'" -ForegroundColor White
}

Write-Host ""
Write-Host "Completado!" -ForegroundColor Cyan
