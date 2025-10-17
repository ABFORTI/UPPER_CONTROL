# 🔐 Permisos de Facturación - Rol Cliente

## 📋 Resumen de Cambios

Se ha actualizado el sistema de permisos para que el rol **cliente** pueda **ver facturas y generar PDFs**, pero **NO ejecutar acciones** de cambio de estado.

---

## ✅ Lo Que El Cliente PUEDE Hacer

### 1. Ver Facturas
- ✅ Acceder a la vista de factura completa
- ✅ Ver todos los detalles de la factura
- ✅ Ver información del CFDI (si hay XML)
- ✅ Ver estado actual de la factura

**Ruta**: `GET /facturas/{factura}`

### 2. Generar/Descargar PDF
- ✅ Ver el PDF de la factura en el navegador
- ✅ Descargar el PDF
- ✅ El PDF se genera automáticamente si no existe

**Ruta**: `GET /facturas/{factura}/pdf`

---

## ❌ Lo Que El Cliente NO PUEDE Hacer

### 1. Crear Facturas
- ❌ No puede acceder a `/ordenes/{orden}/facturar`
- ❌ Solo facturación/admin pueden crear facturas

### 2. Cambiar Estados
- ❌ Marcar como facturado
- ❌ Registrar cobro
- ❌ Marcar como pagado
- ❌ Subir XML

**Estas acciones solo están disponibles para roles**: `facturacion` y `admin`

---

## 🎨 Interfaz Visual

### Para Facturación/Admin:
```
┌────────────────────────────────────┐
│  Factura #123                      │
│  [Ver PDF]                         │
├────────────────────────────────────┤
│  Acciones:                         │
│  [Marcar facturado]                │
│  [Registrar cobro]                 │
│  [Marcar pagado]                   │
└────────────────────────────────────┘
```

### Para Cliente:
```
┌────────────────────────────────────┐
│  Factura #123                      │
│  [Ver PDF]                         │
├────────────────────────────────────┤
│  ℹ️ Vista de solo lectura          │
│  Puedes ver la factura y descargar│
│  el PDF, pero no realizar cambios │
│  de estado.                        │
└────────────────────────────────────┘
```

---

## 📁 Archivos Modificados

### 1. `routes/web.php`

**Antes**:
```php
Route::get('/facturas/{factura}', [FacturaController::class,'show'])
    ->middleware('role:facturacion|admin')->name('facturas.show');
Route::get('/facturas/{factura}/pdf',[FacturaController::class,'pdf'])
    ->name('facturas.pdf');
```

**Después**:
```php
// Cliente puede ver factura y generar PDF
Route::get('/facturas/{factura}', [FacturaController::class,'show'])
    ->middleware('role:facturacion|admin|cliente')->name('facturas.show');
Route::get('/facturas/{factura}/pdf',[FacturaController::class,'pdf'])
    ->middleware('role:facturacion|admin|cliente')->name('facturas.pdf');

// Solo facturacion y admin pueden ejecutar estas acciones
Route::post('/facturas/{factura}/facturado', [FacturaController::class,'marcarFacturado'])
    ->middleware('role:facturacion|admin')->name('facturas.facturado');
// ... otros POSTs siguen igual
```

---

### 2. `resources/js/Pages/Facturas/Show.vue`

**Agregado**:
```javascript
import { usePage } from '@inertiajs/vue3'

const page = usePage()

// Verificar si el usuario puede operar (facturacion/admin)
const puedeOperar = computed(() => {
  const user = page.props.auth?.user
  if (!user) return false
  return user.roles?.some(r => r.name === 'facturacion' || r.name === 'admin') ?? false
})
```

**En el template**:
```vue
<!-- Acciones de estatus (solo para facturacion/admin) -->
<div v-if="puedeOperar" class="mt-2 flex flex-wrap gap-2">
  <!-- Botones de acción -->
</div>

<!-- Mensaje para clientes -->
<div v-else class="mt-4 bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
  <p class="text-sm text-blue-700">
    <strong>Vista de solo lectura.</strong> 
    Puedes ver la factura y descargar el PDF, pero no realizar cambios de estado.
  </p>
</div>
```

---

### 3. `app/Http/Controllers/FacturaController.php`

**Método `show()`**:
```php
public function show(\App\Models\Factura $factura) {
  $this->authorize('view', $factura);
  // Removido: $this->authFacturacion($factura->orden);
  $factura->load('orden.servicio','orden.centro','orden.solicitud.cliente');
  // ...
}
```

**Método `pdf()`**:
```php
public function pdf(\App\Models\Factura $factura)
{
    $this->authorize('view', $factura); // Antes: authorize('view', $factura->orden)
    // ...
}
```

**Métodos de acción** (ya estaban correctos):
```php
public function marcarFacturado(Request $req, Factura $factura) {
  $this->authorize('operar', $factura); // ✅ Solo facturacion/admin
  // ...
}
```

---

### 4. `app/Policies/FacturaPolicy.php`

**Ya existente** (sin cambios):
```php
public function view(User $u, Factura $f): bool {
    if ($u->hasRole('admin')) return true;
    if ($u->hasRole('facturacion')) {
        return (int)$u->centro_trabajo_id === (int)$f->orden->id_centrotrabajo;
    }
    // ✅ cliente puede ver su factura
    if ($u->hasRole('cliente')) return $f->orden->solicitud?->id_cliente === $u->id;
    return false;
}

public function operar(User $u, Factura $f): bool {
    // ❌ Solo facturacion/admin
    return $u->hasRole('admin') ||
           ($u->hasRole('facturacion') && (int)$u->centro_trabajo_id === (int)$f->orden->id_centrotrabajo);
}
```

---

## 🔒 Seguridad Implementada

### Nivel 1: Rutas (Middleware)
```php
->middleware('role:facturacion|admin|cliente')  // Ver
->middleware('role:facturacion|admin')          // Acciones
```

### Nivel 2: Controlador (Policies)
```php
$this->authorize('view', $factura)    // Cliente puede
$this->authorize('operar', $factura)  // Solo facturacion/admin
```

### Nivel 3: Vista (Frontend)
```javascript
const puedeOperar = computed(() => {
  return user.roles?.some(r => r.name === 'facturacion' || r.name === 'admin')
})
```

**Resultado**: ✅ Triple capa de seguridad

---

## 🧪 Cómo Probar

### Como Cliente:

1. **Iniciar sesión** con usuario rol `cliente`

2. **Ver una factura**:
   - Ve a una OT que hayas autorizado
   - Si ya está facturada, verás un enlace a la factura
   - Haz clic para ver la factura

3. **Verificar permisos**:
   - ✅ Deberías ver toda la información de la factura
   - ✅ Deberías ver el botón "Ver PDF"
   - ✅ Deberías ver el mensaje: "Vista de solo lectura"
   - ❌ NO deberías ver botones de "Marcar facturado", etc.

4. **Generar PDF**:
   - Haz clic en "Ver PDF"
   - ✅ Debería abrirse el PDF en una nueva pestaña

5. **Intentar acciones** (prueba de seguridad):
   - Intenta hacer POST a `/facturas/{id}/facturado`
   - ❌ Debería devolver error 403 (Forbidden)

### Como Facturación/Admin:

1. **Iniciar sesión** con usuario rol `facturacion` o `admin`

2. **Ver una factura**:
   - Ve a Facturación → Facturas
   - Selecciona cualquier factura

3. **Verificar permisos**:
   - ✅ Deberías ver toda la información
   - ✅ Deberías ver el botón "Ver PDF"
   - ✅ Deberías ver todos los botones de acción
   - ❌ NO deberías ver el mensaje de "solo lectura"

4. **Ejecutar acciones**:
   - ✅ Puedes cambiar estados
   - ✅ Puedes subir XML
   - ✅ Puedes marcar pagos

---

## 📊 Comparación de Permisos

| Acción | Cliente | Facturación | Admin |
|--------|---------|-------------|-------|
| Ver factura | ✅ (solo sus facturas) | ✅ (su centro) | ✅ (todas) |
| Generar PDF | ✅ | ✅ | ✅ |
| Crear factura | ❌ | ✅ | ✅ |
| Marcar facturado | ❌ | ✅ | ✅ |
| Registrar cobro | ❌ | ✅ | ✅ |
| Marcar pagado | ❌ | ✅ | ✅ |
| Subir XML | ❌ | ✅ | ✅ |
| Ver listado | ❌ | ✅ | ✅ |

---

## 💡 Notas Importantes

1. **El cliente solo ve SUS facturas**: La policy verifica que la factura pertenezca a una solicitud del cliente.

2. **PDF siempre disponible**: El cliente puede generar el PDF incluso si no existe, se creará automáticamente.

3. **Mensaje informativo**: El cliente ve claramente que está en modo "solo lectura".

4. **Seguridad en capas**: Aunque se oculten los botones, las rutas POST siguen protegidas con middleware y policies.

5. **Notificaciones**: El cliente sigue recibiendo notificaciones cuando su factura cambia de estado.

---

## 🚀 Beneficios

✅ **Transparencia**: El cliente puede ver el estado de sus facturas en tiempo real

✅ **Self-service**: El cliente puede descargar sus propios PDFs sin pedir ayuda

✅ **Seguridad**: Triple capa de protección evita modificaciones no autorizadas

✅ **UX mejorada**: Mensaje claro indica las limitaciones del rol

✅ **Auditoría**: Se mantiene registro de quién accede a qué facturas

---

**Última actualización**: 15/10/2025
**Autor**: Sistema Upper Control
**Versión**: 2.1 - Permisos Cliente en Facturación
