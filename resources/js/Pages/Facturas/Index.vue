<script setup>
import { router, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
  filtros: { type: Object, default: () => ({}) },
  urls: { type: Object, default: () => ({}) },
  estatuses: { type: Array, default: () => [] },
  centros: { type: Array, default: () => [] },
  centrosCosto: { type: Array, default: () => [] }
})

// Filtros
const sel = ref(props.filtros?.estatus || '')
const centroSel = ref(props.filtros?.centro || '')
const centroCostoSel = ref(props.filtros?.centro_costo || '')
const yearSel = ref(props.filtros?.year || new Date().getFullYear())
const weekSel = ref(props.filtros?.week || '')
const estatuses = computed(() => props.estatuses || [])
function applyFilter(){
  const params = {}
  if (sel.value) params.estatus = sel.value
  if (centroSel.value) params.centro = centroSel.value
  if (centroCostoSel.value) params.centro_costo = centroCostoSel.value
  if (yearSel.value) params.year = yearSel.value
  if (weekSel.value) params.week = weekSel.value
  currentPage.value = 1
  router.get(props.urls.base, params, { preserveState: true, replace: true })
}
// Nota: el buscador fue removido; para limpiar filtros usa la píldora "Todos"

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

// Utilidades UI
function badgeClass(estatus){
  const e = String(estatus||'').toLowerCase()
  if (e === 'pagado') return 'bg-green-100 text-green-700'        // Pagado: verde
  if (e === 'por_pagar') return 'bg-amber-100 text-amber-700'      // Por pagar: ámbar
  if (e === 'facturado') return 'bg-cyan-100 text-cyan-700'        // Facturado: cian para distinguir
  if (e === 'autorizada_cliente') return 'bg-blue-100 text-blue-700'
  if (e === 'sin_factura') return 'bg-slate-100 text-slate-700'
  return 'bg-gray-100 text-gray-700'
}

// Periodo (semana ISO) desde created_at
function isoWeekNumber (dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d)) return ''
  // Convertir a UTC y calcular semana ISO (Mon=1..Sun=7)
  const target = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
  const dayNr = target.getUTCDay() || 7
  target.setUTCDate(target.getUTCDate() + 4 - dayNr)
  const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1))
  return Math.ceil((((target - yearStart) / 86400000) + 1) / 7)
}

// Paginación (cliente)
const perPage = 10
const currentPage = ref(Number(props.filtros?.page || 1))
function sortItems(arr){
  return [...(arr||[])].sort((a,b)=>{
    const ad = a.created_at || ''
    const bd = b.created_at || ''
    if (ad !== bd) return ad > bd ? -1 : 1
    return (b.id||0) - (a.id||0)
  })
}
const processed = computed(()=> sortItems(props.items))
const totalPages = computed(()=> Math.max(1, Math.ceil((processed.value.length)/perPage)))
const pageItems = computed(()=> {
  const start = (currentPage.value-1)*perPage
  return processed.value.slice(start, start+perPage)
})

function goToPage(p){
  if (p < 1 || p > totalPages.value) return
  currentPage.value = p
  const params = {}
  if (sel.value) params.estatus = sel.value
  if (centroSel.value) params.centro = centroSel.value
  if (centroCostoSel.value) params.centro_costo = centroCostoSel.value
  params.page = String(currentPage.value)
  router.get(props.urls.base, params, { preserveState: true, replace: true })
}
</script>

<template>
  <div class="max-w-none px-1 py-3 sm:px-2 lg:px-3">
    <div class="rounded-xl border bg-white shadow-sm overflow-hidden">
      <div class="px-2 pt-3 pb-2 sm:px-3 lg:px-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight uppercase text-slate-900">Facturación</h1>
        <div class="inline-flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-900/5 px-3 py-2 self-start sm:self-end">
          <div class="leading-tight">
            <div class="text-[0.65rem] uppercase tracking-wide text-slate-500">Periodo actual</div>
            <div class="text-lg font-semibold text-slate-900">Periodo {{ currentPeriod }}</div>
          </div>
          <div class="text-xs text-slate-500">Año {{ currentYear }}</div>
        </div>
      </div>

      <div class="px-2 py-2 sm:px-3 lg:px-4 space-y-3 lg:space-y-0 lg:flex lg:flex-wrap lg:items-center lg:justify-start lg:gap-3">
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full lg:w-auto">
          <select v-model="centroSel" @change="applyFilter" class="border p-2 rounded w-full sm:w-auto sm:min-w-[180px]">
            <option value="">Todos los centros</option>
            <option v-for="c in (props.centros||[])" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
          <select v-model="centroCostoSel" @change="applyFilter" class="border p-2 rounded w-full sm:w-auto sm:min-w-[200px]">
            <option value="">Todos los centros de costo</option>
            <option v-for="cc in (props.centrosCosto||[])" :key="cc.id" :value="cc.id">{{ cc.nombre }}</option>
          </select>
          <select v-model="yearSel" @change="applyFilter" class="border p-2 rounded w-full sm:w-auto sm:min-w-[120px]">
            <option v-for="y in [yearSel-2, yearSel-1, yearSel, yearSel+1]" :key="y" :value="y">{{ y }}</option>
          </select>
          <select v-model="weekSel" @change="applyFilter" class="border p-2 rounded w-full sm:w-auto sm:min-w-[140px]">
            <option value="">Periodos</option>
            <option v-for="w in 53" :key="w" :value="w">Periodo {{ w }}</option>
          </select>
        </div>

        <div class="flex flex-wrap gap-2 overflow-x-auto lg:overflow-visible w-full py-1 lg:w-auto">
          <button @click="sel=''; applyFilter()" :class="['flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border transition-colors', sel==='' ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel==='' ? 'background-color: #1A73E8' : ''">Todos</button>
          <button v-for="e in estatuses" :key="e" @click="sel=e; applyFilter()" :class="['flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border capitalize transition-colors', sel===e ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel===e ? 'background-color: #1A73E8' : ''">{{ e }}</button>
        </div>
      </div>

      <div class="px-2 sm:px-3 lg:px-4 pb-3 hidden md:block">
        <div class="rounded-lg shadow-sm border border-slate-200">
          <div class="overflow-x-auto">
            <table class="w-full text-[0.65rem] sm:text-[0.75rem] xl:text-xs 2xl:text-sm table-auto">
              <thead class="bg-slate-800 text-white uppercase text-xs">
                <tr>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top w-[3.75rem] md:w-[4.25rem] xl:w-[4.75rem]">ID</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top w-[7rem] md:w-[7.5rem] xl:w-[8.5rem]">OT</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top w-[6rem] md:w-[6.5rem] xl:w-[7rem]">Servicio</th>
                  <th class="px-1 sm:px-1.5 py-2 text-left align-top w-[7.5rem] md:w-[8.5rem] xl:w-[9rem] 2xl:w-[10rem]">Centro de costo</th>
                  <th class="px-1 sm:px-1.5 py-2 text-left align-top w-[7rem] md:w-[7.5rem] xl:w-[8rem] 2xl:w-[8.5rem]">Centro</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top w-[4.25rem] md:w-[4.75rem] xl:w-[5.25rem]">Periodo</th>
                  <th class="px-1.5 sm:px-2 py-2 text-right align-top w-[5.5rem] md:w-[6.25rem] xl:w-[6.75rem]">Total</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top w-[7rem]">Estatus</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top w-[8.5rem] md:w-[10rem] xl:w-[14rem] 2xl:w-[18rem]">Folio</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top w-[6rem] md:w-[6.5rem] xl:w-[7rem]">Fecha</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top w-[6rem] md:w-[6.75rem] xl:w-[7.5rem]">Acción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="f in pageItems" :key="f.id" class="border-t even:bg-slate-50 hover:bg-slate-100/60">
                  <td class="px-1.5 sm:px-2 py-2.5 leading-snug font-mono whitespace-nowrap">#{{ f.id }}</td>
                  <td class="px-1.5 sm:px-2 py-2.5 leading-snug whitespace-normal break-words w-[7rem] md:w-[7.5rem] xl:w-[8.5rem] 2xl:w-[10rem]">
                    <template v-if="f.multi">
                      <span :title="f.ots_label || ''">OTs: varias</span>
                    </template>
                    <template v-else-if="f.ots_label">OTs: {{ f.ots_label }}</template>
                    <template v-else>OT #{{ f.orden_id }}</template>
                  </td>
                  <td class="px-1.5 sm:px-2 py-2.5 leading-snug whitespace-normal break-words w-[6rem] md:w-[6.5rem] xl:w-[7rem]">{{ f.servicio || '—' }}</td>
                  <td class="px-1 sm:px-1.5 py-2.5 leading-snug whitespace-normal break-words w-[7.5rem] md:w-[8.5rem] xl:w-[9rem] 2xl:w-[10rem]">{{ f.centro_costo || '—' }}</td>
                  <td class="px-1 sm:px-1.5 py-2.5 leading-snug whitespace-normal break-words w-[7rem] md:w-[7.5rem] xl:w-[8rem] 2xl:w-[8.5rem]">{{ f.centro || '—' }}</td>
                  <td class="px-1.5 sm:px-2 py-2.5 leading-snug whitespace-nowrap text-center w-[4.25rem] md:w-[4.75rem] xl:w-[5.25rem]">{{ isoWeekNumber(f.created_at) || '—' }}</td>
                  <td class="px-1.5 sm:px-2 py-2.5 leading-snug text-right whitespace-nowrap font-semibold">${{ f.total }}</td>
                  <td class="px-1.5 sm:px-2 py-2.5 leading-snug whitespace-normal break-words">
                    <span class="px-2 py-1 rounded-full text-[0.6rem] sm:text-[0.65rem] font-semibold uppercase tracking-wide" :class="badgeClass(f.estatus)">{{ f.estatus }}</span>
                  </td>
                  <td class="px-1.5 sm:px-2 py-2.5 leading-snug break-all w-[8.5rem] md:w-[10rem] xl:w-[14rem] 2xl:w-[18rem]">{{ f.folio || '—' }}</td>
                  <td class="px-1.5 sm:px-2 py-2.5 leading-snug whitespace-nowrap">{{ f.created_at?.slice(0,16) || '—' }}</td>
                  <td class="px-1.5 sm:px-2 py-2.5 leading-snug">
                    <div class="flex flex-wrap items-center justify-start gap-2 min-h-[2rem]">
                      <Link :href="f.url" :class="['inline-flex items-center gap-1 px-3 py-1.5 rounded text-white text-[0.65rem] sm:text-xs font-medium transition-colors', f.estatus === 'autorizada_cliente' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-700 hover:bg-slate-800']">
                        <span v-if="f.estatus === 'autorizada_cliente'">Generar</span>
                        <span v-else>Ver</span>
                      </Link>
                    </div>
                  </td>
                </tr>
                <tr v-if="pageItems.length===0">
                  <td colspan="11" class="p-4 text-center text-sm text-slate-500">Sin registros</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="md:hidden px-2 sm:px-3 lg:px-4 pb-3">
        <div class="space-y-4">
          <div v-for="f in pageItems" :key="f.id" class="border border-slate-200 rounded-xl p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-wide text-slate-500">Folio</div>
                <div class="text-lg font-semibold text-slate-900">#{{ f.id }}</div>
              </div>
              <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide" :class="badgeClass(f.estatus)">{{ f.estatus }}</span>
            </div>
            <div class="mt-3 space-y-2 text-sm">
              <div class="flex justify-between gap-3"><span class="text-slate-500">OT</span><span class="font-medium text-right text-slate-800">
                <template v-if="f.multi">
                  <span :title="f.ots_label || ''">Varias</span>
                </template>
                <template v-else-if="f.ots_label">{{ f.ots_label }}</template>
                <template v-else>#{{ f.orden_id }}</template>
              </span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Servicio</span><span class="font-medium text-right text-slate-800">{{ f.servicio || '—' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Centro</span><span class="font-medium text-right text-slate-800">{{ f.centro || '—' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Centro costo</span><span class="font-medium text-right text-slate-800">{{ f.centro_costo || '—' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Periodo</span><span class="font-medium text-right text-slate-800">{{ isoWeekNumber(f.created_at) || '—' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Total</span><span class="font-semibold text-right text-slate-900">${{ f.total }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Folio</span><span class="font-medium text-right text-slate-800">{{ f.folio || '—' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Fecha</span><span class="font-medium text-right text-slate-800">{{ f.created_at?.slice(0,16) || '—' }}</span></div>
            </div>
            <div class="mt-4 flex justify-end">
              <Link :href="f.url" :class="['inline-flex items-center gap-1.5 px-3 py-1.5 rounded text-white text-sm font-medium transition-colors', f.estatus === 'autorizada_cliente' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-700 hover:bg-slate-800']">
                <span v-if="f.estatus === 'autorizada_cliente'">Generar</span>
                <span v-else>Ver</span>
              </Link>
            </div>
          </div>
          <div v-if="pageItems.length===0" class="text-center text-sm text-slate-500">Sin registros</div>
        </div>
      </div>

      <div class="px-2 sm:px-3 lg:px-4 py-3 flex items-center justify-end gap-2">
        <button class="px-3 py-1.5 rounded border text-sm disabled:opacity-50" :disabled="currentPage<=1" @click="goToPage(currentPage-1)">Anterior</button>
        <span class="text-sm">Página {{ currentPage }} de {{ totalPages }}</span>
        <button class="px-3 py-1.5 rounded border text-sm disabled:opacity-50" :disabled="currentPage>=totalPages" @click="goToPage(currentPage+1)">Siguiente</button>
      </div>
    </div>
  </div>
</template>
