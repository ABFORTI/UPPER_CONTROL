<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  centros: Array,
  servicios: Array,
  teamLeaders: Array,
  clientes: Array,
  canChooseCentro: Boolean,
  defaultCentroId: Number,
})

const form = useForm({
  header: {
    centro_trabajo_id: props.defaultCentroId || null,
    centro_costos_id: null,
    marca_id: null,
    area_id: null,
    descripcion_producto: '',
    cliente_id: null,
    team_leader_id: null,
  },
  servicios: [
    {
      servicio_id: null,
      tipo_cobro: 'pieza',
      cantidad: 1,
      precio_unitario: 0,
    }
  ]
})

// Computed: Calcular subtotal por servicio
const calcularSubtotalServicio = (servicio) => {
  return (servicio.cantidad || 0) * (servicio.precio_unitario || 0)
}

// Computed: Subtotal total de todos los servicios
const subtotalOT = computed(() => {
  return form.servicios.reduce((sum, s) => sum + calcularSubtotalServicio(s), 0)
})

// Computed: IVA (16%)
const ivaOT = computed(() => {
  return subtotalOT.value * 0.16
})

// Computed: Total OT
const totalOT = computed(() => {
  return subtotalOT.value + ivaOT.value
})

// Funciones
function agregarServicio() {
  form.servicios.push({
    servicio_id: null,
    tipo_cobro: 'pieza',
    cantidad: 1,
    precio_unitario: 0,
  })
}

function eliminarServicio(index) {
  if (form.servicios.length > 1) {
    form.servicios.splice(index, 1)
  }
}

function submit() {
  form.post(route('ot-multi-servicio.store'), {
    preserveScroll: true,
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-purple-50 py-8 px-4 sm:px-6 lg:px-8 dark:bg-gradient-to-br dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
    <div class="max-w-7xl mx-auto">
      
      <!-- Header -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-indigo-100 overflow-hidden mb-6 dark:bg-slate-900/80 dark:border-slate-800">
        <div class="px-8 py-6 bg-gradient-to-r from-indigo-600 to-purple-600">
          <div class="flex items-center gap-4">
            <div class="bg-white bg-opacity-20 p-3 rounded-xl backdrop-blur-sm">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
            </div>
            <div>
              <h1 class="text-3xl font-bold text-white">Nueva Solicitud de Servicio</h1>
              <p class="text-indigo-100 text-sm mt-1">OT con múltiples servicios</p>
            </div>
          </div>
        </div>
      </div>

      <form @submit.prevent="submit">
        <div class="grid lg:grid-cols-3 gap-6">
          
          <!-- Columna Izquierda: Formulario -->
          <div class="lg:col-span-2 space-y-6">
            
            <!-- Datos Generales -->
            <div class="bg-white rounded-2xl shadow-lg border-2 border-indigo-100 overflow-hidden dark:bg-slate-900/75 dark:border-indigo-500/30">
              <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Datos Generales
                </h2>
              </div>
              <div class="p-6 space-y-4">
                
                <!-- Centro de Trabajo -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">
                    Centro de Trabajo <span class="text-red-500">*</span>
                  </label>
                  <select v-model="form.header.centro_trabajo_id" 
                          :disabled="!canChooseCentro"
                          class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100 focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 disabled:opacity-60">
                    <option :value="null">Seleccionar centro</option>
                    <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                  </select>
                  <p v-if="form.errors['header.centro_trabajo_id']" class="text-red-600 text-sm mt-2">
                    {{ form.errors['header.centro_trabajo_id'] }}
                  </p>
                </div>

                <!-- Descripción del Producto -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">
                    Descripción del Producto/Servicio <span class="text-red-500">*</span>
                  </label>
                  <textarea v-model="form.header.descripcion_producto"
                            rows="3"
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100 focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400"
                            placeholder="Describe el producto o servicio..."></textarea>
                  <p v-if="form.errors['header.descripcion_producto']" class="text-red-600 text-sm mt-2">
                    {{ form.errors['header.descripcion_producto'] }}
                  </p>
                </div>

                <!-- Campos opcionales en grid -->
                <div class="grid md:grid-cols-2 gap-4">
                  <!-- Cliente -->
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Cliente</label>
                    <select v-model="form.header.cliente_id"
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100">
                      <option :value="null">— Sin cliente —</option>
                      <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                  </div>

                  <!-- Team Leader -->
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Team Leader</label>
                    <select v-model="form.header.team_leader_id"
                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100">
                      <option :value="null">— Sin asignar —</option>
                      <option v-for="tl in teamLeaders" :key="tl.id" :value="tl.id">{{ tl.name }}</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- Servicios (Repeater) -->
            <div class="bg-white rounded-2xl shadow-lg border-2 border-emerald-100 overflow-hidden dark:bg-slate-900/75 dark:border-emerald-500/30">
              <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
                <div class="flex items-center justify-between">
                  <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Servicios
                  </h2>
                  <button type="button" @click="agregarServicio"
                          class="px-4 py-2 bg-white text-emerald-600 rounded-lg font-bold hover:bg-emerald-50 transition-colors">
                    <span class="flex items-center gap-2">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                      </svg>
                      Agregar Servicio
                    </span>
                  </button>
                </div>
              </div>
              
              <div class="p-6 space-y-4">
                <!-- Cards de servicios -->
                <div v-for="(servicio, index) in form.servicios" :key="index"
                     class="bg-gradient-to-br from-gray-50 to-white border-2 border-gray-200 rounded-xl p-5 dark:from-slate-800/50 dark:to-slate-900/50 dark:border-slate-700">
                  
                  <!-- Header del servicio -->
                  <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-slate-200">
                      Servicio #{{ index + 1 }}
                    </h3>
                    <button v-if="form.servicios.length > 1" 
                            type="button" 
                            @click="eliminarServicio(index)"
                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors dark:hover:bg-red-900/20">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>

                  <!-- Campos del servicio -->
                  <div class="space-y-3">
                    <!-- Tipo de Servicio -->
                    <div>
                      <label class="block text-sm font-semibold text-gray-700 mb-1 dark:text-slate-300">
                        Tipo de Servicio <span class="text-red-500">*</span>
                      </label>
                      <select v-model="servicio.servicio_id"
                              class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                        <option :value="null">Seleccionar servicio</option>
                        <option v-for="s in servicios" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                      </select>
                      <p v-if="form.errors[`servicios.${index}.servicio_id`]" class="text-red-600 text-sm mt-1">
                        {{ form.errors[`servicios.${index}.servicio_id`] }}
                      </p>
                    </div>

                    <!-- Grid: Tipo de Cobro, Cantidad, Precio -->
                    <div class="grid grid-cols-3 gap-3">
                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1 dark:text-slate-300">
                          Tipo de Cobro <span class="text-red-500">*</span>
                        </label>
                        <select v-model="servicio.tipo_cobro"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                          <option value="pieza">Pieza</option>
                          <option value="pallet">Pallet</option>
                          <option value="hora">Hora</option>
                          <option value="kg">Kg</option>
                          <option value="m2">M²</option>
                          <option value="otro">Otro</option>
                        </select>
                      </div>

                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1 dark:text-slate-300">
                          Cantidad <span class="text-red-500">*</span>
                        </label>
                        <input type="number" v-model.number="servicio.cantidad" min="1"
                               class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                        <p v-if="form.errors[`servicios.${index}.cantidad`]" class="text-red-600 text-sm mt-1">
                          {{ form.errors[`servicios.${index}.cantidad`] }}
                        </p>
                      </div>

                      <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1 dark:text-slate-300">
                          Precio Unit. <span class="text-red-500">*</span>
                        </label>
                        <input type="number" v-model.number="servicio.precio_unitario" min="0" step="0.01"
                               class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                        <p v-if="form.errors[`servicios.${index}.precio_unitario`]" class="text-red-600 text-sm mt-1">
                          {{ form.errors[`servicios.${index}.precio_unitario`] }}
                        </p>
                      </div>
                    </div>

                    <!-- Subtotal del servicio -->
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 dark:bg-emerald-900/20 dark:border-emerald-500/40">
                      <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Subtotal Servicio:</span>
                        <span class="text-lg font-bold text-emerald-700 dark:text-emerald-300">
                          ${{ calcularSubtotalServicio(servicio).toFixed(2) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Mensaje si no hay servicios -->
                <div v-if="form.servicios.length === 0" class="text-center py-8 text-gray-500">
                  <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                  </svg>
                  <p>No hay servicios agregados</p>
                </div>

                <p v-if="form.errors.servicios" class="text-red-600 text-sm">
                  {{ form.errors.servicios }}
                </p>
              </div>
            </div>

          </div>

          <!-- Columna Derecha: Resumen -->
          <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-lg border-2 border-purple-100 overflow-hidden sticky top-6 dark:bg-slate-900/75 dark:border-purple-500/30">
              <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                  </svg>
                  Resumen de OT
                </h2>
              </div>
              <div class="p-6 space-y-4">
                
                <!-- Contadores -->
                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-4 dark:from-indigo-900/20 dark:to-purple-900/20">
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-600 dark:text-slate-300">Servicios agregados:</span>
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ form.servicios.length }}</span>
                  </div>
                </div>

                <!-- Totales -->
                <div class="space-y-3 pt-4 border-t-2 border-gray-200 dark:border-slate-700">
                  <div class="flex justify-between items-center">
                    <span class="text-gray-600 font-medium dark:text-slate-300">Subtotal:</span>
                    <span class="text-xl font-bold text-gray-800 dark:text-slate-100">${{ subtotalOT.toFixed(2) }}</span>
                  </div>
                  <div class="flex justify-between items-center">
                    <span class="text-gray-600 font-medium dark:text-slate-300">IVA (16%):</span>
                    <span class="text-xl font-bold text-gray-800 dark:text-slate-100">${{ ivaOT.toFixed(2) }}</span>
                  </div>
                  <div class="pt-3 border-t-2 border-gray-300 dark:border-slate-600">
                    <div class="flex justify-between items-center">
                      <span class="text-lg font-bold text-gray-700 dark:text-slate-200">Total:</span>
                      <span class="text-3xl font-bold text-purple-600 dark:text-purple-400">${{ totalOT.toFixed(2) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Botón Submit -->
                <button type="submit" :disabled="form.processing"
                        class="w-full mt-6 py-4 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-bold rounded-xl hover:from-purple-700 hover:to-pink-700 transition-all duration-200 transform hover:scale-[1.02] shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                  <span class="flex items-center justify-center gap-2">
                    <svg v-if="!form.processing" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg v-else class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ form.processing ? 'Creando...' : 'Crear Orden de Trabajo' }}
                  </span>
                </button>
              </div>
            </div>
          </div>

        </div>
      </form>

    </div>
  </div>
</template>
