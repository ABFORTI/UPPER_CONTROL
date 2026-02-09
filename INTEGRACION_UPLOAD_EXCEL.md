# Integración del Componente UploadOtExcel

## Cómo integrar en el formulario de creación/edición de OT

### 1. Importar el componente en tu vista Vue

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
import UploadOtExcel from '@/Components/UploadOtExcel.vue'

const props = defineProps({
  solicitud: Object,
  ordenId: Number, // ID de la orden (si estás editando)
  archivoExistente: Object, // Archivo ya cargado (si existe)
  // ... otros props
})

const form = useForm({
  centro_trabajo_id: null,
  centro_costos_id: null,
  marca_id: null,
  tipo_servicio_id: null,
  descripcion_producto: '',
  area_id: null,
  // ... otros campos
})

// Manejar datos precargados desde Excel
function handlePrefillLoaded({ prefill, warnings }) {
  // Actualizar el formulario con los datos precargados
  if (prefill.centro_trabajo_id) {
    form.centro_trabajo_id = prefill.centro_trabajo_id
  }
  if (prefill.centro_costos_id) {
    form.centro_costos_id = prefill.centro_costos_id
  }
  if (prefill.marca_id) {
    form.marca_id = prefill.marca_id
  }
  if (prefill.tipo_servicio_id) {
    form.tipo_servicio_id = prefill.tipo_servicio_id
  }
  if (prefill.descripcion_producto) {
    form.descripcion_producto = prefill.descripcion_producto
  }
  if (prefill.area_id) {
    form.area_id = prefill.area_id
  }
  
  // Mostrar warnings al usuario
  if (warnings && warnings.length > 0) {
    // Puedes usar una notificación toast, alert, etc.
    console.warn('Warnings al cargar Excel:', warnings)
    // Ejemplo con alert:
    alert('Algunos datos no pudieron mapearse:\n' + warnings.join('\n'))
  }
}
</script>

<template>
  <div class="space-y-6">
    
    <!-- Componente de carga de Excel -->
    <UploadOtExcel 
      v-if="ordenId"
      :orden-id="ordenId"
      :archivo-existente="archivoExistente"
      @prefill-loaded="handlePrefillLoaded"
    />
    
    <!-- Resto del formulario -->
    <form @submit.prevent="submit">
      
      <!-- Campos del formulario que se precargarán -->
      <div class="space-y-4">
        
        <div>
          <label>Centro de Trabajo</label>
          <select v-model="form.centro_trabajo_id" class="...">
            <option :value="null">-- Seleccionar --</option>
            <option v-for="centro in centros" :key="centro.id" :value="centro.id">
              {{ centro.nombre }}
            </option>
          </select>
        </div>
        
        <div>
          <label>Centro de Costos</label>
          <select v-model="form.centro_costos_id" class="...">
            <!-- opciones -->
          </select>
        </div>
        
        <div>
          <label>Marca</label>
          <select v-model="form.marca_id" class="...">
            <!-- opciones -->
          </select>
        </div>
        
        <div>
          <label>Tipo de Servicio</label>
          <select v-model="form.tipo_servicio_id" class="...">
            <!-- opciones -->
          </select>
        </div>
        
        <div>
          <label>Descripción del Producto</label>
          <textarea v-model="form.descripcion_producto" class="..."></textarea>
        </div>
        
        <div>
          <label>Área</label>
          <select v-model="form.area_id" class="...">
            <!-- opciones -->
          </select>
        </div>
        
        <!-- ... más campos -->
        
        <button type="submit">Guardar Orden</button>
      </div>
    </form>
  </div>
</template>
```

## 2. Pasar el archivo existente desde el controlador

En tu controlador (ej: `OrdenController@edit` o `OrdenController@createFromSolicitud`), pasa la información del archivo si existe:

```php
public function edit(Orden $orden)
{
    $this->authorize('update', $orden);
    
    $archivoExistente = null;
    if ($orden->archivo_excel_path) {
        $archivoExistente = [
            'nombre' => $orden->archivo_excel_nombre_original,
            'url_descarga' => route('ordenes.archivo.download', $orden->id),
            'fecha_subida' => optional($orden->archivo_excel_subido_at)->format('d/m/Y H:i'),
            'subido_por' => optional($orden->archivoSubidoPor)->name,
        ];
    }
    
    return Inertia::render('Ordenes/Edit', [
        'orden' => $orden,
        'ordenId' => $orden->id,
        'archivoExistente' => $archivoExistente,
        // ... otros datos
    ]);
}
```

## 3. Notas importantes

### Seguridad
- El controlador ya valida permisos con `$this->authorize('update', $orden)`
- Solo usuarios autorizados pueden subir archivos
- Los archivos se validan (tipo, tamaño) antes de procesarse

### Manejo de errores
- Si el archivo no puede parsearse, se muestra un error
- Si algunos datos no se encuentran en BD, se muestran warnings pero se precargan los que sí existen
- El componente muestra mensajes claros al usuario

### UX
- El componente muestra un indicador de "Procesando..." mientras sube el archivo
- Se puede descargar el archivo previamente subido
- Si se sube un nuevo archivo, reemplaza al anterior

### Formato del Excel esperado

El parser busca etiquetas en las primeras 100 filas del Excel. Ejemplos:

| A | B |
|---|---|
| **Centro de Trabajo** | INGCEDIM |
| **Centro de Costos** | KPI 01 |
| **Marca** | COPPEL |
| **Servicio** | Almacenaje |
| **Descripción del Producto** | Computadoras |

O en formato horizontal:

| A | B | C | D |
|---|---|---|---|
| **Centro** | **Código** | **Marca** | **Servicio** |
| INGCEDIM | KPI 01 | COPPEL | Almacenaje |

El parser es flexible y busca coincidencias aproximadas (sin acentos, case-insensitive).
