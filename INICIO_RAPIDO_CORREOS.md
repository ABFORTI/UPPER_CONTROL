# 📧 Configuración Rápida de Correo Electrónico

## ⚡ Inicio Rápido (5 minutos)

### Opción Recomendada: Mailtrap (Testing)

1. **Registrarse en Mailtrap**
   - Ve a https://mailtrap.io/
   - Crea una cuenta gratuita
   - Crea un inbox

2. **Copiar credenciales**
   ```
   En tu inbox de Mailtrap, ve a "SMTP Settings" y copia:
   - Host
   - Port
   - Username
   - Password
   ```

3. **Editar `.env`**
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=sandbox.smtp.mailtrap.io
   MAIL_PORT=2525
   MAIL_USERNAME=tu_username_aqui
   MAIL_PASSWORD=tu_password_aqui
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@uppercontrol.com
   MAIL_FROM_NAME="Upper Control"
   ```

4. **Limpiar caché**
   ```bash
   php artisan config:clear
   ```

5. **Verificar**
   - Ve a tu inbox en Mailtrap
   - Prueba las acciones del sistema (asignar OT, completar OT, etc.)
   - Verifica que lleguen los correos

---

## 🎯 ¿Qué incluye esta implementación?

### ✅ Documentación Disponible

1. **`GUIA_CORREOS.md`** - Guía completa con todas las opciones de configuración
2. **`EJEMPLOS_CORREOS.md`** - Ejemplos prácticos de uso
3. **`.env.mail.example`** - Plantilla de configuración con todas las opciones

### ✅ Notificaciones Ya Configuradas

Tu aplicación ya tiene estas notificaciones listas para enviar correos:

1. ✅ `OtAsignada` - Cuando se asigna una OT a un usuario
2. ✅ `SolicitudCreadaNotification` - Cuando se crea una solicitud
3. ✅ `OtListaParaCalidad` - Cuando una OT está lista para calidad
4. ✅ `OtValidadaParaCliente` - Cuando calidad valida una OT
5. ✅ `OtAutorizadaParaFacturacion` - Cuando cliente autoriza para facturar
6. ✅ `ClienteAutorizoNotification` - Confirmación de autorización
7. ✅ `CalidadResultadoNotification` - Resultado de inspección de calidad
8. ✅ `SystemEventNotification` - Eventos del sistema

**Todas ya tienen soporte de correo configurado** - Solo necesitas configurar el servicio SMTP.

---

## 🚀 Configuraciones Disponibles

| Servicio | Uso | Costo | Límite | Dificultad |
|----------|-----|-------|--------|------------|
| **Mailtrap** | Testing | Gratis | 500/mes | ⭐ Muy fácil |
| **Gmail** | Dev/Testing | Gratis | 500/día | ⭐⭐ Fácil |
| **SMTP Empresarial** | Producción | Varía | Varía | ⭐⭐ Medio |
| **SendGrid** | Producción | Gratis | 100/día | ⭐⭐⭐ Medio |
| **Mailgun** | Producción | Desde $0 | Varía | ⭐⭐⭐ Medio |
| **Amazon SES** | Producción | $0.10/1000 | Ilimitado | ⭐⭐⭐⭐ Avanzado |

---

## 📝 Comandos Útiles

```bash
# Ver configuración actual
php artisan config:show mail

# Limpiar caché de configuración
php artisan config:clear

# Ver notificaciones en la base de datos
php artisan tinker
>>> App\Models\User::first()->notifications;

# Procesar cola de correos (si usas colas)
php artisan queue:work
```

---

## 🔧 Configuración con Colas (Opcional pero Recomendado)

Para enviar correos en segundo plano:

```bash
# 1. Configurar cola en .env
QUEUE_CONNECTION=database

# 2. Crear tabla de trabajos
php artisan queue:table
php artisan migrate

# 3. Iniciar worker
php artisan queue:work
```

---

## 📚 Documentación Completa

- **Guía Completa**: [GUIA_CORREOS.md](./GUIA_CORREOS.md)
- **Ejemplos de Uso**: [EJEMPLOS_CORREOS.md](./EJEMPLOS_CORREOS.md)
- **Configuración de Ejemplo**: [.env.mail.example](./.env.mail.example)

---

## 🎨 Personalizar Plantillas

```bash
# Publicar plantillas de correo
php artisan vendor:publish --tag=laravel-mail

# Editar estilos
# resources/views/vendor/mail/html/themes/default.css
```

---

## ✉️ Enviar Correo en tu Código

```php
use App\Notifications\OtAsignada;

// Opción 1: A un usuario
$usuario->notify(new OtAsignada($orden));

// Opción 2: A múltiples usuarios
use Illuminate\Support\Facades\Notification;

$usuarios = User::role('coordinador')->get();
Notification::send($usuarios, new OtAsignada($orden));

// Opción 3: Correo simple
use Illuminate\Support\Facades\Mail;

Mail::raw('Mensaje', function ($message) {
    $message->to('correo@ejemplo.com')
            ->subject('Asunto');
});
```

---

## 🆘 Solución de Problemas

### Los correos no se envían
1. Verifica la configuración: `php artisan config:show mail`
2. Limpia la caché: `php artisan config:clear`
3. Revisa los logs: `storage/logs/laravel.log`
4. Verifica que las credenciales SMTP sean correctas

### Error de autenticación
- En Gmail, usa **contraseña de aplicación**, no tu contraseña normal
- Verifica que las credenciales sean correctas
- Revisa que el puerto sea el correcto (587 para TLS, 465 para SSL)

### Los correos llegan a spam
- Usa un servicio profesional (SendGrid, Mailgun)
- Configura registros SPF y DKIM en tu dominio
- Usa un dominio verificado como remitente

---

## 🎯 Recomendación

**Para empezar hoy mismo:**
1. Usa **Mailtrap** (5 minutos de configuración)
2. Prueba todas las notificaciones
3. Cuando estés listo para producción, migra a **SendGrid**

**Ventajas de Mailtrap:**
- ✅ No envía correos reales (seguro para testing)
- ✅ Configuración inmediata
- ✅ Interfaz web para ver correos
- ✅ Gratis hasta 500 correos/mes
- ✅ No requiere dominio verificado

---

## 📞 Soporte

¿Necesitas ayuda? Revisa:
1. **Guía completa**: `GUIA_CORREOS.md`
2. **Ejemplos prácticos**: `EJEMPLOS_CORREOS.md`
3. **Logs de la aplicación**: `storage/logs/laravel.log`

---

## ✨ ¡Listo!

Ahora tienes todo lo necesario para implementar el envío de correos electrónicos en Upper Control. 

**Siguiente paso:** Configura Mailtrap y envía tu primer correo de prueba. 🚀
