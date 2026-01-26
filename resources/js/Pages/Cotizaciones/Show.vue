<script setup>
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

import SendQuotationModal from '@/Components/Cotizaciones/SendQuotationModal.vue'

const props = defineProps({
  cotizacion: Object,
  auditLogs: Array,
  can: Object,
  urls: Object,
})

const page = usePage()
const flashOk = computed(() => page.props.flash?.ok || page.props.flash?.success || null)

const cancelForm = useForm({})

const sendModalOpen = ref(false)

const actionLabel = (action) => {
  const map = {
    created: 'Creada',
    sent: 'Enviada',
    approved: 'Autorizada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada',
  }
  return map[action] || action
}

const formatDateTime = (iso) => {
  if (!iso) return '—'
  try {
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return iso
    return d.toLocaleString()
  } catch (e) {
    return iso
  }
}

const detailLabel = (log) => {
  const p = log?.payload || {}
  if (log?.action === 'approved' && p.solicitudes_generadas != null) {
    return `Solicitudes generadas: ${p.solicitudes_generadas}`
  }
  if (log?.action === 'rejected' && p.motivo) {
    const m = String(p.motivo)
    return m.length > 120 ? `${m.slice(0, 120)}…` : m
  }
  if (log?.action === 'sent' && p.expires_at) {
    return `Expira: ${formatDateTime(p.expires_at)}`
  }
  if (log?.action === 'created' && p.items != null) {
    return `Ítems: ${p.items}`
  }
  if (p.ip) return `IP: ${p.ip}`
  return '—'
}

function send(){
  sendModalOpen.value = true
}
function cancel(){
  if (!confirm('¿Cancelar cotización?')) return
  cancelForm.post(props.urls.cancel, { preserveScroll: true })
}
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 py-4 space-y-4">
    <div v-if="flashOk" class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl">{{ flashOk }}</div>

    <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
      <div class="px-6 py-4" style="background: linear-gradient(90deg, #1E1C8F 0%, #19176F 100%);">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h1 class="text-white font-bold text-2xl">Cotización {{ cotizacion.folio }}</h1>
            <div class="text-white/90 text-sm">Estatus: <strong class="uppercase">{{ cotizacion.estatus }}</strong></div>
          </div>
          <div class="flex gap-2">
            <a
              v-if="urls?.pdf"
              :href="urls.pdf"
              target="_blank"
              rel="noopener"
              class="px-4 py-2 rounded-lg bg-white/15 text-white font-bold border border-white/30"
            >Descargar PDF</a>
            <button v-if="can?.send" @click="send" class="px-4 py-2 rounded-lg bg-amber-400 text-slate-900 font-bold">Enviar</button>
            <button v-if="can?.cancel" @click="cancel" class="px-4 py-2 rounded-lg bg-red-600 text-white font-bold">Cancelar</button>
          </div>
        </div>
      </div>

      <div class="p-6 space-y-4">
        <div class="grid md:grid-cols-2 gap-3 text-sm">
          <div><span class="text-slate-500">Cliente:</span> <strong>{{ cotizacion.cliente?.name }}</strong></div>
          <div><span class="text-slate-500">Centro:</span> <strong>{{ cotizacion.centro?.nombre }}</strong></div>
          <div><span class="text-slate-500">Centro de costos:</span> <strong>{{ cotizacion.centro_costo?.nombre || cotizacion.centroCosto?.nombre || '—' }}</strong></div>
          <div><span class="text-slate-500">Marca:</span> <strong>{{ cotizacion.marca?.nombre || '—' }}</strong></div>
          <div><span class="text-slate-500">Área:</span> <strong>{{ cotizacion.area?.nombre || '—' }}</strong></div>
        </div>

        <div class="border-t pt-4">
          <h2 class="font-bold text-lg mb-2">Ítems</h2>
          <div v-for="it in (cotizacion.items||[])" :key="it.id" class="border border-slate-200 rounded-xl p-4 mb-3">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-semibold">{{ it.descripcion }}</div>
                <div class="text-sm text-slate-600">Cantidad: {{ it.cantidad }}</div>
                <div v-if="it.notas" class="text-sm text-slate-500 mt-1">{{ it.notas }}</div>
              </div>
              <div class="text-right text-sm">
                <div class="text-slate-500">Servicios</div>
                <div class="font-semibold">{{ (it.servicios||[]).length }}</div>
              </div>
            </div>

            <div class="mt-3 overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-slate-500">
                    <th class="text-left py-1">Servicio</th>
                    <th class="text-center py-1">Tamaño</th>
                    <th class="text-center py-1">Cantidad</th>
                    <th class="text-right py-1">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="s in (it.servicios||[])" :key="s.id" class="border-t">
                    <td class="py-2">{{ s.servicio?.nombre || s.id_servicio }}</td>
                    <td class="py-2 text-center">{{ s.tamano || '—' }}</td>
                    <td class="py-2 text-center">{{ s.cantidad }}</td>
                    <td class="py-2 text-right">{{ Number(s.total||0).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="border-t pt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div class="text-slate-600">
            <div>Subtotal: <strong>{{ Number(cotizacion.subtotal||0).toFixed(2) }}</strong></div>
            <div>IVA: <strong>{{ Number(cotizacion.iva||0).toFixed(2) }}</strong></div>
            <div class="text-slate-900">Total: <strong>{{ Number(cotizacion.total||0).toFixed(2) }}</strong></div>
          </div>
          <a :href="route('cotizaciones.index')" class="px-4 py-2 rounded-lg bg-slate-200 text-slate-900 font-semibold text-center">Volver</a>
        </div>

        <div v-if="(auditLogs||[]).length" class="border-t pt-4">
          <h2 class="font-bold text-lg mb-2">Auditoría</h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-slate-500">
                  <th class="text-left py-1">Fecha</th>
                  <th class="text-left py-1">Acción</th>
                  <th class="text-left py-1">Actor</th>
                  <th class="text-left py-1">Detalle</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="l in (auditLogs||[])" :key="l.id" class="border-t">
                  <td class="py-2 whitespace-nowrap">{{ formatDateTime(l.created_at) }}</td>
                  <td class="py-2 font-semibold">{{ actionLabel(l.action) }}</td>
                  <td class="py-2">{{ l.actor?.name || '—' }}</td>
                  <td class="py-2 text-slate-600">
                    {{ detailLabel(l) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <SendQuotationModal
      v-model:open="sendModalOpen"
      :quotation="{ id: cotizacion.id, folio: cotizacion.folio }"
      :default-expires-at="''"
      :urls="{ send: urls.send, recipients: urls.recipients || route('cotizaciones.recipients', cotizacion.id) }"
      title="Enviar cotización al cliente"
    />
  </div>
</template>
