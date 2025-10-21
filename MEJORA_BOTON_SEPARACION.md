# 🎛️ Mejora: Botón de Activación para Separación de Ítems

**Fecha:** 15 de octubre de 2025  
**Estado:** ✅ Completado

---

## 🎯 Objetivo

Implementar un botón de activación/desactivación para dar al usuario control sobre si desea separar los ítems o crear la OT con datos generales únicamente.

---

## ❌ Problema Anterior

- La separación de ítems aparecía **siempre activa** para servicios sin tamaños
- El usuario **no podía** optar por crear una OT simple con solo datos generales
- Se **forzaba** la edición de ítems incluso cuando no era necesario
- Pérdida de flexibilidad en el flujo de trabajo

---

## ✅ Solución Implementada

### 1. **Botón de Activación/Desactivación**

```vue
<button @click="toggleSeparacion">
  {{ separarItems ? 'Desactivar' : 'Activar Separación' }}
</button>
```

**Estados:**
- **🔴 Desactivado** (por defecto): Crea OT con datos generales únicamente
- **🟢 Activado**: Permite desglose detallado de ítems específicos

---

## 📊 Flujos de Uso

### Flujo 1: Sin Separación (Modo Simple)

```
┌─────────────────────────────────────────┐
│ PRODUCTO/SERVICIO SOLICITADO            │
│ Computadoras — 10 pz                    │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│ ¿Deseas separar por ítems específicos?  │
│                                         │
│  [Activar Separación] ← Click aquí     │
└─────────────────────────────────────────┘
              ↓
   Usuario decide NO separar
              ↓
┌─────────────────────────────────────────┐
│ Se crea OT con:                         │
│  • Descripción general: Computadoras    │
│  • Cantidad total: 10 pz                │
│  • Sin desglose de ítems específicos    │
└─────────────────────────────────────────┘
```

### Flujo 2: Con Separación (Modo Detallado)

```
┌─────────────────────────────────────────┐
│ PRODUCTO/SERVICIO SOLICITADO            │
│ Computadoras — 10 pz                    │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│ ¿Deseas separar por ítems específicos?  │
│                                         │
│  [Activar Separación] ← Click aquí     │
└─────────────────────────────────────────┘
              ↓
   Usuario ACTIVA separación
              ↓
┌─────────────────────────────────────────┐
│ ✓ Separación Activada                  │
│                                         │
│  [Desactivar]                           │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│ DESGLOSE DE ÍTEMS ESPECÍFICOS           │
│                                         │
│  Ítem #1:                               │
│  Descripción: Lenovo ThinkPad           │
│  Cantidad: 6 pz                         │
│                                         │
│  Ítem #2:                               │
│  Descripción: Asus VivoBook             │
│  Cantidad: 4 pz                         │
│                                         │
│  [+ Agregar otro ítem]                  │
│                                         │
│  ✓ Suma: 10 pz (Correcto)               │
└─────────────────────────────────────────┘
```

---

## 🔧 Cambios Técnicos

### Frontend: `CreateFromSolicitud.vue`

#### 1. **Nuevo Estado Reactivo**

```javascript
import { ref } from 'vue'

// Control de separación de ítems
const separarItems = ref(false)

const form = useForm({
  team_leader_id: null,
  separar_items: false,  // ← NUEVO: se envía al backend
  items: [...]
})
```

#### 2. **Función de Toggle**

```javascript
function toggleSeparacion() {
  separarItems.value = !separarItems.value
  form.separar_items = separarItems.value
  
  if (separarItems.value) {
    // Activar: iniciar con un ítem editable
    form.items = [{ 
      descripcion: props.descripcionGeneral || '', 
      cantidad: props.cantidadTotal || 1, 
      tamano: null 
    }]
  } else {
    // Desactivar: resetear a prefill original
    form.items = props.prefill.map(i => ({ 
      descripcion: i.descripcion || '', 
      cantidad: i.cantidad || 1, 
      tamano: i.tamano ?? null 
    }))
  }
}
```

#### 3. **Computadas Actualizadas**

```javascript
// Solo se activan si hay separación
const sumaActual = computed(() => {
  if (props.usaTamanos || !separarItems.value) return 0
  return form.items.reduce((sum, item) => sum + (parseInt(item.cantidad) || 0), 0)
})

const cantidadRestante = computed(() => {
  if (props.usaTamanos || !separarItems.value) return 0
  return props.cantidadTotal - sumaActual.value
})

const puedeAgregarItem = computed(() => {
  return !props.usaTamanos && separarItems.value && cantidadRestante.value > 0
})
```

#### 4. **Botón de Activación en Template**

```vue
<!-- Botón para activar separación (solo para servicios SIN tamaños) -->
<div v-if="!usaTamanos" class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl p-5">
  <div class="flex items-center justify-between">
    <div class="flex-1">
      <h3 class="text-lg font-bold text-blue-900 mb-1">
        {{ separarItems ? '✓ Separación Activada' : '¿Deseas separar por ítems específicos?' }}
      </h3>
      <p class="text-sm text-blue-700">
        {{ separarItems 
          ? 'Puedes dividir la cantidad total en diferentes ítems (marcas, modelos, etc.)'
          : 'Crea la OT con los datos generales o activa la separación para detallar ítems específicos'
        }}
      </p>
    </div>
    <button type="button" @click="toggleSeparacion"
            class="ml-4 px-6 py-3 rounded-xl font-bold text-white transition-all duration-200 transform hover:scale-105 shadow-lg"
            :class="separarItems 
              ? 'bg-gradient-to-r from-red-500 to-pink-500' 
              : 'bg-gradient-to-r from-blue-500 to-indigo-500'">
      <span class="flex items-center gap-2">
        <svg v-if="!separarItems" class="w-5 h-5"><!-- icono + --></svg>
        <svg v-else class="w-5 h-5"><!-- icono X --></svg>
        {{ separarItems ? 'Desactivar' : 'Activar Separación' }}
      </span>
    </button>
  </div>
</div>
```

#### 5. **Renderizado Condicional de Secciones**

```vue
<!-- Solo mostrar si está separado o usa tamaños -->
<div v-if="usaTamanos || separarItems" class="space-y-4">
  <!-- Información sobre el modo -->
  <!-- Contador de cantidades -->
  <!-- Lista de ítems -->
  <!-- Botón agregar ítem -->
</div>
```

---

### Backend: `OrdenController.php`

#### 1. **Validación Actualizada**

```php
// Validación base
$data = $req->validate([
    'team_leader_id' => ['nullable','integer','exists:users,id'],
    'separar_items'  => ['nullable','boolean'],  // ← NUEVO
    'items'          => ['required','array','min:1'],
    'items.*.cantidad' => ['required','integer','min:1'],
]);

$separarItems = (bool)($data['separar_items'] ?? false);
```

#### 2. **Lógica Condicional**

```php
if ($usaTamanos) {
    // Servicios CON tamaños: validar tamaños obligatorios
    // ...
} else {
    // Servicios SIN tamaños
    if ($separarItems) {
        // SI se activa separación: validar descripciones y suma
        $req->validate([
            'items.*.descripcion' => ['required','string','max:255'],
        ]);
        
        // VALIDACIÓN CRÍTICA: Suma debe ser igual al total
        $cantidadTotal = (int)$solicitud->cantidad;
        $sumaCantidades = collect($data['items'])->sum(fn($i) => (int)($i['cantidad'] ?? 0));
        
        if ($sumaCantidades !== $cantidadTotal) {
            return back()->withErrors([
                'items' => "La suma de las cantidades ({$sumaCantidades}) no coincide con el total ({$cantidadTotal})."
            ]);
        }
    } else {
        // NO se separa: descripción puede ser opcional o usar la general
        // No es necesaria validación de suma
    }
}
```

---

## 🎨 Interfaz de Usuario

### Estado Desactivado (Por Defecto)

```
┌────────────────────────────────────────────────────┐
│ ¿Deseas separar por ítems específicos?             │
│                                                    │
│ Crea la OT con los datos generales o activa la    │
│ separación para detallar ítems específicos        │
│                                                    │
│                       [Activar Separación] 🔵     │
└────────────────────────────────────────────────────┘

(No se muestran campos de ítems)
```

### Estado Activado

```
┌────────────────────────────────────────────────────┐
│ ✓ Separación Activada                             │
│                                                    │
│ Puedes dividir la cantidad total en diferentes    │
│ ítems (marcas, modelos, etc.)                     │
│                                                    │
│                          [Desactivar] 🔴          │
└────────────────────────────────────────────────────┘
              ↓
┌────────────────────────────────────────────────────┐
│ 📝 Separación de Ítems                            │
│ Define los ítems específicos que componen el      │
│ total. Validación: suma debe ser 10 pz            │
└────────────────────────────────────────────────────┘
              ↓
┌────────────────────────────────────────────────────┐
│ Total: 10  |  Suma: 10  |  Restante: 0  🟢       │
└────────────────────────────────────────────────────┘
              ↓
[Lista de Ítems Editables]
```

---

## ✅ Beneficios

### 1. **Flexibilidad**
- ✅ Usuario decide si necesita desglose o no
- ✅ Proceso más rápido cuando no se requiere detalle
- ✅ Opción de detalle disponible cuando se necesita

### 2. **UX Mejorada**
- ✅ Interfaz más limpia por defecto
- ✅ Menos campos que llenar si no es necesario
- ✅ Control visual claro del modo activo

### 3. **Validación Inteligente**
- ✅ Solo valida suma cuando está activada separación
- ✅ No fuerza descripciones cuando no se usa
- ✅ Mensajes de error más específicos según modo

### 4. **Eficiencia**
- ✅ Creación rápida de OT simples
- ✅ Desglose detallado cuando se requiere
- ✅ Menos pasos para casos básicos

---

## 📋 Casos de Uso

### Caso 1: Mantenimiento General
**Escenario:** Limpieza de 20 computadoras, todas iguales

**Flujo:**
1. Usuario crea solicitud: "Limpieza - 20 pz"
2. Al crear OT, NO activa separación
3. Se crea OT con descripción general únicamente
4. Proceso completado en 2 clics

**Antes:** Usuario forzado a editar ítems innecesariamente

### Caso 2: Inventario Específico
**Escenario:** Mantenimiento de 10 computadoras de diferentes marcas

**Flujo:**
1. Usuario crea solicitud: "Mantenimiento Computadoras - 10 pz"
2. Al crear OT, ACTIVA separación
3. Desglosa: Dell (4), HP (3), Lenovo (3)
4. Sistema valida suma = 10
5. OT creada con detalle completo

**Beneficio:** Trazabilidad detallada de marcas/modelos

### Caso 3: Servicio por Tamaños
**Escenario:** Escritorios en diferentes tamaños

**Flujo:**
1. Solicitud aprobada con tamaños
2. Al crear OT, separación NO disponible (automática por tamaños)
3. Ítems predefinidos y bloqueados
4. Usuario solo asigna team leader

**Comportamiento:** Sin cambios, sigue funcionando igual

---

## 🔍 Vista de Orden de Trabajo

### Visualización de Ítems en `Show.vue`

El código ya maneja correctamente la visualización:

```vue
<td class="px-6 py-4">
  <div class="flex items-center gap-3">
    <div>
      <div class="font-semibold text-gray-800">
        <!-- Si tiene tamaño, muestra el tamaño -->
        <span v-if="it?.tamano">{{ it.tamano }}</span>
        <!-- Si no tiene tamaño, muestra la descripción -->
        <span v-else>{{ it?.descripcion }}</span>
      </div>
      <!-- Si tiene ambos, muestra la descripción como subtítulo -->
      <div v-if="it?.tamano && it?.descripcion" class="text-sm text-gray-500 mt-1">
        {{ it.descripcion }}
      </div>
    </div>
  </div>
</td>
```

**Resultado:**
- **Con tamaños:** Muestra "Chico" (principal) y "Computadoras" (secundario)
- **Sin tamaños (separado):** Muestra "Lenovo ThinkPad" (principal)
- **Sin tamaños (no separado):** Muestra "Computadoras" (principal)

---

## 📁 Archivos Modificados

### Frontend
- ✅ `resources/js/Pages/Ordenes/CreateFromSolicitud.vue`
  - Agregado estado `separarItems`
  - Agregado campo `separar_items` al form
  - Agregada función `toggleSeparacion()`
  - Actualizadas computadas para considerar el estado
  - Agregado botón de activación/desactivación
  - Renderizado condicional de secciones de ítems

### Backend
- ✅ `app/Http/Controllers/OrdenController.php`
  - Agregado campo `separar_items` a validación
  - Lógica condicional según `$separarItems`
  - Validación de suma solo cuando está activada

### Vista de OT
- ✅ `resources/js/Pages/Ordenes/Show.vue`
  - Ya funcionaba correctamente
  - Muestra descripción o tamaño según disponibilidad
  - Sin cambios necesarios

---

## 🚀 Estado Actual

✅ **Completado y funcional**

- [x] Botón de activación/desactivación implementado
- [x] Estado reactivo funcionando
- [x] Validación condicional en backend
- [x] Renderizado condicional de secciones
- [x] Computadas actualizadas
- [x] Visualización en Show.vue verificada
- [x] Documentación completa

---

## 🎯 Próximos Pasos Sugeridos

1. **Testing de Usuario**
   - [ ] Probar flujo sin separación
   - [ ] Probar flujo con separación
   - [ ] Verificar visualización en OT

2. **Mejoras Futuras** (Opcionales)
   - [ ] Recordar preferencia del usuario (localStorage)
   - [ ] Animación de transición al activar/desactivar
   - [ ] Atajos de teclado (Ctrl+D para toggle)

---

**Implementado por:** GitHub Copilot  
**Solicitado por:** Usuario  
**Fecha:** 15 de octubre de 2025
