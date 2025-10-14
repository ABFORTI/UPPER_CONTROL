# 📧 Guía de Implementación de Correo Electrónico

## 📋 Índice
1. [Opciones de Configuración](#opciones-de-configuración)
2. [Opción 1: Gmail (Recomendado para Desarrollo)](#opción-1-gmail)
3. [Opción 2: Mailtrap (Testing)](#opción-2-mailtrap)
4. [Opción 3: SMTP Empresarial](#opción-3-smtp-empresarial)
5. [Opción 4: Servicios Cloud](#opción-4-servicios-cloud)
6. [Verificación y Testing](#verificación-y-testing)
7. [Personalizar Plantillas](#personalizar-plantillas)

---

## Opciones de Configuración

Tu aplicación ya tiene notificaciones configuradas que pueden enviar correos. Solo necesitas configurar el servicio de correo.

### Estado Actual
- ✅ Notificaciones de base de datos funcionando
- ✅ Clases de notificación con soporte de correo (`via ['database','mail']`)
- ⚠️ Correo configurado en modo `log` (solo guarda en logs)

---

## Opción 1: Gmail (Recomendado para Desarrollo)

### Paso 1: Habilitar "Contraseña de Aplicación" en Gmail

1. Ve a tu cuenta de Google: https://myaccount.google.com/
2. Seguridad → Verificación en dos pasos (actívala si no está activa)
3. Contraseñas de aplicación → Generar nueva contraseña
4. Selecciona "Correo" y "Otro (nombre personalizado)"
5. Copia la contraseña de 16 caracteres generada

### Paso 2: Configurar `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tucorreo@gmail.com
MAIL_PASSWORD=xxxx xxxx xxxx xxxx  # Contraseña de aplicación de 16 dígitos
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tucorreo@gmail.com
MAIL_FROM_NAME="Upper Control"
```

### Paso 3: Limpiar caché y probar

```bash
php artisan config:clear
php artisan queue:work  # Si usas colas
```

### ⚠️ Limitaciones de Gmail
- **Límite**: 500 correos/día para cuentas gratuitas
- **Uso**: Solo para desarrollo/testing, no producción

---

## Opción 2: Mailtrap (Testing - Recomendado para Desarrollo)

Mailtrap captura todos los correos sin enviarlos realmente. Perfecto para testing.

### Paso 1: Crear cuenta gratuita
1. Ve a https://mailtrap.io/
2. Crea una cuenta gratuita
3. Crea un inbox
4. Copia las credenciales SMTP

### Paso 2: Configurar `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=tu_username_mailtrap
MAIL_PASSWORD=tu_password_mailtrap
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@uppercontrol.com
MAIL_FROM_NAME="Upper Control"
```

### ✅ Ventajas
- Gratis hasta 500 correos/mes
- No envía correos reales (seguro para testing)
- Interfaz web para ver correos
- No requiere credenciales reales

---

## Opción 3: SMTP Empresarial

Si tu empresa tiene servidor SMTP propio.

### Configurar `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.tuempresa.com
MAIL_PORT=587  # o 465 con SSL
MAIL_USERNAME=usuario@tuempresa.com
MAIL_PASSWORD=tu_password
MAIL_ENCRYPTION=tls  # o 'ssl' si el puerto es 465
MAIL_FROM_ADDRESS=noreply@tuempresa.com
MAIL_FROM_NAME="Upper Control"
```

### Configuraciones comunes:
- **TLS**: Puerto 587
- **SSL**: Puerto 465
- **Sin encriptación**: Puerto 25 (no recomendado)

---

## Opción 4: Servicios Cloud (Producción)

### A) SendGrid (Recomendado para Producción)

**Ventajas**: 100 correos/día gratis, excelente entregabilidad

```bash
composer require symfony/sendgrid-mailer
```

`.env`:
```env
MAIL_MAILER=sendgrid
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=tu_api_key_de_sendgrid
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="Upper Control"
```

### B) Mailgun

```bash
composer require symfony/mailgun-mailer
```

`.env`:
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=tu-dominio.mailgun.org
MAILGUN_SECRET=tu_api_key
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="Upper Control"
```

### C) Amazon SES

```bash
composer require aws/aws-sdk-php
```

`.env`:
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=tu_access_key
AWS_SECRET_ACCESS_KEY=tu_secret_key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="Upper Control"
```

---

## Verificación y Testing

### 1. Verificar configuración

Crea un comando artisan de prueba:

```bash
php artisan make:command TestEmail
```

Edita `app/Console/Commands/TestEmail.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmail extends Command
{
    protected $signature = 'test:email {email}';
    protected $description = 'Enviar correo de prueba';

    public function handle()
    {
        $email = $this->argument('email');
        
        Mail::raw('Este es un correo de prueba desde Upper Control', function ($message) use ($email) {
            $message->to($email)
                    ->subject('Correo de Prueba - Upper Control');
        });

        $this->info("✅ Correo enviado a: {$email}");
        $this->info('Revisa tu bandeja de entrada (puede tardar unos segundos).');
    }
}
```

### 2. Ejecutar prueba

```bash
php artisan test:email tucorreo@ejemplo.com
```

### 3. Verificar logs

Si algo falla, revisa:
```bash
storage/logs/laravel.log
```

---

## Personalizar Plantillas

### 1. Publicar plantillas de correo

```bash
php artisan vendor:publish --tag=laravel-mail
```

Esto crea las plantillas en `resources/views/vendor/mail/`

### 2. Personalizar colores y estilos

Edita `resources/views/vendor/mail/html/themes/default.css`:

```css
/* Color principal */
.button-primary {
    background-color: #1A73E8;
}

/* Logo y marca */
.header a {
    color: #1A73E8;
    font-size: 19px;
    font-weight: bold;
}
```

### 3. Crear plantilla personalizada

```bash
php artisan make:notification OrdenCompletadaNotification
```

Ejemplo completo en `app/Notifications/OrdenCompletadaNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OrdenCompletadaNotification extends Notification
{
    use Queueable;

    public function __construct(public Orden $orden) {}

    public function via($notifiable): array 
    { 
        return ['database', 'mail']; 
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("OT #{$this->orden->id} Completada")
            ->greeting("¡Hola {$notifiable->name}!")
            ->line("La orden de trabajo #{$this->orden->id} ha sido completada.")
            ->line("**Servicio:** {$this->orden->servicio?->nombre}")
            ->line("**Centro:** {$this->orden->centro?->nombre}")
            ->action('Ver Orden de Trabajo', route('ordenes.show', $this->orden))
            ->line('Gracias por usar Upper Control.')
            ->salutation('Saludos, El equipo de Upper Control');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'orden_completada',
            'orden_id' => $this->orden->id,
            'mensaje' => "OT #{$this->orden->id} completada",
            'url' => route('ordenes.show', $this->orden),
        ];
    }
}
```

---

## 🚀 Configuración con Colas (Queue)

Para enviar correos en segundo plano (recomendado en producción):

### 1. Configurar `.env`

```env
QUEUE_CONNECTION=database
```

### 2. Crear tabla de trabajos

```bash
php artisan queue:table
php artisan migrate
```

### 3. Ejecutar worker

En desarrollo:
```bash
php artisan queue:work
```

En producción (con supervisor):
```ini
[program:upper-control-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /ruta/a/tu/proyecto/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/ruta/a/tu/proyecto/storage/logs/worker.log
stopwaitsecs=3600
```

---

## 📊 Verificar Notificaciones Actuales

Tu aplicación ya tiene estas notificaciones configuradas:

1. ✅ `CalidadResultadoNotification` - Resultado de calidad
2. ✅ `ClienteAutorizoNotification` - Cliente autorizó OT
3. ✅ `OtAutorizadaParaFacturacion` - OT lista para facturar
4. ✅ `OtAsignada` - OT asignada a un usuario
5. ✅ `OtValidadaParaCliente` - OT validada por calidad
6. ✅ `OtListaParaCalidad` - OT lista para revisión de calidad
7. ✅ `SystemEventNotification` - Eventos del sistema
8. ✅ `SolicitudCreadaNotification` - Nueva solicitud creada

**Todas ya tienen soporte de correo configurado** 📧

---

## 🎯 Recomendación Final

**Para empezar rápido:**

1. **Desarrollo/Testing**: Usa **Mailtrap** (opción 2) o **Gmail** (opción 1)
2. **Producción**: Usa **SendGrid** (opción 4A) - 100 correos/día gratis

### Configuración rápida con Mailtrap (2 minutos):

1. Regístrate en https://mailtrap.io/
2. Copia las credenciales
3. Actualiza tu `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=TU_USERNAME
MAIL_PASSWORD=TU_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@uppercontrol.com
MAIL_FROM_NAME="Upper Control"
```

4. Limpia caché:
```bash
php artisan config:clear
```

5. Prueba:
```bash
php artisan test:email tucorreo@ejemplo.com
```

¡Listo! 🎉

---

## 🆘 Solución de Problemas

### Error: "Connection could not be established"
- Verifica host, puerto y credenciales
- Comprueba firewall/antivirus
- Intenta con otro puerto (587, 465, 25)

### Error: "Authentication failed"
- Verifica usuario y contraseña
- En Gmail, usa contraseña de aplicación (no tu contraseña normal)
- Verifica que la cuenta no esté bloqueada

### Los correos no llegan
- Revisa carpeta de spam
- Verifica que el dominio del remitente sea válido
- Usa un servicio profesional para mejor entregabilidad

### Correos lentos
- Implementa colas (Queue)
- Usa servicios cloud en lugar de SMTP directo
