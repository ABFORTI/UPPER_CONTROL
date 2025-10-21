# Implementación: Descripción General de Productos

## 📋 Resumen
Se implementó un sistema para preservar el nombre del producto general (de la solicitud) mientras se permite especificar nombres específicos para los sub-ítems en la orden de trabajo.

## 🎯 Objetivo
Cuando se crea una OT, el usuario puede ver claramente:
- **Producto General**: El nombre del producto/servicio desde la solicitud (inmutable)
- **Sub-ítems**: Nombres específicos para cada ítem separado

### Ejemplo Práctico
```
Producto General: "Computadoras" (desde la solicitud)
  ├─ Sub-ítem 1: "Lenovo ThinkPad" - 6 unidades
  └─ Sub-ítem 2: "Asus VivoBook" - 4 unidades
```

## 🔧 Cambios Implementados

### 1. Base de Datos
**Migración**: `2025_10_15_071722_add_descripcion_general_to_ordenes_trabajo.php`

```php
Schema::table('ordenes_trabajo', function (Blueprint $table) {
    $table->string('descripcion_general')->nullable()->after('id');
});
```

**Estado**: ✅ Ejecutada exitosamente

### 2. Modelo Eloquent
**Archivo**: `app/Models/Orden.php`

Se agregó `descripcion_general` al array `$fillable`:

```php
protected $fillable = [
    'id_solicitud','id_centrotrabajo','id_servicio','id_area','team_leader_id',
    'descripcion_general', // ← NUEVO
    'estatus','calidad_resultado','total_planeado','total_real',
    'subtotal','iva','total'
];
```

### 3. Controlador
**Archivo**: `app/Http/Controllers/OrdenController.php`

#### Método `createFromSolicitud()`
Se agregó la descripción general a las props de Inertia:

```php
return Inertia::render('Ordenes/CreateFromSolicitud', [
    // ... otras props
    'descripcionGeneral'  => $solicitud->descripcion ?? '',
]);
```

#### Método `storeFromSolicitud()`
Se guarda la descripción general al crear la orden:

```php
$orden = Orden::create([
    'folio'            => $this->buildFolioOT($solicitud->id_centrotrabajo),
    'id_solicitud'     => $solicitud->id,
    'id_centrotrabajo' => $solicitud->id_centrotrabajo,
    'id_servicio'      => $solicitud->id_servicio,
    'id_area'          => $solicitud->id_area,
    'team_leader_id'   => $data['team_leader_id'] ?? null,
    'descripcion_general' => $solicitud->descripcion ?? '', // ← NUEVO
    'estatus'          => !empty($data['team_leader_id']) ? 'asignada' : 'generada',
    'total_planeado'   => $totalPlan,
    'total_real'       => 0,
    'calidad_resultado'=> 'pendiente',
]);
```

### 4. Frontend (Vue)
**Archivo**: `resources/js/Pages/Ordenes/CreateFromSolicitud.vue`

#### Props
```javascript
const props = defineProps({
  // ... otras props
  descripcionGeneral: { type: String, default: '' }, // ← NUEVO
})
```

#### Template
Se agregó una sección visual para mostrar la descripción general:

```vue
<!-- Descripción General del Producto/Servicio -->
<div v-if="descripcionGeneral" class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-2xl shadow-lg border-2 border-purple-200 p-6">
  <div class="flex items-start gap-4">
    <div class="bg-purple-100 p-3 rounded-xl flex-shrink-0">
      <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
      </svg>
    </div>
    <div class="flex-1">
      <h3 class="text-sm font-bold text-purple-900 uppercase tracking-wide mb-1">Producto/Servicio General</h3>
      <p class="text-xl font-bold text-purple-700">{{ descripcionGeneral }}</p>
      <p class="text-xs text-purple-600 mt-2" v-if="!usaTamanos">
        Los ítems a continuación son sub-categorías o variantes de este producto general
      </p>
    </div>
  </div>
</div>
```

## 🎨 Características de UX

### Tarjeta Visual
- **Diseño**: Gradiente morado-rosa con borde destacado
- **Icono**: Etiqueta SVG para representar "producto"
- **Título**: "Producto/Servicio General" en mayúsculas
- **Contenido**: Descripción en texto grande y bold
- **Contexto adicional**: Para servicios sin tamaños, explica que los ítems son sub-categorías

### Ubicación
La descripción general aparece:
1. **Después del header** (folio, servicio, centro)
2. **Antes del formulario** de asignación de team leader
3. **Prominentemente visible** para dar contexto a todos los ítems

## 📊 Comportamiento por Tipo de Servicio

### Servicios CON Tamaños
```
General: "Uniformes"
  ├─ Chico × 10
  ├─ Mediano × 15
  └─ Grande × 8
```
- Los ítems están bloqueados (cantidades de la solicitud)
- La descripción general da contexto del tipo de uniforme

### Servicios SIN Tamaños
```
General: "Computadoras"
  ├─ Lenovo ThinkPad × 6
  └─ Asus VivoBook × 4
```
- Los ítems son editables (con validación de suma)
- La descripción general indica la categoría principal
- Los sub-ítems tienen nombres específicos (marcas, modelos, etc.)

## ✅ Estado de Implementación

- [x] Migración de base de datos creada y ejecutada
- [x] Modelo actualizado (fillable)
- [x] Controlador actualizado (props y save)
- [x] Frontend actualizado (prop y UI)
- [x] Tarjeta visual implementada
- [x] Condicional para servicios con/sin tamaños

## 🔄 Flujo Completo

1. **Solicitud aprobada** → Tiene `descripcion` (ej: "Computadoras")
2. **Crear OT** → Controller pasa `descripcionGeneral` a Vue
3. **Frontend muestra** → Tarjeta destacada con el nombre general
4. **Usuario llena ítems** → Con nombres específicos (Lenovo, Asus, etc.)
5. **Guardar OT** → Se guarda `descripcion_general` en BD
6. **Resultado** → OT con contexto claro: producto general + detalles específicos

## 🎯 Beneficios

1. **Trazabilidad**: Siempre se sabe qué producto/servicio general se está trabajando
2. **Claridad**: Diferencia clara entre categoría general y variantes específicas
3. **Contexto**: Los team leaders entienden el panorama completo
4. **Validación**: La suma de ítems sigue validándose contra el total aprobado
5. **Inmutable**: El nombre general no cambia, proviene de la solicitud aprobada

## 📝 Notas Técnicas

- El campo `descripcion_general` es **nullable** para compatibilidad con OTs antiguas
- El campo se muestra solo si tiene valor (`v-if="descripcionGeneral"`)
- Para servicios sin tamaños, se explica que los ítems son sub-categorías
- El diseño es consistente con el sistema de colores existente (purple-pink)

## 🚀 Próximos Pasos Sugeridos

1. Actualizar vistas de detalle de OT para mostrar también la descripción general
2. Incluir descripción general en PDFs de OT
3. Agregar descripción general a reportes y exports
4. Considerar agregar descripción general a otros módulos relacionados
