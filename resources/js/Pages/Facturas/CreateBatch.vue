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
  <div class="p-6 max-w-screen-xl mx-auto">
    <div class="mb-6">
      <h1 class="text-2xl font-bold">Registrar factura (múltiples OTs)</h1>
      <div class="text-sm text-slate-600 mt-1">OTs seleccionadas: {{ ids.length }}</div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Columna izquierda: captura y XML -->
      <aside class="space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded border p-4">
          <div class="text-sm font-medium mb-3">Datos de la factura</div>

          <label class="block mb-2 text-sm">Cargar XML CFDI</label>
          <input type="file" accept=".xml,text/xml" @change="onPickXml" class="mb-4" />

          <label class="block mb-2 text-sm">Folio timbrado / externo (UUID)</label>
          <input v-model="form.folio_externo" readonly placeholder="Se llena desde el XML" class="border p-2 rounded w-full mb-3 bg-gray-50 cursor-not-allowed" />

          <label class="block mb-2 text-sm">Folio</label>
          <input v-model="form.folio" readonly placeholder="Se llena desde el XML" class="border p-2 rounded w-full mb-3 bg-gray-50 cursor-not-allowed" />

          <label class="block mb-2 text-sm">Fecha</label>
          <input type="date" v-model="form.fecha" class="border p-2 rounded w-full mb-4" />

          <button @click="submit" class="btn btn-primary w-full">Registrar factura</button>
        </div>
      </aside>

      <!-- Columna derecha: listado de OTs y totales -->
      <div class="md:col-span-2 space-y-4">
        <div class="overflow-auto rounded border bg-white dark:bg-slate-800">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-700 text-left">
              <tr>
                <th class="p-2">OT</th>
                <th class="p-2">Centro</th>
                <th class="p-2">Servicio</th>
                <th class="p-2">Producto/Descripción</th>
                <th class="p-2 text-right">Total OT</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in ordenes" :key="o.id" class="border-t">
                <td class="p-2">#{{ o.id }}</td>
                <td class="p-2">{{ o.centro || '—' }}</td>
                <td class="p-2">{{ o.servicio || '—' }}</td>
                <td class="p-2">{{ o.descripcion_general || '—' }}</td>
                <td class="p-2 text-right">${{ Number(o.total || 0).toFixed(2) }}</td>
              </tr>
              <tr v-if="!ordenes || ordenes.length===0">
                <td colspan="5" class="p-4 text-center text-slate-500">Sin OTs</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded border">
          <div class="px-4 py-3 border-b text-sm font-medium">Resumen de totales</div>
          <div class="p-4">
            <div class="flex justify-between text-base font-semibold">
              <span>Total seleccionado</span>
              <span>${{ totalOTs.toFixed(2) }}</span>
            </div>
            <div class="text-xs text-slate-500 mt-2">Nota: Si el XML tiene un total diferente, se usará el total del XML como definitivo.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
