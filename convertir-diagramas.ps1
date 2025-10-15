# ====================================================================
# Script de Conversión de Diagramas Mermaid a PNG/SVG
# ====================================================================
# Descripción: Convierte archivos .mmd a PNG o SVG usando Mermaid CLI
# Requisito: Mermaid CLI instalado (npm install -g @mermaid-js/mermaid-cli)
# Uso: .\convertir-diagramas.ps1 -Formato PNG
#      .\convertir-diagramas.ps1 -Formato SVG
# ====================================================================

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet("PNG", "SVG", "PDF", "TODOS")]
    [string]$Formato = "PNG",
    
    [Parameter(Mandatory=$false)]
    [ValidateSet("default", "forest", "dark", "neutral")]
    [string]$Tema = "default",
    
    [Parameter(Mandatory=$false)]
    [switch]$FondoTransparente
)

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  🎨 CONVERSOR DE DIAGRAMAS MERMAID" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

# Verificar que mmdc está instalado
try {
    $null = Get-Command mmdc -ErrorAction Stop
} catch {
    Write-Host "❌ ERROR: Mermaid CLI no está instalado" -ForegroundColor Red
    Write-Host ""
    Write-Host "Instala Node.js desde: https://nodejs.org/" -ForegroundColor Yellow
    Write-Host "Luego ejecuta: npm install -g @mermaid-js/mermaid-cli" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

Write-Host "✅ Mermaid CLI detectado" -ForegroundColor Green
Write-Host ""

# Directorios
$inputDir = "diagramas-exportados"
$outputDir = "diagramas-$($Formato.ToLower())"

# Verificar que existe el directorio de entrada
if (!(Test-Path $inputDir)) {
    Write-Host "❌ ERROR: No existe el directorio '$inputDir'" -ForegroundColor Red
    Write-Host ""
    Write-Host "Ejecuta primero: .\exportar-diagramas.ps1" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

# Crear directorio de salida
if (!(Test-Path $outputDir)) {
    New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
}

# Configuración
Write-Host "📋 CONFIGURACIÓN:" -ForegroundColor Cyan
Write-Host "   • Formato: $Formato" -ForegroundColor White
Write-Host "   • Tema: $Tema" -ForegroundColor White
Write-Host "   • Fondo: $(if($FondoTransparente) {'Transparente'} else {'Blanco'})" -ForegroundColor White
Write-Host ""

# Obtener archivos .mmd
$archivos = Get-ChildItem "$inputDir\*.mmd"

if ($archivos.Count -eq 0) {
    Write-Host "⚠️  No se encontraron archivos .mmd en '$inputDir'" -ForegroundColor Yellow
    Write-Host ""
    exit 0
}

Write-Host "📊 Encontrados $($archivos.Count) diagramas" -ForegroundColor Green
Write-Host ""
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  🔄 CONVIRTIENDO..." -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

# Contadores
$exitosos = 0
$fallidos = 0

# Función para convertir
function Convert-Diagram {
    param($InputFile, $OutputFile, $FormatoArchivo)
    
    $args = @(
        "-i", $InputFile,
        "-o", $OutputFile,
        "-t", $Tema
    )
    
    if ($FondoTransparente) {
        $args += "-b", "transparent"
    }
    
    try {
        $process = Start-Process -FilePath "mmdc" -ArgumentList $args -NoNewWindow -Wait -PassThru
        return $process.ExitCode -eq 0
    } catch {
        return $false
    }
}

# Formatos a generar
$formatos = @()
if ($Formato -eq "TODOS") {
    $formatos = @("PNG", "SVG", "PDF")
} else {
    $formatos = @($Formato)
}

# Procesar cada formato
foreach ($fmt in $formatos) {
    $extension = $fmt.ToLower()
    $currentOutputDir = "diagramas-$extension"
    
    if (!(Test-Path $currentOutputDir)) {
        New-Item -ItemType Directory -Force -Path $currentOutputDir | Out-Null
    }
    
    Write-Host "📁 Generando archivos .$extension" -ForegroundColor Cyan
    Write-Host ""
    
    foreach ($archivo in $archivos) {
        $baseName = [System.IO.Path]::GetFileNameWithoutExtension($archivo.Name)
        $outputFile = Join-Path $currentOutputDir "$baseName.$extension"
        
        Write-Host "   🔄 $baseName.$extension " -NoNewline
        
        $success = Convert-Diagram -InputFile $archivo.FullName -OutputFile $outputFile -FormatoArchivo $extension
        
        if ($success) {
            $fileSize = (Get-Item $outputFile).Length
            $fileSizeKB = [math]::Round($fileSize / 1KB, 2)
            Write-Host "✅ " -NoNewline -ForegroundColor Green
            Write-Host "($fileSizeKB KB)" -ForegroundColor DarkGray
            $exitosos++
        } else {
            Write-Host "❌" -ForegroundColor Red
            $fallidos++
        }
    }
    
    Write-Host ""
}

# Resumen
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  ✨ RESUMEN" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
Write-Host "  ✅ Exitosos: $exitosos" -ForegroundColor Green
if ($fallidos -gt 0) {
    Write-Host "  ❌ Fallidos: $fallidos" -ForegroundColor Red
}
Write-Host ""

# Listar directorios creados
Write-Host "📁 Archivos generados en:" -ForegroundColor Cyan
foreach ($fmt in $formatos) {
    $extension = $fmt.ToLower()
    $dir = "diagramas-$extension"
    if (Test-Path $dir) {
        $count = (Get-ChildItem "$dir\*.$extension").Count
        Write-Host "   • .\$dir\ ($count archivos)" -ForegroundColor White
    }
}
Write-Host ""

Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
Write-Host "🎉 ¡Conversión completada!" -ForegroundColor Green
Write-Host ""

# Sugerencias
Write-Host "💡 TIPS DE USO:" -ForegroundColor Cyan
Write-Host ""
Write-Host "  • Para Word/PowerPoint: Usa archivos PNG" -ForegroundColor White
Write-Host "  • Para Web: Usa archivos SVG (menor peso)" -ForegroundColor White
Write-Host "  • Para impresión: Usa archivos PDF" -ForegroundColor White
Write-Host ""

# Abrir carpeta
$openFolder = Read-Host "¿Abrir carpeta de imágenes? (S/N)"
if ($openFolder -eq 'S' -or $openFolder -eq 's' -or $openFolder -eq 'Y' -or $openFolder -eq 'y') {
    foreach ($fmt in $formatos) {
        $extension = $fmt.ToLower()
        $dir = "diagramas-$extension"
        if (Test-Path $dir) {
            Invoke-Item $dir
            break
        }
    }
}

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
