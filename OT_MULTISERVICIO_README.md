# OT con Múltiples Servicios - Documentación

## 📋 Resumen

Sistema que permite crear **Órdenes de Trabajo (OT)** con múltiples servicios en una misma orden. Cada servicio tiene su propia configuración de precio, cantidad, tipo de cobro, items y avances independientes.

## 🎯 Características Principales

- ✅ **Múltiples servicios por OT**: Agregar N servicios en un mismo formulario
- ✅ **Datos compartidos**: Centro de trabajo, descripción del producto, cliente, etc.
- ✅ **Cálculo automático**: Subtotales por servicio, IVA 16%, y total de la OT
- ✅ **Items automáticos**: Cada servicio genera automáticamente un item inicial
- ✅ **Vista detallada**: Cards independientes por servicio con sus items y avances
- ✅ **Transaccional**: Todo se guarda en una sola transacción DB

## 🗂️ Estructura de Base de Datos

### Tablas Principales

#### `ot_servicios`
Almacena los servicios asociados a una OT.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | PK |
| `ot_id` | bigint | FK a `ordenes_trabajo` |
| `servicio_id` | bigint | FK a `servicios_empresa` |
| `tipo_cobro` | string | pieza, pallet, hora, kg, etc. |
| `cantidad` | integer | Cantidad del servicio |
| `precio_unitario` | decimal(10,2) | Precio por unidad |
| `subtotal` | decimal(12,2) | cantidad × precio_unitario |

#### `ot_servicio_items`
Items asociados a cada servicio.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | PK |
| `ot_servicio_id` | bigint | FK a `ot_servicios` |
| `descripcion_item` | string | Descripción del item |
| `planeado` | integer | Cantidad planeada |
| `completado` | integer | Cantidad completada |

#### `ot_servicio_avances`
Avances de producción por servicio.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | PK |
| `ot_servicio_id` | bigint | FK a `ot_servicios` |
| `tarifa` | string | Normal, Extra, Fin de Semana, etc. |
| `precio_unitario_aplicado` | decimal(10,2) | Precio aplicado (nullable) |
| `cantidad_registrada` | integer | Cantidad del avance |
| `comentario` | text | Comentario opcional |
| `created_by` | bigint | FK a `users` |

## 🔗 Relaciones Eloquent

```php
// Orden (OT)
Orden::class
    ->hasMany(OTServicio::class, 'ot_id')

// OTServicio
OTServicio::class
    ->belongsTo(Orden::class, 'ot_id')
    ->belongsTo(ServicioEmpresa::class, 'servicio_id')
    ->hasMany(OTServicioItem::class, 'ot_servicio_id')
    ->hasMany(OTServicioAvance::class, 'ot_servicio_id')

// OTServicioItem
OTServicioItem::class
    ->belongsTo(OTServicio::class, 'ot_servicio_id')

// OTServicioAvance
OTServicioAvance::class
    ->belongsTo(OTServicio::class, 'ot_servicio_id')
    ->belongsTo(User::class, 'created_by')
```

## 🛠️ Backend (Laravel)

### Controlador: `OTMultiServicioController`

#### Método `create()`
Renderiza el formulario para crear una nueva OT con múltiples servicios.

**Ruta:** `GET /ot-multi-servicio/create`

**Permisos:** Usuario autenticado

**Retorna:**
- Centros de trabajo disponibles
- Servicios del catálogo
- Team leaders
- Clientes

#### Método `store(CreateOTRequest $request)`
Guarda la OT con todos sus servicios en una transacción.

**Ruta:** `POST /ot-multi-servicio`

**Payload:**
```json
{
  "header": {
    "centro_trabajo_id": 1,
    "centro_costos_id": 2,
    "marca_id": null,
    "area_id": 3,
    "descripcion_producto": "Producto XYZ",
    "cliente_id": 10,
    "team_leader_id": 5
  },
  "servicios": [
    {
      "servicio_id": 1,
      "tipo_cobro": "pieza",
      "cantidad": 100,
      "precio_unitario": 12.50
    },
    {
      "servicio_id": 2,
      "tipo_cobro": "pallet",
      "cantidad": 10,
      "precio_unitario": 200.00
    }
  ]
}
```

**Proceso:**
1. Crear Orden de Trabajo (OT)
2. Por cada servicio:
   - Crear registro en `ot_servicios`
   - Crear item inicial automático
3. Calcular totales:
   - `subtotal = sum(servicios.subtotal)`
   - `iva = subtotal * 0.16`
   - `total = subtotal + iva`
4. Actualizar OT con totales
5. Log de actividad
6. Notificar al Team Leader (si fue asignado)

#### Método `show(Orden $orden)`
Muestra el detalle de la OT con todos sus servicios.

**Ruta:** `GET /ot-multi-servicio/{orden}`

**Carga:**
- OT con servicios, items y avances
- Totales calculados por servicio
- Métricas: planeado, completado, faltante

## 🎨 Frontend (Vue 3 + Inertia)

### Vista `Create.vue`

**Ubicación:** `resources/js/Pages/OTMultiServicio/Create.vue`

**Características:**
- Formulario reactivo con Vue 3
- Repeater de servicios (agregar/eliminar)
- Cálculo automático en tiempo real:
  - Subtotal por servicio
  - Subtotal OT
  - IVA 16%
  - Total OT
- Validación por servicio con errores individuales
- Resumen lateral sticky

**Controles:**
- `+ Agregar Servicio`: Añade un nuevo servicio al array
- `🗑️ Eliminar`: Elimina un servicio (si hay más de 1)
- `Crear Orden de Trabajo`: Submit del formulario

### Vista `Show.vue`

**Ubicación:** `resources/js/Pages/OTMultiServicio/Show.vue`

**Estructura:**
1. **Header**: Info general de la OT, estatus, totales
2. **Cards de Servicios** (1 por cada servicio):
   - Nombre del servicio
   - Tipo de cobro, cantidad, precio
   - Métricas: planeado, completado, faltante, % progreso
   - Barra de progreso visual
   - Tabla de items
   - Lista de avances registrados
   - Botón "Registrar Avance" (para implementar)

## 📝 FormRequest: `CreateOTRequest`

Valida el payload antes de procesar.

**Reglas:**
```php
'header.centro_trabajo_id' => 'required|integer|exists:centros_trabajo,id'
'header.descripcion_producto' => 'required|string|max:500'
'servicios' => 'required|array|min:1'
'servicios.*.servicio_id' => 'required|integer|exists:servicios_empresa,id'
'servicios.*.tipo_cobro' => 'required|string|max:50'
'servicios.*.cantidad' => 'required|integer|min:1'
'servicios.*.precio_unitario' => 'required|numeric|min:0'
```

## 🚀 Uso

### 1. Crear Nueva OT con Múltiples Servicios

```
GET /ot-multi-servicio/create
```

1. Seleccionar centro de trabajo
2. Ingresar descripción del producto
3. Agregar servicios (mínimo 1):
   - Tipo de servicio
   - Tipo de cobro (pieza, pallet, etc.)
   - Cantidad
   - Precio unitario
4. Asignar Team Leader (opcional)
5. Click en "Crear Orden de Trabajo"

### 2. Ver Detalle de OT

```
GET /ot-multi-servicio/{orden_id}
```

Muestra todos los servicios como cards independientes con:
- Totales OT arriba
- Card por servicio con items y avances
- Métricas de completitud

## 🔄 Método Helper: `recalcTotals()`

Modelo `Orden`:

```php
public function recalcTotals(): void
{
    $subtotal = $this->otServicios()->sum('subtotal');
    $iva = $subtotal * 0.16;
    $total = $subtotal + $iva;

    $this->update([
        'subtotal' => $subtotal,
        'iva' => $iva,
        'total' => $total,
    ]);
}
```

Útil para:
- Actualizar totales después de editar un servicio
- Agregar/eliminar servicios dinámicamente (endpoints futuros)

## ⚙️ TODO / Próximas Funcionalidades

- [ ] Endpoint para editar servicio (actualizar cantidad/precio)
- [ ] Endpoint para agregar/eliminar servicios a OT existente
- [ ] Registrar avances por servicio desde el frontend
- [ ] Exportar OT multiservicio a PDF
- [ ] Validaciones adicionales de permisos por centro
- [ ] Integración con sistema de cotizaciones
- [ ] Historial de cambios por servicio

## 📦 Archivos Creados/Modificados

### Migraciones
- `2026_01_31_000001_create_ot_servicios_table.php`
- `2026_01_31_000002_create_ot_servicio_items_table.php`
- `2026_01_31_000003_create_ot_servicio_avances_table.php`

### Modelos
- `app/Models/OTServicio.php`
- `app/Models/OTServicioItem.php`
- `app/Models/OTServicioAvance.php`
- `app/Models/Orden.php` (modificado: agregada relación `otServicios()` y método `recalcTotals()`)

### Controladores
- `app/Http/Controllers/OTMultiServicioController.php`

### FormRequests
- `app/Http/Requests/CreateOTRequest.php`

### Vistas Vue
- `resources/js/Pages/OTMultiServicio/Create.vue`
- `resources/js/Pages/OTMultiServicio/Show.vue`

### Rutas
- `routes/web.php` (agregadas rutas en sección "OT CON MÚLTIPLES SERVICIOS")

## 🧪 Testing

Para probar el sistema:

1. **Ejecutar migraciones:**
   ```bash
   php artisan migrate
   ```

2. **Acceder al formulario:**
   ```
   /ot-multi-servicio/create
   ```

3. **Crear una OT de prueba:**
   - Seleccionar centro
   - Agregar descripción
   - Agregar 2-3 servicios
   - Verificar que los totales se calculen correctamente

4. **Ver el detalle:**
   ```
   /ot-multi-servicio/{id}
   ```

## 🔒 Seguridad y Permisos

- ✅ Autenticación requerida en todas las rutas
- ✅ Validación de datos con FormRequest
- ✅ Autorización por centro de trabajo
- ✅ Transacciones DB para consistencia
- ✅ Logs de actividad con Spatie Activity Log

## 💡 Notas Importantes

1. **Compatibilidad con sistema legacy:**
   - La OT mantiene el campo `id_servicio` con el primer servicio por compatibilidad
   - Los servicios antiguos siguen funcionando sin cambios

2. **Items automáticos:**
   - Cada servicio crea automáticamente 1 item con:
     - `descripcion_item` = descripción del producto (OT)
     - `planeado` = cantidad del servicio
     - `completado` = 0

3. **Totales:**
   - Se calculan automáticamente en el backend
   - IVA fijo 16%
   - Cachados en la tabla `ordenes_trabajo`

4. **Validación Frontend:**
   - Mínimo 1 servicio requerido
   - Cantidad ≥ 1
   - Precio ≥ 0

---

**Desarrollado por:** Senior Full-Stack Developer (Laravel 10/11 + Vue 3 + Inertia)  
**Fecha:** Enero 2026  
**Versión:** 1.0.0
