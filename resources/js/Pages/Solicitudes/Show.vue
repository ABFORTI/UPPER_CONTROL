<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import FilePreview from '@/Components/FilePreview.vue'

const props = defineProps({ solicitud: Object, can: Object, urls: Object, flags: Object, cotizacion: Object })

function aprobar()  { router.post(props.urls.aprobar) }
function rechazar() { router.post(props.urls.rechazar) }

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
  <div class="p-6 max-w-3xl">
     <h1 class="text-2xl font-bold mb-4">{{ solicitud.folio }}</h1>

    <!-- Botones de aprobar/rechazar: solo coordinador/admin y si está pendiente -->
    <div v-if="can?.aprobar" class="flex gap-2 mb-4">
      <button @click="aprobar" class="px-3 py-2 rounded bg-green-600 text-white">Aprobar</button>
      <button @click="rechazar" class="px-3 py-2 rounded bg-red-600 text-white">Rechazar</button>
    </div>
    <h1 class="text-2xl font-bold mb-4">{{ solicitud.folio }}</h1>
    <div class="mb-2">Servicio: {{ solicitud.servicio?.nombre }}</div>
    <div class="mb-2">Centro: {{ solicitud.centro?.nombre }}</div>
    <div class="mb-2">Estatus: <span class="px-2 py-1 rounded bg-gray-100">{{ solicitud.estatus }}</span></div>
    <div class="mb-2" v-if="solicitud.tamano">Tamaño: {{ solicitud.tamano }}</div>
    <div class="mb-2" v-if="solicitud.descripcion">Descripción: {{ solicitud.descripcion }}</div>
    <div class="mb-2" v-if="solicitud.area">Área: <span class="font-semibold">{{ solicitud.area.nombre }}</span></div>
    <div class="mb-2">Cantidad: {{ solicitud.cantidad }}</div>
    <div class="mb-2" v-if="solicitud.notas">Notas: {{ solicitud.notas }}</div>

    <!-- Desglose por tamaño -->
    <div v-if="solicitud.tamanos?.length" class="mt-4">
      <h2 class="font-semibold mb-2">Desglose por tamaño</h2>
      <table class="min-w-full text-sm border">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-2 border">Tamaño</th>
            <th class="text-right p-2 border">Cantidad</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in solicitud.tamanos" :key="t.id">
            <td class="p-2 border capitalize">{{ t.tamano }}</td>
            <td class="p-2 border text-right">{{ t.cantidad }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Cotización -->
    <div class="mt-6">
      <h2 class="font-semibold mb-2">Cotización</h2>
      <div v-if="cotizacion?.lines?.length" class="border rounded">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left p-2 border">Concepto</th>
              <th class="text-right p-2 border">Cantidad</th>
              <th class="text-right p-2 border">P. Unitario</th>
              <th class="text-right p-2 border">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(l,i) in cotizacion.lines" :key="i">
              <td class="p-2 border">{{ l.label }}</td>
              <td class="p-2 border text-right">{{ l.cantidad }}</td>
              <td class="p-2 border text-right">${{ (l.pu||0).toFixed(2) }}</td>
              <td class="p-2 border text-right">${{ (l.subtotal||0).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
        <div class="p-3 grid gap-1 justify-end">
          <div class="text-sm">Subtotal: <strong>${{ (cotizacion.subtotal||0).toFixed(2) }}</strong></div>
          <div class="text-sm">IVA ({{ ((cotizacion.iva_rate||0)*100).toFixed(0) }}%): <strong>${{ (cotizacion.iva||0).toFixed(2) }}</strong></div>
          <div class="text-base font-semibold">Total: <strong>${{ (cotizacion.total||0).toFixed(2) }}</strong></div>
        </div>
      </div>
    </div>

    <!-- Archivos adjuntos -->
    <div v-if="solicitud.archivos?.length" class="mt-6">
      <h2 class="font-semibold mb-3">Archivos Adjuntos</h2>
      <div class="border rounded divide-y">
        <div v-for="archivo in solicitud.archivos" :key="archivo.id" 
             class="p-3 flex items-center justify-between hover:bg-gray-50">
          <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <div>
              <div class="font-medium text-sm">{{ archivo.nombre_original || archivo.path?.split('/').pop() || 'Archivo' }}</div>
              <div class="text-xs text-gray-500">
                {{ archivo.size ? (archivo.size / 1024).toFixed(0) : '0' }} KB
                <span v-if="archivo.mime"> • {{ archivo.mime }}</span>
              </div>
            </div>
          </div>
          <div class="flex gap-2">
            <button v-if="canPreview(archivo.mime)"
                    @click="openPreview(archivo)"
                    class="px-3 py-1 rounded text-sm bg-gray-600 text-white hover:bg-gray-700">
              <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              Ver
            </button>
            <a :href="route('archivos.download', archivo.id)" 
               class="px-3 py-1 rounded text-sm bg-blue-600 text-white hover:bg-blue-700">
              Descargar
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <!-- Botón de Generar OT (solo cuando ya está aprobada) -->
    <div v-if="solicitud.estatus === 'aprobada'" class="mt-3">
      <template v-if="!flags?.tiene_ot">
        <a :href="urls.generar_ot"
           class="inline-block px-3 py-2 rounded bg-black text-white">
          Generar OT
        </a>
      </template>
      <template v-else>
        <div class="px-3 py-2 rounded bg-yellow-100 text-yellow-800 inline-block">
          Ya existe una Orden de Trabajo para esta solicitud
        </div>
      </template>
    </div>
    </div>

    <!-- Modal de previsualización -->
    <FilePreview v-if="showPreviewModal && previewFile"
                 :archivo="previewFile"
                 @close="closePreview" />
  </div>
</template>
