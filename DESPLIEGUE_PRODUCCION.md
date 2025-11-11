# 🚀 Guía de Despliegue a Producción - UPPER CONTROL

## 📋 Índice
1. [Requisitos del Servidor](#requisitos-del-servidor)
2. [Configuración del Entorno (.env)](#configuración-del-entorno-env)
3. [Configuración de Emails](#configuración-de-emails)
4. [Pasos de Despliegue](#pasos-de-despliegue)
5. [Optimizaciones de Producción](#optimizaciones-de-producción)
6. [Seguridad](#seguridad)
7. [Mantenimiento y Backups](#mantenimiento-y-backups)
8. [Troubleshooting](#troubleshooting)

---

## 🖥️ Requisitos del Servidor

### Software Requerido
- **PHP**: >= 8.2
  - Extensiones: `mbstring`, `xml`, `bcmath`, `pdo`, `mysql`, `zip`, `gd`, `curl`, `fileinfo`, `tokenizer`
- **Base de Datos**: MySQL 8.0+ o MariaDB 10.11+
- **Node.js**: >= 18.x (para compilar assets)
- **Composer**: >= 2.x
- **Servidor Web**: Nginx o Apache con mod_rewrite
- **Redis** (opcional pero recomendado para cache y colas)

### Recursos Mínimos Recomendados
- **CPU**: 2 cores
- **RAM**: 4GB
- **Disco**: 20GB SSD
- **Ancho de banda**: 100Mbps

### Permisos de Directorio
```bash
sudo chown -R www-data:www-data /var/www/upper-control
sudo chmod -R 755 /var/www/upper-control
sudo chmod -R 775 /var/www/upper-control/storage
sudo chmod -R 775 /var/www/upper-control/bootstrap/cache
```

---

## ⚙️ Configuración del Entorno (.env)

### 1. Copiar archivo de ejemplo
```bash
cp .env.example .env
```

### 2. Configuración Base de Aplicación

```env
# ========================================
# APLICACIÓN
# ========================================
APP_NAME="UPPER CONTROL"
APP_ENV=production
APP_KEY=base64:TU_CLAVE_AQUI_GENERADA_CON_php_artisan_key_generate
APP_DEBUG=false
APP_URL=https://tu-dominio.com

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_MX

# ========================================
# BASE DE DATOS
# ========================================
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=upper_control_prod
DB_USERNAME=tu_usuario_db
DB_PASSWORD=TU_PASSWORD_SEGURO_AQUI

# ========================================
# CACHE Y SESIONES
# ========================================
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=tu-dominio.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true

# ========================================
# REDIS (Recomendado para producción)
# ========================================
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

# ========================================
# COLAS (JOBS)
# ========================================
QUEUE_CONNECTION=redis
# Si usas Supervisor o systemd para workers:
QUEUE_FAILED_DRIVER=database

# ========================================
# LOGGING
# ========================================
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# ========================================
# FILESYSTEM
# ========================================
FILESYSTEM_DISK=local
# Para archivos públicos (facturas, reportes):
# FILESYSTEM_DISK=public
```

---

## 📧 Configuración de Emails

El sistema envía notificaciones para:
- Solicitudes creadas
- Órdenes de trabajo asignadas
- Validaciones de calidad
- Autorizaciones de cliente
- Recordatorios de validación
- Facturas generadas
- Eventos del sistema

### Opción 1: Gmail (Desarrollo/Testing)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-correo@gmail.com
MAIL_PASSWORD=tu_app_password_de_gmail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-correo@gmail.com
MAIL_FROM_NAME="UPPER CONTROL"
```

**⚠️ Importante para Gmail:**
1. Habilita la "Verificación en 2 pasos" en tu cuenta de Google
2. Genera una "Contraseña de aplicación" en https://myaccount.google.com/apppasswords
3. Usa esa contraseña en `MAIL_PASSWORD`

### Opción 2: SendGrid (Recomendado para Producción)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=TU_SENDGRID_API_KEY_AQUI
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@tu-dominio.com
MAIL_FROM_NAME="UPPER CONTROL"
```

**Ventajas SendGrid:**
- ✅ 100 emails/día gratis
- ✅ Métricas de entrega
- ✅ Alta confiabilidad
- ✅ Sin límites de autenticación 2FA

**Registro:** https://signup.sendgrid.com/

### Opción 3: Amazon SES (Alto Volumen)

```env
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=notificaciones@tu-dominio.com
MAIL_FROM_NAME="UPPER CONTROL"

AWS_ACCESS_KEY_ID=tu_access_key
AWS_SECRET_ACCESS_KEY=tu_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_SES_REGION=us-east-1
```

**Ventajas Amazon SES:**
- ✅ $0.10 por cada 1,000 emails
- ✅ Integración con AWS
- ✅ Escalabilidad ilimitada

### Opción 4: Mailgun

```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=tu-dominio.com
MAILGUN_SECRET=tu_mailgun_api_key
MAIL_FROM_ADDRESS=notificaciones@tu-dominio.com
MAIL_FROM_NAME="UPPER CONTROL"
```

**Ventajas Mailgun:**
- ✅ 5,000 emails/mes gratis (primeros 3 meses)
- ✅ API robusta
- ✅ Validación de emails

### Opción 5: SMTP Corporativo

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.tu-empresa.com
MAIL_PORT=587
MAIL_USERNAME=notificaciones@tu-empresa.com
MAIL_PASSWORD=password_del_servidor_smtp
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@tu-empresa.com
MAIL_FROM_NAME="UPPER CONTROL"
```

### Verificar Configuración de Email

Después de configurar, prueba el envío:

```bash
php artisan tinker
```

```php
Mail::raw('Prueba de email desde UPPER CONTROL', function($msg) {
    $msg->to('tu-email-de-prueba@ejemplo.com')
        ->subject('Test de Configuración');
});
```

---

## 🚀 Pasos de Despliegue

### 1. Clonar Repositorio en Servidor

```bash
cd /var/www
git clone https://github.com/ABFORTI/UPPER_CONTROL.git upper-control
cd upper-control
```

### 2. Instalar Dependencias

```bash
# PHP Dependencies
composer install --optimize-autoloader --no-dev

# Node Dependencies
npm ci

# Compilar assets para producción
npm run build
```

### 3. Configurar Entorno

```bash
# Copiar y editar .env
cp .env.example .env
nano .env

# Generar APP_KEY
php artisan key:generate

# Generar enlace simbólico para storage público
php artisan storage:link
```

### 4. Preparar Base de Datos

```bash
# Crear base de datos
mysql -u root -p
```

```sql
CREATE DATABASE upper_control_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'upper_user'@'localhost' IDENTIFIED BY 'PASSWORD_SEGURO_AQUI';
GRANT ALL PRIVILEGES ON upper_control_prod.* TO 'upper_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Ejecutar migraciones
php artisan migrate --force

# Ejecutar seeders (datos iniciales)
php artisan db:seed --force
```

### 5. Optimizar Aplicación

```bash
# Cachear configuración
php artisan config:cache

# Cachear rutas
php artisan route:cache

# Cachear vistas
php artisan view:cache

# Optimizar autoloader
composer dump-autoload --optimize
```

### 6. Configurar Cron Jobs

Agregar a crontab del servidor:

```bash
crontab -e
```

```cron
# UPPER CONTROL - Tareas programadas
* * * * * cd /var/www/upper-control && php artisan schedule:run >> /dev/null 2>&1
```

### 7. Configurar Queue Workers (Supervisor)

Instalar Supervisor:
```bash
sudo apt install supervisor
```

Crear archivo de configuración:
```bash
sudo nano /etc/supervisor/conf.d/upper-control-worker.conf
```

```ini
[program:upper-control-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/upper-control/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/upper-control/storage/logs/worker.log
stopwaitsecs=3600
```

Activar workers:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start upper-control-worker:*
```

### 8. Configurar Servidor Web (Nginx)

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name tu-dominio.com www.tu-dominio.com;
    
    # Redirigir a HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name tu-dominio.com www.tu-dominio.com;
    root /var/www/upper-control/public;

    # SSL Configuration (usar Certbot para generar)
    ssl_certificate /etc/letsencrypt/live/tu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tu-dominio.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Limits
    client_max_body_size 100M;
    fastcgi_read_timeout 300;
}
```

Activar sitio:
```bash
sudo ln -s /etc/nginx/sites-available/upper-control /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 9. Obtener Certificado SSL (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d tu-dominio.com -d www.tu-dominio.com
```

---

## ⚡ Optimizaciones de Producción

### 1. PHP-FPM Tuning

Editar `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

```bash
sudo systemctl restart php8.2-fpm
```

### 2. OPcache (Caché de Bytecode PHP)

Editar `/etc/php/8.2/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.fast_shutdown=1
```

### 3. Redis Persistence

Editar `/etc/redis/redis.conf`:

```conf
maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

---

## 🔒 Seguridad

### Checklist de Seguridad

#### ✅ Variables de Entorno
- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `SESSION_SECURE_COOKIE=true` (si usas HTTPS)
- [ ] `SESSION_HTTP_ONLY=true`
- [ ] Password de DB fuerte (>16 chars, alfanumérico)

#### ✅ Permisos de Archivos
```bash
# .env solo lectura para www-data
chmod 640 .env
chown www-data:www-data .env

# Storage y cache escribibles
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### ✅ Firewall (UFW)
```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

#### ✅ Actualizar Dependencias
```bash
composer update --no-dev
npm audit fix
```

#### ✅ Deshabilitar Rutas de Desarrollo
Verificar que no estén expuestas en producción:
- `/horizon` (si usas Laravel Horizon)
- `/telescope` (si usas Laravel Telescope)

---

## 💾 Mantenimiento y Backups

### Backup Automático (Spatie Laravel Backup)

El proyecto ya incluye `spatie/laravel-backup`. Configurar:

```bash
# Editar config/backup.php si es necesario
php artisan backup:run
```

Configurar cron para backups diarios:
```cron
# Backup diario a las 2 AM
0 2 * * * cd /var/www/upper-control && php artisan backup:run --only-db >> /dev/null 2>&1

# Backup semanal completo (con archivos)
0 3 * * 0 cd /var/www/upper-control && php artisan backup:run >> /dev/null 2>&1

# Limpiar backups antiguos
0 4 * * * cd /var/www/upper-control && php artisan backup:clean >> /dev/null 2>&1
```

### Backup Manual de Base de Datos

```bash
# Crear backup
mysqldump -u upper_user -p upper_control_prod > backup_$(date +%Y%m%d).sql

# Restaurar backup
mysql -u upper_user -p upper_control_prod < backup_20251111.sql
```

### Monitoreo de Logs

```bash
# Logs de aplicación
tail -f storage/logs/laravel.log

# Logs de Nginx
tail -f /var/log/nginx/error.log

# Logs de workers
tail -f storage/logs/worker.log
```

---

## 🔧 Troubleshooting

### Error: "500 Internal Server Error"
1. Revisar logs: `tail -f storage/logs/laravel.log`
2. Verificar permisos: `ls -la storage bootstrap/cache`
3. Limpiar caches: `php artisan cache:clear && php artisan config:clear`

### Emails no se envían
1. Verificar configuración SMTP: `php artisan tinker` + comando de prueba
2. Revisar logs de mail: `storage/logs/laravel.log`
3. Verificar queue workers: `sudo supervisorctl status upper-control-worker`

### Assets no cargan (CSS/JS)
1. Recompilar: `npm run build`
2. Verificar permisos: `ls -la public/build`
3. Limpiar cache de navegador

### Base de datos no conecta
1. Verificar credenciales en `.env`
2. Test de conexión: `php artisan tinker` > `DB::connection()->getPdo();`
3. Verificar que MySQL esté corriendo: `sudo systemctl status mysql`

### Workers no procesan jobs
```bash
# Reiniciar workers
sudo supervisorctl restart upper-control-worker:*

# Ver estado
sudo supervisorctl status

# Ver logs
tail -f storage/logs/worker.log
```

---

## 📚 Comandos Útiles Post-Deploy

### Limpiar Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Ver Estado de la Aplicación
```bash
php artisan about
```

### Ejecutar Migraciones Pendientes
```bash
php artisan migrate --force
```

### Recrear Caches de Optimización
```bash
php artisan optimize
```

### Ver Jobs Fallidos
```bash
php artisan queue:failed
php artisan queue:retry all
```

---

## 🎯 Checklist Final Pre-Launch

- [ ] `.env` configurado correctamente (producción)
- [ ] `APP_DEBUG=false` y `APP_ENV=production`
- [ ] APP_KEY generada
- [ ] Base de datos migrada y seeded
- [ ] Emails configurados y probados
- [ ] SSL configurado (HTTPS)
- [ ] Cron jobs configurados
- [ ] Queue workers corriendo (Supervisor)
- [ ] Permisos de archivos correctos
- [ ] Firewall configurado
- [ ] Backups automáticos configurados
- [ ] Monitoring/logs configurados
- [ ] Prueba de todas las funcionalidades críticas:
  - [ ] Login/Logout
  - [ ] Creación de solicitudes
  - [ ] Asignación de órdenes
  - [ ] Validación de calidad
  - [ ] Generación de facturas
  - [ ] Envío de notificaciones por email
  - [ ] Generación de QR en facturas
  - [ ] Exportación de reportes Excel/PDF

---

## 📞 Soporte

Para asistencia técnica durante el despliegue:
- Repositorio: https://github.com/ABFORTI/UPPER_CONTROL
- Documentación adicional: Ver archivos `.md` en la raíz del proyecto

---

**¡Éxito en tu despliegue! 🚀**
