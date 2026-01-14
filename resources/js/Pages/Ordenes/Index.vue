<script setup>
import { computed, ref, watch, watchEffect } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  data: Object,
  filters: Object,
  servicios: Array,
  centros: Array,
  centrosCostos: Array,
  urls: Object
})

const rows = computed(()=> props.data?.data ?? [])
const isPeriod = computed(()=> !!(props.filters?.week))

// Permisos: solo admin o facturacion pueden marcar y facturar
const page = usePage()
const canFacturar = computed(() => {
  const roles = page.props?.auth?.user?.roles || []
  return roles.includes('admin') || roles.includes('facturacion')
})

// Selección múltiple para facturación (usar array para v-model de checkboxes)
const selectedIds = ref([])
const anySelected = computed(()=> (selectedIds.value?.length || 0) > 0)

function isSelectable(o){
  const sinFactura = !o.facturacion || o.facturacion === 'sin_factura'
  return o.estatus === 'autorizada_cliente' && sinFactura
}

function openBatch(){
  const ids = (selectedIds.value || []).slice()
  if (ids.length === 0) return
  router.get(props.urls?.facturas_batch_create, { ids: ids.join(',') })
}

const sel = ref(props.filters?.estatus || '')
const factSel = ref(props.filters?.facturacion || '')
const centroSel = ref(props.filters?.centro || '')
const centroCostoSel = ref(props.filters?.centro_costo || '')
const yearSel = ref(props.filters?.year || new Date().getFullYear())
const weekSel = ref(props.filters?.week || '')
const estatuses = computed(() => [
  'generada',
  'asignada',
  'en_proceso',
  'completada',
  'autorizada_cliente'
])

const currentPeriod = computed(() => {
  if (weekSel.value) return Number(weekSel.value)
  const now = new Date()
  const value = isoWeekNumber(now)
  return value || '—'
})

const currentYear = computed(() => {
  const parsed = Number(yearSel.value)
  if (!Number.isNaN(parsed) && parsed) return parsed
  return new Date().getFullYear()
})

function applyFilter(){
  const params = {}
  if (sel.value) params.estatus = sel.value
  if (factSel.value) params.facturacion = factSel.value
  if (centroSel.value) params.centro = centroSel.value
  if (centroCostoSel.value) params.centro_costo = centroCostoSel.value
  if (yearSel.value) params.year = yearSel.value
  if (weekSel.value) params.week = weekSel.value
  router.get(props.urls.index, params, { preserveState: true, replace: true })
}

const exportParams = computed(() => {
  const base = { ...(props.filters || {}) }

  // sincronizar con la UI (lo que el usuario ve actualmente)
  if (sel.value) base.estatus = sel.value
  else delete base.estatus

  if (factSel.value) base.facturacion = factSel.value
  else delete base.facturacion

  if (centroSel.value) base.centro = centroSel.value
  else delete base.centro

  if (centroCostoSel.value) base.centro_costo = centroCostoSel.value
  else delete base.centro_costo

  if (yearSel.value) base.year = yearSel.value
  else delete base.year

  if (weekSel.value) base.week = weekSel.value
  else delete base.week

  base.format = 'xlsx'
  return base
})

const exportUrl = computed(() => {
  const url = props.urls?.export
  if (!url) return '#'
  return route('ordenes.export', exportParams.value)
})

const exportFacturacionUrl = computed(() => {
  const url = props.urls?.export_facturacion
  if (!url) return '#'
  return route('ordenes.exportFacturacion', exportParams.value)
})

function factBadgeClass(v){
  const e = String(v || '').toLowerCase()
  if (e === 'pagado') return 'bg-green-100 text-green-700'
  if (e === 'por_pagar') return 'bg-amber-100 text-amber-700'
  if (e === 'facturado') return 'bg-cyan-100 text-cyan-700'
  if (e === 'sin_factura') return 'bg-slate-100 text-slate-700'
  return 'bg-gray-100 text-gray-700'
}

watch(rows, (val) => {
  if (isPeriod.value && Array.isArray(val)) {
    selectedIds.value = val.filter(v => isSelectable(v)).map(r => Number(r.id))
  }
}, { immediate: true })

watchEffect(() => {
  if (isPeriod.value) {
    const val = rows.value || []
    selectedIds.value = (Array.isArray(val) ? val : []).filter(v => isSelectable(v)).map(r => Number(r.id))
  } else {
    selectedIds.value = []
  }
})

function isoWeekNumber(dateStr){
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d)) return ''
  const target = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
  const dayNr = target.getUTCDay() || 7
  target.setUTCDate(target.getUTCDate() + 4 - dayNr)
  const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1))
  return Math.ceil((((target - yearStart) / 86400000) + 1) / 7)
}
</script>

<template>
  <div class="max-w-none px-1 py-3 sm:px-2 lg:px-3">
    <div class="rounded-xl border bg-white shadow-sm overflow-hidden">
      <div class="px-2 pt-3 pb-2 sm:px-3 lg:px-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight uppercase text-slate-900">Órdenes de Trabajo</h1>
        <div class="inline-flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-900/5 px-3 py-2 self-start sm:self-end">
          <div class="leading-tight">
            <div class="text-[0.65rem] uppercase tracking-wide text-slate-500">Periodo actual</div>
            <div class="text-lg font-semibold text-slate-900">Periodo {{ currentPeriod }}</div>
          </div>
          <div class="text-xs text-slate-500">Año {{ currentYear }}</div>
        </div>
      </div>

      <div class="px-2 py-2 sm:px-3 lg:px-4 space-y-3 lg:space-y-0 lg:flex lg:flex-wrap lg:items-center lg:justify-start lg:gap-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full lg:w-auto lg:justify-start">
          <button v-if="canFacturar && anySelected" @click="openBatch" class="w-full sm:w-auto px-4 py-2 rounded text-white font-semibold" style="background:#1A73E8">Registrar factura</button>
        </div>

        <div class="flex flex-col gap-3 w-full lg:w-auto lg:flex-row lg:flex-wrap lg:items-center lg:justify-start lg:gap-3">
          <select v-model="centroSel" @change="applyFilter" class="border p-2 rounded w-full lg:w-auto lg:min-w-[180px]">
            <option value="">Todos los centros</option>
            <option v-for="c in (props.centros||[])" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>

          <select v-model="centroCostoSel" @change="applyFilter" class="border p-2 rounded w-full lg:w-auto lg:min-w-[200px]">
            <option value="">Todos los centros de costo</option>
            <option v-for="cc in (props.centrosCostos||[])" :key="cc.id" :value="cc.id">{{ cc.nombre }}</option>
          </select>

          <select v-model="yearSel" @change="applyFilter" class="border p-2 rounded w-full lg:w-auto lg:min-w-[120px]">
            <option v-for="y in [yearSel-2, yearSel-1, yearSel, yearSel+1]" :key="y" :value="y">{{ y }}</option>
          </select>

          <select v-model="weekSel" @change="applyFilter" class="border p-2 rounded w-full lg:w-auto lg:min-w-[140px]">
            <option value="">Periodos</option>
            <option v-for="w in 53" :key="w" :value="w">Periodo {{ w }}</option>
          </select>

          <a :href="exportUrl"
             class="w-full lg:w-auto inline-flex items-center justify-center px-4 py-2 rounded font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors">
            Descargar Excel
          </a>

          <a :href="exportFacturacionUrl"
             class="w-full lg:w-auto inline-flex items-center justify-center px-4 py-2 rounded font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors">
            Excel facturación
          </a>

          <div class="flex flex-wrap sm:flex-nowrap gap-2 overflow-x-auto lg:overflow-visible w-full py-1 lg:w-auto lg:justify-start">
            <button @click="sel=''; applyFilter()" :class="['flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border transition-colors', sel==='' ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel==='' ? 'background-color: #1A73E8' : ''">Todos</button>
            <button v-for="e in estatuses" :key="e" @click="sel=e; applyFilter()" :class="['flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border capitalize transition-colors', sel===e ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel===e ? 'background-color: #1A73E8' : ''">{{ e }}</button>
          </div>

          <div class="flex items-center gap-2 w-full lg:w-auto lg:justify-start">
            <button @click="factSel = (factSel==='sin_factura'?'': 'sin_factura'); applyFilter()"
              :class="['w-full md:w-auto px-4 py-2 rounded-full text-sm font-semibold border uppercase transition-colors', factSel==='sin_factura' ? 'text-white border-[#0ea5e9]' : 'bg-white text-slate-700 border-slate-300']"
              :style="factSel==='sin_factura' ? 'background-color: #0ea5e9' : ''">
              Sin factura
            </button>
          </div>
        </div>
      </div>

      <div v-if="isPeriod" class="px-2 pb-3 sm:px-3 lg:px-4">
        <div class="rounded-lg shadow-sm border border-slate-200">
          <div class="overflow-x-auto">
            <div class="max-h-[60vh] overflow-y-auto overscroll-contain">
              <table class="w-full text-[0.7rem] md:text-xs 2xl:text-sm table-auto">
                <thead class="bg-slate-800 text-white uppercase text-xs">
                  <tr>
                    <th v-if="canFacturar" class="w-10 px-1 sm:px-1.5 py-2 text-center align-top"></th>
                    <th class="w-16 px-1 sm:px-1.5 py-2 text-center align-top whitespace-nowrap">ID</th>
                    <th class="px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Producto</th>
                    <th class="px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Servicio</th>
                    <th class="hidden xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Centro</th>
                    <th class="hidden xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Área</th>
                    <th class="px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Estatus</th>
                    <th class="hidden 2xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Centro de costo</th>
                    <th class="hidden 2xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Marca</th>
                    <th class="hidden xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Facturación</th>
                    <th class="hidden xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">TL</th>
                    <th class="hidden 2xl:table-cell w-16 px-1.5 sm:px-2 py-2 text-center align-top whitespace-nowrap">Periodo</th>
                    <th class="w-28 px-1.5 sm:px-2 py-2 text-center align-top whitespace-nowrap">Fecha</th>
                    <th class="w-36 px-1.5 sm:px-2 py-2 text-center align-top whitespace-nowrap">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="o in rows" :key="o.id" class="border-t even:bg-slate-50 hover:bg-slate-100/60">
                    <td v-if="canFacturar" class="w-10 px-1 sm:px-1.5 py-3 text-center align-middle">
                      <input
                        type="checkbox"
                        :disabled="!isSelectable(o)"
                        :class="!isSelectable(o)
                          ? 'opacity-40 cursor-not-allowed accent-gray-300'
                          : 'cursor-pointer accent-[#1A73E8] hover:accent-[#1557b0]'
                        "
                        :title="!isSelectable(o) ? 'No seleccionable: ya facturada o sin autorización del cliente' : 'Seleccionar para facturar'"
                        v-model="selectedIds"
                        :value="Number(o.id)"
                      />
                    </td>
                    <td class="w-16 px-1 sm:px-1.5 py-3 leading-snug text-center font-mono whitespace-nowrap">#{{ o.id }}</td>
                    <td class="px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.producto || '-' }}</td>
                    <td class="px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.servicio?.nombre || '-' }}</td>
                    <td class="hidden xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.centro?.nombre || '-' }}</td>
                    <td class="hidden xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.area?.nombre || '-' }}</td>
                    <td class="px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">
                      <span class="px-2.5 py-1 rounded-full text-[0.6rem] font-semibold uppercase tracking-wide"
                            :class="{
                              'bg-slate-100 text-slate-700 border border-slate-300': o.estatus==='generada',
                              'bg-blue-100 text-blue-700 border border-blue-300': o.estatus==='asignada',
                              'bg-orange-100 text-orange-700 border border-orange-300': o.estatus==='en_proceso',
                              'bg-emerald-100 text-emerald-700 border border-emerald-300': o.estatus==='completada',
                              'bg-indigo-100 text-indigo-700 border border-indigo-300': o.estatus==='autorizada_cliente'
                            }">{{ o.estatus }}</span>
                    </td>
                    <td class="hidden 2xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.centro_costo?.nombre || '-' }}</td>
                    <td class="hidden 2xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.marca?.nombre || '-' }}</td>
                    <td class="hidden xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">
                      <span :class="['px-2.5 py-1 rounded-full text-[0.6rem] font-semibold uppercase tracking-wide', factBadgeClass(o.facturacion)]">{{ o.facturacion }}</span>
                    </td>
                    <td class="hidden xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.team_leader?.name || '—' }}</td>
                    <td class="hidden 2xl:table-cell w-16 px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-nowrap">{{ isoWeekNumber(o.created_at_raw || o.fecha_iso || o.fecha) || '—' }}</td>
                    <td class="w-28 px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-nowrap">{{ o.fecha }}</td>
                    <td class="w-36 px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">
                      <div class="flex flex-wrap items-center justify-center gap-2 xl:flex-col xl:items-center xl:gap-1 2xl:flex-row">
                        <a :href="o.urls.show" class="inline-flex items-center gap-1.5 xl:gap-1 px-3 xl:px-2 py-1.5 xl:py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs xl:text-[0.6rem] font-medium rounded-lg transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                          </svg>
                          Ver
                        </a>
                        <a v-if="o.estatus==='completada' && o.calidad_resultado==='pendiente'" :href="o.urls.calidad" class="inline-flex items-center gap-1.5 xl:gap-1 px-3 xl:px-2 py-1.5 xl:py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs xl:text-[0.6rem] font-medium rounded-lg transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          </svg>
                          Calidad
                        </a>
                        <a v-if="canFacturar && o.estatus==='autorizada_cliente'" :href="o.urls.facturar" class="inline-flex items-center gap-1.5 xl:gap-1 px-3 xl:px-2 py-1.5 xl:py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs xl:text-[0.6rem] font-medium rounded-lg transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                          </svg>
                          Facturar
                        </a>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div v-else>
        <div class="px-2 pb-3 sm:px-3 lg:px-4 hidden md:block">
          <div class="rounded-lg shadow-sm border border-slate-200">
            <div class="overflow-x-auto">
              <table class="w-full text-[0.7rem] md:text-xs 2xl:text-sm table-auto">
                <thead class="bg-slate-800 text-white uppercase text-xs">
                  <tr>
                    <th v-if="canFacturar" class="w-10 px-1 sm:px-1.5 py-2 text-center align-top"></th>
                    <th class="w-16 px-1 sm:px-1.5 py-2 text-center align-top whitespace-nowrap">ID</th>
                    <th class="px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Producto</th>
                    <th class="px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Servicio</th>
                    <th class="hidden xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Centro</th>
                    <th class="hidden xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Área</th>
                    <th class="px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Estatus</th>
                    <th class="hidden 2xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Centro de costo</th>
                    <th class="hidden 2xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Marca</th>
                    <th class="hidden xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">Facturación</th>
                    <th class="hidden xl:table-cell px-1.5 sm:px-2 py-2 text-center align-top break-words whitespace-normal">TL</th>
                    <th class="hidden 2xl:table-cell w-16 px-1.5 sm:px-2 py-2 text-center align-top whitespace-nowrap">Periodo</th>
                    <th class="w-28 px-1.5 sm:px-2 py-2 text-center align-top whitespace-nowrap">Fecha</th>
                    <th class="w-36 px-1.5 sm:px-2 py-2 text-center align-top whitespace-nowrap">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="o in rows" :key="o.id" class="border-t even:bg-slate-50 hover:bg-slate-100/60">
                    <td v-if="canFacturar" class="w-10 px-1 sm:px-1.5 py-3 text-center align-middle">
                      <input
                        type="checkbox"
                        :disabled="!isSelectable(o)"
                        :class="!isSelectable(o)
                          ? 'opacity-40 cursor-not-allowed accent-gray-300'
                          : 'cursor-pointer accent-[#1A73E8] hover:accent-[#1557b0]'
                        "
                        :title="!isSelectable(o) ? 'No seleccionable: ya facturada o sin autorización del cliente' : 'Seleccionar para facturar'"
                        v-model="selectedIds"
                        :value="Number(o.id)"
                      />
                    </td>
                    <td class="w-16 px-1 sm:px-1.5 py-3 leading-snug text-center font-mono whitespace-nowrap">#{{ o.id }}</td>
                    <td class="px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.producto || '-' }}</td>
                    <td class="px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.servicio?.nombre || '-' }}</td>
                    <td class="hidden xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.centro?.nombre || '-' }}</td>
                    <td class="hidden xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.area?.nombre || '-' }}</td>
                    <td class="px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">
                      <span class="px-2.5 py-1 rounded-full text-[0.6rem] font-semibold uppercase tracking-wide"
                            :class="{
                              'bg-slate-100 text-slate-700 border border-slate-300': o.estatus==='generada',
                              'bg-blue-100 text-blue-700 border border-blue-300': o.estatus==='asignada',
                              'bg-orange-100 text-orange-700 border border-orange-300': o.estatus==='en_proceso',
                              'bg-emerald-100 text-emerald-700 border border-emerald-300': o.estatus==='completada',
                              'bg-indigo-100 text-indigo-700 border border-indigo-300': o.estatus==='autorizada_cliente'
                            }">{{ o.estatus }}</span>
                    </td>
                    <td class="hidden 2xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.centro_costo?.nombre || '-' }}</td>
                    <td class="hidden 2xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.marca?.nombre || '-' }}</td>
                    <td class="hidden xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">
                      <span :class="['px-2.5 py-1 rounded-full text-[0.6rem] font-semibold uppercase tracking-wide', factBadgeClass(o.facturacion)]">{{ o.facturacion }}</span>
                    </td>
                    <td class="hidden xl:table-cell px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">{{ o.team_leader?.name || '—' }}</td>
                    <td class="hidden 2xl:table-cell w-16 px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-nowrap">{{ isoWeekNumber(o.created_at_raw || o.fecha_iso || o.fecha) || '—' }}</td>
                    <td class="w-28 px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-nowrap">{{ o.fecha }}</td>
                    <td class="w-36 px-1.5 sm:px-2 py-3 leading-snug text-center whitespace-normal break-words">
                      <div class="flex flex-wrap items-center justify-center gap-2 xl:flex-col xl:items-center xl:gap-1 2xl:flex-row">
                        <a :href="o.urls.show" class="inline-flex items-center gap-1.5 xl:gap-1 px-3 xl:px-2 py-1.5 xl:py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs xl:text-[0.6rem] font-medium rounded-lg transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                          </svg>
                          Ver
                        </a>
                        <a v-if="o.estatus==='completada' && o.calidad_resultado==='pendiente'" :href="o.urls.calidad" class="inline-flex items-center gap-1.5 xl:gap-1 px-3 xl:px-2 py-1.5 xl:py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs xl:text-[0.6rem] font-medium rounded-lg transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          </svg>
                          Calidad
                        </a>
                        <a v-if="canFacturar && o.estatus==='autorizada_cliente'" :href="o.urls.facturar" class="inline-flex items-center gap-1.5 xl:gap-1 px-3 xl:px-2 py-1.5 xl:py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs xl:text-[0.6rem] font-medium rounded-lg transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                          </svg>
                          Facturar
                        </a>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="md:hidden px-2 pb-3 sm:px-3 lg:px-4">
          <div class="space-y-4">
            <div v-for="o in rows" :key="o.id" class="border border-slate-200 rounded-xl p-4 shadow-sm">
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                  <input v-if="canFacturar" type="checkbox" :disabled="!isSelectable(o)" :class="!isSelectable(o) ? 'opacity-40 cursor-not-allowed accent-gray-300' : 'cursor-pointer accent-[#1A73E8] hover:accent-[#1557b0]'" :title="!isSelectable(o) ? 'No seleccionable: ya facturada o sin autorización del cliente' : 'Seleccionar para facturar'" v-model="selectedIds" :value="Number(o.id)" />
                  <div>
                    <div class="text-xs uppercase tracking-wide text-slate-500">Folio</div>
                    <div class="text-lg font-semibold text-slate-900">#{{ o.id }}</div>
                  </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide"
                      :class="{
                        'bg-slate-100 text-slate-700 border border-slate-300': o.estatus==='generada',
                        'bg-blue-100 text-blue-700 border border-blue-300': o.estatus==='asignada',
                        'bg-orange-100 text-orange-700 border border-orange-300': o.estatus==='en_proceso',
                        'bg-emerald-100 text-emerald-700 border border-emerald-300': o.estatus==='completada',
                        'bg-indigo-100 text-indigo-700 border border-indigo-300': o.estatus==='autorizada_cliente'
                      }">{{ o.estatus }}</span>
              </div>

              <div class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Producto</span>
                  <span class="font-medium text-slate-800 text-right">{{ o.producto || '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Servicio</span>
                  <span class="font-medium text-slate-800 text-right">{{ o.servicio?.nombre || '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Centro</span>
                  <span class="font-medium text-slate-800 text-right">{{ o.centro?.nombre || '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Área</span>
                  <span class="font-medium text-slate-800 text-right">{{ o.area?.nombre || '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Centro de costo</span>
                  <span class="font-medium text-slate-800 text-right">{{ o.centro_costo?.nombre || '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Marca</span>
                  <span class="font-medium text-slate-800 text-right">{{ o.marca?.nombre || '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Facturación</span>
                  <span :class="['px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide', factBadgeClass(o.facturacion)]">{{ o.facturacion }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Team Leader</span>
                  <span class="font-medium text-slate-800 text-right">{{ o.team_leader?.name || '—' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Periodo</span>
                  <span class="font-medium text-slate-800 text-right">{{ isoWeekNumber(o.created_at_raw || o.fecha_iso || o.fecha) || '—' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                  <span class="text-slate-500">Fecha</span>
                  <span class="font-medium text-slate-800 text-right">{{ o.fecha }}</span>
                </div>
              </div>

              <div class="mt-4 flex flex-wrap gap-2">
                <a :href="o.urls.show" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                  Ver
                </a>
                <a v-if="o.estatus==='completada' && o.calidad_resultado==='pendiente'" :href="o.urls.calidad" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Calidad
                </a>
                <a v-if="canFacturar && o.estatus==='autorizada_cliente'" :href="o.urls.facturar" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                  Facturar
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!isPeriod" class="px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center justify-center md:justify-end gap-2">
        <Link v-for="link in data.links" :key="link.label" :href="link.url || '#'"
              class="px-3 py-1.5 rounded border text-sm min-w-[2.5rem] text-center"
              :class="{'bg-slate-900 text-white border-slate-900': link.active}"
              v-html="link.label" />
      </div>
    </div>
  </div>
</template>
