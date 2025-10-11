# Solución de Problemas - Archivos Adjuntos

## 🐛 Problema: "El archivo no se encontraba disponible en el sitio"

### Causas Comunes

#### 1. **Enlace simbólico de storage no configurado**
```bash
php artisan storage:link
```

#### 2. **Archivos no se están guardando**

**Verificar:**
1. Revisa los logs: `storage/logs/laravel.log`
2. Verifica permisos de escritura en `storage/app/public/`
3. Comprueba que el directorio existe:
   ```bash
   mkdir -p storage/app/public/solicitudes
   chmod 755 storage/app/public/solicitudes
   ```

#### 3. **Configuración de Filesystem**

Verifica `config/filesystems.php`:
```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],
```

#### 4. **APP_URL incorrecta**

En `.env`:
```env
APP_URL=http://localhost/UPPER_CONTROL/public
```

### 🔍 Diagnóstico

#### Verificar si hay archivos guardados:
```bash
php artisan tinker
```
```php
$solicitud = App\Models\Solicitud::find(15);
$solicitud->archivos;
```

Si retorna `[]` (vacío), los archivos no se guardaron.

#### Verificar estructura de directorios:
```bash
ls -la storage/app/public/solicitudes/
```

### ✅ Solución Paso a Paso

#### 1. **Limpiar caché**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

#### 2. **Recrear enlace simbólico**
```bash
# En Windows (PowerShell como Administrador)
Remove-Item public\storage -Recurse -Force
php artisan storage:link

# En Linux/Mac
rm -rf public/storage
php artisan storage:link
```

#### 3. **Verificar permisos (Linux/Mac)**
```bash
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chown -R www-data:www-data storage
chown -R www-data:www-data bootstrap/cache
```

#### 4. **Probar nuevamente**
1. Crear una nueva solicitud
2. Adjuntar un archivo pequeño (ej: imagen de 100KB)
3. Enviar el formulario
4. Verificar en base de datos:
   ```sql
   SELECT * FROM archivos WHERE fileable_type = 'App\\Models\\Solicitud';
   ```

### 🧪 Prueba Manual

Crea un archivo de prueba directamente:

```php
// En tinker
$solicitud = App\Models\Solicitud::find(15);

$solicitud->archivos()->create([
    'path' => 'test.txt',
    'mime' => 'text/plain',
    'size' => 100,
    'subtipo' => 'test'
]);

// Crear archivo físico
file_put_contents(storage_path('app/public/test.txt'), 'Prueba');

// Verificar URL
$solicitud->archivos->first()->url;
```

Luego accede a: `http://localhost/UPPER_CONTROL/public/storage/test.txt`

Si funciona, el problema es con el formulario de subida.

### 📝 Logs de Depuración

Agrega logs temporales en `SolicitudController.php`:

```php
// Después de la línea: if ($req->hasFile('archivos')) {
\Log::info('Archivos detectados', [
    'tiene_archivos' => $req->hasFile('archivos'),
    'archivos_count' => count($req->file('archivos') ?? []),
]);
```

Revisa: `storage/logs/laravel.log`

### 🚨 Errores Conocidos

#### Error: "The file was not uploaded due to..."
- **Causa**: `upload_max_filesize` o `post_max_size` en `php.ini` muy pequeños
- **Solución**: 
  ```ini
  upload_max_filesize = 20M
  post_max_size = 25M
  ```

#### Error: "Failed to store uploaded file"
- **Causa**: Permisos incorrectos
- **Solución**: `chmod 755 storage/app/public`

#### Archivos con nombres hash extraños
- **Causa**: URL mal formada
- **Solución**: Usar `$archivo->url` en lugar de construir manualmente

### 📱 Validación del Formulario

Asegúrate que el frontend envía correctamente:

```javascript
// En Create.vue, la función guardar() debe tener:
form.post(props.urls.store, {
  forceFormData: true,  // ← IMPORTANTE para archivos
  onSuccess: () => { ... }
});
```

### 🔐 Seguridad

Si los archivos contienen información sensible:

1. **No** uses el disco `public`
2. Crea rutas protegidas para descargas:
   ```php
   Route::get('/archivos/{archivo}/download', [ArchivoController::class, 'download'])
       ->middleware('auth');
   ```

### 🆘 Contacto Soporte

Si el problema persiste:
1. Revisar `storage/logs/laravel.log`
2. Verificar Network tab en DevTools (ver payload de request)
3. Tomar screenshot del error completo
4. Compartir versión de PHP y Laravel

---

**Última actualización:** 11 de octubre de 2025
