<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  solicitud: Object,
  folio: String,
  teamLeaders: Array,
  areas: { type: Array, default: () => [] },
  urls: Object,
  cotizacion: Object,
  prefill: { type: Array, default: () => [] },
  usaTamanos: { type: Boolean, default: false },
  cantidadTotal: { type: Number, default: 0 },
  descripcionGeneral: { type: String, default: '' },
})

// Control de separación de ítems
const separarItems = ref(false)

const form = useForm({
  team_leader_id: null,
  id_area: props.solicitud?.id_area ?? null,
  separar_items: false,
  items: props.prefill.length > 0 
    ? props.prefill.map(i => ({ 
        descripcion: i.descripcion || '', 
        cantidad: i.cantidad || 1, 
        tamano: i.tamano ?? null 
      }))
    : [{ descripcion: '', cantidad: 1, tamano: null }]
})

const areaBloqueada = computed(() => !!props.solicitud?.id_area)

// Contador en tiempo real para servicios SIN tamaños
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

const alertaSuma = computed(() => {
  if (props.usaTamanos) return 'success' // Servicios con tamaños siempre OK
  if (!separarItems.value) return 'success' // Sin separación siempre OK
  if (sumaActual.value < props.cantidadTotal) return 'warning'
  if (sumaActual.value > props.cantidadTotal) return 'error'
  return 'success'
})

function toggleSeparacion() {
  separarItems.value = !separarItems.value
  form.separar_items = separarItems.value
  
  if (separarItems.value) {
    // Activar separación: iniciar con un ítem editable
    form.items = [{ 
      descripcion: '', 
      cantidad: props.cantidadTotal || 1, 
      tamano: null 
    }]
  } else {
    // Desactivar separación: usar descripción general
    form.items = [{ 
      descripcion: props.descripcionGeneral || '', 
      cantidad: props.cantidadTotal || 1, 
      tamano: null 
    }]
  }
}

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
  const payload = Object.assign({}, form);
  form.transform(() => payload).post(props.urls.store, { preserveScroll: true })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-upper-50 to-upper-100 py-8 px-4 sm:px-6 lg:px-8 dark:bg-gradient-to-br dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
    <div class="max-w-6xl mx-auto space-y-6">
      
      <!-- Header Card -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-upper-50 overflow-hidden dark:bg-slate-900/80 dark:border-slate-800 dark:shadow-[0_20px_45px_rgba(0,0,0,0.55)]">
        <div class="px-8 py-6 bg-[#1E1C8F]">
          <div class="flex items-center gap-4">
            <div class="bg-white bg-opacity-20 p-3 rounded-xl backdrop-blur-sm">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
              </svg>
            </div>
            <div>
              <h1 class="text-3xl font-bold text-white">Generar Orden de Trabajo</h1>
              <p class="text-white text-sm mt-1">{{ folio }}</p>
            </div>
          </div>
        </div>
  <div class="bg-upper-50 px-8 py-4 border-b border-upper-50 dark:border-slate-700/80">
    <div class="flex items-center gap-6 text-sm flex-wrap">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Servicio:</strong> {{ solicitud?.servicio?.nombre || solicitud?.id_servicio }}</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Centro:</strong> {{ solicitud?.centro?.nombre || solicitud?.id_centrotrabajo }}</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9h5l3 3v5l-9 9-8-8zM16 6h.01"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Centro de costos:</strong> {{ solicitud?.centroCosto?.nombre || '—' }}</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9h5l3 3v5l-9 9-8-8zM16 6h.01"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Marca:</strong> {{ solicitud?.marca?.nombre || '—' }}</span>
            </div>
            <div v-if="solicitud?.sku" class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>SKU:</strong> {{ solicitud.sku }}</span>
            </div>
            <div v-if="solicitud?.origen" class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.6 9h16.8M3.6 15h16.8"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Origen:</strong> {{ solicitud.origen }}</span>
            </div>
            <div v-if="solicitud?.pedimento" class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Pedimento:</strong> {{ solicitud.pedimento }}</span>
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
          
          <!-- Descripción General del Producto/Servicio -->
          <div v-if="descripcionGeneral" class="bg-upper-50 rounded-2xl shadow-lg border-2 border-upper-50 overflow-hidden dark:border-slate-700/80">
            <div class="p-6">
              <div class="flex items-start gap-4">
                <div class="bg-upper-50 p-3 rounded-xl flex-shrink-0 dark:bg-slate-900/60">
                  <svg class="w-6 h-6 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                  </svg>
                </div>
                <div class="flex-1">
                  <h3 class="text-sm font-bold text-[#1E1C8F] uppercase tracking-wide mb-2">Producto/Servicio Solicitado</h3>
                  <div class="flex items-baseline gap-3">
                    <p class="text-2xl font-bold text-[#1E1C8F]">{{ descripcionGeneral }}</p>
                    <span class="text-lg font-semibold text-[#1E1C8F]">— {{ cantidadTotal }} pz</span>
                  </div>
                    <p class="text-xs text-[#1E1C8F] mt-3" v-if="!usaTamanos">
                    💡 Puedes dividir esta cantidad en diferentes ítems con nombres específicos (marca, modelo, etc.)
                  </p>
                </div>
              </div>
            </div>
          </div>

          <form @submit.prevent="submit" class="space-y-6">
            
            <!-- Team Leader Section -->
            <div class="bg-white rounded-2xl shadow-lg border-2 border-indigo-100 overflow-hidden hover:shadow-xl transition-shadow duration-300 dark:bg-slate-900/75 dark:border-indigo-500/30">
              <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-6 py-4 dark:from-indigo-500 dark:to-blue-500">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                  Asignación de Team Leader
                </h2>
              </div>
              <div class="p-6">
                <!-- Área: coordinador seleccionará el área para la OT -->
                <div class="mb-4">
                  <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Área</label>
                  <select v-model="form.id_area" :disabled="areaBloqueada" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100 disabled:opacity-60 disabled:cursor-not-allowed">
                    <option :value="null">— Sin seleccionar —</option>
                    <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                  </select>
                  <p v-if="areaBloqueada" class="text-xs text-gray-500 mt-2 dark:text-slate-400">Área definida por el cliente (no editable).</p>
                  <p v-if="form.errors.id_area" class="text-red-600 text-sm mt-2">{{ form.errors.id_area }}</p>
                </div>
                <label class="block text-sm font-semibold text-gray-700 mb-3 dark:text-slate-200">
                  Seleccionar Team Leader
                  <span class="text-gray-400 font-normal ml-1 dark:text-slate-400">(Opcional)</span>
                </label>
                <div class="relative">
                  <select v-model="form.team_leader_id" 
                          class="w-full px-4 py-3 pr-10 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200 appearance-none bg-white text-gray-800 font-medium dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100 dark:focus:ring-indigo-400/40">
                    <option :value="null">— Sin asignar —</option>
                    <option v-for="u in teamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 dark:text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </div>
                </div>
                <p v-if="form.errors.team_leader_id" class="mt-2 text-sm text-red-600 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ form.errors.team_leader_id }}
                </p>
              </div>
            </div>

            <!-- Items Section -->
        <div class="bg-white rounded-2xl shadow-lg border-2 border-emerald-100 overflow-hidden hover:shadow-xl transition-shadow duration-300 dark:bg-slate-900/75 dark:border-emerald-500/30">
          <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4 dark:from-emerald-500 dark:to-teal-500">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                  </svg>
                  <span v-if="usaTamanos">Ítems por Tamaño</span>
                  <span v-else>Desglose de Ítems</span>
                </h2>
              </div>
              <div class="p-6 space-y-4">
                
                <!-- Botón para activar separación (solo para servicios SIN tamaños) -->
                <div v-if="!usaTamanos" class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl p-5 dark:from-blue-900/30 dark:to-indigo-900/30 dark:border-blue-500/40">
                  <div class="flex items-center justify-between">
                    <div class="flex-1">
                      <h3 class="text-lg font-bold text-blue-900 mb-1 dark:text-blue-100">
                        {{ separarItems ? '✓ Separación Activada' : '¿Deseas separar por ítems específicos?' }}
                      </h3>
                      <p class="text-sm text-blue-700 dark:text-blue-200">
                        {{ separarItems 
                          ? 'Puedes dividir la cantidad total en diferentes ítems (marcas, modelos, etc.)'
                          : 'Crea la OT con los datos generales o activa la separación para detallar ítems específicos'
                        }}
                      </p>
                    </div>
                    <button type="button" @click="toggleSeparacion"
                            class="ml-4 px-6 py-3 rounded-xl font-bold text-white transition-all duration-200 transform hover:scale-105 shadow-lg"
                            :class="separarItems 
                              ? 'bg-gradient-to-r from-red-500 to-pink-500 hover:from-red-600 hover:to-pink-600 dark:from-red-500 dark:to-rose-500' 
                              : 'bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 dark:from-blue-500 dark:to-indigo-500'">
                      <span class="flex items-center gap-2">
                        <svg v-if="!separarItems" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        {{ separarItems ? 'Desactivar' : 'Activar Separación' }}
                      </span>
                    </button>
                  </div>
                </div>
                
                <!-- INFO: Modo de operación (solo si está separado o tiene tamaños) -->
                <div v-if="usaTamanos || separarItems" class="space-y-4">
                <div v-if="usaTamanos" class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 dark:bg-blue-900/25 dark:border-blue-500/40">
                  <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5 dark:text-blue-300" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-blue-800 dark:text-blue-200">
                      <p class="font-semibold mb-1">📏 Servicio con Tamaños</p>
                      <p>Los ítems están predefinidos por tamaños desde la solicitud aprobada. Las cantidades no pueden modificarse.</p>
                    </div>
                  </div>
                </div>

                <div v-else class="bg-green-50 border-2 border-green-200 rounded-xl p-4 dark:bg-emerald-900/20 dark:border-emerald-500/40">
                  <div class="flex gap-3">
                    <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5 dark:text-emerald-300" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-green-800 dark:text-emerald-200">
                      <p class="font-semibold mb-1">📝 Separación de Ítems</p>
                      <p>Define los ítems específicos (marcas, modelos, variantes) que componen el total.</p>
                      <p class="mt-1"><strong>Validación:</strong> La suma de las cantidades debe ser <strong>{{ cantidadTotal }} pz</strong>.</p>
                    </div>
                  </div>
                </div>

                <!-- Contador de Cantidades (Solo para servicios SIN tamaños) -->
       <div v-if="!usaTamanos" class="sticky top-4 z-10 rounded-xl p-5 border-2 shadow-lg bg-white dark:bg-slate-900/80 dark:border-slate-700"
                     :class="{
                       'from-yellow-50 to-orange-50 border-yellow-300': alertaSuma === 'warning',
                       'from-red-50 to-pink-50 border-red-300': alertaSuma === 'error',
                       'from-emerald-50 to-teal-50 border-emerald-300': alertaSuma === 'success'
                     }">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-600 mb-1 dark:text-slate-300">Cantidad Total Aprobada</p>
                      <p class="text-3xl font-bold" :class="{
                        'text-yellow-700 dark:text-yellow-300': alertaSuma === 'warning',
                        'text-red-700 dark:text-rose-300': alertaSuma === 'error',
                        'text-emerald-700 dark:text-emerald-300': alertaSuma === 'success'
                      }">
                        {{ cantidadTotal }}
                      </p>
                    </div>
                    <div class="text-center px-6 py-3 bg-white bg-opacity-50 rounded-xl dark:bg-slate-900/60 dark:bg-opacity-80">
                      <p class="text-xs font-medium text-gray-600 mb-1 dark:text-slate-300">Suma Actual</p>
                      <p class="text-4xl font-bold" :class="{
                        'text-yellow-700 dark:text-yellow-300': alertaSuma === 'warning',
                        'text-red-700 dark:text-rose-300': alertaSuma === 'error',
                        'text-emerald-700 dark:text-emerald-300': alertaSuma === 'success'
                      }">
                        {{ sumaActual }}
                      </p>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-medium text-gray-600 mb-1 dark:text-slate-300">Restante</p>
                      <p class="text-3xl font-bold" :class="{
                        'text-yellow-700 dark:text-yellow-300': cantidadRestante > 0,
                        'text-red-700 dark:text-rose-300': cantidadRestante < 0,
                        'text-emerald-700 dark:text-emerald-300': cantidadRestante === 0
                      }">
                        {{ cantidadRestante }}
                      </p>
                    </div>
                  </div>
                  
                  <!-- Estado Visual -->
                  <div class="mt-4 pt-4 border-t-2" :class="{
                    'border-yellow-200 dark:border-yellow-400/40': alertaSuma === 'warning',
                    'border-red-200 dark:border-rose-400/40': alertaSuma === 'error',
                    'border-emerald-200 dark:border-emerald-400/40': alertaSuma === 'success'
                  }">
                    <div v-if="alertaSuma === 'warning'" class="flex items-center gap-2 text-yellow-800 dark:text-yellow-300">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                      </svg>
                      <p class="font-semibold text-sm">Faltan {{ cantidadRestante }} unidades por asignar</p>
                    </div>
                    <div v-else-if="alertaSuma === 'error'" class="flex items-center gap-2 text-red-800 dark:text-rose-300">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                      </svg>
                      <p class="font-semibold text-sm">¡Excede en {{ Math.abs(cantidadRestante) }} unidades!</p>
                    </div>
                    <div v-else class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300">
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
                       class="bg-gray-50 rounded-xl p-5 border-2 border-emerald-100 hover:border-emerald-300 transition-all duration-200 dark:bg-slate-900/60 dark:border-emerald-500/30 dark:hover:border-emerald-400/40">
                    
                    <div class="flex items-center justify-between mb-4">
                      <span class="text-sm font-bold text-emerald-700 bg-emerald-100 px-3 py-1 rounded-full dark:text-emerald-200 dark:bg-emerald-500/20">
                        Ítem #{{ i + 1 }}
                      </span>
                      <button v-if="!usaTamanos && separarItems && form.items.length > 1" 
                              type="button" 
                              @click="removeItem(i)"
                              class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-white bg-red-50 hover:bg-red-600 rounded-lg transition-all duration-200 flex items-center gap-1 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Eliminar
                      </button>
                    </div>

                    <!-- Servicios CON tamaños: campos bloqueados -->
                    <div v-if="usaTamanos" class="grid md:grid-cols-2 gap-4">
                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Tamaño</label>
                        <div class="px-4 py-3 bg-gray-100 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold uppercase dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100">
                          {{ it.tamano || 'N/A' }}
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Campo bloqueado (viene de la solicitud)</p>
                      </div>
                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Cantidad</label>
                        <div class="px-4 py-3 bg-gray-100 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100">
                          {{ it.cantidad }}
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Campo bloqueado (cantidad aprobada)</p>
                      </div>
                    </div>

                    <!-- Servicios SIN tamaños: campos editables -->
                    <div v-else class="space-y-4">
                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Descripción</label>
                        <textarea v-model="it.descripcion" 
                                  rows="2"
                                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400 transition-all duration-200 resize-none dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
                                  placeholder="Ej: Lenovo ThinkPad T480, Asus VivoBook 15, Dell Latitude 5400, etc."></textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Describe el trabajo o producto específico</p>
                      </div>
                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">
                          Cantidad
                          <span v-if="cantidadRestante > 0 && i === form.items.length - 1" class="text-green-600 font-normal ml-2">
                            (Disponible: {{ cantidadRestante + (parseInt(it.cantidad) || 0) }})
                          </span>
                        </label>
                        <div class="relative">
                               <input type="number" 
                                 min="1" 
                                 :max="cantidadTotal"
                                 v-model.number="it.cantidad" 
                                 class="w-full px-4 py-3 pl-10 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-400 transition-all duration-200 dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100 dark:placeholder-slate-400" 
                                 placeholder="0" />
                          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                            </svg>
                          </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Cantidad de este ítem específico</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Add Item Button (Solo para servicios SIN tamaños) -->
    <button v-if="!usaTamanos" 
                        type="button" 
                        @click="addItem"
                        :disabled="!puedeAgregarItem"
                        class="w-full px-5 py-3 border-2 border-dashed rounded-xl font-semibold transition-all duration-200 flex items-center justify-center gap-2 dark:text-slate-200"
                        :class="puedeAgregarItem 
                          ? 'bg-emerald-50 border-emerald-300 text-emerald-700 hover:bg-emerald-100 hover:border-emerald-400 dark:bg-emerald-500/10 dark:border-emerald-500/40 dark:text-emerald-200 dark:hover:border-emerald-400/60' 
                          : 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-500'">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                  </svg>
                  {{ puedeAgregarItem ? 'Agregar otro ítem' : 'No hay cantidad disponible para más ítems' }}
                </button>

                <!-- Errors -->
                 <div v-if="form.errors['items'] || form.errors['items.0.descripcion'] || form.errors['items.0.cantidad']" 
                   class="bg-red-50 border-2 border-red-200 rounded-xl p-4 dark:bg-rose-500/10 dark:border-rose-500/40">
                  <p v-if="form.errors['items']" class="text-sm text-red-600 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ form.errors['items'] }}
                  </p>
                  <p v-if="form.errors['items.0.descripcion']" class="text-sm text-red-600 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ form.errors['items.0.descripcion'] }}
                  </p>
                  <p v-if="form.errors['items.0.cantidad']" class="text-sm text-red-600 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ form.errors['items.0.cantidad'] }}
                  </p>
                </div>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="bg-white rounded-2xl shadow-lg border-2 border-upper-50 overflow-hidden dark:bg-slate-900/75 dark:border-slate-700/80">
              <div class="p-6">
                <button type="submit" 
                        :disabled="form.processing || (!usaTamanos && alertaSuma !== 'success')"
                        class="w-full px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold text-lg rounded-xl shadow-xl hover:shadow-2xl hover:scale-105 transform transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center justify-center gap-3 dark:from-blue-500 dark:to-indigo-500">
                  <svg v-if="!form.processing" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <svg v-else class="w-6 h-6 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                  </svg>
                  {{ form.processing ? 'Creando Orden de Trabajo...' : 'Crear Orden de Trabajo' }}
                </button>
                
                <!-- Mensaje de ayuda si no puede enviar -->
                <div v-if="!usaTamanos && separarItems && alertaSuma !== 'success' && !form.processing" class="mt-3 bg-yellow-50 border-2 border-yellow-200 rounded-xl p-3 dark:bg-amber-500/10 dark:border-amber-500/40">
                  <p class="text-sm text-yellow-800 flex items-center gap-2 dark:text-amber-200">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>Ajusta las cantidades para que sumen exactamente <strong>{{ cantidadTotal }}</strong> unidades</span>
                  </p>
                </div>
              </div>
            </div>

            <!-- Success Message -->
            <div v-if="$page.props?.flash?.ok" 
                 class="bg-gradient-to-r from-emerald-50 to-teal-50 border-2 border-emerald-300 rounded-2xl p-5 flex items-center gap-3 dark:from-emerald-500/10 dark:to-teal-500/10 dark:border-emerald-500/40">
              <div class="bg-emerald-500 p-2 rounded-full dark:bg-emerald-400/80">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
              </div>
              <p class="text-emerald-800 font-semibold dark:text-emerald-200">{{ $page.props.flash.ok }}</p>
            </div>
          </form>
        </div>

        <!-- Right Column: Cotización -->
        <div class="lg:col-span-1">
          <div v-if="cotizacion?.lines?.length" class="bg-white rounded-2xl shadow-lg border-2 border-orange-100 overflow-hidden sticky top-6 hover:shadow-xl transition-shadow duration-300 dark:bg-slate-900/80 dark:border-orange-500/40">
            <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-6 py-4 dark:from-orange-500 dark:to-amber-500">
              <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Cotización de Referencia
              </h2>
            </div>
            
            <div class="p-5">
              <!-- Items -->
              <div class="space-y-3 mb-5">
       <div v-for="(l,i) in cotizacion.lines" :key="i" 
         class="bg-gradient-to-br from-orange-50 to-amber-50 rounded-xl p-4 border border-orange-100 dark:from-amber-500/10 dark:to-orange-500/10 dark:border-orange-500/40">
                  <div class="font-semibold text-gray-800 mb-3 dark:text-amber-200">{{ l.label }}</div>
                  <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center">
                      <span class="text-gray-600 dark:text-slate-300">Cantidad:</span>
                      <span class="font-bold text-gray-800 dark:text-amber-100">{{ l.cantidad }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="text-gray-600 dark:text-slate-300">P. Unitario:</span>
                      <span class="font-bold text-orange-600 dark:text-orange-300">${{ (l.pu||0).toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-orange-200 dark:border-orange-500/40">
                      <span class="text-gray-700 font-medium dark:text-slate-200">Subtotal:</span>
                      <span class="font-bold text-gray-800 dark:text-amber-100">${{ (l.subtotal||0).toFixed(2) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Totales -->
              <div class="border-t-2 border-orange-300 pt-4 space-y-3 dark:border-orange-500/40">
                <div class="flex justify-between items-center">
                  <span class="text-gray-600 font-medium dark:text-slate-300">Subtotal:</span>
                  <span class="font-bold text-gray-800 text-lg dark:text-amber-100">${{ (cotizacion.subtotal||0).toFixed(2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600 font-medium dark:text-slate-300">IVA ({{ ((cotizacion.iva_rate||0)*100).toFixed(0) }}%):</span>
                  <span class="font-bold text-gray-800 text-lg dark:text-amber-100">${{ (cotizacion.iva||0).toFixed(2) }}</span>
                </div>
                <div class="bg-gradient-to-r from-orange-600 to-amber-600 rounded-xl px-5 py-4 flex justify-between items-center shadow-lg dark:from-orange-500 dark:to-amber-500">
                  <span class="text-white font-bold text-lg">Total:</span>
                  <span class="text-white font-bold text-2xl">${{ (cotizacion.total||0).toFixed(2) }}</span>
                </div>
              </div>

              <!-- Info Note -->
              <div class="mt-4 bg-blue-50 border-2 border-blue-200 rounded-xl p-4 dark:bg-blue-900/20 dark:border-blue-500/40">
                <div class="flex gap-2">
                  <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5 dark:text-blue-300" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                  </svg>
                  <p class="text-sm text-blue-800 dark:text-blue-200">
                    Esta cotización es de <strong>referencia</strong>. Los montos finales se calcularán según los ítems agregados.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
