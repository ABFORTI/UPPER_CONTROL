<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  orden: { type: Object, required: true },
  total_sugerido: { type: Number, default: 0 },
  urls: { type: Object, required: true }
})

const form = reactive({
  total: null,
  folio: '',
  folio_externo: ''
})

const xmlFile = ref(null)

function submit () {
  const data = new FormData()
  // Solo enviar total si el usuario lo capturó
  if (form.total !== null && form.total !== '' && !isNaN(Number(form.total))) {
    data.append('total', String(form.total))
  }
  if (form.folio) data.append('folio', form.folio)
  if (form.folio_externo) data.append('folio_externo', form.folio_externo)
  if (xmlFile.value) data.append('xml', xmlFile.value)
  router.post(props.urls.store, data, { forceFormData: true })
}

function parseXmlAndFill(xmlText){
  try {
    const parser = new DOMParser()
    const doc = parser.parseFromString(xmlText, 'text/xml')
    // Folio CFDI (atributo Folio en cfdi:Comprobante)
    const comp = doc.getElementsByTagName('cfdi:Comprobante')[0] || doc.getElementsByTagName('Comprobante')[0]
    if (comp) {
      const folio = comp.getAttribute('Folio') || comp.getAttribute('folio')
      const total = comp.getAttribute('Total') || comp.getAttribute('total')
      if (folio) form.folio = folio
      if (total && !isNaN(Number(total))) form.total = Number(total)
    }
    // UUID en TimbreFiscalDigital (folio externo SAT)
    const tfd = doc.getElementsByTagName('tfd:TimbreFiscalDigital')[0] || doc.getElementsByTagName('TimbreFiscalDigital')[0]
    if (tfd) {
      const uuid = tfd.getAttribute('UUID') || tfd.getAttribute('Uuid') || tfd.getAttribute('uuid')
      if (uuid) form.folio_externo = uuid
    }
  } catch (e) {
    console.warn('No se pudo parsear el XML:', e)
  }
}

function onPickXml(e){
  const file = e.target.files?.[0]
  if (!file) return
  xmlFile.value = file
  const reader = new FileReader()
  reader.onload = () => parseXmlAndFill(String(reader.result || ''))
  reader.readAsText(file)
}
</script>

<template>
  <div class="p-6 max-w-screen-2xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-6">
      <div>
        <h1 class="text-2xl font-bold">Registrar factura</h1>
        <div class="text-sm text-slate-500 mt-1">OT #{{ orden?.id }} — {{ orden?.servicio?.nombre || '—' }} · Cliente: {{ orden?.cliente?.name || '—' }}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Columna izquierda: Datos de la factura -->
      <aside class="space-y-4">
        <!-- Formulario de registro de factura -->
        <div class="bg-white dark:bg-slate-800 rounded border p-4">
          <div class="text-sm font-medium mb-3">Datos de la factura</div>
          <label class="block mb-2 text-sm">Total de la factura</label>
          <input type="number" step="0.01" v-model="form.total" placeholder="Se llena desde el XML" readonly class="border p-2 rounded w-full mb-1 bg-gray-50 cursor-not-allowed" />
          <br />
          <br />
          

          <label class="block mb-2 text-sm">Cargar XML CFDI</label>
          <input type="file" accept=".xml,text/xml" @change="onPickXml" class="mb-4" />

          <label class="block mb-2 text-sm">Folio timbrado / externo</label>
          <input v-model="form.folio_externo" placeholder="Se llena desde el XML" readonly class="border p-2 rounded w-full mb-3 bg-gray-50 cursor-not-allowed" />

          <label class="block mb-2 text-sm">Folio</label>
          <input v-model="form.folio" placeholder="Se llena desde el XML" readonly class="border p-2 rounded w-full mb-4 bg-gray-50 cursor-not-allowed" />

          <button @click="submit" class="btn btn-primary w-full">Registrar factura</button>
        </div>
      </aside>

      <!-- Columna derecha: información de la OT, resumen y conceptos -->
      <div class="md:col-span-2 space-y-4">
        <!-- Tarjetas resumen OT -->
        <div class="grid md:grid-cols-3 gap-4">
          <div class="p-4 rounded border bg-white dark:bg-slate-800">
            <div class="text-xs uppercase text-slate-500">Empresa / Centro</div>
            <div class="text-base font-medium">{{ orden?.empresa || orden?.centro?.nombre || '—' }}</div>
          </div>
          <div class="p-4 rounded border bg-white dark:bg-slate-800">
            <div class="text-xs uppercase text-slate-500">Servicio</div>
            <div class="text-base font-medium">{{ orden?.servicio?.nombre || '—' }}</div>
          </div>
          <div class="p-4 rounded border bg-white dark:bg-slate-800">
            <div class="text-xs uppercase text-slate-500">Cliente</div>
            <div class="text-base font-medium">{{ orden?.cliente?.name || '—' }}</div>
          </div>
        </div>

        <!-- Resumen simple -->
        <div class="grid md:grid-cols-2 gap-4">
          <div class="p-4 rounded border bg-white dark:bg-slate-800">
            <div class="text-xs uppercase text-slate-500">Cantidad total</div>
            <div class="text-base font-medium">{{ Number(orden?.resumen?.cantidad_total || 0).toLocaleString() }}</div>
          </div>
          <div class="p-4 rounded border bg-white dark:bg-slate-800">
            <div class="text-xs uppercase text-slate-500">Precio unitario</div>
            <div class="text-base font-medium">${{ Number(orden?.resumen?.precio_unitario || 0).toFixed(2) }}</div>
          </div>
        </div>

        

        <!-- Items -->
        <div class="overflow-auto rounded border bg-white dark:bg-slate-800">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-700 text-left">
              <tr>
                <th class="p-2">Descripción</th>
                <th class="p-2">Tamaño</th>
                <th class="p-2 text-right">Cantidad</th>
                <th class="p-2 text-right">Precio unitario</th>
                <th class="p-2 text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="it in orden?.items || []" :key="it.id" class="border-t">
                <td class="p-2">{{ it.descripcion || '—' }}</td>
                <td class="p-2">{{ it.tamano || '—' }}</td>
                <td class="p-2 text-right">{{ it.cantidad?.toLocaleString() }}</td>
                <td class="p-2 text-right">${{ Number(it.precio_unitario || 0).toFixed(2) }}</td>
                <td class="p-2 text-right">${{ Number(it.subtotal || 0).toFixed(2) }}</td>
              </tr>
              <tr v-if="!(orden?.items?.length)">
                <td colspan="5" class="p-4 text-center text-slate-500">Sin items</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Resumen de totales (OT) debajo de datos de la OT -->
        <div class="bg-white dark:bg-slate-800 rounded border">
          <div class="px-4 py-3 border-b text-sm font-medium">Resumen de totales (OT)</div>
          <div class="p-4 space-y-2">
            <div class="flex justify-between text-sm"><span>Subtotal</span><span>${{ Number(orden?.totales?.subtotal || 0).toFixed(2) }}</span></div>
            <div class="flex justify-between text-sm"><span>IVA</span><span>${{ Number(orden?.totales?.iva || 0).toFixed(2) }}</span></div>
            <div class="flex justify-between text-base font-semibold border-t pt-2"><span>Total OT</span><span>${{ Number(orden?.totales?.total || 0).toFixed(2) }}</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
