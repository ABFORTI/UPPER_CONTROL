# Solución: Duplicación de Avances en Órdenes Multi-Servicio

## 📋 Resumen del Problema

Los avances en órdenes con múltiples servicios se duplicaban por tres razones:

1. **Código duplicado en backend**: Dos bloques de código insertando el mismo avance
2. **Falta de recarga en frontend**: No se actualizaba la vista después de guardar
3. **Sin idempotencia**: El método `create()` no prevenía duplicados

## ✅ Soluciones Implementadas

### 1. Eliminación de Código Duplicado (Backend)

**Archivo**: `app/Http/Controllers/OrdenController.php`

**Problema**: Había DOS lugares donde se registraba el avance multi-servicio:
- Líneas 675-776: Con `firstOrCreate()` (correcto)
- Líneas 977-1000: Con `create()` (DUPLICABA)

**Solución**: Eliminé el segundo bloque (líneas 977-1000)

```php
// ANTES: Código duplicado que insertaba dos veces
if ($esMultiServicio && !empty($data['id_servicio'])) {
    \App\Models\OTServicioAvance::create([...]);  // ← DUPLICABA
}

// DESPUÉS: Solo existe el bloque con firstOrCreate()
$avanceCreado = \App\Models\OTServicioAvance::firstOrCreate(
    ['ot_servicio_id' => $otServicio->id, 'request_id' => $requestId],
    [/* atributos */]
);
```

### 2. Recarga de Datos en Frontend

**Archivo**: `resources/js/Pages/Ordenes/Show.vue`

**Problema**: Después de guardar el avance, NO se recargaban los datos del servidor.

**Solución**: Agregué `router.reload()` en el callback `onSuccess`

```javascript
// ANTES
onSuccess: () => {
  console.log('✅ Avance guardado exitosamente')
  avancesMultiServicio.value[servicioId].items.forEach(i => i.cantidad = '')
  avancesMultiServicio.value[servicioId].comentario = ''
}

// DESPUÉS
onSuccess: () => {
  console.log('✅ Avance guardado exitosamente')
  avancesMultiServicio.value[servicioId].items.forEach(i => i.cantidad = '')
  avancesMultiServicio.value[servicioId].comentario = ''
  
  // CRÍTICO: Recargar datos desde servidor para evitar desincronización
  router.reload({ only: ['orden', 'cotizacion', 'unidades'], preserveScroll: true })
}
```

### 3. Idempotencia con `firstOrCreate()` (Ya implementada previamente)

**Archivo**: `app/Http/Controllers/OrdenController.php` (líneas 695-720)

**Mecanismo**:
- Frontend genera `request_id` único: `{servicioId}-{timestamp}-{random}`
- Backend usa `firstOrCreate()` con constraint único en `(ot_servicio_id, request_id)`
- Si el request_id ya existe → devuelve el existente (no duplica)
- Si es nuevo → crea el registro

```php
$requestId = $req->input('_request_id');
$avanceCreado = \App\Models\OTServicioAvance::firstOrCreate(
    ['ot_servicio_id' => $otServicio->id, 'request_id' => $requestId],
    [
        'tarifa' => $tipoTarifa,
        'precio_unitario_aplicado' => $precioAplicado,
        'cantidad_registrada' => $totalCantidadRegistrada,
        'comentario' => $comentarioFinal,
        'created_by' => Auth::id(),
    ]
);

$wasRecentlyCreated = $avanceCreado->wasRecentlyCreated;
Log::info('✅ Avance procesado', [
    'was_recently_created' => $wasRecentlyCreated,  // true=nuevo, false=duplicado
]);
```

### 4. Migración de Base de Datos

**Archivo**: `database/migrations/2026_02_04_125819_add_request_id_to_ot_servicio_avances_table.php`

**Cambios**:
- Añadida columna `request_id` (string, 100, nullable)
- Constraint único en `(ot_servicio_id, request_id)` → previene duplicados a nivel BD

```php
public function up(): void
{
    Schema::table('ot_servicio_avances', function (Blueprint $table) {
        $table->string('request_id', 100)->nullable()->after('created_by');
        $table->unique(['ot_servicio_id', 'request_id'], 'uk_servicio_request');
    });
}
```

## 🧪 Cómo Probar

1. **Ir a una orden con múltiples servicios**
2. **Registrar un avance**:
   - Seleccionar cantidad (ej: 2 unidades)
   - Tipo de tarifa: NORMAL
   - Click en "Guardar Avance"
3. **Verificar logs**: `storage/logs/laravel.log`
   ```
   [2026-02-04] local.INFO: 🔵 INICIO registrarAvance {"invocation_id":"invoke_..."}
   [2026-02-04] local.INFO: 🔥 JUSTO ANTES de firstOrCreate() {"request_id":"51-1770231119224-..."}
   [2026-02-04] local.INFO: ✅ Avance procesado {"was_recently_created":true}
   ```
4. **Verificar tabla "Segmentos de Producción"**:
   - Debe aparecer **SOLO 1 fila** con la cantidad registrada
   - Total debe coincidir con el subtotal del servicio
5. **Verificar subtotal del servicio**:
   - Debe ser: `cantidad * precio_unitario`
   - No debe duplicarse

### Caso de Prueba: Duplicado Detectado

Si intenta enviar el mismo `request_id` dos veces:

```
[2026-02-04] local.INFO: ℹ️ Request duplicado detectado por request_id - devolviendo existente
{"was_recently_created":false}
```

## 📊 Cálculo de Subtotales (Comportamiento Correcto)

### Para Servicios Multi-Servicio

```php
// Backend (líneas 733-751)
$todosAvances = \App\Models\OTServicioAvance::where('ot_servicio_id', $otServicio->id)->get();
$subtotalTotal = 0;

foreach ($todosAvances as $av) {
    $cantidad = (int)$av->cantidad_registrada;
    $precio = (float)$av->precio_unitario_aplicado;
    $subtotalTotal += round($precio * $cantidad, 2);
}

$otServicio->subtotal = $subtotalTotal;  // ← Usa suma de segmentos, NO precio base
$otServicio->save();
```

### Total de la Orden

```php
// Backend (líneas 763-770)
$subtotalOT = \App\Models\OTServicio::where('ot_id', $orden->id)->sum('subtotal');
$ivaOT = round($subtotalOT * 0.16, 2);
$totalOT = $subtotalOT + $ivaOT;

$orden->subtotal = $subtotalOT;
$orden->total = $totalOT;
```

## 🎯 Resultado Final

- ✅ Cada avance se registra **UNA SOLA VEZ**
- ✅ Subtotal se calcula desde **segmentos guardados**, no precio base
- ✅ Total de la orden = suma de subtotales de servicios
- ✅ UI muestra datos actualizados después de guardar
- ✅ No hay duplicados visuales ni en BD
- ✅ Idempotencia garantizada a nivel BD con constraint único

## 🔍 Archivos Modificados

1. `app/Http/Controllers/OrdenController.php`:
   - Eliminado bloque duplicado (líneas 977-1000)
   - Mantenido `firstOrCreate()` con idempotencia (líneas 675-776)

2. `resources/js/Pages/Ordenes/Show.vue`:
   - Agregado `router.reload()` en `onSuccess` (línea ~446)

3. `database/migrations/2026_02_04_125819_add_request_id_to_ot_servicio_avances_table.php`:
   - Migración aplicada exitosamente ✅

4. `app/Models/OTServicioAvance.php`:
   - Agregado `'request_id'` a `$fillable`

## ⚠️ Notas Importantes

- El frontend **ya estaba** generando `request_id` correctamente
- El frontend **ya tenía** el flag `processing` para prevenir doble-click
- El problema principal era el **código duplicado en backend**
- La idempotencia es una **capa adicional de protección**

## 📝 Logs de Diagnóstico

Los logs ahora incluyen:
- `invocation_id`: ID único por ejecución del método
- `request_id`: ID único por request del frontend
- `was_recently_created`: `true` si es nuevo, `false` si era duplicado

Esto permite diagnosticar fácilmente si hay problemas en el futuro.
