<script setup>
import { reactive, ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  ordenes: { type: Array, required: true },
  suma_total: { type: Number, required: true },
  ids: { type: Array, required: true },
  urls: { type: Object, required: true },
})

const form = reactive({
  folio: '',
  folio_externo: '',
  fecha: new Date().toISOString().slice(0,10),
})
const xmlFile = ref(null)

function submit(){
  const data = new FormData()
  for (const id of props.ids) data.append('orden_ids[]', String(id))
  if (form.folio) data.append('folio', form.folio)
  if (form.folio_externo) data.append('folio_externo', form.folio_externo)
  if (form.fecha) data.append('fecha', form.fecha)
  if (xmlFile.value) data.append('xml', xmlFile.value)
  router.post(props.urls.store, data, { forceFormData: true })
}

function parseXmlAndFill(xmlText){
  try{
    const parser = new DOMParser()
    const doc = parser.parseFromString(xmlText, 'text/xml')
    const comp = doc.getElementsByTagName('cfdi:Comprobante')[0] || doc.getElementsByTagName('Comprobante')[0]
    if (comp){
      const folio = comp.getAttribute('Folio') || comp.getAttribute('folio')
      const total = comp.getAttribute('Total') || comp.getAttribute('total')
      if (folio) form.folio = folio
      // Total de XML lo toma el backend si viene; aquí solo mostramos suma
    }
    const tfd = doc.getElementsByTagName('tfd:TimbreFiscalDigital')[0] || doc.getElementsByTagName('TimbreFiscalDigital')[0]
    if (tfd){
      const uuid = tfd.getAttribute('UUID') || tfd.getAttribute('Uuid') || tfd.getAttribute('uuid')
      if (uuid) form.folio_externo = uuid
    }
  }catch(e){ console.warn('No se pudo parsear el XML', e) }
}

function onPickXml(e){
  const file = e.target.files?.[0]
  if (!file) return
  xmlFile.value = file
  const reader = new FileReader()
  reader.onload = () => parseXmlAndFill(String(reader.result || ''))
  reader.readAsText(file)
}

const totalOTs = computed(()=> Number(props.suma_total || 0))
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-indigo-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
      <!-- Header Card -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-indigo-100 overflow-hidden">
        <div class="bg-[#1E1C8F] px-8 py-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h1 class="text-3xl font-bold text-white">Registrar factura (múltiples OTs)</h1>
              <div class="text-indigo-100 mt-1">OTs seleccionadas: {{ ids.length }}</div>
            </div>
            <div class="px-4 py-2 rounded-xl bg-white/10 text-white font-semibold border-2 border-white/20 backdrop-blur-sm">
              Total seleccionado: ${{ totalOTs.toFixed(2) }}
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Columna izquierda: captura y XML -->
        <aside class="space-y-4">
          <div class="bg-white rounded-2xl shadow-lg border-2 border-orange-100 overflow-hidden">
            <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-6 py-4">
              <h2 class="text-lg font-bold text-white">Datos de la factura</h2>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Cargar XML CFDI</label>
                <input type="file" accept=".xml,text/xml" @change="onPickXml"
                  class="block w-full text-sm text-gray-700 file:mr-4 file:py-3 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200 file:transition-all file:duration-200 border-2 border-gray-200 rounded-xl" />
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Folio timbrado / externo (UUID)</label>
                <input v-model="form.folio_externo" readonly placeholder="Se llena desde el XML"
                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-gray-50 cursor-not-allowed" />
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Folio</label>
                <input v-model="form.folio" readonly placeholder="Se llena desde el XML"
                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-gray-50 cursor-not-allowed" />
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha</label>
                <input type="date" v-model="form.fecha" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl" />
              </div>

              <button @click="submit" class="w-full px-6 py-3 bg-gradient-to-r from-orange-600 to-amber-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200">Registrar factura</button>
            </div>
          </div>
        </aside>

        <!-- Columna derecha: listado de OTs y totales -->
        <div class="md:col-span-2 space-y-4">
          <div class="overflow-auto rounded-2xl border-2 border-indigo-100 bg-white">
            <table class="min-w-full text-sm">
              <thead class="bg-indigo-50 text-left">
                <tr class="text-indigo-900">
                  <th class="p-3 font-bold">OT</th>
                  <th class="p-3 font-bold">Centro</th>
                  <th class="p-3 font-bold">Servicio</th>
                  <th class="p-3 font-bold">Producto/Descripción</th>
                  <th class="p-3 text-right font-bold">Total OT</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="o in ordenes" :key="o.id" class="border-t border-indigo-100 hover:bg-indigo-50/40">
                  <td class="p-3">#{{ o.id }}</td>
                  <td class="p-3">{{ o.centro || '—' }}</td>
                  <td class="p-3">{{ o.servicio || '—' }}</td>
                  <td class="p-3">{{ o.descripcion_general || '—' }}</td>
                  <td class="p-3 text-right">${{ Number(o.total || 0).toFixed(2) }}</td>
                </tr>
                <tr v-if="!ordenes || ordenes.length===0">
                  <td colspan="5" class="p-4 text-center text-slate-500">Sin OTs</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="bg-white rounded-2xl border-2 border-emerald-100 overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white font-bold">Resumen de totales</div>
            <div class="p-6">
              <div class="flex justify-between text-base font-semibold">
                <span>Total seleccionado</span>
                <span class="text-emerald-700">${{ totalOTs.toFixed(2) }}</span>
              </div>
              <div class="text-xs text-slate-500 mt-2">Nota: Si el XML tiene un total diferente, se usará el total del XML como definitivo.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
