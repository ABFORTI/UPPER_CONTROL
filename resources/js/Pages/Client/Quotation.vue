<script setup>
import { computed, onMounted, ref } from 'vue'

defineOptions({ layout: null })

const props = defineProps({
  quotationId: { type: Number, required: true },
  token: { type: String, default: null },
})

const loading = ref(true)
const error = ref(null)
const info = ref(null)
const quote = ref(null)

const token = computed(() => {
  // Preferir prop (viene del servidor). Fallback a query string actual.
  const fromProps = props.token
  if (fromProps) return String(fromProps)
  try {
    const u = new URL(window.location.href)
    return u.searchParams.get('token') || ''
  } catch {
    return ''
  }
})

const apiShowUrl = computed(() => route('api.client.quotations.show', { cotizacion: props.quotationId }))
const apiApproveUrl = computed(() => route('api.client.quotations.approve', { cotizacion: props.quotationId }))
const apiRejectUrl = computed(() => route('api.client.quotations.reject', { cotizacion: props.quotationId }))

function mapApiError(err){
  const status = err?.response?.status
  const code = err?.response?.data?.code
  const message = err?.response?.data?.message || err?.message || 'Error'

  if (status === 401 && (code === 'TOKEN_REQUIRED' || code === 'TOKEN_INVALID')) {
    return 'El enlace es inválido o el token no es válido. Revisa el correo y vuelve a abrir el link.'
  }
  if (status === 410 && code === 'QUOTATION_EXPIRED') {
    return 'Cotización expirada'
  }
  if (status === 409 && code === 'STATUS_NOT_SENT') {
    return 'Esta cotización ya fue procesada (aprobada/rechazada) o no está disponible.'
  }
  if (status === 422) {
    // Validación
    const errs = err?.response?.data?.errors
    if (errs) {
      const firstKey = Object.keys(errs)[0]
      const firstMsg = errs[firstKey]?.[0]
      if (firstMsg) return String(firstMsg)
    }
  }
  return String(message)
}

async function fetchQuotation(){
  loading.value = true
  error.value = null
  info.value = null
  try {
    const url = route('api.client.quotations.show', { cotizacion: props.quotationId, token: token.value || '' })
    const res = await window.axios.get(url, { headers: { Accept: 'application/json' } })
    quote.value = res.data?.data || null
  } catch (e) {
    error.value = mapApiError(e)
    quote.value = null
  } finally {
    loading.value = false
  }
}

const canAct = computed(() => {
  if (!quote.value) return false
  return String(quote.value.status || '') === 'sent'
})

const showReject = ref(false)
const rejectReason = ref('')
const posting = ref(false)

async function approve(){
  if (!canAct.value) return
  if (!confirm('¿Autorizar esta cotización?')) return

  posting.value = true
  error.value = null
  info.value = null
  try {
    const url = route('api.client.quotations.approve', { cotizacion: props.quotationId, token: token.value || '' })
    const res = await window.axios.post(url, {}, { headers: { Accept: 'application/json' } })
    quote.value = res.data?.data || quote.value
    info.value = res.data?.message || 'Cotización autorizada.'
  } catch (e) {
    error.value = mapApiError(e)
  } finally {
    posting.value = false
  }
}

function openReject(){
  if (!canAct.value) return
  rejectReason.value = ''
  showReject.value = true
}

async function reject(){
  if (!canAct.value) return

  posting.value = true
  error.value = null
  info.value = null
  try {
    const url = route('api.client.quotations.reject', { cotizacion: props.quotationId, token: token.value || '' })
    const payload = {}
    if (String(rejectReason.value || '').trim()) payload.motivo = String(rejectReason.value).trim()
    const res = await window.axios.post(url, payload, { headers: { Accept: 'application/json' } })
    quote.value = res.data?.data || quote.value
    info.value = res.data?.message || 'Cotización rechazada.'
    showReject.value = false
  } catch (e) {
    error.value = mapApiError(e)
  } finally {
    posting.value = false
  }
}

const money = (n) => {
  const v = Number(n || 0)
  return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

onMounted(() => {
  fetchQuotation()
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 py-6 px-4">
    <div class="max-w-6xl mx-auto space-y-4">
      <div class="bg-white border border-slate-200 rounded-2xl shadow overflow-hidden">
        <div class="px-6 py-4" style="background: linear-gradient(90deg, #1E1C8F 0%, #19176F 100%);">
          <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
            <div>
              <h1 class="text-white font-bold text-2xl">Ver cotización</h1>
              <p class="text-white/90 text-sm">Revisa y responde desde este enlace.</p>
            </div>

            <div class="flex gap-2">
              <button
                class="px-4 py-2 rounded-lg bg-green-500 text-white font-bold disabled:opacity-60"
                :disabled="loading || posting || !canAct"
                @click="approve"
              >Autorizar</button>
              <button
                class="px-4 py-2 rounded-lg bg-red-600 text-white font-bold disabled:opacity-60"
                :disabled="loading || posting || !canAct"
                @click="openReject"
              >Rechazar</button>
            </div>
          </div>
        </div>

        <div class="p-6">
          <div v-if="loading" class="text-slate-600">Cargando cotización…</div>

          <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
            {{ error }}
          </div>

          <div v-else-if="info" class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl mb-4">
            {{ info }}
          </div>

          <div v-if="!loading && quote" class="space-y-4">
            <div class="grid md:grid-cols-2 gap-3 text-sm">
              <div><span class="text-slate-500">Folio:</span> <strong>{{ quote.folio || quote.id }}</strong></div>
              <div><span class="text-slate-500">Estatus:</span> <strong class="uppercase">{{ quote.status }}</strong></div>
              <div><span class="text-slate-500">Cliente:</span> <strong>{{ quote.client?.name || '—' }}</strong></div>
              <div><span class="text-slate-500">Centro:</span> <strong>{{ quote.centro?.name || '—' }}</strong></div>
              <div><span class="text-slate-500">Centro de costos:</span> <strong>{{ quote.centro_costo?.name || '—' }}</strong></div>
              <div><span class="text-slate-500">Marca:</span> <strong>{{ quote.brand?.name || '—' }}</strong></div>
            </div>

            <div class="border-t pt-4">
              <h2 class="font-bold text-lg mb-2">Ítems</h2>

              <div v-for="it in (quote.items || [])" :key="it.id" class="border border-slate-200 rounded-xl p-4 mb-3 bg-white">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="font-semibold">{{ it.description }}</div>
                    <div class="text-sm text-slate-600">Cantidad: {{ it.quantity }}</div>
                    <div v-if="it.notes" class="text-sm text-slate-500 mt-1">{{ it.notes }}</div>
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
                      <tr v-for="s in (it.services || [])" :key="s.id" class="border-t">
                        <td class="py-2">{{ s.service?.name || s.service_id }}</td>
                        <td class="py-2 text-center">{{ s.size || '—' }}</td>
                        <td class="py-2 text-center">{{ s.quantity }}</td>
                        <td class="py-2 text-right">{{ money(s.total) }}</td>
                      </tr>
                      <tr v-if="(it.services || []).length === 0" class="border-t">
                        <td class="py-2 text-slate-500" colspan="4">Sin servicios</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="border-t pt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
              <div class="text-slate-600">
                <div>Subtotal: <strong>{{ money(quote.subtotal) }}</strong></div>
                <div>IVA: <strong>{{ money(quote.iva) }}</strong></div>
                <div class="text-slate-900">Total: <strong>{{ money(quote.total) }}</strong></div>
              </div>

              <div v-if="!canAct" class="text-sm text-slate-500">
                Esta cotización ya no admite acciones.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Rechazo -->
      <div v-if="showReject" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg p-5">
          <div class="text-lg font-bold">Rechazar cotización</div>
          <div class="text-sm text-slate-600 mt-1">Comentario (opcional)</div>
          <textarea
            v-model="rejectReason"
            rows="4"
            class="w-full mt-3 px-4 py-3 rounded-xl border-2 border-slate-200"
            placeholder="Motivo de rechazo (opcional)"
          />
          <div class="mt-4 flex gap-2 justify-end">
            <button
              @click="showReject=false"
              :disabled="posting"
              class="px-4 py-2 rounded-lg bg-slate-200 text-slate-900 font-semibold disabled:opacity-60"
            >Cancelar</button>
            <button
              @click="reject"
              :disabled="posting"
              class="px-4 py-2 rounded-lg bg-red-600 text-white font-bold disabled:opacity-60"
            >Confirmar rechazo</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
