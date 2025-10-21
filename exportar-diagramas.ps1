# ====================================================================
# Script de Exportacion de Diagramas Mermaid
# ====================================================================
# Descripcion: Extrae todos los diagramas Mermaid de archivos .md
#              y los guarda como archivos .mmd individuales
# Uso: .\exportar-diagramas.ps1
# ====================================================================

Write-Host ""
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "  EXPORTADOR DE DIAGRAMAS MERMAID" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

# Directorio de salida
$outputDir = "diagramas-exportados"

# Crear directorio si no existe
if (!(Test-Path $outputDir)) {
    New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
    Write-Host "OK Creado directorio: $outputDir" -ForegroundColor Green
} else {
    Write-Host "Usando directorio existente: $outputDir" -ForegroundColor Yellow
}

Write-Host ""

# Archivos con diagramas
$archivos = @(
    "DIAGRAMAS.md",
    "DIAGRAMAS_TECNICOS.md",
    "DIAGRAMAS_SECUENCIA.md"
)

# Contador global
$totalDiagramas = 0

# Procesar cada archivo
foreach ($archivo in $archivos) {
    if (!(Test-Path $archivo)) {
        Write-Host "⚠️  No encontrado: $archivo" -ForegroundColor Red
        continue
    }
    
    Write-Host "📄 Procesando: $archivo" -ForegroundColor Cyan
    Write-Host "   ────────────────────────────────────────" -ForegroundColor DarkGray
    
    # Leer contenido
    $content = Get-Content $archivo -Raw -Encoding UTF8
    
    # Extraer bloques mermaid
    $pattern = '(?s)```mermaid\r?\n(.*?)\r?\n```'
    $matches = [regex]::Matches($content, $pattern)
    
    if ($matches.Count -eq 0) {
        Write-Host "   ⚠️  No se encontraron diagramas" -ForegroundColor Yellow
        Write-Host ""
        continue
    }
    
    $counter = 1
    $baseName = $archivo -replace '\.md$', ''
    
    foreach ($match in $matches) {
        $mermaidCode = $match.Groups[1].Value
        
        # Generar nombre descriptivo basado en el contenido
        $diagramType = "diagrama"
        if ($mermaidCode -match '^\s*graph') { $diagramType = "grafo" }
        elseif ($mermaidCode -match '^\s*flowchart') { $diagramType = "flujo" }
        elseif ($mermaidCode -match '^\s*sequenceDiagram') { $diagramType = "secuencia" }
        elseif ($mermaidCode -match '^\s*classDiagram') { $diagramType = "clases" }
        elseif ($mermaidCode -match '^\s*stateDiagram') { $diagramType = "estados" }
        elseif ($mermaidCode -match '^\s*erDiagram') { $diagramType = "er" }
        elseif ($mermaidCode -match '^\s*mindmap') { $diagramType = "mindmap" }
        
        $fileName = "$outputDir\$baseName-$diagramType-$counter.mmd"
        
        # Guardar código
        Set-Content -Path $fileName -Value $mermaidCode -Encoding UTF8
        
        # Mostrar progreso
        $fileSize = (Get-Item $fileName).Length
        Write-Host "   ✅ $diagramType-$counter.mmd " -NoNewline -ForegroundColor Green
        Write-Host "($fileSize bytes)" -ForegroundColor DarkGray
        
        $counter++
        $totalDiagramas++
    }
    
    Write-Host ""
}

# Resumen
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  ✨ RESUMEN" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
Write-Host "  📊 Total de diagramas extraídos: $totalDiagramas" -ForegroundColor Green
Write-Host "  📁 Ubicación: .\$outputDir\" -ForegroundColor Green
Write-Host ""

# Verificar si Mermaid CLI está instalado
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  🔄 PRÓXIMOS PASOS" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

$mmdcInstalled = $false
try {
    $null = Get-Command mmdc -ErrorAction Stop
    $mmdcInstalled = $true
} catch {
    $mmdcInstalled = $false
}

if ($mmdcInstalled) {
    Write-Host "✅ Mermaid CLI detectado" -ForegroundColor Green
    Write-Host ""
    Write-Host "Puedes convertir los diagramas a PNG con:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  # Convertir todos a PNG" -ForegroundColor White
    Write-Host "  Get-ChildItem .\$outputDir\*.mmd | ForEach-Object {" -ForegroundColor Gray
    Write-Host "      `$pngFile = `$_.FullName -replace '\.mmd`$', '.png'" -ForegroundColor Gray
    Write-Host "      mmdc -i `$_.FullName -o `$pngFile -b transparent" -ForegroundColor Gray
    Write-Host "  }" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  # Convertir todos a SVG" -ForegroundColor White
    Write-Host "  Get-ChildItem .\$outputDir\*.mmd | ForEach-Object {" -ForegroundColor Gray
    Write-Host "      `$svgFile = `$_.FullName -replace '\.mmd`$', '.svg'" -ForegroundColor Gray
    Write-Host "      mmdc -i `$_.FullName -o `$svgFile" -ForegroundColor Gray
    Write-Host "  }" -ForegroundColor Gray
    Write-Host ""
} else {
    Write-Host "⚠️  Mermaid CLI no está instalado" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Opciones para exportar:" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  1️⃣  Usar Mermaid Live (RECOMENDADO - Sin instalación)" -ForegroundColor White
    Write-Host "     • Abre: https://mermaid.live/" -ForegroundColor Gray
    Write-Host "     • Pega el contenido de cualquier archivo .mmd" -ForegroundColor Gray
    Write-Host "     • Click en 'Actions' → 'PNG image' o 'SVG'" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  2️⃣  Instalar Mermaid CLI (Para automatización)" -ForegroundColor White
    Write-Host "     • Requiere Node.js: https://nodejs.org/" -ForegroundColor Gray
    Write-Host "     • Ejecuta: npm install -g @mermaid-js/mermaid-cli" -ForegroundColor Gray
    Write-Host "     • Luego vuelve a ejecutar este script" -ForegroundColor Gray
    Write-Host ""
}

Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
Write-Host "🎉 ¡Exportación completada!" -ForegroundColor Green
Write-Host ""

# Abrir carpeta de salida
$openFolder = Read-Host "¿Abrir carpeta de diagramas exportados? (S/N)"
if ($openFolder -eq 'S' -or $openFolder -eq 's' -or $openFolder -eq 'Y' -or $openFolder -eq 'y') {
    Invoke-Item $outputDir
}
