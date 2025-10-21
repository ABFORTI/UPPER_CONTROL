# 📧 Sistema de Recordatorios de Validación de OT

## 🎯 Descripción

Sistema automático que envía recordatorios por correo y notificación a clientes que tienen órdenes de trabajo:
- ✅ Completadas
- ✅ Validadas por calidad
- ❌ **Pendientes de autorización por el cliente**

## ⚙️ Configuración

### 1. Variables de Entorno (.env)

```env
# Intervalo de recordatorios (en minutos)
# PRUEBAS: 1 minuto
# PRODUCCIÓN: 360 (6 horas)
RECORDATORIO_VALIDACION_INTERVALO=1
```

### 2. Programación Automática

El comando se ejecuta **cada minuto** pero verifica el intervalo configurado antes de enviar recordatorios.

#### Ejecución del Scheduler (Requerido)

En **desarrollo**:
```bash
php artisan schedule:work
```

En **producción** (agregar a crontab):
```bash
* * * * * cd /ruta/a/tu/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

## 🚀 Uso

### Ejecutar Manualmente

```bash
php artisan recordatorios:validacion-ot
```

### Salida de Ejemplo

```
🔍 Buscando órdenes pendientes de validación...
📋 Encontradas 3 orden(es) pendiente(s).
✅ Recordatorio enviado a cliente1@ejemplo.com para OT #123 (8h en espera)
⏳ OT #124: Último recordatorio hace 30 min. Esperando...
✅ Recordatorio enviado a cliente2@ejemplo.com para OT #125 (12h en espera)

🎉 Proceso completado: 2 recordatorio(s) enviado(s).
```

## 📋 Lógica del Sistema

### Condiciones para Enviar Recordatorio

1. **Estado de la OT**:
   - `estatus = 'completada'`
   - `calidad_resultado = 'validado'`
   - `cliente_autorizada_at = NULL`

2. **Intervalo de Tiempo**:
   - Ha pasado el tiempo configurado desde el último recordatorio
   - Si es el primer recordatorio, se envía inmediatamente

3. **Cliente Válido**:
   - La solicitud tiene un cliente asignado
   - El cliente tiene email válido

### Cálculo de Tiempo en Espera

El sistema calcula las horas desde que calidad validó la OT:
- Busca en `activity_log` el evento `calidad_validar`
- Calcula la diferencia hasta ahora
- Muestra en el correo: "X días y Y horas" o "X horas"

## 📧 Contenido del Recordatorio

### Correo Electrónico

**Asunto:** ⏰ Recordatorio: OT #123 Pendiente de Autorización

**Contenido:**
- Nombre del cliente
- Número de orden
- Servicio
- Centro de trabajo
- Tiempo en espera
- Botón de acción para autorizar

### Notificación de Campana

Se registra en la base de datos para mostrar en el icono de notificaciones del sistema.

## 🔧 Personalización

### Cambiar Intervalo de Recordatorios

**Para pruebas (1 minuto):**
```env
RECORDATORIO_VALIDACION_INTERVALO=1
```

**Para producción (6 horas):**
```env
RECORDATORIO_VALIDACION_INTERVALO=360
```

**Para producción (12 horas):**
```env
RECORDATORIO_VALIDACION_INTERVALO=720
```

**Para producción (24 horas):**
```env
RECORDATORIO_VALIDACION_INTERVALO=1440
```

### Desactivar Recordatorios

Simplemente no inicies el scheduler o comenta la línea en `app/Console/Kernel.php`:

```php
// $schedule->command('recordatorios:validacion-ot')->everyMinute();
```

## 📊 Monitoreo

### Ver Recordatorios Enviados

```sql
-- Ver recordatorios de las últimas 24 horas
SELECT 
    n.id,
    u.name as cliente,
    u.email,
    JSON_EXTRACT(n.data, '$.orden_id') as orden_id,
    JSON_EXTRACT(n.data, '$.horas_espera') as horas_espera,
    n.created_at,
    n.read_at
FROM notifications n
INNER JOIN users u ON n.notifiable_id = u.id
WHERE n.type = 'App\\Notifications\\RecordatorioValidacionOt'
  AND n.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY n.created_at DESC;
```

### Ver Órdenes Pendientes

```sql
SELECT 
    o.id,
    o.estatus,
    o.calidad_resultado,
    s.folio as solicitud,
    u.name as cliente,
    u.email,
    sv.nombre as servicio,
    TIMESTAMPDIFF(HOUR, 
        (SELECT created_at 
         FROM activity_log 
         WHERE subject_id = o.id 
           AND event = 'calidad_validar' 
         ORDER BY created_at DESC LIMIT 1
        ), 
        NOW()
    ) as horas_espera
FROM ordenes o
INNER JOIN solicituds s ON o.id_solicitud = s.id
INNER JOIN users u ON s.id_cliente = u.id
INNER JOIN servicio_empresas sv ON o.id_servicio = sv.id
WHERE o.estatus = 'completada'
  AND o.calidad_resultado = 'validado'
  AND o.cliente_autorizada_at IS NULL
ORDER BY horas_espera DESC;
```

## 🎯 Ventajas del Sistema

1. **Automático**: No requiere intervención manual
2. **Configurable**: Ajusta el intervalo según necesites
3. **No invasivo**: Respeta el intervalo para no hacer spam
4. **Trazable**: Registra todos los recordatorios enviados
5. **Dual**: Correo + notificación en sistema
6. **Informativo**: Muestra tiempo de espera

## ⚠️ Consideraciones

### Rendimiento

- El comando es eficiente: solo consulta OTs en estado específico
- Verifica intervalos antes de enviar
- No afecta el rendimiento del sistema

### Correos

- Usa el sistema de colas si `QUEUE_CONNECTION=database`
- Los correos se envían en segundo plano
- Revisa logs si hay problemas: `storage/logs/laravel.log`

### Testing

Para probar rápidamente:

1. **Configurar intervalo a 1 minuto:**
   ```env
   RECORDATORIO_VALIDACION_INTERVALO=1
   ```

2. **Crear orden de prueba:**
   - Crear solicitud
   - Generar OT
   - Completar OT
   - Validar por calidad
   - NO autorizar como cliente

3. **Iniciar scheduler:**
   ```bash
   php artisan schedule:work
   ```

4. **Observar logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

5. **Esperar 1 minuto** y verificar correo en Mailtrap

## 🔄 Flujo Completo

```
┌─────────────────────┐
│  OT Completada      │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Calidad Valida      │
│ (evento registrado) │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Cliente no         │
│  autoriza           │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Scheduler ejecuta   │
│ cada minuto         │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ ¿Pasó intervalo     │
│ configurado?        │
└──────────┬──────────┘
           │
    SI     │     NO
    ┌──────┴──────┐
    ▼             ▼
┌───────┐    ┌─────────┐
│Enviar │    │ Esperar │
│Email +│    └─────────┘
│Noti   │
└───────┘
```

## 📞 Soporte

Si tienes problemas:

1. Verifica que el scheduler esté corriendo: `php artisan schedule:work`
2. Revisa logs: `storage/logs/laravel.log`
3. Verifica configuración: `php artisan config:show business`
4. Ejecuta manualmente: `php artisan recordatorios:validacion-ot`

---

**Fecha de implementación:** 14 de octubre de 2025  
**Estado:** ✅ Funcional y probado
