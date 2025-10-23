<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import FilePreview from '@/Components/FilePreview.vue'

const props = defineProps({ solicitud: Object, can: Object, urls: Object, flags: Object, cotizacion: Object })

function aprobar()  { router.post(props.urls.aprobar) }

// Rechazo con motivo: abrimos modal y enviamos motivo por POST
const showRechazoModal = ref(false)
const motivoRechazo = ref('')

function rechazar() {
  // abrir modal
  motivoRechazo.value = ''
  showRechazoModal.value = true
}

function submitRechazo() {
  if (!motivoRechazo.value || motivoRechazo.value.trim().length < 3) {
    return; // aquí podríamos mostrar validación simple
  }
  router.post(props.urls.rechazar, { motivo: motivoRechazo.value })
}

// Modal de previsualización
const showPreviewModal = ref(false)
const previewFile = ref(null)

function openPreview(archivo) {
  previewFile.value = archivo
  showPreviewModal.value = true
}

function closePreview() {
  showPreviewModal.value = false
  previewFile.value = null
}

function canPreview(mime) {
  return mime?.startsWith('image/') || mime === 'application/pdf'
}
</script>


<template>
  <div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto space-y-6">
      
      <!-- Header Card -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-upper-50 overflow-hidden">
  <div class="px-6 py-5 bg-[#1E1C8F]">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-3xl font-bold text-white mb-1">{{ solicitud.folio }}</h1>
              <p class="text-white text-sm">Revisión de Solicitud</p>
            </div>
            <!-- Status Badge -->
            <div class="flex items-center gap-2">
              <span v-if="solicitud.estatus === 'pendiente'" 
                    class="px-4 py-2 rounded-xl bg-yellow-500 bg-opacity-20 text-yellow-100 font-semibold text-sm backdrop-blur-sm border border-yellow-300 border-opacity-30">
                <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
                Pendiente
              </span>
              <span v-else-if="solicitud.estatus === 'aprobada'" 
                    class="px-4 py-2 rounded-xl bg-green-500 bg-opacity-20 text-green-100 font-semibold text-sm backdrop-blur-sm border border-green-300 border-opacity-30">
                <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Aprobada
              </span>
              <span v-else-if="solicitud.estatus === 'rechazada'" 
                    class="px-4 py-2 rounded-xl bg-red-500 bg-opacity-20 text-red-100 font-semibold text-sm backdrop-blur-sm border border-red-300 border-opacity-30">
                <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                Rechazada
              </span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
  <div v-if="can?.aprobar" class="px-6 py-4 border-b border-upper-50 bg-white">
          <div class="flex gap-3">
      <button @click="aprobar" 
        class="flex-1 px-6 py-3 rounded-xl bg-emerald-600 text-white font-semibold shadow-lg hover:bg-emerald-700 hover:shadow-xl hover:scale-105 transform transition-all duration-200 flex items-center justify-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Aprobar Solicitud
            </button>
      <button @click="rechazar" 
        class="flex-1 px-6 py-3 rounded-xl bg-red-600 text-white font-semibold shadow-lg hover:bg-red-700 hover:shadow-xl hover:scale-105 transform transition-all duration-200 flex items-center justify-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Rechazar Solicitud
            </button>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Detalles de la Solicitud -->
        <div class="lg:col-span-2 space-y-6">
          
          <!-- Información General -->
          <div class="bg-white rounded-2xl shadow-lg border-2 border-upper-50 overflow-hidden hover:shadow-xl transition-shadow duration-300">
            <div class="px-6 py-4 bg-[#1E1C8F]">
              <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Información General
              </h2>
            </div>
            <div class="p-6 space-y-4">
              <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-upper-50 rounded-xl p-4 border border-upper-50">
                  <label class="text-xs font-semibold text-[#1E1C8F] uppercase tracking-wide">Servicio</label>
                  <p class="mt-1 text-lg font-bold text-gray-800">{{ solicitud.servicio?.nombre }}</p>
                </div>
                <div class="bg-upper-50 rounded-xl p-4 border border-upper-50">
                  <label class="text-xs font-semibold text-[#1E1C8F] uppercase tracking-wide">Centro de Trabajo</label>
                  <p class="mt-1 text-lg font-bold text-gray-800">{{ solicitud.centro?.nombre }}</p>
                </div>
              </div>
              
              <div v-if="solicitud.area" class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-xl p-4 border border-emerald-100">
                <label class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Área</label>
                <p class="mt-1 text-lg font-bold text-gray-800">{{ solicitud.area.nombre }}</p>
              </div>

              <div class="grid md:grid-cols-2 gap-4">
                <div v-if="solicitud.tamano" class="bg-gradient-to-br from-orange-50 to-amber-50 rounded-xl p-4 border border-orange-100">
                  <label class="text-xs font-semibold text-orange-600 uppercase tracking-wide">Tamaño</label>
                  <p class="mt-1 text-lg font-bold text-gray-800 capitalize">{{ solicitud.tamano }}</p>
                </div>
                <div class="bg-gradient-to-br from-cyan-50 to-blue-50 rounded-xl p-4 border border-cyan-100">
                  <label class="text-xs font-semibold text-cyan-600 uppercase tracking-wide">Cantidad</label>
                  <p class="mt-1 text-lg font-bold text-gray-800">{{ solicitud.cantidad }}</p>
                </div>
              </div>

              <div v-if="solicitud.descripcion" class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Descripción</label>
                <p class="mt-2 text-gray-700 leading-relaxed">{{ solicitud.descripcion }}</p>
              </div>

              <div v-if="solicitud.notas" class="bg-amber-50 rounded-xl p-4 border border-amber-200">
                <label class="text-xs font-semibold text-amber-600 uppercase tracking-wide flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                  </svg>
                  Notas Adicionales
                </label>
                <p class="mt-2 text-gray-700 leading-relaxed">{{ solicitud.notas }}</p>
              </div>

              <div v-if="solicitud.motivo_rechazo" class="bg-red-50 rounded-xl p-4 border border-red-200">
                <label class="text-xs font-semibold text-red-600 uppercase tracking-wide flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                  </svg>
                  Motivo del Rechazo
                </label>
                <p class="mt-2 text-gray-700 leading-relaxed whitespace-pre-wrap">{{ solicitud.motivo_rechazo }}</p>
              </div>
            </div>
          </div>

          <!-- Desglose por tamaño -->
          <div v-if="solicitud.tamanos?.length" class="bg-white rounded-2xl shadow-lg border-2 border-upper-50 overflow-hidden hover:shadow-xl transition-shadow duration-300">
            <div class="px-6 py-4 bg-[#1E1C8F]">
              <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Desglose por Tamaño
              </h2>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full">
                <thead class="bg-upper-50">
                  <tr>
                    <th class="text-left px-6 py-4 text-sm font-bold text-[#1E1C8F] uppercase tracking-wider">Tamaño</th>
                    <th class="text-right px-6 py-4 text-sm font-bold text-[#1E1C8F] uppercase tracking-wider">Cantidad</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="t in solicitud.tamanos" :key="t.id" class="hover:bg-upper-50 transition-colors duration-150">
                    <td class="px-6 py-4 text-gray-800 font-medium capitalize">{{ t.tamano }}</td>
                    <td class="px-6 py-4 text-right text-gray-800 font-bold">{{ t.cantidad }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Archivos Adjuntos -->
          <div v-if="solicitud.archivos?.length" class="bg-white rounded-2xl shadow-lg border-2 border-indigo-100 overflow-hidden hover:shadow-xl transition-shadow duration-300">
            <div class="px-6 py-4 bg-[#1E1C8F]">
              <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                Archivos Adjuntos
              </h2>
            </div>
            <div class="p-4 space-y-2">
           <div v-for="archivo in solicitud.archivos" :key="archivo.id" 
             class="flex items-center justify-between p-4 bg-white rounded-xl border border-gray-200 hover:border-upper-200 hover:shadow-md transition-all duration-200">
                <div class="flex items-center gap-4">
                  <div class="p-3 rounded-xl bg-[#1E1C8F]">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div>
                    <div class="font-semibold text-gray-800">{{ archivo.nombre_original || archivo.path?.split('/').pop() || 'Archivo' }}</div>
                    <div class="text-sm text-gray-500 flex items-center gap-2 mt-1">
                      <span class="px-2 py-0.5 bg-gray-200 rounded text-xs font-medium">
                        {{ archivo.size ? (archivo.size / 1024).toFixed(0) : '0' }} KB
                      </span>
                      <span v-if="archivo.mime" class="text-xs">{{ archivo.mime }}</span>
                    </div>
                  </div>
                </div>
                <div class="flex gap-2">
      <button v-if="canPreview(archivo.mime)"
        @click="openPreview(archivo)"
        class="px-4 py-2 rounded-xl bg-gray-600 text-white font-medium hover:bg-gray-700 shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver
                  </button>
            <a :href="route('archivos.download', archivo.id)" 
              class="px-4 py-2 rounded-xl bg-[#1E1C8F] text-white font-medium hover:opacity-95 shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Cotización & Acciones -->
        <div class="lg:col-span-1 space-y-6">
          
          <!-- Cotización Sticky Card -->
          <div class="bg-white rounded-2xl shadow-lg border-2 border-emerald-100 overflow-hidden sticky top-6 hover:shadow-xl transition-shadow duration-300">
            <div class="px-6 py-4 bg-[#1E1C8F]">
              <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Cotización
              </h2>
            </div>
            
            <div v-if="cotizacion?.lines?.length" class="p-5">
              <!-- Items -->
              <div class="space-y-3 mb-4">
       <div v-for="(l,i) in cotizacion.lines" :key="i" 
         class="bg-gray-50 rounded-xl p-4 border border-upper-50">
                  <div class="font-semibold text-gray-800 mb-2">{{ l.label }}</div>
                  <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-gray-600">Cantidad:</div>
                    <div class="text-right font-bold text-gray-800">{{ l.cantidad }}</div>
                    <div class="text-gray-600">P. Unitario:</div>
                    <div class="text-right font-bold text-[#1E1C8F]">${{ (l.pu||0).toFixed(2) }}</div>
                    <div class="text-gray-600">Subtotal:</div>
                    <div class="text-right font-bold text-gray-800">${{ (l.subtotal||0).toFixed(2) }}</div>
                  </div>
                </div>
              </div>

              <!-- Totales -->
              <div class="border-t-2 border-upper-50 pt-4 space-y-3">
                <div class="flex justify-between items-center text-sm">
                  <span class="text-gray-600 font-medium">Subtotal:</span>
                  <span class="font-bold text-gray-800 text-lg">${{ (cotizacion.subtotal||0).toFixed(2) }}</span>
                </div>
                <div class="flex justify-between items-center text-sm">
                  <span class="text-gray-600 font-medium">IVA ({{ ((cotizacion.iva_rate||0)*100).toFixed(0) }}%):</span>
                  <span class="font-bold text-gray-800 text-lg">${{ (cotizacion.iva||0).toFixed(2) }}</span>
                </div>
                <div class="rounded-xl px-4 py-3 flex justify-between items-center bg-[#FF7A00]">
                  <span class="text-white font-bold text-lg">Total:</span>
                  <span class="text-white font-bold text-2xl">${{ (cotizacion.total||0).toFixed(2) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Generar OT Card -->
          <div v-if="solicitud.estatus === 'aprobada'" class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 overflow-hidden hover:shadow-xl transition-shadow duration-300">
            <div class="bg-gradient-to-r from-gray-700 to-gray-900 px-6 py-4">
              <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Orden de Trabajo
              </h3>
            </div>
            <div class="p-5">
              <template v-if="!flags?.tiene_ot">
                <a :href="urls.generar_ot"
                   class="block w-full px-5 py-3 rounded-xl bg-gradient-to-r from-gray-800 to-black text-white font-semibold text-center shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200">
                  <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                  </svg>
                  Generar Orden de Trabajo
                </a>
              </template>
              <template v-else>
                <div class="px-4 py-3 rounded-xl bg-gradient-to-r from-yellow-100 to-amber-100 border-2 border-yellow-300 text-yellow-800 text-center">
                  <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                  </svg>
                  <span class="font-semibold">Ya existe una OT para esta solicitud</span>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>

      <!-- Modal de previsualización -->
      <FilePreview v-if="showPreviewModal && previewFile"
                   :archivo="previewFile"
                   @close="closePreview" />

      <!-- Modal Rechazo -->
      <div v-if="showRechazoModal" class="fixed inset-0 z-60 flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-black/40" @click="showRechazoModal = false"></div>
        <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-xl p-6 z-50">
          <h3 class="text-lg font-semibold">Motivo del Rechazo</h3>
          <p class="text-sm text-gray-500 mt-1">Indica por qué se rechaza la solicitud. Este motivo será visible para el cliente.</p>
          <textarea v-model="motivoRechazo" rows="6" class="w-full mt-4 p-3 border rounded-md" placeholder="Escribe el motivo del rechazo..."></textarea>
          <div class="mt-4 flex justify-end gap-3">
            <button @click="showRechazoModal = false" class="px-4 py-2 rounded-lg bg-gray-200">Cancelar</button>
            <button @click="submitRechazo" class="px-4 py-2 rounded-lg bg-red-600 text-white">Enviar Rechazo</button>
          </div>
        </div>
      </div>
  </div>
</template>
