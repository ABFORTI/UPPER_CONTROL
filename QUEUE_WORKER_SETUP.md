# 🔄 Configuración del Worker de Colas

## Problema Identificado

Las notificaciones en Upper Control usan `ShouldQueue`, lo que significa que se **encolan** en la tabla `jobs` de la base de datos y necesitan ser **procesadas por un worker** para:

1. Guardarse en la tabla `notifications` (para mostrarlas en la interfaz)
2. Enviarse por correo electrónico (si está configurado)

**Sin el worker corriendo**, las notificaciones se quedan en la cola y nunca se procesan.

## Verificar Estado de la Cola

```powershell
# Ver cuántos trabajos hay pendientes
php artisan queue:work --once

# Ver trabajos fallidos
php artisan queue:failed
```

## Soluciones

### Opción 1: Worker Manual (Desarrollo)

Para desarrollo local, abre una terminal **separada** y ejecuta:

```powershell
# Opción A: Worker simple que se detiene cuando la cola está vacía
php artisan queue:work --stop-when-empty

# Opción B: Worker continuo (recomendado para desarrollo)
php artisan queue:work --sleep=3 --tries=3
```

### Opción 2: Script PowerShell (Desarrollo - Recomendado)

Usa el script incluido que reinicia automáticamente el worker:

```powershell
.\start-queue-worker.ps1
```

Este script:
- Mantiene el worker corriendo continuamente
- Se reinicia automáticamente cada 60 segundos (para recargar cambios de código)
- Se reinicia si hay errores
- Presiona `Ctrl+C` para detenerlo

### Opción 3: Procesar Cola Manualmente (Testing)

Si solo quieres probar puntualmente:

```powershell
# Procesar todos los trabajos pendientes y detenerse
php artisan queue:work --stop-when-empty
```

### Opción 4: Producción (Supervisor/systemd)

Para producción, configura **Supervisor** (Linux) o un **Servicio de Windows**:

**Supervisor (Linux):**
```ini
[program:upper-control-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /ruta/a/upper-control/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/ruta/a/upper-control/storage/logs/worker.log
stopwaitsecs=3600
```

**Windows Service:**
Usa herramientas como [NSSM](https://nssm.cc/) o Task Scheduler con trigger "At startup".

## Opción Alternativa: Deshabilitar Cola

Si no quieres gestionar el worker, puedes hacer que las notificaciones se ejecuten **sincrónicamente**:

### 1. Cambiar `SystemEventNotification.php`:

```php
// Eliminar "implements ShouldQueue"
class SystemEventNotification extends Notification // ← sin ShouldQueue
{
    use Queueable;
    // ... resto igual
}
```

### 2. Cambiar otras notificaciones:

Buscar todas las clases en `app/Notifications/` que tengan `implements ShouldQueue` y quitarlo.

**⚠️ IMPORTANTE**: Ejecutar notificaciones sincrónicamente puede **ralentizar las respuestas HTTP** si el envío de correos es lento.

## Verificar que Funciona

Después de iniciar el worker:

1. **Crear una solicitud** como usuario cliente
2. **Entrar como coordinador** a `/notificaciones`
3. **Verificar** que aparece la notificación en la pestaña "No leídas"

## Logs

Los logs del worker aparecen en:
- `storage/logs/laravel.log` (errores de trabajos)
- Salida estándar del terminal donde corre el worker

## Comandos Útiles

```powershell
# Ver estado de la cola
php artisan queue:monitor

# Reintentar trabajos fallidos
php artisan queue:retry all

# Limpiar trabajos fallidos
php artisan queue:flush

# Purgar trabajos de una cola específica
php artisan queue:clear database --queue=default
```

## Troubleshooting

### "No aparecen notificaciones"
1. Verificar que el worker está corriendo: `php artisan queue:work --once`
2. Ver trabajos pendientes: revisar tabla `jobs` en la BD
3. Procesar cola manualmente: `php artisan queue:work --stop-when-empty`

### "Las notificaciones aparecen pero todas leídas"
- Verifica que no estés marcándolas como leídas automáticamente en algún middleware o componente Vue
- Revisa la pestaña "Leídas" para confirmar que las notificaciones antiguas están ahí

### "Error al encolar notificación"
- Verifica que XAMPP/MySQL esté corriendo
- Confirma que la tabla `jobs` existe: `php artisan migrate`
