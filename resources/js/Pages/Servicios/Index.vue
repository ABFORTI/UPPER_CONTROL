<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  centros:  { type: Array,  required: true },
  centroId: { type: Number, required: true },
  rows:     { type: Array,  required: true },
  urls:     { type: Object, required: true },
})

const selCentro = ref(props.centroId)
function changeCentro(){
  const q = new URLSearchParams({ centro: String(selCentro.value) })
  router.get(`${props.urls.index}?${q.toString()}`, {}, { preserveState: false, replace: true })
}

const flashOk = computed(()=> usePage().props?.flash?.ok ?? null)

// Ya no hay formulario aquí; se mueve a Servicios/Create

// Guardar una fila (precios de un servicio) para el centro seleccionado
const saving = ref({})
function saveRow(r){
  const form = useForm({})
  const payload = {
    id_centro: selCentro.value,
    items: [ r.usa_tamanos
      ? { id_servicio: r.id_servicio, usa_tamanos: true, tamanos: {
          chico: Number(r._chico ?? r.tamanos?.chico ?? 0),
          mediano: Number(r._mediano ?? r.tamanos?.mediano ?? 0),
          grande: Number(r._grande ?? r.tamanos?.grande ?? 0),
          jumbo: Number(r._jumbo ?? r.tamanos?.jumbo ?? 0),
        } }
      : { id_servicio: r.id_servicio, usa_tamanos: false, precio_base: Number(r._unitario ?? r.precio_base ?? 0) }
    ]
  }
  saving.value[r.id_servicio] = true
  form.transform(() => payload).post(props.urls.guardar, {
    preserveScroll: true,
    onFinish: () => { saving.value[r.id_servicio] = false },
  })
}

// Formulario para clonar servicios desde otro centro al seleccionado
const cloneForm = useForm({ centro_origen: null })
const otherCentros = computed(() => (props.centros || []).filter(c => Number(c.id) !== Number(selCentro.value)))
watch(otherCentros, (list) => { if (!cloneForm.centro_origen && list?.length) cloneForm.centro_origen = list[0].id }, { immediate: true })
function doClone(){
  if (!cloneForm.centro_origen) return
  cloneForm.transform(() => ({
    centro_origen: Number(cloneForm.centro_origen),
    centro_destino: Number(selCentro.value),
  })).post(props.urls.clonar, { preserveScroll: true })
}

// Eliminar un servicio del centro actual
function removeRow(r){
  if (!confirm(`¿Eliminar "${r.servicio}" de este centro?`)) return
  const form = useForm({})
  form.transform(() => ({ id_centro: Number(selCentro.value), id_servicio: Number(r.id_servicio) }))
      .post(props.urls.eliminar, { preserveScroll: true })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-emerald-50 to-teal-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
      
      <!-- Header con selector de centro -->
      <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl shadow-xl p-8 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-extrabold text-white flex items-center gap-3">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
              SERVICIOS
            </h1>
            <p class="text-emerald-100 mt-2">Gestiona los servicios y precios por centro de trabajo</p>
          </div>
          <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-xl p-4 border-2 border-white border-opacity-30">
            <label class="block text-sm font-semibold text-white mb-2">Centro de trabajo</label>
            <select v-model.number="selCentro" 
                    @change="changeCentro" 
                    class="px-4 py-2.5 rounded-lg border-2 border-emerald-200 min-w-[14rem] bg-white font-semibold text-gray-700 focus:ring-4 focus:ring-emerald-200 transition-all duration-200">
              <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Mensaje flash de éxito -->
      <div v-if="flashOk" 
           class="mb-6 rounded-xl border-2 border-emerald-300 bg-emerald-50 text-emerald-800 px-6 py-4 flex items-center gap-3 shadow-md">
        <svg class="w-6 h-6 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="font-semibold">{{ flashOk }}</span>
      </div>

      <!-- Botón Agregar servicio -->
      <div class="mb-6 flex justify-end">
        <a :href="urls.create" 
           class="px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 font-bold text-white hover:shadow-xl transition-all duration-200 transform hover:scale-105 flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
          </svg>
          Agregar servicio
        </a>
      </div>

      <!-- Estado vacío -->
      <div v-if="!rows?.length" class="bg-white rounded-2xl shadow-xl border-2 border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
          <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
            No hay servicios en este centro
          </h2>
        </div>
        <div class="p-8">
          <div class="text-center py-12">
            <svg class="w-24 h-24 mx-auto text-gray-300 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
            <p class="text-xl text-gray-600 mb-8">Empieza agregando un servicio o clona desde otro centro</p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center max-w-2xl mx-auto">
              <a :href="urls.create" 
                 class="px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 font-bold text-white hover:shadow-xl transition-all duration-200 transform hover:scale-105 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Agregar servicio
              </a>
              
              <div v-if="otherCentros.length" class="flex items-center gap-3 bg-gray-50 px-6 py-3 rounded-xl border-2 border-gray-200">
                <span class="text-sm font-semibold text-gray-700">o clonar desde:</span>
                <select v-model.number="cloneForm.centro_origen" 
                        class="px-3 py-2 rounded-lg border-2 border-gray-300 bg-white font-semibold text-gray-700">
                  <option v-for="c in otherCentros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
                <button @click="doClone" 
                        :disabled="cloneForm.processing || !cloneForm.centro_origen"
                        class="px-4 py-2 rounded-lg bg-gray-700 text-white font-semibold hover:bg-gray-800 disabled:opacity-50 transition-all duration-200">
                  {{ cloneForm.processing ? 'Clonando…' : 'Clonar' }}
                </button>
              </div>
            </div>
            
            <p v-if="cloneForm.errors.centro_origen" class="text-red-600 text-sm mt-4 flex items-center justify-center gap-1">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
              </svg>
              {{ cloneForm.errors.centro_origen }}
            </p>
          </div>
        </div>
      </div>

      <!-- Tabla de servicios -->
      <div v-if="rows?.length" class="bg-white rounded-2xl shadow-xl border-2 border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
          <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Precios por servicio
          </h2>
        </div>
        
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Servicio</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tipo</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Unitario</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Chico</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Mediano</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Grande</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Jumbo</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Acciones</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="r in rows" :key="r.id_servicio" class="hover:bg-emerald-50 transition-colors duration-150">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="font-semibold text-gray-900">{{ r.servicio }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span v-if="r.usa_tamanos" 
                        class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 border border-gray-300">
                    Por tamaños
                  </span>
                  <span v-else 
                        class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300">
                    Unitario
                  </span>
                </td>
                <td class="px-6 py-4 text-right">
                  <template v-if="!r.usa_tamanos">
                    <input type="number" 
                           step="0.01" 
                           min="0" 
                           v-model.number="r._unitario" 
                           class="w-32 px-3 py-2 border-2 border-gray-200 rounded-lg text-right font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition-all duration-200" />
                  </template>
                  <span v-else class="text-gray-400 font-medium">—</span>
                </td>
                <template v-if="r.usa_tamanos">
                  <td class="px-6 py-4 text-right">
                    <input type="number" step="0.01" min="0" v-model.number="r._chico" 
                           class="w-28 px-3 py-2 border-2 border-gray-200 rounded-lg text-right font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition-all duration-200" />
                  </td>
                  <td class="px-6 py-4 text-right">
                    <input type="number" step="0.01" min="0" v-model.number="r._mediano" 
                           class="w-28 px-3 py-2 border-2 border-gray-200 rounded-lg text-right font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition-all duration-200" />
                  </td>
                  <td class="px-6 py-4 text-right">
                    <input type="number" step="0.01" min="0" v-model.number="r._grande" 
                           class="w-28 px-3 py-2 border-2 border-gray-200 rounded-lg text-right font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition-all duration-200" />
                  </td>
                  <td class="px-6 py-4 text-right">
                    <input type="number" step="0.01" min="0" v-model.number="r._jumbo" 
                           class="w-28 px-3 py-2 border-2 border-gray-200 rounded-lg text-right font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition-all duration-200" />
                  </td>
                </template>
                <template v-else>
                  <td class="px-6 py-4 text-center text-gray-400 font-medium">—</td>
                  <td class="px-6 py-4 text-center text-gray-400 font-medium">—</td>
                  <td class="px-6 py-4 text-center text-gray-400 font-medium">—</td>
                  <td class="px-6 py-4 text-center text-gray-400 font-medium">—</td>
                </template>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex gap-2">
                    <button @click="saveRow(r)" 
                            :disabled="saving[r.id_servicio]"
                            class="px-4 py-2 rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 font-semibold text-white hover:shadow-lg transition-all duration-200 disabled:opacity-50 flex items-center gap-1">
                      <svg v-if="!saving[r.id_servicio]" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                      </svg>
                      <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      {{ saving[r.id_servicio] ? 'Guardando…' : 'Guardar' }}
                    </button>
                    <button @click="removeRow(r)" 
                            class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 font-semibold text-white transition-all duration-200 flex items-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                      Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Barra de clonación (cuando hay servicios) -->
      <div v-if="rows?.length && otherCentros.length" 
           class="mt-6 bg-white rounded-xl shadow-lg border-2 border-gray-100 p-6">
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <span class="text-sm font-semibold text-gray-700">Clonar servicios desde:</span>
          </div>
          <select v-model.number="cloneForm.centro_origen" 
                  class="px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white font-semibold text-gray-700 focus:ring-2 focus:ring-emerald-200 min-w-[12rem]">
            <option v-for="c in otherCentros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
          <button @click="doClone" 
                  :disabled="cloneForm.processing || !cloneForm.centro_origen"
                  class="px-6 py-2.5 rounded-lg bg-gray-700 text-white font-semibold hover:bg-gray-800 disabled:opacity-50 transition-all duration-200 flex items-center gap-2">
            <svg v-if="!cloneForm.processing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ cloneForm.processing ? 'Clonando…' : 'Clonar (solo faltantes)' }}
          </button>
        </div>
        <p v-if="cloneForm.errors.centro_origen" class="text-red-600 text-sm mt-3 text-center flex items-center justify-center gap-1">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
          {{ cloneForm.errors.centro_origen }}
        </p>
      </div>

    </div>
  </div>
</template>

<!-- script adicional eliminado; todo vive en <script setup> -->
