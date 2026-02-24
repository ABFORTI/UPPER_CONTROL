<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  orden: { type: Object, required: true },
  cortes: { type: Array, default: () => [] },
})

const emit = defineEmits(['corteCreado'])

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles ?? [])
const canManageCortes = computed(() => {
  const allowed = ['admin', 'coordinador', 'team_leader', 'facturacion']
  return roles.value.some(role => allowed.includes(role))
})

// ── Formato moneda ──
const money = (v) => new Intl.NumberFormat('es-MX', {
  style: 'currency', currency: 'MXN',
  minimumFractionDigits: 2, maximumFractionDigits: 2,
}).format(Number(v) || 0)

// ── Formato fecha ──
const fmtDate = (d) => {
  if (!d) return '—'
  try {
    const date = new Date(d)
    if (isNaN(date.getTime())) return String(d)
    return new Intl.DateTimeFormat('es-MX', {
      year: 'numeric', month: '2-digit', day: '2-digit',
    }).format(date)
  } catch { return String(d) }
}

// ── Estado del modal ──
const showModal = ref(false)
const loading = ref(false)
const previewLoading = ref(false)
const error = ref('')
const successMsg = ref('')

// ── Formulario ──
const periodoInicio = ref('')
const periodoFin = ref('')
const crearOtHija = ref(true)
const conceptos = ref([])

// ── Inicializar con la semana actual ──
const today = new Date()
const dayOfWeek = today.getDay()
const mondayOffset = dayOfWeek === 0 ? -6 : 1 - dayOfWeek
const monday = new Date(today)
monday.setDate(today.getDate() + mondayOffset)
const sunday = new Date(monday)
sunday.setDate(monday.getDate() + 6)

const formatISO = (d) => d.toISOString().split('T')[0]
periodoInicio.value = formatISO(monday)
periodoFin.value = formatISO(sunday)

// ── Monto total calculado ──
const montoTotal = computed(() =>
  conceptos.value.reduce((sum, c) => sum + (Number(c.cantidad_cortada) || 0) * (Number(c.precio_unitario) || 0), 0)
)

// ── Abrir modal y obtener preview ──
async function openModal() {
  error.value = ''
  successMsg.value = ''
  showModal.value = true
  await fetchPreview()
}

async function fetchPreview() {
  previewLoading.value = true
  error.value = ''
  try {
    const response = await fetch(route('ot-cortes.preview', { ot: props.orden.id }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        periodo_inicio: periodoInicio.value || null,
        periodo_fin: periodoFin.value || null,
      }),
    })
    const data = await response.json()
    if (!response.ok) {
      error.value = data.message || 'Error al obtener preview'
      return
    }
    conceptos.value = (data.conceptos || []).map(c => ({
      ...c,
      cantidad_cortada: c.sugerencia_cantidad_corte,
    }))
  } catch (e) {
    error.value = 'Error de conexión al obtener preview.'
  } finally {
    previewLoading.value = false
  }
}

// ── Refrescar preview al cambiar periodo ──
watch([periodoInicio, periodoFin], () => {
  if (showModal.value && periodoInicio.value && periodoFin.value) {
    fetchPreview()
  }
})

// ── Confirmar corte ──
async function confirmarCorte() {
  loading.value = true
  error.value = ''
  successMsg.value = ''

  const detalles = conceptos.value
    .filter(c => Number(c.cantidad_cortada) > 0)
    .map(c => ({
      ot_servicio_id: c.concepto_tipo === 'servicio' ? c.ot_servicio_id : null,
      orden_item_id: c.concepto_tipo === 'item' ? c.orden_item_id : null,
      cantidad_cortada: Number(c.cantidad_cortada),
    }))

  if (detalles.length === 0) {
    error.value = 'Debe incluir al menos un concepto con cantidad > 0.'
    loading.value = false
    return
  }

  try {
    const response = await fetch(route('ot-cortes.store', { ot: props.orden.id }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        periodo_inicio: periodoInicio.value,
        periodo_fin: periodoFin.value,
        crear_ot_hija: crearOtHija.value,
        detalles,
      }),
    })
    const data = await response.json()
    if (!response.ok) {
      error.value = data.message || 'Error al crear el corte.'
      return
    }
    successMsg.value = data.message || 'Corte creado exitosamente.'
    emit('corteCreado', data.corte)
    // Recargar la página para refrescar datos
    setTimeout(() => {
      showModal.value = false
      router.visit(window.location.href, { replace: true, preserveScroll: true })
    }, 1500)
  } catch (e) {
    error.value = 'Error de conexión al crear el corte.'
  } finally {
    loading.value = false
  }
}

// ── Detalle de corte (expandir) ──
const expandedCorte = ref(null)
const changingStatus = ref(null)
function toggleCorte(id) {
  expandedCorte.value = expandedCorte.value === id ? null : id
}

async function cambiarEstatus(corte, nuevoEstatus) {
  changingStatus.value = corte.id
  error.value = ''
  successMsg.value = ''

  try {
    const response = await fetch(route('ot-cortes.updateEstatus', { corte: corte.id }), {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ estatus: nuevoEstatus }),
    })

    const data = await response.json()
    if (!response.ok) {
      error.value = data.message || 'No se pudo actualizar el estatus del corte.'
      return
    }

    successMsg.value = data.message || 'Estatus actualizado.'
    setTimeout(() => {
      router.visit(window.location.href, { replace: true, preserveScroll: true })
    }, 700)
  } catch (e) {
    error.value = 'Error de conexión al actualizar estatus del corte.'
  } finally {
    changingStatus.value = null
  }
}

// ── Badge de estatus ──
const estatusBadge = (estatus) => {
  const map = {
    draft: 'bg-gray-100 text-gray-700 border-gray-300 dark:bg-gray-700/40 dark:text-gray-200 dark:border-gray-600',
    ready_to_bill: 'bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-500/20 dark:text-blue-200 dark:border-blue-500/40',
    billed: 'bg-emerald-100 text-emerald-700 border-emerald-300 dark:bg-emerald-500/20 dark:text-emerald-200 dark:border-emerald-500/40',
    void: 'bg-red-100 text-red-700 border-red-300 dark:bg-red-500/20 dark:text-red-200 dark:border-red-500/40',
  }
  return map[estatus] || map.draft
}

const estatusLabel = (estatus) => {
  const map = { draft: 'Borrador', ready_to_bill: 'Listo p/ facturar', billed: 'Facturado', void: 'Anulado' }
  return map[estatus] || estatus
}
</script>

<template>
  <div class="space-y-4">
    <!-- Encabezado con botón -->
    <div class="bg-white rounded-2xl shadow-lg border-2 border-cyan-100 overflow-hidden dark:bg-slate-900/80 dark:border-cyan-500/30">
      <div class="bg-gradient-to-r from-cyan-600 to-blue-600 px-4 py-2 dark:from-cyan-500 dark:to-blue-500">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-bold text-white flex items-center gap-1.5 leading-tight">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
            Cortes de OT
          </h2>
            <button v-if="canManageCortes" @click="openModal"
                  class="px-3 py-1.5 bg-white/20 hover:bg-white/30 border border-white/30 text-white text-xs font-bold rounded-lg transition-all duration-200 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Generar Corte
          </button>
        </div>
      </div>

      <!-- OT Padre / Hija info -->
      <div v-if="orden.parent_ot_id" class="px-4 py-2 bg-cyan-50 border-b border-cyan-100 dark:bg-cyan-500/10 dark:border-cyan-500/20">
        <p class="text-xs text-cyan-800 dark:text-cyan-200">
          <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
          </svg>
          Esta es una <strong>OT hija</strong> (split #{{ orden.split_index }}) de la OT #{{ orden.parent_ot_id }}
        </p>
      </div>

      <!-- Status de OT -->
      <div v-if="orden.ot_status && orden.ot_status !== 'active'" class="px-4 py-2 border-b"
           :class="{
             'bg-amber-50 border-amber-100 dark:bg-amber-500/10 dark:border-amber-500/20': orden.ot_status === 'partial',
             'bg-gray-50 border-gray-100 dark:bg-gray-500/10 dark:border-gray-500/20': orden.ot_status === 'closed',
             'bg-red-50 border-red-100 dark:bg-red-500/10 dark:border-red-500/20': orden.ot_status === 'canceled',
           }">
        <p class="text-xs font-semibold"
           :class="{
             'text-amber-700 dark:text-amber-200': orden.ot_status === 'partial',
             'text-gray-700 dark:text-gray-200': orden.ot_status === 'closed',
             'text-red-700 dark:text-red-200': orden.ot_status === 'canceled',
           }">
          Estado OT: 
          <span v-if="orden.ot_status === 'partial'">Parcial (con corte pendiente de remanente)</span>
          <span v-else-if="orden.ot_status === 'closed'">Cerrada (todo cortado)</span>
          <span v-else-if="orden.ot_status === 'canceled'">Cancelada</span>
        </p>
      </div>

      <!-- Listado de cortes -->
      <div class="p-4">
        <div v-if="!cortes || cortes.length === 0" class="text-center py-6">
          <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <p class="text-sm text-gray-500 dark:text-slate-400">No hay cortes registrados para esta OT.</p>
        </div>

        <div v-else class="space-y-3">
          <div v-for="corte in cortes" :key="corte.id"
               class="border-2 rounded-xl overflow-hidden transition-all duration-200"
               :class="expandedCorte === corte.id ? 'border-cyan-300 dark:border-cyan-500/50' : 'border-gray-200 dark:border-slate-700'">
            <!-- Header del corte -->
            <button @click="toggleCorte(corte.id)"
                    class="w-full px-4 py-3 flex items-center justify-between gap-3 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
              <div class="flex items-center gap-3 min-w-0">
                <span class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ corte.folio_corte }}</span>
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full border" :class="estatusBadge(corte.estatus)">
                  {{ estatusLabel(corte.estatus) }}
                </span>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-sm font-bold text-emerald-700 dark:text-emerald-300">{{ money(corte.monto_total) }}</span>
                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': expandedCorte === corte.id }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </div>
            </button>

            <!-- Detalle expandible -->
            <div v-if="expandedCorte === corte.id" class="border-t border-gray-200 dark:border-slate-700 px-4 py-3 space-y-3 bg-gray-50/50 dark:bg-slate-800/30">
              <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                  <span class="text-gray-500 dark:text-slate-400">Periodo:</span>
                  <span class="ml-1 font-semibold text-gray-700 dark:text-slate-200">{{ fmtDate(corte.periodo_inicio) }} — {{ fmtDate(corte.periodo_fin) }}</span>
                </div>
                <div>
                  <span class="text-gray-500 dark:text-slate-400">Creado por:</span>
                  <span class="ml-1 font-semibold text-gray-700 dark:text-slate-200">{{ corte.created_by?.name || '—' }}</span>
                </div>
                <div v-if="corte.ot_hija">
                  <span class="text-gray-500 dark:text-slate-400">OT hija:</span>
                  <a :href="`/ordenes/${corte.ot_hija.id}`" class="ml-1 font-semibold text-cyan-600 hover:text-cyan-800 dark:text-cyan-400 dark:hover:text-cyan-300 underline">
                    {{ corte.ot_hija.folio }}
                  </a>
                </div>
              </div>

              <!-- Tabla de detalles -->
              <div class="overflow-x-auto">
                <table class="w-full text-xs">
                  <thead>
                    <tr class="border-b border-gray-200 dark:border-slate-600">
                      <th class="text-left py-1.5 px-2 text-gray-500 dark:text-slate-400 font-semibold">Servicio</th>
                      <th class="text-right py-1.5 px-2 text-gray-500 dark:text-slate-400 font-semibold">Cantidad</th>
                      <th class="text-right py-1.5 px-2 text-gray-500 dark:text-slate-400 font-semibold">P.U.</th>
                      <th class="text-right py-1.5 px-2 text-gray-500 dark:text-slate-400 font-semibold">Importe</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="det in corte.detalles" :key="det.id" class="border-b border-gray-100 dark:border-slate-700/50">
                      <td class="py-1.5 px-2 text-gray-700 dark:text-slate-200">{{ det.servicio_nombre }}</td>
                      <td class="py-1.5 px-2 text-right text-gray-700 dark:text-slate-200 font-mono">{{ det.cantidad_cortada }}</td>
                      <td class="py-1.5 px-2 text-right text-gray-700 dark:text-slate-200 font-mono">{{ money(det.precio_unitario_snapshot) }}</td>
                      <td class="py-1.5 px-2 text-right font-bold text-gray-800 dark:text-slate-100 font-mono">{{ money(det.importe_snapshot) }}</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="border-t-2 border-gray-300 dark:border-slate-600">
                      <td colspan="3" class="py-2 px-2 text-right font-bold text-gray-700 dark:text-slate-200">Total:</td>
                      <td class="py-2 px-2 text-right font-bold text-emerald-700 dark:text-emerald-300 font-mono">{{ money(corte.monto_total) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>

              <div class="flex flex-wrap gap-2 pt-1">
                <button v-if="canManageCortes && corte.estatus === 'ready_to_bill'"
                        @click="cambiarEstatus(corte, 'void')"
                        :disabled="changingStatus === corte.id"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-300 text-red-700 bg-red-50 hover:bg-red-100 disabled:opacity-60 dark:border-red-500/40 dark:text-red-200 dark:bg-red-500/10 dark:hover:bg-red-500/20">
                  Anular corte
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════ MODAL GENERAR CORTE ═══════ -->
    <Modal :show="showModal" max-width="3xl" @close="showModal = false">
      <div class="p-6 space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold text-gray-800 dark:text-slate-100 flex items-center gap-2">
            <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
            Generar Corte de OT #{{ orden.id }}
          </h2>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 dark:text-slate-500 dark:hover:text-slate-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Alertas -->
        <div v-if="error" class="bg-red-50 border-2 border-red-200 rounded-xl p-3 dark:bg-red-500/10 dark:border-red-500/40">
          <p class="text-sm text-red-700 dark:text-red-300">{{ error }}</p>
        </div>
        <div v-if="successMsg" class="bg-emerald-50 border-2 border-emerald-200 rounded-xl p-3 dark:bg-emerald-500/10 dark:border-emerald-500/40">
          <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ successMsg }}</p>
        </div>

        <!-- Periodo -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-slate-200 mb-1">Inicio del periodo</label>
            <input type="date" v-model="periodoInicio"
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-cyan-100 focus:border-cyan-400 transition-all dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100 dark:focus:ring-cyan-400/40 dark:focus:border-cyan-400/60" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-slate-200 mb-1">Fin del periodo</label>
            <input type="date" v-model="periodoFin"
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-cyan-100 focus:border-cyan-400 transition-all dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100 dark:focus:ring-cyan-400/40 dark:focus:border-cyan-400/60" />
          </div>
        </div>

        <!-- Tabla de conceptos -->
        <div v-if="previewLoading" class="text-center py-8">
          <svg class="animate-spin h-8 w-8 mx-auto text-cyan-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p class="text-sm text-gray-500 dark:text-slate-400 mt-2">Calculando preview...</p>
        </div>

        <div v-else-if="conceptos.length > 0" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b-2 border-gray-200 dark:border-slate-600">
                <th class="text-left py-2 px-2 text-gray-600 dark:text-slate-300 font-semibold">Servicio</th>
                <th class="text-right py-2 px-2 text-gray-600 dark:text-slate-300 font-semibold">Contratado</th>
                <th class="text-right py-2 px-2 text-gray-600 dark:text-slate-300 font-semibold">Ejecutado</th>
                <th class="text-right py-2 px-2 text-gray-600 dark:text-slate-300 font-semibold">Ya cortado</th>
                <th class="text-right py-2 px-2 text-gray-600 dark:text-slate-300 font-semibold">Disponible</th>
                <th class="text-center py-2 px-2 text-gray-600 dark:text-slate-300 font-semibold">Cantidad a cortar</th>
                <th class="text-right py-2 px-2 text-gray-600 dark:text-slate-300 font-semibold">P.U.</th>
                <th class="text-right py-2 px-2 text-gray-600 dark:text-slate-300 font-semibold">Importe</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(c, idx) in conceptos" :key="idx" class="border-b border-gray-100 dark:border-slate-700/50 hover:bg-gray-50 dark:hover:bg-slate-800/30">
                <td class="py-2 px-2 text-gray-700 dark:text-slate-200 font-medium">{{ c.servicio_nombre }}</td>
                <td class="py-2 px-2 text-right text-gray-600 dark:text-slate-300 font-mono">{{ c.contratado }}</td>
                <td class="py-2 px-2 text-right text-gray-600 dark:text-slate-300 font-mono">{{ c.ejecutado_total }}</td>
                <td class="py-2 px-2 text-right text-gray-600 dark:text-slate-300 font-mono">{{ c.cortado_previo }}</td>
                <td class="py-2 px-2 text-right font-semibold font-mono"
                    :class="c.ejecutado_no_cortado > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'">
                  {{ c.ejecutado_no_cortado }}
                </td>
                <td class="py-2 px-2 text-center">
                  <input type="number" v-model.number="c.cantidad_cortada"
                         :min="0" :max="c.ejecutado_no_cortado" step="1"
                         class="w-24 text-center px-2 py-1 border-2 rounded-lg font-mono text-sm transition-all
                                border-gray-200 focus:ring-2 focus:ring-cyan-200 focus:border-cyan-400
                                dark:bg-slate-900/60 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-cyan-400/40 dark:focus:border-cyan-400/60"
                         :class="{ 'border-red-400 dark:border-red-500': c.cantidad_cortada > c.ejecutado_no_cortado }" />
                </td>
                <td class="py-2 px-2 text-right text-gray-600 dark:text-slate-300 font-mono">{{ money(c.precio_unitario) }}</td>
                <td class="py-2 px-2 text-right font-bold text-gray-800 dark:text-slate-100 font-mono">
                  {{ money((Number(c.cantidad_cortada) || 0) * (Number(c.precio_unitario) || 0)) }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-gray-300 dark:border-slate-600">
                <td colspan="7" class="py-3 px-2 text-right font-bold text-gray-700 dark:text-slate-200 text-base">Total del corte:</td>
                <td class="py-3 px-2 text-right font-bold text-emerald-700 dark:text-emerald-300 text-base font-mono">{{ money(montoTotal) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div v-else class="text-center py-6">
          <p class="text-sm text-gray-500 dark:text-slate-400">No hay conceptos para mostrar. Verifique que la OT tenga servicios con avances.</p>
        </div>

        <!-- Opciones -->
        <div class="flex items-center gap-3 bg-gray-50 dark:bg-slate-800/40 rounded-xl px-4 py-3 border border-gray-200 dark:border-slate-700">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="crearOtHija"
                   class="rounded border-gray-300 text-cyan-600 shadow-sm focus:ring-cyan-500 dark:border-slate-500 dark:bg-slate-900/60 dark:focus:ring-cyan-400/60" />
            <span class="text-sm font-medium text-gray-700 dark:text-slate-200">Crear OT hija con remanente</span>
          </label>
          <span class="text-xs text-gray-500 dark:text-slate-400">(Se copiarán los conceptos con las cantidades restantes)</span>
        </div>

        <!-- Acciones -->
        <div class="flex items-center justify-end gap-3 pt-2">
          <button @click="showModal = false"
                  class="px-5 py-2.5 border-2 border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-all dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">
            Cancelar
          </button>
          <button @click="confirmarCorte" :disabled="loading || conceptos.length === 0"
                  class="px-6 py-2.5 bg-gradient-to-r from-cyan-600 to-blue-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center gap-2">
            <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span v-if="loading">Procesando...</span>
            <span v-else>Confirmar Corte</span>
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>
