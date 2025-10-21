# 📧 Sistema de Emails Personalizados - Upper Control

## 🎨 Descripción General

Se ha implementado un **sistema completo de notificaciones por email** con diseño moderno, profesional y elegante. Cada tipo de notificación tiene un formato personalizado que se adapta perfectamente a su contexto.

---

## 🎯 Características Principales

### ✅ Diseño Visual Moderno
- **Gradientes de marca**: Indigo → Purple → Pink
- **Tipografía elegante**: Sistema de fuentes San Francisco / Segoe UI
- **Botones con sombras**: Bordes redondeados y efectos visuales
- **Responsive**: Perfectamente adaptado a móviles y escritorio
- **Iconos emoji**: Para mejor identificación visual

### 📝 Estructura de Emails
Todos los emails incluyen:
- ✉️ **Saludo personalizado**: "¡Hola {nombre}!"
- 📋 **Detalles estructurados**: Información organizada con bullets
- 🎯 **Llamado a la acción**: Botón destacado para acción principal
- 💬 **Mensaje contextual**: Explicación clara del propósito
- ✍️ **Firma profesional**: "Equipo Upper Control"

---

## 📬 Tipos de Notificaciones Implementadas

### 1. 📝 Nueva Solicitud de Servicio
**Archivo**: `SolicitudCreadaNotification.php`

**Cuándo se envía**: Al crear una nueva solicitud

**Destinatarios**: Coordinadores/Administradores

**Contenido**:
- Folio de la solicitud
- Servicio solicitado
- Centro de trabajo
- Tamaño del servicio
- Fecha de creación
- Botón: "📋 Revisar Solicitud"

---

### 2. 🎯 OT Asignada a Responsable
**Archivo**: `OtAsignada.php`

**Cuándo se envía**: Al asignar una OT a un líder de equipo

**Destinatarios**: Responsable de equipo asignado

**Contenido**:
- Número de OT
- Servicio a realizar
- Centro de trabajo
- Fecha de creación
- Recordatorio de responsabilidades
- Botón: "👁️ Ver Orden de Trabajo"

---

### 3. ✅ OT Lista para Revisión de Calidad
**Archivo**: `OtListaParaCalidad.php`

**Cuándo se envía**: Al completarse una OT y quedar pendiente de revisión

**Destinatarios**: Personal de Calidad

**Contenido**:
- Número de OT
- Servicio realizado
- Centro de trabajo
- Responsable del trabajo
- Solicitud de inspección
- Botón: "🔍 Revisar en Calidad"

---

### 4. ✅❌ Resultado de Revisión de Calidad
**Archivo**: `CalidadResultadoNotification.php`

**Cuándo se envía**: Al validar o rechazar una OT en calidad

**Destinatarios**: Responsable de la OT

**Contenido**:
- Estado: ✅ APROBADO o ❌ RECHAZADO
- Número de OT
- Servicio
- Centro de trabajo
- Observaciones (si las hay)
- Mensaje diferenciado según resultado
- Botón: "📋 Ver Orden de Trabajo"

**Características especiales**:
- Icono y mensaje cambian según resultado
- Observaciones destacadas en cursiva
- Alerta visual en caso de rechazo

---

### 5. ✅ OT Validada - Pendiente Autorización Cliente
**Archivo**: `OtValidadaParaCliente.php`

**Cuándo se envía**: Al aprobar una OT en calidad

**Destinatarios**: Cliente/Usuario autorizado

**Contenido**:
- Número de OT
- Servicio realizado
- Centro de trabajo
- Estado de validación
- Recordatorio de acción requerida
- Botón: "✅ Autorizar Orden de Trabajo"

---

### 6. ✅ Cliente Autorizó OT
**Archivo**: `ClienteAutorizoNotification.php`

**Cuándo se envía**: Al autorizar el cliente una OT

**Destinatarios**: Administradores/Personal interesado

**Contenido**:
- Número de OT
- Servicio
- Centro de trabajo
- Confirmación de autorización
- Aviso de siguiente paso (facturación)
- Botón: "📋 Ver Orden de Trabajo"

---

### 7. 💰 OT Lista para Facturación
**Archivo**: `OtAutorizadaParaFacturacion.php`

**Cuándo se envía**: Al autorizar el cliente una OT

**Destinatarios**: Personal de Facturación

**Contenido**:
- Número de OT
- Servicio
- Centro de trabajo
- Estado: Lista para facturar
- Acción requerida
- Botón: "💵 Generar Factura"

---

### 8. 📄 Factura Generada
**Archivo**: `FacturaGeneradaNotification.php`

**Cuándo se envía**: Al generar una factura

**Destinatarios**: Cliente/Interesados

**Contenido**:
- Número de factura
- OT relacionada
- Servicio facturado
- Centro de trabajo
- **Total en MXN**
- Fecha de emisión
- **PDF adjunto** (si está disponible)
- Botón: "📋 Ver Factura Completa"

**Características especiales**:
- Adjunta PDF automáticamente si existe
- Mensaje diferenciado si PDF está en generación

---

### 9. ⏰ Recordatorio de Validación Pendiente
**Archivo**: `RecordatorioValidacionOt.php`

**Cuándo se envía**: Por tarea programada (cada X horas)

**Destinatarios**: Cliente/Usuario con OT pendiente

**Contenido**:
- Número de OT
- Servicio
- Centro de trabajo
- **Tiempo en espera** (días y horas)
- Urgencia del recordatorio
- Botón: "✅ Autorizar Orden Ahora"

**Características especiales**:
- Calcula tiempo en espera dinámicamente
- Formato amigable: "2 días y 5 horas"
- Tono urgente pero profesional

---

### 10. 🔔 Eventos del Sistema
**Archivo**: `SystemEventNotification.php`

**Cuándo se envía**: Eventos genéricos del sistema

**Destinatarios**: Variable según evento

**Contenido**:
- Título personalizado del evento
- Mensaje descriptivo
- URL opcional para acción
- Botón: "🔗 Ver Detalles" (si hay URL)

---

## 🎨 Diseño Visual (CSS Personalizado)

### Colores de Marca
```css
/* Gradiente de fondo */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Header gradiente */
background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);

/* Botón primario */
background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);

/* Botón éxito */
background: linear-gradient(135deg, #10b981 0%, #059669 100%);

/* Botón error */
background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
```

### Características del Diseño
- ✅ Bordes redondeados (12px en tarjetas, 8px en botones)
- ✅ Sombras suaves para profundidad
- ✅ Espaciado generoso (40px padding)
- ✅ Tipografía moderna y legible
- ✅ Header con gradiente llamativo
- ✅ Footer con texto en blanco semi-transparente

---

## 📁 Archivos Modificados

### Notificaciones (app/Notifications/)
1. ✅ `SolicitudCreadaNotification.php`
2. ✅ `OtAsignada.php`
3. ✅ `OtListaParaCalidad.php`
4. ✅ `CalidadResultadoNotification.php`
5. ✅ `OtValidadaParaCliente.php`
6. ✅ `ClienteAutorizoNotification.php`
7. ✅ `OtAutorizadaParaFacturacion.php`
8. ✅ `FacturaGeneradaNotification.php`
9. ✅ `RecordatorioValidacionOt.php`
10. ✅ `SystemEventNotification.php`

### Vistas de Email (resources/views/vendor/mail/)
1. ✅ `html/themes/default.css` - CSS personalizado con gradientes
2. ✅ `html/header.blade.php` - Header con icono y estilo mejorado

---

## 🔧 Configuración

### Archivo .env
```env
MAIL_MAILER=log  # Cambiar a 'smtp' para producción
MAIL_FROM_ADDRESS=noreply@uppercontrol.com
MAIL_FROM_NAME="Upper Control"
```

### Modo de Desarrollo (Actual)
- **Driver**: `log`
- Los emails se guardan en `storage/logs/laravel.log`
- No se consumen límites de Mailtrap

### Modo de Producción
1. Cambiar `MAIL_MAILER=smtp` en `.env`
2. Configurar credenciales SMTP reales
3. Ejecutar `php artisan config:clear`

---

## 🎯 Mejores Prácticas Implementadas

### ✅ Contenido
- Mensajes claros y concisos
- Información estructurada con bullets
- Llamado a la acción evidente
- Tono profesional pero amigable

### ✅ Diseño
- Responsive (móvil y escritorio)
- Gradientes de marca consistentes
- Iconos para mejor identificación
- Jerarquía visual clara

### ✅ Técnico
- Uso de Markdown de Laravel
- Código limpio y mantenible
- Fácil personalización
- Performance optimizado

---

## 📊 Flujo Completo de Notificaciones

```
1. 📝 Nueva Solicitud
   └─> SolicitudCreadaNotification → Coordinador

2. ✅ Solicitud Aprobada → Genera OT
   └─> OtAsignada → Responsable de Equipo

3. 🏗️ Trabajo en Progreso (avances)
   └─> (Sin notificación automática)

4. ✅ OT Completada
   └─> OtListaParaCalidad → Personal de Calidad

5a. ✅ Calidad Aprueba
    └─> CalidadResultadoNotification → Responsable
    └─> OtValidadaParaCliente → Cliente

5b. ❌ Calidad Rechaza
    └─> CalidadResultadoNotification → Responsable
    └─> (Volver a paso 3)

6. ✅ Cliente Autoriza
   └─> ClienteAutorizoNotification → Administradores
   └─> OtAutorizadaParaFacturacion → Facturación

7. 💰 Factura Generada
   └─> FacturaGeneradaNotification → Cliente (con PDF adjunto)

⏰ Recordatorios Periódicos
   └─> RecordatorioValidacionOt → Clientes con OTs pendientes
```

---

## 🚀 Cómo Probar

### Ver emails en logs:
```bash
# Limpiar logs anteriores
echo "" > storage/logs/laravel.log

# Realizar acción que genere email

# Ver el email en el log
cat storage/logs/laravel.log
```

### Cambiar a Mailtrap para ver diseño visual:
```bash
# 1. Editar .env
MAIL_MAILER=smtp

# 2. Limpiar cache
php artisan config:clear

# 3. Realizar acción

# 4. Ver en https://mailtrap.io
```

---

## 📝 Notas Importantes

1. ✅ **Todos los emails personalizados** - Cada uno con contexto específico
2. ✅ **Diseño unificado** - Misma identidad visual en todos
3. ✅ **Iconos consistentes** - Mejor identificación de tipo de email
4. ✅ **Información completa** - Todos los datos relevantes incluidos
5. ✅ **Llamados a acción claros** - Botones destacados
6. ✅ **PDFs adjuntos** - En facturas cuando están disponibles
7. ✅ **Recordatorios inteligentes** - Con cálculo de tiempo de espera

---

## 🎨 Ejemplos Visuales

### Email de OT Asignada
```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔧 UPPER CONTROL (gradiente header)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

¡Hola Juan Pérez!

Se te ha asignado una nueva Orden de Trabajo
para que la gestiones.

Información de la OT:
• Número: #42
• Servicio: Mantenimiento Preventivo
• Centro de Trabajo: Planta Norte
• Fecha de Creación: 14/10/2025 10:30

Como responsable de equipo, es tu tarea
coordinar y supervisar el trabajo.

┌─────────────────────────────────┐
│   👁️ Ver Orden de Trabajo      │
└─────────────────────────────────┘
      (botón con gradiente)

Recuerda registrar los avances y evidencias
conforme se realice el trabajo.

Éxito en tu gestión,
Equipo Upper Control
```

---

## 🔄 Mantenimiento Futuro

### Para agregar nuevos emails:
1. Crear notificación en `app/Notifications/`
2. Usar estructura similar a las existentes
3. Agregar iconos emoji relevantes
4. Incluir todos los datos necesarios
5. Documentar en este archivo

### Para cambiar diseño global:
1. Editar `resources/views/vendor/mail/html/themes/default.css`
2. Probar en modo log primero
3. Verificar en clientes de email populares

---

**Última actualización**: 14/10/2025
**Autor**: Sistema Upper Control
**Versión**: 2.0 - Emails Personalizados
