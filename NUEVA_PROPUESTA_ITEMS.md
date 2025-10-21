# 🎯 Nueva Propuesta: Sistema Inteligente de Ítems con Separación

## 📋 Análisis de la Propuesta del Usuario

### ✅ Lógica Correcta Identificada:

1. **Servicios CON `usa_tamanos = 1`** (Distribución, Surtido, Embalaje):
   - Items ya vienen definidos por tamaños desde la solicitud
   - Cantidades NO deben modificarse (ya fueron aprobadas)
   - Solo mostrar para confirmación

2. **Servicios SIN `usa_tamanos = 0`** (Almacenaje, Transporte, Traspaleo):
   - **NUEVA FUNCIONALIDAD**: Separar cantidad total en sub-items
   - Permitir descripción libre por cada sub-item
   - **Validación crítica**: Σ cantidades de items = cantidad total aprobada
   - Contador visual en tiempo real

### 📝 Ejemplo Real:

**Solicitud Aprobada**:
- Servicio: Transporte (usa_tamanos = 0)
- Descripción: "Computadoras"
- Cantidad: 10

**Al Crear OT** - Opciones:

**Opción A: No Separar** (Default)
```
✅ Item único:
   - Descripción: "Computadoras"
   - Cantidad: 10
```

**Opción B: Separar por Modelo**
```
✅ Item 1:
   - Descripción: "Lenovo ThinkPad"
   - Cantidad: 6
   
✅ Item 2:
   - Descripción: "Asus VivoBook"
   - Cantidad: 4
   
   Total: 6 + 4 = 10 ✅
```

**❌ Validación - No permitir**:
```
❌ Item 1: Lenovo = 6
❌ Item 2: Asus = 5
   Total: 6 + 5 = 11 ❌ (Excede cantidad aprobada)
```

---

## 🚀 Implementación Completa

### 1️⃣ **Backend: Controlador Actualizado**

```php
// OrdenController::createFromSolicitud()

public function createFromSolicitud(Solicitud $solicitud)
{
    $this->authorize('createFromSolicitud', [Orden::class, $solicitud->id_centrotrabajo]);
    $this->authorizeFromCentro($solicitud->id_centrotrabajo);
    
    if ($solicitud->estatus !== 'aprobada') {
        abort(422, 'La solicitud no está aprobada.');
    }
    
    if ($solicitud->ordenes()->exists()) {
        return redirect()->route('solicitudes.show', $solicitud->id)
            ->withErrors(['ot' => 'Ya existe una Orden de Trabajo para esta solicitud.']);
    }

    $solicitud->load(['servicio','centro','tamanos']);

    // LÓGICA BASADA EN usa_tamanos
    $usaTamanos = (bool)($solicitud->servicio->usa_tamanos ?? false);
    $prefill = [];

    if ($usaTamanos && $solicitud->tamanos->count() > 0) {
        // Servicios CON tamaños: Items fijos, no editables
        foreach ($solicitud->tamanos as $t) {
            $prefill[] = [
                'tamano'      => (string)($t->tamano ?? ''),
                'cantidad'    => (int)($t->cantidad ?? 0),
                'descripcion' => null, // No necesario
                'editable'    => false, // NO permitir editar
            ];
        }
    } else {
        // Servicios SIN tamaños: Item único, separable
        $prefill[] = [
            'descripcion' => $solicitud->descripcion ?? 'Item',
            'cantidad'    => (int)($solicitud->cantidad ?? 1),
            'tamano'      => null,
            'editable'    => true, // Permitir separar
        ];
    }

    $teamLeaders = User::role('team_leader')
        ->where('centro_trabajo_id', $solicitud->id_centrotrabajo)
        ->select('id','name')->orderBy('name')->get();

    // Cotización (sin cambios)
    $pricing = app(\App\Domain\Servicios\PricingService::class);
    $ivaRate = 0.16;
    $cotLines = [];
    $sub = 0.0;
    
    if ($usaTamanos && $solicitud->tamanos && $solicitud->tamanos->count() > 0) {
        foreach ($solicitud->tamanos as $t) {
            $tam = (string)($t->tamano ?? '');
            $cant = (int)($t->cantidad ?? 0);
            if ($cant <= 0) continue;
            $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, $tam);
            $lineSub = $pu * $cant;
            $sub += $lineSub;
            $cotLines[] = ['label'=>ucfirst($tam), 'cantidad'=>$cant, 'pu'=>$pu, 'subtotal'=>$lineSub];
        }
    } else {
        $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, null);
        $sub = $pu * (int)($solicitud->cantidad ?? 0);
        $cotLines[] = ['label'=>'Item', 'cantidad'=>(int)($solicitud->cantidad ?? 0), 'pu'=>$pu, 'subtotal'=>$sub];
    }

    $iva = $sub * $ivaRate;
    $total = $sub + $iva;

    return Inertia::render('Ordenes/CreateFromSolicitud', [
        'solicitud'   => $solicitud,
        'folio'       => $this->buildFolioOT($solicitud->id_centrotrabajo),
        'teamLeaders' => $teamLeaders,
        'prefill'     => $prefill,
        'usaTamanos'  => $usaTamanos,  // NUEVO: Indica modo de operación
        'cantidadTotal' => (int)($solicitud->cantidad ?? 1), // NUEVO: Para validación
        'urls' => [
            'back'  => route('solicitudes.show', $solicitud),
            'store' => route('ordenes.storeFromSolicitud', $solicitud),
        ],
        'cotizacion' => [
            'lines'    => $cotLines,
            'subtotal' => $sub,
            'iva'      => $iva,
            'iva_rate' => $ivaRate,
            'total'    => $total,
        ],
    ]);
}
```

### 2️⃣ **Backend: Validación Mejorada**

```php
// OrdenController::storeFromSolicitud()

public function storeFromSolicitud(Request $req, Solicitud $solicitud)
{
    $this->authorize('createFromSolicitud', [Orden::class, $solicitud->id_centrotrabajo]);
    $this->authorizeFromCentro($solicitud->id_centrotrabajo);
    
    if ($solicitud->estatus !== 'aprobada') {
        abort(422, 'La solicitud no está aprobada.');
    }
    
    if ($solicitud->ordenes()->exists()) {
        return redirect()->route('solicitudes.show', $solicitud->id)
            ->withErrors(['ot' => 'Ya existe una Orden de Trabajo para esta solicitud.']);
    }

    $solicitud->load('servicio');
    $usaTamanos = (bool)($solicitud->servicio->usa_tamanos ?? false);

    // Validación base
    $data = $req->validate([
        'team_leader_id' => ['nullable','integer','exists:users,id'],
        'items'          => ['required','array','min:1'],
        'items.*.cantidad' => ['required','integer','min:1'],
    ]);

    if ($usaTamanos) {
        // Servicios CON tamaños: validar tamaños
        $req->validate([
            'items.*.tamano' => ['required','in:chico,mediano,grande'],
        ]);
        
        // Las cantidades NO deben modificarse, validar que coincidan con solicitud
        $solicitud->load('tamanos');
        $expectedItems = $solicitud->tamanos->keyBy('tamano')->map(fn($t) => (int)$t->cantidad)->toArray();
        
        foreach ($data['items'] as $item) {
            $tamano = $item['tamano'] ?? null;
            if (!isset($expectedItems[$tamano])) {
                return back()->withErrors(['items' => "Tamaño {$tamano} no existe en la solicitud."]);
            }
            if ((int)$item['cantidad'] !== $expectedItems[$tamano]) {
                return back()->withErrors(['items' => "La cantidad del tamaño {$tamano} no coincide con la solicitud aprobada."]);
            }
        }
    } else {
        // Servicios SIN tamaños: validar descripciones y suma de cantidades
        $req->validate([
            'items.*.descripcion' => ['required','string','max:255'],
        ]);
        
        // VALIDACIÓN CRÍTICA: Suma de cantidades = cantidad total aprobada
        $cantidadTotal = (int)$solicitud->cantidad;
        $sumaCantidades = collect($data['items'])->sum(fn($i) => (int)($i['cantidad'] ?? 0));
        
        if ($sumaCantidades !== $cantidadTotal) {
            return back()->withErrors([
                'items' => "La suma de cantidades ({$sumaCantidades}) no coincide con la cantidad aprobada ({$cantidadTotal})."
            ]);
        }
    }

    // Crear Orden (resto del código sin cambios)
    $orden = DB::transaction(function () use ($solicitud, $data, $usaTamanos) {
        $totalPlan = collect($data['items'])->sum(fn($i) => (int)($i['cantidad'] ?? 0));

        $orden = Orden::create([
            'folio'            => $this->buildFolioOT($solicitud->id_centrotrabajo),
            'id_solicitud'     => $solicitud->id,
            'id_centrotrabajo' => $solicitud->id_centrotrabajo,
            'id_servicio'      => $solicitud->id_servicio,
            'id_area'          => $solicitud->id_area,
            'team_leader_id'   => $data['team_leader_id'] ?? null,
            'estatus'          => !empty($data['team_leader_id']) ? 'asignada' : 'generada',
            'total_planeado'   => $totalPlan,
            'total_real'       => 0,
            'calidad_resultado'=> 'pendiente',
        ]);

        $pricing = app(\App\Domain\Servicios\PricingService::class);
        $sub = 0.0;
        
        foreach ($data['items'] as $it) {
            $tamano = $it['tamano'] ?? null;
            $pu = (float)$pricing->precioUnitario($solicitud->id_centrotrabajo, $solicitud->id_servicio, $tamano);

            OrdenItem::create([
                'id_orden'          => $orden->id,
                'descripcion'       => $it['descripcion'] ?? null,
                'tamano'            => $tamano,
                'cantidad_planeada' => (int)$it['cantidad'],
                'precio_unitario'   => $pu,
                'subtotal'          => $pu * (int)$it['cantidad'],
            ]);
            $sub += $pu * (int)$it['cantidad'];
        }

        $ivaRate = 0.16;
        $iva = $sub * $ivaRate;
        $total = $sub + $iva;
        $orden->subtotal = $sub;
        $orden->iva = $iva;
        $orden->total = $total;
        $orden->save();

        return $orden;
    });

    $this->act('ordenes')
        ->performedOn($orden)
        ->event('generar_ot')
        ->withProperties(['team_leader_id' => $orden->team_leader_id])
        ->log("OT #{$orden->id} generada desde solicitud {$solicitud->folio}");

    if ($orden->team_leader_id) {
        $teamLeader = User::find($orden->team_leader_id);
        if ($teamLeader) {
            $teamLeader->notify(new OtAsignada($orden));
        }
    }

    Notifier::toRoleInCentro(
        'calidad',
        $orden->id_centrotrabajo,
        'OT generada',
        "Se generó la OT #{$orden->id} (pendiente de revisión al completar).",
        route('ordenes.show',$orden->id)
    );

    GenerateOrdenPdf::dispatch($orden->id)->onQueue('pdfs');

    return redirect()->route('ordenes.show', $orden->id)->with('ok','OT creada correctamente');
}
```

### 3️⃣ **Frontend: Vue Component Inteligente**

```vue
<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

const props = defineProps({
  solicitud: Object,
  folio: String,
  teamLeaders: Array,
  urls: Object,
  cotizacion: Object,
  prefill: { type: Array, default: () => [] },
  usaTamanos: { type: Boolean, default: false },
  cantidadTotal: { type: Number, default: 0 },
})

const form = useForm({
  team_leader_id: null,
  items: props.prefill.length > 0 
    ? props.prefill.map(i => ({ 
        descripcion: i.descripcion || '', 
        cantidad: i.cantidad || 1, 
        tamano: i.tamano ?? null 
      }))
    : [{ descripcion: '', cantidad: 1, tamano: null }]
})

// Contador en tiempo real
const sumaActual = computed(() => {
  return form.items.reduce((sum, item) => sum + (parseInt(item.cantidad) || 0), 0)
})

const cantidadRestante = computed(() => {
  return props.cantidadTotal - sumaActual.value
})

const puedeAgregarItem = computed(() => {
  return !props.usaTamanos && cantidadRestante.value > 0
})

const alertaSuma = computed(() => {
  if (props.usaTamanos) return null
  if (sumaActual.value < props.cantidadTotal) return 'warning'
  if (sumaActual.value > props.cantidadTotal) return 'error'
  return 'success'
})

function addItem() {
  if (!puedeAgregarItem.value) return
  form.items.push({ 
    descripcion: '', 
    cantidad: Math.min(1, cantidadRestante.value), 
    tamano: null 
  })
}

function removeItem(i) {
  if (form.items.length <= 1) return
  form.items.splice(i, 1)
}

function submit() {
  form.post(props.urls.store, { preserveScroll: true })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-purple-50 to-pink-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto space-y-6">
      
      <!-- Header Card -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-purple-100 overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-8 py-6">
          <div class="flex items-center gap-4">
            <div class="bg-white bg-opacity-20 p-3 rounded-xl backdrop-blur-sm">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
              </svg>
            </div>
            <div>
              <h1 class="text-3xl font-bold text-white">Generar Orden de Trabajo</h1>
              <p class="text-purple-100 text-sm mt-1">{{ folio }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-8 py-4 border-b border-purple-100">
          <div class="flex items-center gap-6 text-sm">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
              <span class="text-gray-700"><strong>Servicio:</strong> {{ solicitud?.servicio?.nombre }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="px-3 py-1 rounded-full text-xs font-bold"
                    :class="usaTamanos ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'">
                {{ usaTamanos ? '📏 Usa Tamaños' : '📝 Descripción Libre' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Form -->
        <div class="lg:col-span-2 space-y-6">
          
          <form @submit.prevent="submit" class="space-y-6">
            
            <!-- Team Leader Section -->
            <div class="bg-white rounded-2xl shadow-lg border-2 border-indigo-100 overflow-hidden">
              <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                  Asignación de Team Leader
                </h2>
              </div>
              <div class="p-6">
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                  Seleccionar Team Leader
                  <span class="text-gray-400 font-normal ml-1">(Opcional)</span>
                </label>
                <select v-model="form.team_leader_id" 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all">
                  <option :value="null">— Sin asignar —</option>
                  <option v-for="u in teamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>

            <!-- Items Section -->
            <div class="bg-white rounded-2xl shadow-lg border-2 border-emerald-100 overflow-hidden">
              <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                  </svg>
                  Ítems de la Orden de Trabajo
                </h2>
              </div>
              <div class="p-6 space-y-4">
                
                <!-- INFO: Modo de operación -->
                <div v-if="usaTamanos" class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                  <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-blue-800">
                      <p class="font-semibold mb-1">📏 Servicio con Tamaños</p>
                      <p>Los ítems están predefinidos por tamaños. Las cantidades no pueden modificarse (ya fueron aprobadas).</p>
                    </div>
                  </div>
                </div>

                <div v-else class="bg-green-50 border-2 border-green-200 rounded-xl p-4">
                  <div class="flex gap-3">
                    <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-green-800">
                      <p class="font-semibold mb-1">📝 Separación de Ítems</p>
                      <p>Puedes separar la cantidad total (<strong>{{ cantidadTotal }}</strong>) en varios ítems con diferentes descripciones.</p>
                      <p class="mt-1"><strong>Importante:</strong> La suma de todas las cantidades debe ser exactamente <strong>{{ cantidadTotal }}</strong>.</p>
                    </div>
                  </div>
                </div>

                <!-- Contador de Cantidades (Solo para servicios SIN tamaños) -->
                <div v-if="!usaTamanos" class="sticky top-4 z-10 bg-gradient-to-r rounded-xl p-5 border-2 shadow-lg"
                     :class="{
                       'from-yellow-50 to-orange-50 border-yellow-300': alertaSuma === 'warning',
                       'from-red-50 to-pink-50 border-red-300': alertaSuma === 'error',
                       'from-emerald-50 to-teal-50 border-emerald-300': alertaSuma === 'success'
                     }">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-600 mb-1">Cantidad Total Aprobada</p>
                      <p class="text-3xl font-bold" :class="{
                        'text-yellow-700': alertaSuma === 'warning',
                        'text-red-700': alertaSuma === 'error',
                        'text-emerald-700': alertaSuma === 'success'
                      }">
                        {{ cantidadTotal }}
                      </p>
                    </div>
                    <div class="text-center px-6 py-3 bg-white bg-opacity-50 rounded-xl">
                      <p class="text-xs font-medium text-gray-600 mb-1">Suma Actual</p>
                      <p class="text-4xl font-bold" :class="{
                        'text-yellow-700': alertaSuma === 'warning',
                        'text-red-700': alertaSuma === 'error',
                        'text-emerald-700': alertaSuma === 'success'
                      }">
                        {{ sumaActual }}
                      </p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-600 mb-1">Restante</p>
                      <p class="text-3xl font-bold" :class="{
                        'text-yellow-700': cantidadRestante > 0,
                        'text-red-700': cantidadRestante < 0,
                        'text-emerald-700': cantidadRestante === 0
                      }">
                        {{ cantidadRestante }}
                      </p>
                    </div>
                  </div>
                  
                  <!-- Estado Visual -->
                  <div class="mt-4 pt-4 border-t-2" :class="{
                    'border-yellow-200': alertaSuma === 'warning',
                    'border-red-200': alertaSuma === 'error',
                    'border-emerald-200': alertaSuma === 'success'
                  }">
                    <div v-if="alertaSuma === 'warning'" class="flex items-center gap-2 text-yellow-800">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                      </svg>
                      <p class="font-semibold text-sm">Faltan {{ cantidadRestante }} unidades por asignar</p>
                    </div>
                    <div v-else-if="alertaSuma === 'error'" class="flex items-center gap-2 text-red-800">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                      </svg>
                      <p class="font-semibold text-sm">¡Excede en {{ Math.abs(cantidadRestante) }} unidades!</p>
                    </div>
                    <div v-else class="flex items-center gap-2 text-emerald-800">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                      </svg>
                      <p class="font-semibold text-sm">✓ Cantidades correctas</p>
                    </div>
                  </div>
                </div>

                <!-- Item List -->
                <div class="space-y-4">
                  <div v-for="(it, i) in form.items" :key="i" 
                       class="bg-gradient-to-br from-gray-50 to-emerald-50 rounded-xl p-5 border-2 border-emerald-100">
                    
                    <div class="flex items-center justify-between mb-4">
                      <span class="text-sm font-bold text-emerald-700 bg-emerald-100 px-3 py-1 rounded-full">
                        Ítem #{{ i + 1 }}
                      </span>
                      <button v-if="!usaTamanos && form.items.length > 1" 
                              type="button" 
                              @click="removeItem(i)"
                              class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-white bg-red-50 hover:bg-red-600 rounded-lg transition-all">
                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Eliminar
                      </button>
                    </div>

                    <!-- Servicios CON tamaños -->
                    <div v-if="usaTamanos" class="grid md:grid-cols-2 gap-4">
                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tamaño</label>
                        <div class="px-4 py-3 bg-gray-100 border-2 border-gray-300 rounded-xl text-gray-600 font-semibold">
                          {{ it.tamano?.toUpperCase() || 'N/A' }}
                        </div>
                      </div>
                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cantidad</label>
                        <div class="px-4 py-3 bg-gray-100 border-2 border-gray-300 rounded-xl text-gray-600 font-semibold">
                          {{ it.cantidad }}
                        </div>
                      </div>
                    </div>

                    <!-- Servicios SIN tamaños -->
                    <div v-else class="space-y-4">
                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Descripción</label>
                        <textarea v-model="it.descripcion" 
                                  rows="2"
                                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400 transition-all resize-none"
                                  placeholder="Ej: Lenovo ThinkPad, Asus VivoBook, etc."></textarea>
                      </div>
                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                          Cantidad
                          <span v-if="cantidadRestante > 0 && i === form.items.length - 1" class="text-green-600 font-normal ml-2">
                            (Disponible: {{ cantidadRestante }})
                          </span>
                        </label>
                        <input type="number" 
                               min="1" 
                               :max="cantidadTotal"
                               v-model.number="it.cantidad" 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400 transition-all" 
                               placeholder="0" />
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Add Item Button (Solo para servicios SIN tamaños) -->
                <button v-if="!usaTamanos" 
                        type="button" 
                        @click="addItem"
                        :disabled="!puedeAgregarItem"
                        class="w-full px-5 py-3 border-2 border-dashed rounded-xl font-semibold transition-all flex items-center justify-center gap-2"
                        :class="puedeAgregarItem 
                          ? 'bg-gradient-to-r from-emerald-100 to-teal-100 border-emerald-300 text-emerald-700 hover:from-emerald-200 hover:to-teal-200 hover:border-emerald-400' 
                          : 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed'">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                  </svg>
                  {{ puedeAgregarItem ? 'Agregar otro ítem' : 'No hay cantidad disponible para más ítems' }}
                </button>

                <!-- Errors -->
                <div v-if="form.errors.items" class="bg-red-50 border-2 border-red-200 rounded-xl p-4">
                  <p class="text-sm text-red-600 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ form.errors.items }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="bg-white rounded-2xl shadow-lg border-2 border-purple-100 p-6">
              <button type="submit" 
                      :disabled="form.processing || (!usaTamanos && alertaSuma !== 'success')"
                      class="w-full px-8 py-4 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-bold text-lg rounded-xl shadow-xl hover:shadow-2xl hover:scale-105 transform transition-all disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center justify-center gap-3">
                <svg v-if="!form.processing" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <svg v-else class="w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ form.processing ? 'Creando Orden de Trabajo...' : 'Crear Orden de Trabajo' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Right Column: Cotización -->
        <div class="lg:col-span-1">
          <div v-if="cotizacion?.lines?.length" class="bg-white rounded-2xl shadow-lg border-2 border-orange-100 overflow-hidden sticky top-6">
            <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-6 py-4">
              <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Cotización de Referencia
              </h2>
            </div>
            
            <div class="p-5">
              <div class="space-y-3 mb-5">
                <div v-for="(l,i) in cotizacion.lines" :key="i" 
                     class="bg-gradient-to-br from-orange-50 to-amber-50 rounded-xl p-4 border border-orange-100">
                  <div class="font-semibold text-gray-800 mb-3">{{ l.label }}</div>
                  <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                      <span class="text-gray-600">Cantidad:</span>
                      <span class="font-bold text-gray-800">{{ l.cantidad }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-600">P. Unitario:</span>
                      <span class="font-bold text-orange-600">${{ (l.pu||0).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-orange-200">
                      <span class="text-gray-700 font-medium">Subtotal:</span>
                      <span class="font-bold text-gray-800">${{ (l.subtotal||0).toFixed(2) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="border-t-2 border-orange-300 pt-4 space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600 font-medium">Subtotal:</span>
                  <span class="font-bold text-gray-800 text-lg">${{ (cotizacion.subtotal||0).toFixed(2) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600 font-medium">IVA ({{ ((cotizacion.iva_rate||0)*100).toFixed(0) }}%):</span>
                  <span class="font-bold text-gray-800 text-lg">${{ (cotizacion.iva||0).toFixed(2) }}</span>
                </div>
                <div class="bg-gradient-to-r from-orange-600 to-amber-600 rounded-xl px-5 py-4 flex justify-between items-center shadow-lg">
                  <span class="text-white font-bold text-lg">Total:</span>
                  <span class="text-white font-bold text-2xl">${{ (cotizacion.total||0).toFixed(2) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
```

---

## 🎯 Resultado Final

### Para Servicios CON Tamaños (Distribución, Surtido, Embalaje):
- ✅ Items bloqueados, no editables
- ✅ Solo muestra tamaños y cantidades aprobadas
- ✅ Validación: cantidades deben coincidir con solicitud

### Para Servicios SIN Tamaños (Almacenaje, Transporte, Traspaleo):
- ✅ Puede separar cantidad en múltiples items
- ✅ Descripciones libres por cada item
- ✅ Contador visual en tiempo real
- ✅ Validación: Σ cantidades = cantidad total aprobada
- ✅ Botón "Agregar item" deshabilitado si no hay cantidad disponible

### Ejemplo Visual del Contador:

```
┌─────────────────────────────────────────────┐
│  Cantidad Total: 10  │  Suma: 10  │  Restante: 0  │
│                   ✓ Cantidades correctas           │
└─────────────────────────────────────────────┘

Ítem #1: Lenovo ThinkPad = 6
Ítem #2: Asus VivoBook = 4

[+ Agregar otro ítem] (deshabilitado - no hay cantidad)
```

---

**¿Te parece bien esta solución? ¿Quieres que la implemente?** 🚀
