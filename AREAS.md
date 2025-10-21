# Sistema de Gestión de Áreas

## 📋 Descripción

El sistema de áreas permite que cada centro de trabajo defina y gestione sus propias áreas operativas (Almacén, Producción, Calidad, etc.). Las áreas se pueden asociar a solicitudes y órdenes de trabajo para mejor organización y seguimiento.

## 🎯 Funcionalidades

### Para Coordinadores y Administradores
- ✅ Crear nuevas áreas para su centro de trabajo
- ✅ Editar áreas existentes (nombre, descripción, estado)
- ✅ Activar/Desactivar áreas
- ✅ Eliminar áreas que no estén en uso

### Para Clientes
- ✅ Seleccionar un área al crear una solicitud (opcional)
- ✅ Ver el área asociada en solicitudes y órdenes de trabajo

## 🗂️ Estructura de Datos

### Tabla: `areas`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | BIGINT | ID único del área |
| `id_centrotrabajo` | BIGINT | Centro de trabajo al que pertenece |
| `nombre` | VARCHAR | Nombre del área (ej: "Almacén") |
| `descripcion` | TEXT | Descripción opcional del área |
| `activo` | BOOLEAN | Si el área está activa o no |
| `created_at` | TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | Fecha de última actualización |

### Relaciones
- **Solicitud** → `id_area` (foreign key a `areas.id`)
- **Orden** → `id_area` (foreign key a `areas.id`)
- **CentroTrabajo** → `hasMany(Area)` - Un centro tiene muchas áreas

## 🚀 Uso del Sistema

### 1. Acceder a la Gestión de Áreas

**Ruta:** `/areas`

**Permisos:** Solo coordinadores y administradores

- Los **coordinadores** ven y gestionan solo las áreas de su centro
- Los **administradores** ven y gestionan áreas de todos los centros

### 2. Crear una Nueva Área

1. Hacer clic en el botón **"+ Nueva Área"**
2. Completar el formulario:
   - **Centro de Trabajo**: (solo si eres admin y hay múltiples centros)
   - **Nombre**: Nombre descriptivo del área
   - **Descripción**: Detalles adicionales (opcional)
   - **Activa**: Marcar si el área estará disponible para usar
3. Hacer clic en **"Guardar"**

### 3. Editar un Área

1. En la lista de áreas, hacer clic en **"Editar"**
2. Modificar los datos necesarios
3. Hacer clic en **"Guardar"**

### 4. Eliminar un Área

1. En la lista de áreas, hacer clic en **"Eliminar"**
2. Confirmar la eliminación

⚠️ **Nota:** Si una área está asociada a solicitudes u órdenes, se mantendrán esas referencias pero el área ya no estará disponible para nuevas solicitudes.

### 5. Usar Áreas en Solicitudes

Al crear una nueva solicitud:

1. Seleccionar el servicio y otros datos
2. En el campo **"Área"**, elegir una de las áreas activas del centro
3. El área seleccionada se copiará automáticamente a la orden de trabajo cuando se genere

## 📊 Áreas Predefinidas (Ejemplo)

Al ejecutar el seeder (`AreaSeeder`), se crean las siguientes áreas para cada centro:

- **Almacén** - Área de almacenamiento y bodega
- **Producción** - Área de manufactura y producción
- **Calidad** - Control y aseguramiento de calidad
- **Mantenimiento** - Mantenimiento de equipos e instalaciones
- **Oficinas** - Área administrativa
- **Empaque** - Área de empaque y embalaje
- **Recepción** - Recepción de materiales
- **Embarques** - Embarques y logística

### Ejecutar el Seeder

```bash
php artisan db:seed --class=AreaSeeder
```

## 🔧 Implementación Técnica

### Archivos Principales

#### Backend
- `app/Models/Area.php` - Modelo Eloquent
- `app/Http/Controllers/AreaController.php` - Controlador CRUD
- `database/migrations/2025_10_11_042451_create_areas_table.php` - Migración de tabla
- `database/migrations/2025_10_11_042514_update_area_to_foreign_key_in_solicitudes_and_ordenes.php` - Actualización de referencias

#### Frontend
- `resources/js/Pages/Areas/Index.vue` - Vista principal de gestión
- `resources/js/Pages/Solicitudes/Create.vue` - Select de áreas al crear solicitud
- `resources/js/Pages/Solicitudes/Show.vue` - Mostrar área en solicitud
- `resources/js/Pages/Ordenes/Show.vue` - Mostrar área en orden

### Rutas

```php
// routes/web.php
Route::middleware(['auth','role:admin|coordinador'])->group(function () {
    Route::get('/areas', [AreaController::class,'index'])->name('areas.index');
    Route::post('/areas', [AreaController::class,'store'])->name('areas.store');
    Route::put('/areas/{area}', [AreaController::class,'update'])->name('areas.update');
    Route::delete('/areas/{area}', [AreaController::class,'destroy'])->name('areas.destroy');
});
```

### Validación de Permisos

El sistema valida automáticamente que:
- Los coordinadores solo pueden gestionar áreas de su centro de trabajo
- Los administradores pueden gestionar áreas de cualquier centro
- Las áreas inactivas no aparecen en los selects de solicitudes

## 🎨 Interfaz de Usuario

### Pantalla Principal (/areas)

- **Vista agrupada por centro**: Las áreas se muestran organizadas por centro de trabajo
- **Tabla con información**: Nombre, Descripción, Estado (Activa/Inactiva)
- **Acciones rápidas**: Editar y Eliminar en cada fila
- **Modales**: Creación y edición mediante ventanas modales

### Selector en Solicitudes

- **Dropdown dinámico**: Se actualiza según el centro de trabajo seleccionado
- **Solo áreas activas**: No se muestran áreas desactivadas
- **Opcional**: No es obligatorio seleccionar un área

## 📝 Notas Importantes

1. **Áreas por Centro**: Cada centro tiene su propio conjunto de áreas independientes
2. **Referencias Conservadas**: Si eliminas un área, las solicitudes/órdenes antiguas mantienen la referencia
3. **Desactivación vs Eliminación**: Se recomienda desactivar áreas en lugar de eliminarlas para mantener el historial
4. **Migración Automática**: Al actualizar, las columnas `area` tipo VARCHAR se convierten a `id_area` tipo foreign key

## 🔄 Flujo de Datos

```
Cliente crea Solicitud 
  → Selecciona Área (opcional)
    → Solicitud.id_area = Area.id

Coordinador genera OT desde Solicitud
  → Orden.id_area = Solicitud.id_area (se copia automáticamente)

Visualización
  → Solicitud/Orden muestra Area.nombre
```

## ⚙️ Configuración Adicional

### Cambiar Áreas Predefinidas

Editar `database/seeders/AreaSeeder.php` y modificar el array `$areasComunes` con tus propias áreas.

### Hacer Campo Obligatorio

Si quieres que el área sea obligatoria en solicitudes:

1. En `resources/js/Pages/Solicitudes/Create.vue`, cambiar:
```vue
<option :value="null">— Selecciona área (opcional) —</option>
```
a:
```vue
<option :value="null">— Selecciona área —</option>
```

2. En `app/Http/Controllers/SolicitudController.php`, agregar validación:
```php
$req->validate([
    'id_area' => ['required', 'exists:areas,id'],
    // ... otras validaciones
]);
```

## 📞 Soporte

Para problemas o preguntas sobre el sistema de áreas, contactar al equipo de desarrollo.

---

**Última actualización:** 11 de octubre de 2025
