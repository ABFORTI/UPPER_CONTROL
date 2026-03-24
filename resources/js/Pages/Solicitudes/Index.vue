<script setup>
import { usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
  data: Object, // paginator
  filters: Object, // { estatus, servicio, year, week }
  servicios: Array,
  marcas: Array,
  centros: Array,
  centrosCostos: Array,
  urls: Object, // { index }
  can: Object,
})

// Filtros tipo píldoras por estatus (idénticos a Facturación)
const sel = ref(props.filters?.estatus || '')
// Estatus fijos en orden coherente con el módulo
const estatuses = computed(() => ['pendiente', 'aprobada', 'rechazada'])
// Filtros alineados con la página de Órdenes
const servicioSel = ref(props.filters?.servicio || '')
const marcaSel = ref(props.filters?.marca || '')
const centroSel = ref(props.filters?.centro || '')
const centroCostoSel = ref(props.filters?.centro_costo || '')
const folioSel = ref(props.filters?.folio || '')
const desdeSel = ref(props.filters?.desde || '')
const hastaSel = ref(props.filters?.hasta || '')
const yearSel = ref(props.filters?.year || '')
const weekSel = ref(props.filters?.week || '')
const showDeleted = ref(!!props.filters?.show_deleted)
const availableYears = computed(() => {
  const y = new Date().getFullYear()
  return [y - 2, y - 1, y, y + 1]
})
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
const hasPagination = computed(() => Array.isArray(props.data?.links) && props.data.links.length > 0)
const solicitudesBaseUrl = computed(() => String(props.urls?.index || '/solicitudes').replace(/\/+$/, ''))

function applyFilter(){
  const params = {}
  if (sel.value) params.estatus = sel.value
  if (servicioSel.value) params.servicio = servicioSel.value
  if (marcaSel.value) params.marca = marcaSel.value
  if (centroSel.value) params.centro = centroSel.value
  if (centroCostoSel.value) params.centro_costo = centroCostoSel.value
  if (folioSel.value) params.folio = folioSel.value
  if (desdeSel.value) params.desde = desdeSel.value
  if (hastaSel.value) params.hasta = hastaSel.value
  if (yearSel.value) params.year = yearSel.value
  if (weekSel.value) params.week = weekSel.value
  if (showDeleted.value) params.show_deleted = 1
  router.get(props.urls.index, params, { preserveState: true, replace: true })
}

function clearFilters(){
  sel.value = ''
  servicioSel.value = ''
  marcaSel.value = ''
  centroSel.value = ''
  centroCostoSel.value = ''
  folioSel.value = ''
  desdeSel.value = ''
  hastaSel.value = ''
  yearSel.value = ''
  weekSel.value = ''
  showDeleted.value = false
  router.get(props.urls.index, {}, { preserveState: true, replace: true })
}

function eliminarSolicitud(id){
  if (!confirm('¿Seguro? Esto se puede restaurar')) return
  router.delete(`${solicitudesBaseUrl.value}/${id}`)
}

function restaurarSolicitud(id){
  router.post(`${solicitudesBaseUrl.value}/${id}/restore`)
}

function forzarSolicitud(id){
  const motivo = prompt('Motivo para eliminar definitivamente:')
  if (!motivo || motivo.trim().length < 5) return
  router.delete(`${solicitudesBaseUrl.value}/${id}/force`, { data: { motivo } })
}

function cancelarSolicitud(id){
  const motivo = prompt('Motivo de cancelación:')
  if (!motivo || motivo.trim().length < 5) return
  router.post(`${solicitudesBaseUrl.value}/${id}/cancelar`, { motivo })
}

function toPage(link){ if(link.url){ router.get(link.url, {}, {preserveState:true}) } }

// Mostramos la fecha exactamente como viene del backend (s.fecha)

// Exportar/Copy (cliente) - similar a Facturas
function toCsv(items){
  const headers = ['ID Solicitud','Folio','Usuario','Producto','Servicio','Centro','Centro de costos','Marca','Cantidad','Archivo','Estatus','Periodo','Fecha']
  const rows = items.map(s => [
    s.id_solicitud ?? s.id,
    s.folio || s.id,
    s.cliente?.name || '-',
    s.producto || '-',
    s.servicio?.nombre || '-',
    s.centro?.nombre || '-',
    s.centroCosto?.nombre || '-',
    s.marca?.nombre || '-',
    s.cantidad ?? '',
    s.archivos?.length > 0 ? 'Sí' : 'No',
    s.estatus ?? '',
    s.periodo ?? (isoWeekNumber(s.fecha_iso || s.created_at_raw || s.fecha) || ''),
    s.fecha || ''
  ])
  const csv = [headers, ...rows].map(r => r.map(v => `"${String(v??'').replaceAll('"','""')}"`).join(',')).join('\n')
  return csv
}
function downloadExcel(){
  const csv = toCsv(props.data?.data || [])
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'solicitudes.csv'
  a.click()
  URL.revokeObjectURL(url)
}
async function copyTable(){
  try{
    const tsv = (props.data?.data||[]).map(s => [s.id_solicitud ?? s.id, s.folio||s.id, s.cliente?.name||'-', s.producto||'-', s.servicio?.nombre||'-', s.centro?.nombre||'-', s.centroCosto?.nombre||'-', s.marca?.nombre||'-', s.cantidad??'', s.archivos?.length > 0 ? 'Sí' : 'No', s.estatus??'', s.periodo ?? (isoWeekNumber(s.fecha_iso || s.created_at_raw || s.fecha) || ''), s.fecha||''].join('\t')).join('\n')
    await navigator.clipboard.writeText(tsv)
  }catch(e){ console.warn('No se pudo copiar:', e) }
}

// Roles para ocultar botón crear a gerente_upper (solo lectura)
const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles ?? [])
const isGerente = computed(() => roles.value.includes('gerente_upper'))
const isCoordinador = computed(() => roles.value.includes('coordinador'))

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
  <div class="max-w-none px-2 py-1.5 sm:px-3 lg:px-4">
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm px-2.5 py-2 sm:px-3 sm:py-2.5">
      <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
          <h1 class="font-display text-xl sm:text-2xl font-semibold tracking-wide uppercase text-slate-900 dark:text-slate-100 leading-tight">SOLICITUDES</h1>
        </div>

        <div class="flex w-full flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-end lg:w-auto">
          <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-900/5 px-2.5 py-1.5">
            <div class="leading-tight">
              <div class="text-[0.65rem] uppercase tracking-wide text-slate-500">Periodo actual</div>
              <div class="text-base font-semibold text-slate-900">Periodo {{ currentPeriod }}</div>
            </div>
            <div class="text-xs text-slate-500">Año {{ currentYear }}</div>
          </div>

          <a v-if="!isGerente && !isCoordinador"
             href="./solicitudes/create"
             class="inline-flex h-9 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white bg-[#1A73E8] hover:bg-[#1557b0] transition-colors">
            AGREGAR +
          </a>
        </div>
      </div>

      <div class="mt-2 rounded-lg border border-slate-200/90 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/50 p-2.5">
        <div class="grid grid-cols-1 gap-2 md:grid-cols-3 xl:grid-cols-12">
          <div class="flex items-center gap-1.5 xl:col-span-2">
            <button @click="downloadExcel" class="inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-semibold text-white shadow-sm shadow-green-500/20" style="background:#22c55e">Excel</button>
            <button @click="copyTable" class="inline-flex h-9 items-center justify-center rounded-md px-3 text-sm font-semibold text-white shadow-sm shadow-slate-500/20" style="background:#64748b">Copiar</button>
          </div>

          <select v-model="centroSel" @change="applyFilter" class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100 xl:col-span-3">
            <option value="">Todos los centros</option>
            <option v-for="c in (centros || [])" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>

          <select v-model="centroCostoSel" @change="applyFilter" class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100 xl:col-span-3">
            <option value="">Todos los centros de costo</option>
            <option v-for="cc in (centrosCostos || [])" :key="cc.id" :value="cc.id">{{ cc.nombre }}</option>
          </select>

          <select v-model="servicioSel" @change="applyFilter" class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100 xl:col-span-3">
            <option value="">Servicio: Todos</option>
            <option v-for="s in servicios" :key="s.id" :value="s.id">{{ s.nombre }}</option>
          </select>

          <select v-model="marcaSel" @change="applyFilter" class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100 xl:col-span-3">
            <option value="">Marca: Todas</option>
            <option v-for="m in (marcas || [])" :key="m.id" :value="m.id">{{ m.nombre }}</option>
          </select>

          <input v-model="folioSel" @change="applyFilter" type="text" placeholder="Folio"
                 class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100 xl:col-span-1" />

          <select v-model="yearSel" @change="applyFilter" class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100 xl:col-span-1">
            <option value="">Año</option>
            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
          </select>

          <select v-model="weekSel" @change="applyFilter" class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100 xl:col-span-1">
            <option value="">Periodos</option>
            <option v-for="w in 53" :key="w" :value="w">Periodo {{ w }}</option>
          </select>

          <input
            v-model="desdeSel"
            type="date"
            @change="applyFilter"
            class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100 xl:col-span-2"
            title="Fecha inicial"
          />

          <input
            v-model="hastaSel"
            type="date"
            @change="applyFilter"
            class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100 xl:col-span-2"
            title="Fecha final"
          />
        </div>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap items-center gap-1.5">
            <button @click="sel=''; applyFilter()" :class="['px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors', sel==='' ? 'text-white border-[#1A73E8]' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700']" :style="sel==='' ? 'background-color: #1A73E8' : ''">Todos</button>
            <button v-for="e in estatuses" :key="e" @click="sel=e; applyFilter()" :class="['px-3 py-1.5 rounded-full text-xs font-semibold border capitalize transition-colors', sel===e ? 'text-white border-[#1A73E8]' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700']" :style="sel===e ? 'background-color: #1A73E8' : ''">{{ e }}</button>
          </div>

          <div class="flex flex-wrap items-center justify-end gap-2 ml-auto">
            <label v-if="can?.manage_deleted" class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
              <input type="checkbox" v-model="showDeleted" @change="applyFilter" />
              Mostrar eliminados
            </label>

            <button
              type="button"
              @click="clearFilters"
              class="inline-flex h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
            >
              Limpiar filtros
            </button>
          </div>
        </div>
      </div>

      <!-- Tabla desktop -->
      <div class="mt-2 hidden md:block">
        <div class="rounded-lg shadow-sm border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950/40">
          <div class="overflow-x-auto">
          <table class="w-full text-xs md:text-sm xl:text-[0.95rem] 2xl:text-base table-auto">
            <thead class="bg-slate-800 text-white uppercase text-xs">
              <tr>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">ID Solicitud</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Folio</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Usuario</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Producto</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Servicio</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Centro</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Centro de costos</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Marca</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Cantidad</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Archivo</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Estatus</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Periodo</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Fecha</th>
                <th class="w-44 px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in data.data" :key="s.id" class="border-t border-slate-100 dark:border-slate-800 even:bg-slate-50 dark:even:bg-slate-800/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/70 text-slate-800 dark:text-slate-100 transition-colors">
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[6rem] xl:px-3">{{ s.id_solicitud ?? s.id }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[9rem] xl:px-3">{{ s.folio || s.id }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[11rem] xl:px-3">{{ s.cliente?.name || '-' }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[11rem] xl:px-3">{{ s.producto || '-' }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[12rem] xl:px-3">{{ s.servicio?.nombre || '-' }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[11rem] xl:px-3">{{ s.centro?.nombre || '-' }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[11rem] xl:px-3">{{ s.centroCosto?.nombre || '-' }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[10rem] xl:px-3">{{ s.marca?.nombre || '-' }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[6rem] xl:px-3">{{ s.cantidad }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[8rem] xl:px-3">
                  <span v-if="s.archivos?.length > 0" class="inline-flex items-center gap-1 text-green-600 dark:text-emerald-300">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    {{ s.archivos.length }}
                  </span>
                  <span v-else class="text-gray-400 dark:text-slate-500">-</span>
                </td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[10rem] xl:px-3">
                  <span class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide"
                        :class="{
                          'bg-green-100 text-green-700 dark:bg-emerald-500/20 dark:text-emerald-300': s.estatus==='aprobada',
                          'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200': s.estatus==='pendiente',
                          'bg-red-100 text-red-700 dark:bg-rose-500/20 dark:text-rose-200': s.estatus==='rechazada'
                        }">{{ s.estatus }}</span>
                </td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[6rem] xl:px-3">{{ s.periodo ?? (isoWeekNumber(s.fecha_iso || s.created_at_raw || s.fecha) || '—') }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[9rem] xl:px-3">{{ s.fecha || '' }}</td>
                <td class="w-44 px-2 py-2 leading-snug text-center whitespace-nowrap xl:max-w-[10rem] xl:px-3">
                  <div class="inline-flex items-center justify-center gap-1.5 flex-nowrap whitespace-nowrap">
                  <a v-if="!s.deleted_at" :href="`./solicitudes/${s.id}`" title="Ver" aria-label="Ver" class="inline-flex h-7 w-7 items-center justify-center bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400 text-white rounded-lg shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                  </a>
                  <div v-if="can?.manage_deleted" class="inline-flex items-center justify-center gap-1.5 flex-nowrap whitespace-nowrap">
                    <button v-if="!s.deleted_at" @click="eliminarSolicitud(s.id)" title="Eliminar" aria-label="Eliminar" class="inline-flex h-7 w-7 items-center justify-center rounded-lg shadow-sm bg-red-600 text-white hover:bg-red-700">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12M9 7V5h6v2m-7 4v6m4-6v6M5 7l1 12h12l1-12"/>
                      </svg>
                    </button>
                    <button v-if="!s.deleted_at" @click="cancelarSolicitud(s.id)" title="Cancelar" aria-label="Cancelar" class="inline-flex h-7 w-7 items-center justify-center rounded-lg shadow-sm bg-amber-600 text-white hover:bg-amber-700">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/>
                      </svg>
                    </button>
                    <button v-if="s.deleted_at" @click="restaurarSolicitud(s.id)" title="Restaurar" aria-label="Restaurar" class="inline-flex h-7 w-7 items-center justify-center rounded-lg shadow-sm bg-emerald-600 text-white hover:bg-emerald-700">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                      </svg>
                    </button>
                    <button v-if="s.deleted_at" @click="forzarSolicitud(s.id)" title="Definitivo" aria-label="Definitivo" class="inline-flex h-7 w-7 items-center justify-center rounded-lg shadow-sm bg-black text-white hover:bg-slate-900">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12M9 7V5h6v2m-7 4v6m4-6v6M5 7l1 12h12l1-12"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 8l8 8"/>
                      </svg>
                    </button>
                  </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- Listado móvil -->
      <div class="mt-2 md:hidden">
        <div class="space-y-4">
          <div v-for="s in data.data" :key="s.id" class="border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm bg-white dark:bg-slate-900/80 text-slate-800 dark:text-slate-100">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">ID Solicitud</div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ s.id_solicitud ?? s.id }}</div>
                <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Folio</div>
                <div class="text-lg font-semibold text-slate-900 dark:text-white">{{ s.folio || s.id }}</div>
              </div>
              <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide"
                    :class="{
                      'bg-green-100 text-green-700 dark:bg-emerald-500/20 dark:text-emerald-300': s.estatus==='aprobada',
                      'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200': s.estatus==='pendiente',
                      'bg-red-100 text-red-700 dark:bg-rose-500/20 dark:text-rose-200': s.estatus==='rechazada'
                    }">{{ s.estatus }}</span>
            </div>

            <div class="mt-3 space-y-2 text-sm">
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Usuario</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.cliente?.name || '-' }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Producto</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.producto || '-' }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Servicio</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.servicio?.nombre || '-' }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Centro</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.centro?.nombre || '-' }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Centro de costos</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.centroCosto?.nombre || '-' }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Marca</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.marca?.nombre || '-' }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Cantidad</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.cantidad }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Periodo</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.periodo ?? (isoWeekNumber(s.fecha_iso || s.created_at_raw || s.fecha) || '—') }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Fecha</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.fecha || '' }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-slate-500 dark:text-slate-400">Archivos</span>
                <span class="font-medium text-slate-800 dark:text-slate-100 text-right">{{ s.archivos?.length > 0 ? `${s.archivos.length} archivo(s)` : '—' }}</span>
              </div>
            </div>

            <div class="mt-4">
              <a :href="`./solicitudes/${s.id}`" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400 text-white text-sm font-semibold transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Ver detalle
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Paginación -->
      <div v-if="hasPagination" class="px-2 sm:px-3 lg:px-4 py-3 flex flex-wrap items-center justify-center md:justify-end gap-2">
        <button v-for="link in data.links" :key="link.label"
                @click="toPage(link)"
                class="px-3 py-1.5 rounded border text-sm min-w-[2.5rem] text-center border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                :class="{ 'bg-slate-900 text-white border-slate-900 dark:bg-blue-500 dark:border-blue-500 dark:text-white': link.active }"
                v-html="link.label" />
      </div>
    </div>
  </div>
</template>