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
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-indigo-50 py-8 px-4 sm:px-6 lg:px-8 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900">
    <div class="max-w-7xl mx-auto space-y-6">
      <!-- Header Card -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-indigo-100 overflow-hidden dark:bg-slate-900/80 dark:border-indigo-500/40">
        <div class="bg-[#1E1C8F] px-5 sm:px-8 py-6 dark:bg-gradient-to-r dark:from-[#1E1C8F] dark:to-indigo-500">
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="space-y-3">
              <h1 class="text-3xl font-bold text-white">Registrar factura</h1>
              <div class="text-indigo-100 mt-1">OT #{{ orden?.id }} — {{ orden?.servicio?.nombre || '—' }} · Cliente: {{ orden?.cliente?.name || '—' }}</div>
              <div v-if="orden?.descripcion_general" class="inline-flex items-start gap-2 bg-white/10 text-white border-2 border-white/20 rounded-xl px-4 py-2 backdrop-blur-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide opacity-90">Producto/Servicio</div>
                  <div class="text-sm font-bold">{{ orden.descripcion_general }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Info Bar -->
        <div class="bg-gradient-to-r from-indigo-50 to-[#eef2ff] px-5 sm:px-8 py-4 border-b border-indigo-100 dark:bg-gradient-to-r dark:from-slate-900/80 dark:via-slate-900/60 dark:to-slate-900/40 dark:border-slate-800">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div class="flex items-center gap-2 text-gray-700 dark:text-slate-200">
              <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Centro:</strong> {{ orden?.centro?.nombre || orden?.empresa || '—' }}</span>
            </div>
            <div class="flex items-center gap-2 text-gray-700 dark:text-slate-200">
              <svg class="w-5 h-5 text-[#1E1C8F] dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Servicio:</strong> {{ orden?.servicio?.nombre || '—' }}</span>
            </div>
            <div class="flex items-center gap-2 text-gray-700 dark:text-slate-200">
              <svg class="w-5 h-5 text-[#1E1C8F] dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Cliente:</strong> {{ orden?.cliente?.name || '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Columna izquierda: Datos de la factura -->
        <aside class="space-y-4">
          <div class="bg-white rounded-2xl shadow-lg border-2 border-orange-100 overflow-hidden dark:bg-slate-900/80 dark:border-orange-500/40">
            <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-5 sm:px-6 py-4 dark:from-orange-500 dark:to-amber-500">
              <h2 class="text-lg font-bold text-white">Datos de la factura</h2>
            </div>
            <div class="p-5 sm:p-6 space-y-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Total de la factura</label>
                <input type="number" step="0.01" v-model="form.total" placeholder="Se llena desde el XML"
                  readonly class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-gray-50 cursor-not-allowed text-gray-800 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder-slate-400" />
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Cargar XML CFDI</label>
                <input type="file" accept=".xml,text/xml" @change="onPickXml"
                  class="block w-full text-sm text-gray-700 file:mr-4 file:py-3 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200 file:transition-all file:duration-200 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-orange-100 dark:text-slate-100 dark:border-slate-700 dark:file:bg-orange-500/20 dark:file:text-orange-200 dark:hover:file:bg-orange-500/30" />
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Folio timbrado / externo</label>
                <input v-model="form.folio_externo" placeholder="Se llena desde el XML" readonly class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-gray-50 cursor-not-allowed text-gray-800 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder-slate-400" />
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Folio</label>
                <input v-model="form.folio" placeholder="Se llena desde el XML" readonly class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl bg-gray-50 cursor-not-allowed text-gray-800 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100 dark:placeholder-slate-400" />
              </div>

              <button @click="submit" class="w-full px-5 sm:px-6 py-3 bg-gradient-to-r from-orange-600 to-amber-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 dark:from-orange-500 dark:to-amber-500">Registrar factura</button>
            </div>
          </div>
        </aside>

        <!-- Columna derecha: información de la OT, resumen y conceptos -->
        <div class="lg:col-span-2 space-y-4">
          <!-- Resumen simple -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl border-2 border-blue-100 bg-white shadow-sm dark:bg-slate-900/70 dark:border-blue-500/40">
              <div class="text-xs uppercase text-slate-500 dark:text-slate-400">Cantidad total</div>
              <div class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ Number(orden?.resumen?.cantidad_total || 0).toLocaleString() }}</div>
            </div>
            <div class="p-4 rounded-xl border-2 border-blue-100 bg-white shadow-sm dark:bg-slate-900/70 dark:border-blue-500/40">
              <div class="text-xs uppercase text-slate-500 dark:text-slate-400">Precio unitario</div>
              <div class="text-lg font-semibold text-slate-900 dark:text-slate-100">${{ Number(orden?.resumen?.precio_unitario || 0).toFixed(2) }}</div>
            </div>
          </div>

          <!-- Items -->
          <div class="rounded-2xl border-2 border-indigo-100 bg-white dark:bg-slate-900/80 dark:border-indigo-500/40">
            <div class="hidden md:block overflow-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-indigo-50 text-left dark:bg-indigo-500/10">
                  <tr class="text-indigo-900 dark:text-indigo-200">
                    <th class="p-3 font-bold">Descripción</th>
                    <th class="p-3 font-bold">Tamaño</th>
                    <th class="p-3 text-right font-bold">Cantidad</th>
                    <th class="p-3 text-right font-bold">Precio unitario</th>
                    <th class="p-3 text-right font-bold">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="it in orden?.items || []" :key="it.id" class="border-t border-indigo-100 hover:bg-indigo-50/40 dark:border-slate-800 dark:hover:bg-indigo-500/10">
                    <td class="p-3 text-slate-800 dark:text-slate-100">{{ it.descripcion || '—' }}</td>
                    <td class="p-3 text-slate-800 dark:text-slate-100">{{ it.tamano || '—' }}</td>
                    <td class="p-3 text-right text-slate-800 dark:text-slate-100">{{ it.cantidad?.toLocaleString() }}</td>
                    <td class="p-3 text-right text-slate-800 dark:text-slate-100">${{ Number(it.precio_unitario || 0).toFixed(2) }}</td>
                    <td class="p-3 text-right text-slate-800 dark:text-slate-100">${{ Number(it.subtotal || 0).toFixed(2) }}</td>
                  </tr>
                  <tr v-if="!(orden?.items?.length)">
                    <td colspan="5" class="p-4 text-center text-slate-500 dark:text-slate-400">Sin items</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="md:hidden p-4 space-y-3">
              <div v-for="it in orden?.items || []" :key="`m-${it.id}`" class="border border-indigo-100 rounded-xl p-4 bg-white shadow-sm dark:bg-slate-900/70 dark:border-indigo-500/40">
                <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ it.descripcion || '—' }}</div>
                <div class="mt-2 grid grid-cols-2 gap-3 text-xs text-slate-600 dark:text-slate-400">
                  <div>
                    <div class="uppercase tracking-wide text-[0.65rem] text-slate-500 dark:text-slate-500">Tamaño</div>
                    <div class="font-semibold text-slate-800 dark:text-slate-100">{{ it.tamano || '—' }}</div>
                  </div>
                  <div class="text-right">
                    <div class="uppercase tracking-wide text-[0.65rem] text-slate-500 dark:text-slate-500">Cantidad</div>
                    <div class="font-semibold text-slate-800 dark:text-slate-100">{{ it.cantidad?.toLocaleString() }}</div>
                  </div>
                  <div>
                    <div class="uppercase tracking-wide text-[0.65rem] text-slate-500 dark:text-slate-500">Precio unitario</div>
                    <div class="font-semibold text-slate-800 dark:text-slate-100">${{ Number(it.precio_unitario || 0).toFixed(2) }}</div>
                  </div>
                  <div class="text-right">
                    <div class="uppercase tracking-wide text-[0.65rem] text-slate-500 dark:text-slate-500">Subtotal</div>
                    <div class="font-semibold text-emerald-700 dark:text-emerald-300">${{ Number(it.subtotal || 0).toFixed(2) }}</div>
                  </div>
                </div>
              </div>
              <div v-if="!(orden?.items?.length)" class="border border-dashed border-indigo-200 rounded-xl p-6 text-center text-slate-500 text-sm dark:border-slate-700 dark:text-slate-400">
                Sin items
              </div>
            </div>
          </div>

          <!-- Resumen de totales (OT) -->
          <div class="bg-white rounded-2xl border-2 border-emerald-100 overflow-hidden dark:bg-slate-900/80 dark:border-emerald-500/40">
            <div class="px-5 sm:px-6 py-4 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white font-bold dark:from-emerald-500 dark:to-teal-500">Resumen de totales (OT)</div>
            <div class="p-5 sm:p-6 space-y-3 text-sm text-slate-700 dark:text-slate-200">
              <div class="flex flex-wrap justify-between gap-2"><span>Subtotal</span><span>${{ Number(orden?.totales?.subtotal || 0).toFixed(2) }}</span></div>
              <div class="flex flex-wrap justify-between gap-2"><span>IVA</span><span>${{ Number(orden?.totales?.iva || 0).toFixed(2) }}</span></div>
              <div class="flex flex-wrap justify-between gap-2 text-base font-semibold border-t pt-3 border-slate-200 dark:border-slate-700"><span>Total OT</span><span class="text-emerald-700 dark:text-emerald-300">${{ Number(orden?.totales?.total || 0).toFixed(2) }}</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
