<script setup>
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  cotizacion: Object,
  token: String,
  token_required: Boolean,
  is_expired: Boolean,
  expired_message: String,
  can: Object,
  urls: Object,
})

const page = usePage()
const flashOk = computed(() => page.props.flash?.ok || page.props.flash?.success || null)

const approveForm = useForm({ token: props.token || '' })
const rejectForm = useForm({ motivo: '', token: props.token || '' })
const showReject = ref(false)

function approve(){
  if (!confirm('¿Autorizar esta cotización? Se generarán solicitudes automáticamente.')) return
  approveForm.post(props.urls.approve, { preserveScroll: true })
}

function openReject(){
  showReject.value = true
  rejectForm.motivo = ''
  rejectForm.token = props.token || ''
}

function reject(){
  rejectForm.post(props.urls.reject, { preserveScroll: true, onSuccess: () => { showReject.value = false } })
}
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 py-4 space-y-4">
    <div v-if="flashOk" class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl">{{ flashOk }}</div>

    <div v-if="is_expired" class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl">
      {{ expired_message || 'Cotización expirada' }}
    </div>

    <div v-if="token_required && !token" class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl">
      Para <strong>autorizar</strong> o <strong>rechazar</strong> esta cotización, abre el enlace que te llegó por correo.
    </div>

    <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
      <div class="px-6 py-4" style="background: linear-gradient(90deg, #1E1C8F 0%, #19176F 100%);">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h1 class="text-white font-bold text-2xl">Revisar Cotización {{ cotizacion.folio }}</h1>
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
            <button v-if="can?.approve" @click="approve" class="px-4 py-2 rounded-lg bg-green-500 text-white font-bold">Autorizar</button>
            <button v-if="can?.reject" @click="openReject" class="px-4 py-2 rounded-lg bg-red-600 text-white font-bold">Rechazar</button>
          </div>
        </div>
      </div>

      <div class="p-6 space-y-4">
        <div class="grid md:grid-cols-2 gap-3 text-sm">
          <div><span class="text-slate-500">Cliente:</span> <strong>{{ cotizacion.cliente?.name }}</strong></div>
          <div><span class="text-slate-500">Centro:</span> <strong>{{ cotizacion.centro?.nombre }}</strong></div>
          <div><span class="text-slate-500">Centro de costos:</span> <strong>{{ cotizacion.centro_costo?.nombre || cotizacion.centroCosto?.nombre || '—' }}</strong></div>
          <div><span class="text-slate-500">Marca:</span> <strong>{{ cotizacion.marca?.nombre || '—' }}</strong></div>
        </div>

        <div v-if="cotizacion.motivo_rechazo" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
          <div class="font-semibold">Motivo de rechazo</div>
          <div class="text-sm mt-1">{{ cotizacion.motivo_rechazo }}</div>
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
      </div>
    </div>

    <!-- Modal Rechazo -->
    <div v-if="showReject" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg p-5">
        <div class="text-lg font-bold">Rechazar cotización</div>
        <div class="text-sm text-slate-600 mt-1">Indica el motivo para el coordinador.</div>
        <textarea v-model="rejectForm.motivo" rows="4" class="w-full mt-3 px-4 py-3 rounded-xl border-2 border-slate-200" placeholder="Motivo de rechazo" />
        <p v-if="rejectForm.errors.motivo" class="text-red-600 text-sm mt-1">{{ rejectForm.errors.motivo }}</p>
        <div class="mt-4 flex gap-2 justify-end">
          <button @click="showReject=false" class="px-4 py-2 rounded-lg bg-slate-200 text-slate-900 font-semibold">Cancelar</button>
          <button @click="reject" :disabled="rejectForm.processing" class="px-4 py-2 rounded-lg bg-red-600 text-white font-bold disabled:opacity-60">Rechazar</button>
        </div>
      </div>
    </div>
  </div>
</template>
