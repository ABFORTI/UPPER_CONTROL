# 📋 Sistema de Recordatorios de Validación - Resumen de Implementación

## ✅ Archivos Creados

### 1. Notificación Principal
- **`app/Notifications/RecordatorioValidacionOt.php`**
  - Envía correo y notificación de campana
  - Muestra tiempo de espera (días/horas)
  - Incluye botón de acción para autorizar
  - Implementa ShouldQueue para envío en segundo plano

### 2. Comando Automático
- **`app/Console/Commands/EnviarRecordatoriosValidacion.php`**
  - Se ejecuta cada minuto vía scheduler
  - Busca órdenes pendientes de validación
  - Verifica intervalos para evitar spam
  - Calcula tiempo de espera desde validación de calidad
  - Registra todos los envíos

### 3. Comando de Testing
- **`app/Console/Commands/SimularOtPendiente.php`**
  - Facilita pruebas del sistema
  - Configura OT en estado pendiente
  - Limpia recordatorios anteriores
  - Crea evento de validación simulado

### 4. Documentación
- **`RECORDATORIOS_VALIDACION.md`** - Guía completa (configuración, monitoreo, SQL queries)
- **`INICIO_RAPIDO_RECORDATORIOS.md`** - Guía de 3 minutos para iniciar
- **`.env.mail.example`** - Actualizado con configuración de recordatorios

## 🔧 Archivos Modificados

### 1. `config/business.php`
- Agregada configuración: `recordatorio_validacion_intervalo_minutos`
- Default: 1 minuto (pruebas)
- Producción: 360 minutos (6 horas)

### 2. `app/Console/Kernel.php`
- Agregado schedule para recordatorios
- Se ejecuta cada minuto
- Respeta intervalo configurado

### 3. `INICIO_RAPIDO_CORREOS.md`
- Actualizado con información de recordatorios
- Nueva notificación listada

## ⚙️ Configuración Necesaria

### Variables de Entorno (.env)

```env
# Intervalo de recordatorios (en minutos)
RECORDATORIO_VALIDACION_INTERVALO=1  # Pruebas: 1 minuto
# RECORDATORIO_VALIDACION_INTERVALO=360  # Producción: 6 horas
```

### Iniciar Scheduler

**Desarrollo:**
```bash
php artisan schedule:work
```

**Producción (crontab):**
```bash
* * * * * cd /ruta/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

## 🎯 Funcionalidad

### ¿Qué hace el sistema?

1. **Cada minuto** el scheduler ejecuta el comando
2. **Busca órdenes** que cumplan:
   - `estatus = 'completada'`
   - `calidad_resultado = 'validado'`
   - `cliente_autorizada_at IS NULL`
3. **Verifica intervalo** desde último recordatorio
4. **Si pasó el intervalo**, envía:
   - 📧 Correo electrónico
   - 🔔 Notificación de campana
5. **Registra** el envío en la base de datos

### Contenido del Recordatorio

**Correo incluye:**
- Saludo personalizado con nombre del cliente
- Número de orden
- Servicio
- Centro de trabajo
- **Tiempo en espera** (ej: "2 días y 3 horas")
- Botón de acción directo a la orden
- Mensaje de urgencia

**Notificación incluye:**
- Tipo: `recordatorio_validacion`
- Orden ID
- Horas de espera
- URL a la orden

## 🧪 Cómo Probar

### Método 1: Manual Rápido

```bash
# 1. Configurar intervalo a 1 minuto
echo "RECORDATORIO_VALIDACION_INTERVALO=1" >> .env

# 2. Limpiar caché
php artisan config:clear

# 3. Ejecutar comando
php artisan recordatorios:validacion-ot
```

### Método 2: Simular OT

```bash
# 1. Simular OT pendiente (usa ID de una OT real)
php artisan test:simular-ot-pendiente 2

# 2. Ejecutar recordatorios
php artisan recordatorios:validacion-ot

# 3. Verificar en Mailtrap
```

### Método 3: Flujo Completo

1. Crear solicitud (como cliente)
2. Generar OT (como coordinador)
3. Completar OT (como team leader)
4. Validar (como calidad)
5. NO autorizar (como cliente)
6. Iniciar scheduler: `php artisan schedule:work`
7. Esperar 1 minuto
8. Verificar correo en Mailtrap

## 📊 Monitoreo

### Ver Recordatorios Enviados (SQL)

```sql
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

### Ver Órdenes Pendientes (SQL)

```sql
SELECT 
    o.id,
    o.estatus,
    o.calidad_resultado,
    s.folio,
    u.name as cliente,
    u.email,
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
WHERE o.estatus = 'completada'
  AND o.calidad_resultado = 'validado'
  AND o.cliente_autorizada_at IS NULL;
```

## 🔄 Flujo del Sistema

```
                    ┌─────────────────────┐
                    │   SCHEDULER         │
                    │  (cada minuto)      │
                    └──────────┬──────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │  Comando Recordatorios│
                    └──────────┬──────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │ Buscar OTs:         │
                    │ - Completadas       │
                    │ - Validadas         │
                    │ - No autorizadas    │
                    └──────────┬──────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │ Para cada OT:       │
                    │ ¿Pasó intervalo?    │
                    └────┬─────────┬──────┘
                         │         │
                      SÍ │         │ NO
                         │         │
                         ▼         ▼
              ┌──────────────┐  ┌──────────┐
              │ Calcular     │  │ Saltar   │
              │ horas espera │  │ (esperar)│
              └──────┬───────┘  └──────────┘
                     │
                     ▼
              ┌──────────────┐
              │ Enviar:      │
              │ - Email      │
              │ - Campana    │
              └──────┬───────┘
                     │
                     ▼
              ┌──────────────┐
              │ Registrar    │
              │ en DB        │
              └──────────────┘
```

## 🎯 Características

### ✅ Ventajas

1. **Automático**: No requiere intervención manual
2. **Configurable**: Intervalo ajustable según necesidad
3. **Inteligente**: No envía spam, respeta intervalos
4. **Dual Channel**: Correo + notificación de campana
5. **Trazable**: Todos los recordatorios quedan registrados
6. **Informativo**: Muestra tiempo exacto de espera
7. **Eficiente**: Solo consulta órdenes pendientes
8. **Asíncrono**: Usa colas para no bloquear
9. **Testing-friendly**: Comandos de prueba incluidos
10. **Documentado**: Guías completas y ejemplos

### ⚙️ Configuración Flexible

- **1 minuto** → Pruebas rápidas
- **60 minutos** → Recordatorio cada hora
- **360 minutos** → Cada 6 horas (recomendado producción)
- **720 minutos** → Cada 12 horas
- **1440 minutos** → Una vez al día

## 📝 Comandos Disponibles

```bash
# Ejecutar recordatorios manualmente
php artisan recordatorios:validacion-ot

# Simular OT pendiente para testing
php artisan test:simular-ot-pendiente {orden_id}

# Iniciar scheduler (desarrollo)
php artisan schedule:work

# Ver configuración
php artisan config:show business

# Limpiar caché
php artisan config:clear

# Ver logs
tail -f storage/logs/laravel.log
```

## 🚀 Pasos para Producción

1. **Cambiar intervalo** en `.env`:
   ```env
   RECORDATORIO_VALIDACION_INTERVALO=360
   ```

2. **Limpiar caché**:
   ```bash
   php artisan config:clear
   ```

3. **Configurar crontab**:
   ```bash
   crontab -e
   ```
   Agregar:
   ```
   * * * * * cd /var/www/upper-control && php artisan schedule:run >> /dev/null 2>&1
   ```

4. **Cambiar a servicio SMTP de producción** (SendGrid/Mailgun)

5. **Opcional**: Configurar colas con workers:
   ```bash
   php artisan queue:work --daemon
   ```

## ⚠️ Consideraciones

### Límites de Correo

- **Mailtrap Free**: ~10 correos/minuto
- **Gmail**: 500 correos/día
- **SendGrid Free**: 100 correos/día
- **Mailgun**: 5,000 correos/mes gratis

### Rendimiento

- Comando optimizado, solo consulta OTs específicas
- Verifica intervalos antes de enviar
- No afecta performance del sistema
- Se ejecuta en segundo plano

### Testing

- Usa intervalo de 1 minuto
- Limita recordatorios con comando de simulación
- Verifica en Mailtrap, no usuarios reales

## 🆘 Solución de Problemas

### No se envían recordatorios

1. Verifica scheduler: `ps aux | grep schedule`
2. Ejecuta manualmente: `php artisan recordatorios:validacion-ot`
3. Revisa logs: `tail -f storage/logs/laravel.log`
4. Verifica config: `php artisan config:show business`

### Demasiados correos

- Aumenta el intervalo: `RECORDATORIO_VALIDACION_INTERVALO=360`
- Verifica que no haya múltiples schedulers corriendo

### No hay órdenes pendientes

- Simula una: `php artisan test:simular-ot-pendiente 2`
- Verifica en base de datos con SQL queries

## 📞 Soporte

- **Documentación completa**: `RECORDATORIOS_VALIDACION.md`
- **Inicio rápido**: `INICIO_RAPIDO_RECORDATORIOS.md`
- **Configuración email**: `GUIA_CORREOS.md`
- **Logs**: `storage/logs/laravel.log`

---

**Fecha de implementación:** 14 de octubre de 2025  
**Versión:** 1.0.0  
**Estado:** ✅ Funcional y probado  
**Desarrollador:** Sistema automatizado

## 📈 Próximas Mejoras (Opcional)

- [ ] Dashboard con estadísticas de recordatorios
- [ ] Configurar diferentes intervalos por centro
- [ ] Escalar recordatorios (ej: 1h, 6h, 24h, 48h)
- [ ] Notificación a coordinadores si cliente no responde
- [ ] Integración con WhatsApp/SMS
- [ ] Panel de administración para gestionar recordatorios
