# Documentación: PDF de Orden de Trabajo - Actualizado

## Descripción General

El PDF de Orden de Trabajo ha sido actualizado para soportar:
- **Órdenes tradicionales** (servicio único)
- **Órdenes multi-servicio** (múltiples servicios)
- **Servicios adicionales** agregados dinámicamente
- **Avances por tarifa** (NORMAL, EXTRA, FIN_DE_SEMANA)
- **Faltantes registrados** vs. **Pendiente por hacer**

---

## Archivos Actualizados

### 1. Vista Blade
**Archivo:** `resources/views/pdf/orden.blade.php`

**Características:**
- Diseño limpio tipo documento corporativo
- Compatible con DomPDF
- CSS inline con estilos responsive para PDF
- Soporte para paginación en A4/Letter
- Formateo de moneda (MXN) y fechas (dd/mm/yyyy)

### 2. Controlador
**Archivo:** `app/Http/Controllers/OrdenController.php`
**Método:** `pdf(Orden $orden)`

**Eager Loading implementado:**
```php
$orden->load([
    'servicio',
    'centro',
    'teamLeader',
    'area',
    'items',
    'solicitud.cliente',
    'solicitud.marca',
    'aprobaciones.user',
    'otServicios' => function($query) {
        $query->with([
            'servicio',
            'addedBy',
            'items',
            'avances' => function($q) {
                $q->with('createdBy')
                  ->orderBy('created_at', 'asc');
            }
        ])->orderBy('created_at', 'asc');
    }
]);
```

### 3. Job de Generación
**Archivo:** `app/Jobs/GenerateOrdenPdf.php`

Actualizado con el mismo eager loading para generación asíncrona de PDFs en caché.

---

## Estructura del PDF

### 1. Encabezado
```
┌─────────────────────────────────────────────┐
│ [Logo]              ORDEN DE TRABAJO        │
│                     [BADGE: MULTI-SERVICIO] │
│                                             │
│ OT: OT-123    ID: SOL-456   Fecha: ...     │
└─────────────────────────────────────────────┘
```

**Elementos:**
- Logo de la empresa (si existe en `public/img/logo.png`)
- Título "ORDEN DE TRABAJO"
- Badge "MULTI-SERVICIO" (solo si aplica)
- Folio de OT, ID de solicitud y fecha

### 2. Datos Generales

Tabla con información clave:
- Cliente
- Marca
- Centro Operativo
- Código del servicio
- Fecha de creación
- Team Leader asignado

### 3. Tabla de Ítems

**Si hay ítems:**
```
┌──────────┬──────────────────┬──────────┬──────────────┐
│  Clave   │   Descripción    │ Cantidad │ Observaciones│
├──────────┼──────────────────┼──────────┼──────────────┤
│  M       │ Producto X       │  1,000   │ Marca A      │
└──────────┴──────────────────┴──────────┴──────────────┘
```

**Si no hay ítems:**
```
┌──────────────────────────────────────────────┐
│       Sin ítems registrados                  │
└──────────────────────────────────────────────┘
```

### 4. Servicios de la OT

Para cada servicio registrado (origen: SOLICITADO):

```
╔════════════════════════════════════════════════╗
║ ETIQUETADO (KPI-01)                            ║
║ Cantidad: 1,000 | P.U.: $5.00 | Subtotal: $5,000 ║
╠════════════════════════════════════════════════╣
║                                                ║
║  [Mini-resumen]                                ║
║  ┌──────────┬───────────┬─────────┬──────────┐║
║  │ Planeado │ Completado│ Faltantes│ Pendiente│║
║  │  1,000   │    800    │    50   │   150    │║
║  └──────────┴───────────┴─────────┴──────────┘║
║                                                ║
║  Segmentos / Avances Registrados:              ║
║  ┌────────┬──────┬──────┬─────────┬─────────┐ ║
║  │ Tarifa │ Cant │ P.U. │ Subtotal│  Usuario │ ║
║  ├────────┼──────┼──────┼─────────┼─────────┤ ║
║  │ NORMAL │ 600  │$5.00 │$3,000.00│ Juan P. │ ║
║  │ EXTRA  │ 150  │$7.50 │$1,125.00│ María G.│ ║
║  │ FIN_DE_SEMANA│ 50 │$10.00│$500.00│ Pedro L.│ ║
║  └────────┴──────┴──────┴─────────┴─────────┘ ║
╚════════════════════════════════════════════════╝
```

**Cálculos:**
- **Completado:** Suma de `cantidad_registrada` de todos los avances
- **Faltantes Registrados:** Suma de `faltante` de todos los items del servicio
- **Pendiente:** `Planeado - (Completado + Faltantes Registrados)`

### 5. Servicios Adicionales

Si se agregaron servicios con origen "ADICIONAL":

```
┌──────────────┬────────────┬──────────────┬─────────────┐
│   Servicio   │Fecha/Hora  │ Agregado por │Justificación│
├──────────────┼────────────┼──────────────┼─────────────┤
│ Almacenaje   │01/02 10:30 │ Supervisor A │ Cliente     │
│              │            │              │ solicitó    │
└──────────────┴────────────┴──────────────┴─────────────┘
```

Si no hay:
```
No se agregaron servicios adicionales a esta orden
```

### 6. Totales Generales

```
                    ┌────────────────────────┐
                    │ Subtotal:  $5,000.00   │
                    │ IVA (16%):   $800.00   │
                    │ ───────────────────    │
                    │ Total:     $5,800.00   │
                    └────────────────────────┘
```

**Nota:** Para multi-servicio, muestra:
> *Totales calculados con base en los avances registrados por tipo de tarifa*

### 7. Control del Proceso

```
┌─────────────┬──────────┬─────────────┬───────────┐
│Fecha Inicio │10/01/2026│Fecha Término│15/01/2026 │
│Hora Inicio  │08:00 am  │Hora Término │06:00 pm   │
└─────────────┴──────────┴─────────────┴───────────┘
```

### 8. Firmas

```
 _____________    _____________    _____________
Nombre/Firma      Solicitante       Autoriza
Juan Pérez        Cliente S.A.      María López
10/01/2026        15/01/2026        15/01/2026
```

---

## Estructura de Datos Esperada

### Modelo Orden

```php
// Relaciones requeridas para el PDF
$orden = [
    'id' => 1,
    'folio' => 'OT-001',
    'created_at' => Carbon,
    'fecha_completada' => '2026-01-15 18:00:00',
    
    // Relaciones
    'servicio' => ServicioEmpresa,
    'centro' => CentroTrabajo,
    'area' => Area,
    'teamLeader' => User,
    
    'solicitud' => [
        'folio' => 'SOL-001',
        'cliente' => Cliente,
        'marca' => Marca
    ],
    
    'items' => [ // OrdenItem[]
        [
            'tamano' => 'M',
            'sku' => 'SKU-001',
            'descripcion' => 'Producto X',
            'cantidad_planeada' => 1000,
            'marca' => 'Marca A'
        ]
    ],
    
    'otServicios' => [ // OTServicio[]
        [
            'id' => 1,
            'servicio_id' => 5,
            'cantidad' => 1000,
            'precio_unitario' => 5.00,
            'subtotal' => 5000.00,
            'origen' => 'SOLICITADO', // o 'ADICIONAL'
            'nota' => 'Justificación si es adicional',
            'created_at' => Carbon,
            
            'servicio' => [
                'nombre' => 'Etiquetado',
                'codigo' => 'KPI-01'
            ],
            
            'addedBy' => User, // Si origen = ADICIONAL
            
            'items' => [ // OTServicioItem[]
                [
                    'planeado' => 500,
                    'completado' => 450,
                    'faltante' => 30
                ]
            ],
            
            'avances' => [ // OTServicioAvance[]
                [
                    'tarifa' => 'NORMAL', // o 'EXTRA', 'FIN_DE_SEMANA'
                    'cantidad_registrada' => 600,
                    'precio_unitario_aplicado' => 5.00,
                    'created_at' => Carbon,
                    'createdBy' => User
                ]
            ]
        ]
    ],
    
    'aprobaciones' => [
        [
            'estatus' => 'aprobado',
            'user' => User
        ]
    ]
];
```

### Tipos de Tarifa

```php
// En OTServicioAvance
const TARIFAS = [
    'NORMAL',         // Verde  #059669
    'EXTRA',          // Ámbar  #f59e0b
    'FIN_DE_SEMANA'   // Morado #8b5cf6
];
```

### Origen de Servicio

```php
// En OTServicio
const ORIGEN = [
    'SOLICITADO',  // Servicio original de la solicitud
    'ADICIONAL'    // Servicio agregado posteriormente
];
```

---

## Cálculos Importantes

### 1. Detectar Multi-Servicio
```php
$esMultiServicio = $orden->otServicios->count() > 1;
```

### 2. Calcular Totales del Servicio
```php
// Método en modelo OTServicio
public function calcularTotales(): array
{
    $items = $this->items;
    
    $planeado = $items->sum('planeado');
    $completado = $items->sum('completado');
    $faltantesRegistrados = $items->sum('faltante');
    
    $pendiente = max(0, $planeado - ($completado + $faltantesRegistrados));
    
    return [
        'planeado' => $planeado,
        'completado' => $completado,
        'faltantes_registrados' => $faltantesRegistrados,
        'pendiente' => $pendiente,
        'total' => $planeado
    ];
}
```

### 3. Totales Generales
```php
// Para multi-servicio
$subtotal = $orden->otServicios->sum('subtotal');

// Para tradicional
$subtotal = $orden->items->sum('subtotal');

$iva = $subtotal * 0.16;
$total = $subtotal + $iva;
```

---

## Formateo de Datos

### Moneda
```php
// Formato: $1,234.56 MXN
number_format($valor, 2) . ' MXN'
```

### Fechas
```php
// Fecha corta: 15/01/2026
$fecha->format('d/m/Y')

// Fecha con hora: 15/01/2026 06:00 pm
$fecha->format('d/m/Y h:i a')

// Fecha larga: viernes, 15 de enero, 2026
$fecha->locale('es')->isoFormat('dddd, D [de] MMMM, YYYY')
```

### Números
```php
// Cantidad: 1,000
number_format($cantidad)
```

---

## Compatibilidad con DomPDF

### ✅ Soportado
- `display: table`, `table-cell`, `table-row`
- `border`, `padding`, `margin`
- Colores hex y nombres
- `text-align`, `font-weight`, `font-size`
- Imágenes embebidas (base64 o rutas locales)

### ❌ No Soportado
- `flexbox` (reemplazado con tablas)
- `grid`
- `@media queries` (limitado)
- Fuentes web externas (usar DejaVu Sans incluida)

### Alternativas Implementadas
```css
/* En lugar de flexbox */
.row { display: table; width: 100%; }
.cell { display: table-cell; }

/* En lugar de justify-content: space-between */
.cell-left { width: 50%; text-align: left; }
.cell-right { width: 50%; text-align: right; }
```

---

## Prevenir N+1 Queries

El eager loading está optimizado para cargar todo en una sola consulta:

```php
// ✅ BUENO: Una consulta
$orden->load([
    'otServicios.avances.createdBy',
    'otServicios.items'
]);

// ❌ MALO: N+1 queries
foreach($orden->otServicios as $servicio) {
    foreach($servicio->avances as $avance) {
        $avance->createdBy->name; // Query por cada avance
    }
}
```

---

## Testing y Verificación

### Casos de Prueba

1. **OT Tradicional (sin otServicios)**
   - Debe mostrar items y totales desde `orden_items`
   - No debe mostrar badge "MULTI-SERVICIO"
   - No debe mostrar sección de servicios

2. **OT Multi-Servicio (2+ otServicios)**
   - Badge "MULTI-SERVICIO" visible
   - Bloque por cada servicio con avances
   - Totales calculados desde otServicios

3. **Con Servicios Adicionales**
   - Tabla de servicios adicionales poblada
   - Mostrar usuario y justificación

4. **Sin Avances Registrados**
   - Mensaje: "No se han registrado avances para este servicio"

5. **Diferentes Tarifas**
   - NORMAL (verde), EXTRA (ámbar), FIN_DE_SEMANA (morado)

### Comandos de Prueba

```bash
# Regenerar PDF de una orden específica
php artisan tinker
>>> $orden = \App\Models\Orden::find(23);
>>> \App\Jobs\GenerateOrdenPdf::dispatchSync($orden->id);

# Ver PDF en navegador
# Navegar a: /ordenes/{id}/pdf
```

---

## Troubleshooting

### Problema: Logo no aparece
**Solución:** Verificar que `public/img/logo.png` existe y es accesible.

### Problema: Datos faltantes
**Solución:** Verificar eager loading en el controlador.

### Problema: Formato roto en PDF
**Solución:** Verificar que no se usen propiedades CSS no soportadas por DomPDF.

### Problema: Tablas cortadas
**Solución:** Agregar `page-break-inside: avoid;` en elementos que no deben dividirse.

---

## Futuras Mejoras

- [ ] Agregar códigos QR para trazabilidad
- [ ] Exportar en múltiples formatos (Excel, CSV)
- [ ] Gráficos de progreso por servicio
- [ ] Firmas digitales
- [ ] Integración con sistema de notificaciones
- [ ] Versionado de PDFs

---

**Última actualización:** 8 de febrero de 2026  
**Autor:** Sistema Upper Control  
**Versión:** 2.0
