# 📧 Ejemplos Prácticos de Envío de Correos

## 📋 Casos de Uso Actuales en Upper Control

### 1. Notificación de OT Asignada

**Cuándo se envía:** Cuando un coordinador asigna una OT a un usuario

**Código actual:** `app/Notifications/OtAsignada.php`

```php
// En tu controlador (OrdenController.php)
use App\Notifications\OtAsignada;

// Enviar notificación al usuario asignado
$usuario->notify(new OtAsignada($orden));
```

---

### 2. Notificación de Solicitud Creada

**Cuándo se envía:** Cuando un cliente crea una nueva solicitud

**Uso en controlador:**

```php
use App\Notifications\SolicitudCreadaNotification;
use App\Support\Notify;

// Notificar a coordinadores del centro
$coordinadores = Notify::usersByRoleAndCenter('coordinador', $solicitud->id_centrotrabajo);
Notify::send($coordinadores, new SolicitudCreadaNotification($solicitud));
```

---

### 3. Notificación de OT Lista para Calidad

**Cuándo se envía:** Cuando una OT es completada y necesita revisión de calidad

**Uso:**

```php
use App\Notifications\OtListaParaCalidad;

// Notificar al equipo de calidad
$usuariosCalidad = User::role('calidad')->get();
Notification::send($usuariosCalidad, new OtListaParaCalidad($orden));
```

---

### 4. Notificación de Cliente Autoriza OT

**Cuándo se envía:** Cuando el cliente autoriza una OT para facturación

**Uso actual en `ClienteController.php`:**

```php
use App\Notifications\OtAutorizadaParaFacturacion;

// Avisar a facturación del centro
$factUsers = Notify::usersByRoleAndCenter('facturacion', $orden->id_centrotrabajo);
Notify::send($factUsers, new OtAutorizadaParaFacturacion($orden));
```

---

## 🚀 Cómo Enviar Correos en tu Código

### Método 1: Enviar a un usuario específico

```php
use App\Models\User;
use App\Notifications\OtAsignada;

$usuario = User::find(1);
$usuario->notify(new OtAsignada($orden));
```

### Método 2: Enviar a múltiples usuarios

```php
use Illuminate\Support\Facades\Notification;
use App\Models\User;

$usuarios = User::role('coordinador')->get();
Notification::send($usuarios, new OtAsignada($orden));
```

### Método 3: Enviar según rol y centro

```php
use App\Support\Notify;

// Enviar a todos los coordinadores de un centro específico
$coordinadores = Notify::usersByRoleAndCenter('coordinador', $centroId);
Notify::send($coordinadores, new SolicitudCreadaNotification($solicitud));
```

### Método 4: Correo simple sin notificación

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Mensaje de prueba', function ($message) {
    $message->to('correo@ejemplo.com')
            ->subject('Asunto del correo');
});
```

### Método 5: Correo con vista personalizada

```php
use Illuminate\Support\Facades\Mail;

Mail::send('emails.orden-completada', ['orden' => $orden], function ($message) use ($usuario) {
    $message->to($usuario->email)
            ->subject("OT #{$orden->id} Completada");
});
```

---

## 📝 Crear Nuevas Notificaciones

### Paso 1: Generar la clase

```bash
php artisan make:notification OrdenRetrasadaNotification
```

### Paso 2: Editar la notificación

```php
<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OrdenRetrasadaNotification extends Notification
{
    use Queueable;

    public function __construct(public Orden $orden, public int $diasRetraso) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("⚠️ OT #{$this->orden->id} Retrasada")
            ->greeting("Hola {$notifiable->name},")
            ->line("La orden de trabajo #{$this->orden->id} lleva **{$this->diasRetraso} días de retraso**.")
            ->line("**Servicio:** {$this->orden->servicio?->nombre}")
            ->line("Por favor, revisa el estado de esta orden.")
            ->action('Ver Orden', route('ordenes.show', $this->orden))
            ->line('Gracias por tu atención.')
            ->salutation('Saludos, Upper Control');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'orden_retrasada',
            'orden_id' => $this->orden->id,
            'dias_retraso' => $this->diasRetraso,
            'mensaje' => "OT #{$this->orden->id} con {$this->diasRetraso} días de retraso",
            'url' => route('ordenes.show', $this->orden),
        ];
    }
}
```

### Paso 3: Usar la notificación

```php
use App\Notifications\OrdenRetrasadaNotification;

$usuario->notify(new OrdenRetrasadaNotification($orden, $diasRetraso));
```

---

## 🎨 Personalizar Plantillas de Correo

### Publicar plantillas

```bash
php artisan vendor:publish --tag=laravel-mail
```

Esto crea las plantillas en: `resources/views/vendor/mail/`

### Estructura de plantillas

```
resources/views/vendor/mail/
├── html/
│   ├── button.blade.php       # Botones de acción
│   ├── footer.blade.php       # Footer del correo
│   ├── header.blade.php       # Header del correo
│   ├── layout.blade.php       # Layout principal
│   ├── message.blade.php      # Plantilla de mensaje
│   ├── panel.blade.php        # Paneles de contenido
│   └── themes/
│       └── default.css        # Estilos CSS
└── text/
    ├── button.blade.php
    ├── footer.blade.php
    ├── header.blade.php
    ├── layout.blade.php
    ├── message.blade.php
    └── panel.blade.php
```

### Personalizar colores

Edita `resources/views/vendor/mail/html/themes/default.css`:

```css
/* Color primario de Upper Control */
.button-primary {
    background-color: #1A73E8 !important;
    border-color: #1A73E8 !important;
}

/* Header */
.header a {
    color: #1A73E8;
    font-size: 19px;
    font-weight: bold;
    text-decoration: none;
}

/* Footer */
.footer {
    background-color: #f5f5f5;
    border-top: 1px solid #e0e0e0;
}
```

---

## 📊 Monitorear Correos Enviados

### Ver en logs (si MAIL_MAILER=log)

```bash
tail -f storage/logs/laravel.log | grep "mail"
```

### Ver en base de datos (notificaciones)

```sql
SELECT * FROM notifications 
WHERE type LIKE '%Notification' 
AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC;
```

### Ver trabajos de cola pendientes

```sql
SELECT * FROM jobs ORDER BY created_at DESC;
```

### Ver trabajos fallidos

```sql
SELECT * FROM failed_jobs ORDER BY failed_at DESC;
```

---

## 🔧 Comandos Útiles

### Probar envío de correo

```bash
php artisan test:email tucorreo@ejemplo.com
```

### Limpiar caché de configuración

```bash
php artisan config:clear
```

### Ver configuración actual

```bash
php artisan config:show mail
```

### Procesar cola de correos

```bash
# Procesar todos los trabajos pendientes
php artisan queue:work

# Procesar solo la cola de correos
php artisan queue:work --queue=mail-queue

# Procesar una vez y salir
php artisan queue:work --once

# Con timeout
php artisan queue:work --timeout=60
```

### Reintentar trabajos fallidos

```bash
# Ver trabajos fallidos
php artisan queue:failed

# Reintentar un trabajo específico
php artisan queue:retry [id]

# Reintentar todos
php artisan queue:retry all

# Limpiar trabajos fallidos
php artisan queue:flush
```

---

## 🎯 Buenas Prácticas

### 1. Usar Colas para Correos

```php
// Implementa ShouldQueue
class MiNotificacion extends Notification implements ShouldQueue
{
    use Queueable;
    
    // El correo se enviará en segundo plano
}
```

### 2. Manejar Errores

```php
public function failed(\Throwable $exception)
{
    // Log del error
    \Log::error("Fallo al enviar notificación: " . $exception->getMessage());
    
    // Notificar a administradores
    // ...
}
```

### 3. Preferencias de Usuario

```php
public function via($notifiable): array
{
    // Solo enviar email si el usuario lo permite
    if ($notifiable->prefers_email_notifications) {
        return ['database', 'mail'];
    }
    
    return ['database'];
}
```

### 4. Rate Limiting

```php
use Illuminate\Support\Facades\RateLimiter;

// Limitar notificaciones por usuario
$executed = RateLimiter::attempt(
    'send-notification:'.$user->id,
    5, // máximo 5 por minuto
    function() use ($user, $notification) {
        $user->notify($notification);
    }
);
```

### 5. Testing

```php
// En tus tests
use Illuminate\Support\Facades\Notification;

public function test_envia_notificacion_cuando_ot_completada()
{
    Notification::fake();
    
    // Ejecutar acción que envía notificación
    $this->post(route('ordenes.completar', $orden));
    
    // Verificar que se envió
    Notification::assertSentTo(
        $usuario,
        OtCompletadaNotification::class
    );
}
```

---

## 📱 Próximos Pasos

### 1. Configurar correo (elige una opción)
- ✅ Mailtrap para testing
- ✅ Gmail para desarrollo
- ✅ SendGrid para producción

### 2. Probar comando
```bash
php artisan test:email tucorreo@ejemplo.com
```

### 3. Configurar colas (opcional pero recomendado)
```bash
php artisan queue:table
php artisan migrate
php artisan queue:work
```

### 4. Personalizar plantillas
```bash
php artisan vendor:publish --tag=laravel-mail
```

### 5. Crear notificaciones personalizadas según necesites

---

## 🆘 Soporte

Si tienes problemas:

1. Revisa los logs: `storage/logs/laravel.log`
2. Verifica la configuración: `php artisan config:show mail`
3. Prueba con el comando: `php artisan test:email`
4. Consulta la guía completa: `GUIA_CORREOS.md`
