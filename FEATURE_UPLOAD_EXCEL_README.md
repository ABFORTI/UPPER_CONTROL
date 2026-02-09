# Funcionalidad: Carga de Excel para Precargar OT

## Resumen

Se implementó la funcionalidad para subir archivos Excel (.xlsx) en el formulario de creación/edición de OT y precargar automáticamente los campos del formulario.

## Archivos Creados

### Backend

1. **`database/migrations/2026_02_08_000001_add_archivo_excel_fields_to_ordenes_trabajo.php`**
   - Agrega campos a la tabla `ordenes_trabajo` para almacenar metadata del archivo Excel
   - Campos: path, nombre original, mime, size, subido por, fecha

2. **`app/Services/ExcelOtParser.php`**
   - Servicio para parsear archivos Excel
   - Lee el archivo y extrae datos buscando etiquetas conocidas
   - Flexible: soporta formato vertical y horizontal
   - Case-insensitive y sin acentos

3. **`app/Services/OtPrefillMapper.php`**
   - Servicio para mapear textos del Excel a IDs de la base de datos
   - Busca en tablas: centros_trabajo, centros_costos, marcas, servicios_empresa, areas
   - Genera warnings para datos no encontrados
   - Extrae códigos de textos (ej: "KPI 01" → "KPI01")

4. **`app/Http/Controllers/OrdenExcelController.php`**
   - Controlador dedicado para upload/download de archivos
   - `upload()`: Sube archivo, parsea, mapea y devuelve JSON con prefill
   - `download()`: Descarga archivo previamente subido
   - Validaciones: tipo, tamaño (max 10MB), autorización

### Frontend

5. **`resources/js/Components/UploadOtExcel.vue`**
   - Componente Vue3 + Composition API
   - Input de archivo con validación client-side
   - Indicador de loading
   - Muestra archivo existente con botón de descarga
   - Emisión de evento `prefill-loaded` con datos
   - Manejo de errores y warnings

### Documentación

6. **`INTEGRACION_UPLOAD_EXCEL.md`**
   - Guía completa de integración
   - Ejemplos de código para usar el componente
   - Formato esperado del Excel
   - Notas de seguridad y UX

### Tests

7. **`tests/Feature/OrdenExcelUploadTest.php`**
   - Tests de integración para upload/download
   - Validaciones: autenticación, tipo de archivo, tamaño
   - Reemplazo de archivos
   - 8 test cases

8. **`tests/Unit/ExcelOtParserTest.php`**
   - Tests unitarios para parser y mapper
   - Formato vertical/horizontal
   - Case-insensitive
   - Extracción de códigos
   - 7 test cases

## Archivos Modificados

1. **`app/Models/Orden.php`**
   - Agregados campos fillable para archivo Excel
   - Agregada relación `archivoSubidoPor()`

2. **`routes/web.php`**
   - Agregado import de `OrdenExcelController`
   - Agregadas rutas:
     - `POST /ordenes/{orden}/archivo` → upload
     - `GET /ordenes/{orden}/archivo` → download

## Rutas API

```php
// Subir archivo Excel y obtener prefill
POST /ordenes/{orden}/archivo
Content-Type: multipart/form-data
Body: excel=<archivo.xlsx>

Response:
{
  "success": true,
  "archivo": {
    "nombre": "datos_ot.xlsx",
    "url_descarga": "/ordenes/123/archivo",
    "fecha_subida": "08/02/2026 14:30",
    "subido_por": "Juan Pérez"
  },
  "prefill": {
    "centro_trabajo_id": 1,
    "centro_costos_id": 3,
    "marca_id": null,
    "tipo_servicio_id": 8,
    "descripcion_producto": "Computadoras",
    "area_id": 5
  },
  "warnings": [
    "No se encontró Marca en catálogo para: 'MARCA_INVALIDA'"
  ]
}

// Descargar archivo
GET /ordenes/{orden}/archivo
Response: Binary file download
```

## Uso del Componente

```vue
<script setup>
import UploadOtExcel from '@/Components/UploadOtExcel.vue'

const form = useForm({ /* campos */ })

function handlePrefillLoaded({ prefill, warnings }) {
  // Actualizar formulario con datos precargados
  form.centro_trabajo_id = prefill.centro_trabajo_id
  // ... más campos
}
</script>

<template>
  <UploadOtExcel 
    :orden-id="ordenId"
    :archivo-existente="archivoExistente"
    @prefill-loaded="handlePrefillLoaded"
  />
  
  <!-- Formulario -->
</template>
```

## Formato Excel Esperado

El parser busca etiquetas en las primeras 100 filas:

### Formato Vertical
| A | B |
|---|---|
| **Centro de Trabajo** | INGCEDIM |
| **Centro de Costos** | KPI 01 |
| **Marca** | COPPEL |

### Formato Horizontal
| A | B | C |
|---|---|---|
| **Centro** | **Código** | **Marca** |
| INGCEDIM | KPI 01 | COPPEL |

## Seguridad

- ✅ Autorización mediante Policy (`$this->authorize('update', $orden)`)
- ✅ Validación de tipo de archivo (solo .xlsx, .xls)
- ✅ Validación de tamaño (máx. 10MB)
- ✅ CSRF token requerido
- ✅ Archivos almacenados en storage privado

## Testing

Ejecutar tests:

```bash
# Feature tests
php artisan test --filter OrdenExcelUploadTest

# Unit tests
php artisan test --filter ExcelOtParserTest

# Todos los tests
php artisan test
```

## Instalación de Dependencias

```bash
composer require phpoffice/phpspreadsheet
php artisan migrate
php artisan storage:link
```

## Próximos Pasos (Opcional)

- [ ] Agregar soporte para más formatos (CSV, ODS)
- [ ] Implementar preview del Excel antes de cargar
- [ ] Agregar histórico de archivos cargados
- [ ] Exportar template de Excel predefinido
- [ ] Validaciones avanzadas del contenido del Excel

## Notas Técnicas

- **Mapeo flexible**: Busca por código o nombre con coincidencias aproximadas
- **Warnings vs Errors**: Si no encuentra un dato, no falla, solo advierte
- **Reemplazo de archivos**: Al subir nuevo archivo, elimina el anterior
- **Eager loading**: El componente carga datos de catálogo al inicio
- **Almacenamiento**: `storage/app/public/ot_archivos/`
- **Convención de nombres**: `OT_{id}_{timestamp}_{random}.xlsx`
