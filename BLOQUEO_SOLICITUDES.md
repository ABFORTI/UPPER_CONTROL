# Bloqueo de Solicitudes por OTs Vencidas

## Descripción
Esta funcionalidad bloquea la creación de nuevas solicitudes cuando hay órdenes de trabajo completadas que no han sido autorizadas por el cliente dentro del tiempo límite configurado.

## Configuración

El tiempo límite se controla desde el archivo `config/business.php`:

```php
'ot_autorizacion_timeout_minutos' => env('OT_AUTORIZACION_TIMEOUT_MINUTOS', 1),
```

### Valores recomendados:

**Para PRUEBAS/DESARROLLO:**
```php
'ot_autorizacion_timeout_minutos' => 1,  // 1 minuto
```

**Para PRODUCCIÓN:**
```php
'ot_autorizacion_timeout_minutos' => 4320,  // 72 horas (3 días)
```

### Cambiar el tiempo límite:

#### Opción 1: Editar directamente el archivo de configuración
1. Abre `config/business.php`
2. Cambia el valor del segundo parámetro de `env()`:
   - Para 1 minuto (pruebas): `env('OT_AUTORIZACION_TIMEOUT_MINUTOS', 1)`
   - Para 72 horas (producción): `env('OT_AUTORIZACION_TIMEOUT_MINUTOS', 4320)`
3. Guarda el archivo
4. Limpia la caché: `php artisan config:clear`

#### Opción 2: Usar variables de entorno (recomendado para producción)
1. Abre tu archivo `.env`
2. Agrega o modifica esta línea:
   ```
   OT_AUTORIZACION_TIMEOUT_MINUTOS=4320
   ```
3. Guarda el archivo
4. Limpia la caché: `php artisan config:clear`

### Otros valores útiles:

| Tiempo deseado | Minutos | Uso |
|----------------|---------|-----|
| 1 minuto | 1 | Pruebas inmediatas |
| 5 minutos | 5 | Pruebas rápidas |
| 30 minutos | 30 | Pruebas con más tiempo |
| 1 hora | 60 | Pruebas realistas cortas |
| 24 horas (1 día) | 1440 | Producción relajada |
| 48 horas (2 días) | 2880 | Producción intermedia |
| 72 horas (3 días) | 4320 | **Producción estándar** ✅ |
| 168 horas (7 días) | 10080 | Producción muy permisiva |

## Deshabilitar la funcionalidad

Si necesitas desactivar temporalmente el bloqueo sin cambiar el tiempo:

En `config/business.php`:
```php
'bloquear_solicitudes_por_ots_vencidas' => false,
```

O en `.env`:
```
BLOQUEAR_SOLICITUDES_OTS_VENCIDAS=false
```

## Comportamiento

1. **Solo afecta a clientes**: Administradores, coordinadores, facturación y calidad NO son bloqueados
2. **Por centro de trabajo**: El bloqueo aplica solo a solicitudes del mismo centro donde hay OTs vencidas
3. **Vista de bloqueo**: Los usuarios bloqueados ven una página explicativa con:
   - Mensaje claro del motivo
   - Lista de OTs pendientes de autorizar
   - Enlaces directos a cada OT
   - Instrucciones para desbloquear
4. **Detección**: Se verifica tanto en la vista de crear solicitud como al enviar el formulario

## Archivos modificados

- `config/business.php` - Configuración central
- `app/Http/Controllers/SolicitudController.php` - Lógica de bloqueo
- `resources/js/Pages/Solicitudes/Bloqueada.vue` - Vista de bloqueo
- `BLOQUEO_SOLICITUDES.md` - Esta documentación

## Pruebas recomendadas

### Para probar el bloqueo (1 minuto):

1. Configura `ot_autorizacion_timeout_minutos` = 1
2. Como coordinador/admin, completa una OT (cambia estatus a "completada")
3. Espera 1 minuto y medio
4. Como cliente del mismo centro, intenta crear una solicitud
5. Deberías ver la página de bloqueo
6. Autoriza la OT pendiente
7. Intenta crear la solicitud nuevamente
8. Debería funcionar normalmente

### Para producción:

1. Cambia `ot_autorizacion_timeout_minutos` a 4320 (72 horas)
2. Ejecuta `php artisan config:clear`
3. Reinicia los workers de queue si los usas
4. Verifica en logs que la configuración se aplicó

## Notas importantes

⚠️ **Recuerda limpiar la caché** después de cualquier cambio en configuración:
```bash
php artisan config:clear
php artisan cache:clear
```

✅ **Para pasar a producción**: No olvides cambiar el valor de 1 a 4320 minutos antes de deploy.
