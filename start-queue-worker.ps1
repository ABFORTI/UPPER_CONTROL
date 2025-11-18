# Script para mantener el worker de colas corriendo en desarrollo
# Uso: .\start-queue-worker.ps1

Write-Host "🚀 Iniciando worker de colas de Laravel..." -ForegroundColor Green
Write-Host "📍 Presiona Ctrl+C para detener" -ForegroundColor Yellow
Write-Host ""

# Loop infinito que reinicia el worker si falla
while ($true) {
    try {
        # Ejecutar el worker - se reiniciará automáticamente cada 60 segundos
        php artisan queue:work --sleep=3 --tries=3 --max-time=60
        
        # Si el worker termina normalmente (ej: por timeout), esperar un poco antes de reiniciar
        Write-Host "⚠️ Worker detenido, reiniciando en 2 segundos..." -ForegroundColor Yellow
        Start-Sleep -Seconds 2
    }
    catch {
        Write-Host "❌ Error en worker: $_" -ForegroundColor Red
        Write-Host "🔄 Reiniciando en 5 segundos..." -ForegroundColor Yellow
        Start-Sleep -Seconds 5
    }
}
