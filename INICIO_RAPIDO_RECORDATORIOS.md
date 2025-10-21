# 🚀 Guía de Inicio Rápido - Recordatorios de Validación

## ⚡ Configuración en 3 Minutos

### 1. Configurar Intervalo

Edita tu archivo `.env`:

```env
# Para pruebas (1 minuto)
RECORDATORIO_VALIDACION_INTERVALO=1

# Para producción (6 horas)
# RECORDATORIO_VALIDACION_INTERVALO=360
```

### 2. Limpiar Caché

```bash
php artisan config:clear
```

### 3. Iniciar Scheduler

**En desarrollo:**
```bash
php artisan schedule:work
```

**En producción** (agregar a crontab):
```bash
* * * * * cd /ruta/a/tu/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

## 🧪 Probar el Sistema

### Opción A: Prueba Manual

```bash
# 1. Ejecutar comando directamente
php artisan recordatorios:validacion-ot
```

### Opción B: Simular OT Pendiente

```bash
# 1. Simular una OT como pendiente (usa el ID de una OT existente)
php artisan test:simular-ot-pendiente 2

# 2. Esperar 1 minuto o ejecutar manualmente
php artisan recordatorios:validacion-ot

# 3. Verificar correo en Mailtrap
```

### Opción C: Crear Flujo Completo

1. **Crear solicitud** como cliente
2. **Generar OT** como coordinador
3. **Completar OT** como team leader
4. **Validar por calidad** como calidad
5. **NO autorizar** como cliente
6. **Esperar** 1 minuto
7. **Verificar** correo en Mailtrap

## 📊 Verificar que Funciona

### Ver en el Sistema

1. Inicia sesión como cliente
2. Revisa el icono de campana (🔔)
3. Deberías ver: "⏰ Recordatorio: OT #X pendiente de autorización"

### Ver en Mailtrap

1. Ve a https://mailtrap.io/
2. Abre tu inbox
3. Busca el correo con asunto: "⏰ Recordatorio: OT #X Pendiente de Autorización"

### Ver en Logs

```bash
tail -f storage/logs/laravel.log
```

## 🎯 ¿Qué Hace el Sistema?

```
┌─────────────────────────────────────────────┐
│ Cada minuto el Scheduler ejecuta:          │
│ php artisan recordatorios:validacion-ot    │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
         ┌─────────────────┐
         │ Busca OTs:      │
         │ - Completadas   │
         │ - Validadas     │
         │ - No autorizadas│
         └────────┬────────┘
                  │
                  ▼
       ┌──────────────────────┐
       │ Para cada OT:        │
       │ ¿Pasó intervalo?     │
       └──────┬───────┬───────┘
              │       │
           SÍ │       │ NO
              │       │
              ▼       ▼
       ┌──────────┐  ┌─────────┐
       │ Enviar   │  │ Saltar  │
       │ Email +  │  │ (esperar│
       │ Campana  │  │ más)    │
       └──────────┘  └─────────┘
```

## 🔧 Comandos Útiles

```bash
# Ver configuración actual
php artisan config:show business

# Ejecutar recordatorios manualmente
php artisan recordatorios:validacion-ot

# Simular OT pendiente
php artisan test:simular-ot-pendiente {id_orden}

# Ver scheduler en acción
php artisan schedule:work

# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Limpiar caché
php artisan config:clear
```

## 📧 Contenido del Correo

El cliente recibe un correo con:

- ✅ Saludo personalizado
- ✅ Número de orden
- ✅ Servicio
- ✅ Centro de trabajo
- ✅ Tiempo en espera (ej: "2 días y 3 horas")
- ✅ Botón para autorizar directamente
- ✅ Mensaje de urgencia

**Ejemplo:**

```
⏰ Recordatorio: OT #123 Pendiente de Autorización

Hola Juan Pérez,

Te recordamos que tienes una orden de trabajo pendiente de autorización.

Orden: #123
Servicio: Montacargas
Centro: Upper CDMX
Tiempo en espera: 1 día y 5 horas

La orden fue completada y validada por calidad. Por favor, 
revísala y autorízala para continuar con el proceso de facturación.

[Autorizar Orden]

Es importante que autorices esta orden lo antes posible.

Saludos, Upper Control
```

## ⚠️ Notas Importantes

1. **El scheduler debe estar corriendo** para que funcione automáticamente
2. **En desarrollo** usa `php artisan schedule:work`
3. **En producción** configura el crontab
4. **Límite de correos**: Mailtrap free tiene límite de correos por segundo
5. **Pruebas**: Usa intervalo de 1 minuto
6. **Producción**: Cambia a 360 minutos (6 horas)

## 🆘 Solución de Problemas

### No se envían recordatorios

```bash
# 1. Verifica que el scheduler esté corriendo
ps aux | grep schedule

# 2. Ejecuta manualmente para ver errores
php artisan recordatorios:validacion-ot

# 3. Verifica la configuración
php artisan config:show business

# 4. Limpia caché
php artisan config:clear
```

### "Too many emails per second"

Este es un límite de Mailtrap (plan gratuito). Soluciones:

- Espera unos segundos entre pruebas
- Usa Gmail para pruebas locales
- En producción usa SendGrid/Mailgun

### No hay órdenes pendientes

```bash
# Simula una OT pendiente
php artisan test:simular-ot-pendiente 2

# O verifica manualmente en la base de datos:
# mysql> SELECT id, estatus, calidad_resultado, cliente_autorizada_at 
#        FROM ordenes WHERE estatus='completada' 
#        AND calidad_resultado='validado' 
#        AND cliente_autorizada_at IS NULL;
```

## ✅ Checklist de Implementación

- [ ] Agregar `RECORDATORIO_VALIDACION_INTERVALO=1` a `.env`
- [ ] Ejecutar `php artisan config:clear`
- [ ] Iniciar scheduler: `php artisan schedule:work`
- [ ] Probar con: `php artisan recordatorios:validacion-ot`
- [ ] Verificar correo en Mailtrap
- [ ] Para producción: cambiar intervalo a 360
- [ ] Para producción: configurar crontab

## 📚 Documentación Completa

- **Guía Completa**: [RECORDATORIOS_VALIDACION.md](./RECORDATORIOS_VALIDACION.md)
- **Configuración Email**: [GUIA_CORREOS.md](./GUIA_CORREOS.md)
- **Ejemplos**: [EJEMPLOS_CORREOS.md](./EJEMPLOS_CORREOS.md)

---

**¿Listo?** Ejecuta los 3 comandos del inicio y tendrás el sistema funcionando en 3 minutos. 🚀
