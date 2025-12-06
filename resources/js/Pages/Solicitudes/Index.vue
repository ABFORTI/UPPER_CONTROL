<script setup>
import { usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
  data: Object, // paginator
  filters: Object, // { estatus, servicio, year, week }
  servicios: Array,
  urls: Object // { index }
})

// Filtros tipo píldoras por estatus (idénticos a Facturación)
const sel = ref(props.filters?.estatus || '')
// Estatus fijos en orden coherente con el módulo
const estatuses = computed(() => ['pendiente', 'aprobada', 'rechazada'])
// Filtro avanzado único: servicio
const servicioSel = ref(props.filters?.servicio || '')
const yearSel = ref(props.filters?.year || new Date().getFullYear())
const weekSel = ref(props.filters?.week || '')

function applyFilter(){
  const params = { estatus: sel.value }
  if (servicioSel.value) params.servicio = servicioSel.value
  if (yearSel.value) params.year = yearSel.value
  if (weekSel.value) params.week = weekSel.value
  router.get(props.urls.index, params, { preserveState: true, replace: true })
}

function toPage(link){ if(link.url){ router.get(link.url, {}, {preserveState:true}) } }

// Mostramos la fecha exactamente como viene del backend (s.fecha)

// Exportar/Copy (cliente) - similar a Facturas
function toCsv(items){
  const headers = ['Folio','Usuario','Producto','Servicio','Centro','Centro de costos','Marca','Cantidad','Archivo','Estatus','Fecha']
  const rows = items.map(s => [
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
    const tsv = (props.data?.data||[]).map(s => [s.folio||s.id, s.cliente?.name||'-', s.producto||'-', s.servicio?.nombre||'-', s.centro?.nombre||'-', s.centroCosto?.nombre||'-', s.marca?.nombre||'-', s.cantidad??'', s.archivos?.length > 0 ? 'Sí' : 'No', s.estatus??'', s.fecha||''].join('\t')).join('\n')
    await navigator.clipboard.writeText(tsv)
  }catch(e){ console.warn('No se pudo copiar:', e) }
}

// Roles para ocultar botón crear a gerente (solo lectura)
const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles ?? [])
const isGerente = computed(() => roles.value.includes('gerente'))

</script>


<template>
  <div class="max-w-none px-4 py-6 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
      <h1 class="font-display text-3xl font-semibold tracking-wide uppercase text-slate-900 dark:text-slate-100">Solicitudes</h1>
      <a v-if="!isGerente" href="./solicitudes/create" class="btn btn-primary w-full md:w-auto text-center">AGREGAR +</a>
    </div>

    <!-- Tarjeta principal -->
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden">
      <!-- Acciones y filtros -->
      <div class="px-4 py-4 sm:px-6 lg:px-8 space-y-4 md:space-y-0 md:flex md:flex-wrap md:items-center md:justify-between md:gap-4">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full md:w-auto">
          <button @click="downloadExcel" class="w-full sm:w-auto px-4 py-2 rounded text-white font-semibold shadow-sm shadow-green-500/20" style="background:#22c55e">Excel</button>
          <button @click="copyTable" class="w-full sm:w-auto px-4 py-2 rounded text-white font-semibold shadow-sm shadow-slate-500/20" style="background:#64748b">Copiar</button>
        </div>

        <div class="flex flex-col gap-3 w-full md:w-auto md:flex-row md:flex-wrap md:items-center md:justify-end md:gap-3">
          <!-- Año -->
          <select v-model="yearSel" @change="applyFilter" class="border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 p-2 rounded w-full md:w-auto md:min-w-[120px]">
            <option v-for="y in [yearSel-2, yearSel-1, yearSel, yearSel+1]" :key="y" :value="y">{{ y }}</option>
          </select>

          <!-- Semana -->
          <select v-model="weekSel" @change="applyFilter" class="border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 p-2 rounded w-full md:w-auto md:min-w-[140px]">
            <option value="">Periodos</option>
            <option v-for="w in 53" :key="w" :value="w">Periodo {{ w }}</option>
          </select>

          <!-- Select de servicio -->
          <select v-model="servicioSel" @change="applyFilter" class="border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 p-2 rounded w-full md:w-auto md:min-w-[180px]">
            <option value="">Servicio: Todos</option>
            <option v-for="s in servicios" :key="s.id" :value="s.id">{{ s.nombre }}</option>
          </select>

          <!-- Píldoras de estatus -->
          <div class="flex flex-wrap sm:flex-nowrap gap-2 overflow-x-auto md:overflow-visible w-full py-1 md:w-auto">
            <button @click="sel=''; applyFilter()" :class="['flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border transition-colors', sel==='' ? 'text-white border-[#1A73E8]' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700']" :style="sel==='' ? 'background-color: #1A73E8' : ''">Todos</button>
            <button v-for="e in estatuses" :key="e" @click="sel=e; applyFilter()" :class="['flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border capitalize transition-colors', sel===e ? 'text-white border-[#1A73E8]' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700']" :style="sel===e ? 'background-color: #1A73E8' : ''">{{ e }}</button>
          </div>
        </div>
      </div>

      <!-- Tabla desktop -->
      <div class="px-4 pb-4 sm:px-6 lg:px-8 hidden md:block">
        <div class="rounded-lg shadow-sm border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950/40">
          <div class="overflow-x-auto">
          <table class="w-full text-xs md:text-sm xl:text-[0.95rem] 2xl:text-base table-auto">
            <thead class="bg-slate-800 text-white uppercase text-xs">
              <tr>
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
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Fecha</th>
                <th class="px-2 py-2 text-center align-top break-words xl:whitespace-nowrap">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in data.data" :key="s.id" class="border-t border-slate-100 dark:border-slate-800 even:bg-slate-50 dark:even:bg-slate-800/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/70 text-slate-800 dark:text-slate-100 transition-colors">
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
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[9rem] xl:px-3">{{ s.fecha || '' }}</td>
                <td class="px-2 py-2 leading-snug text-center break-words whitespace-normal xl:max-w-[10rem] xl:px-3">
                  <a :href="`./solicitudes/${s.id}`" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- Listado móvil -->
      <div class="md:hidden px-4 pb-4 sm:px-6 lg:px-8">
        <div class="space-y-4">
          <div v-for="s in data.data" :key="s.id" class="border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm bg-white dark:bg-slate-900/80 text-slate-800 dark:text-slate-100">
            <div class="flex items-start justify-between gap-3">
              <div>
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
      <div class="px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center justify-center md:justify-end gap-2">
        <button v-for="link in data.links" :key="link.label"
                @click="toPage(link)"
                class="px-3 py-1.5 rounded border text-sm min-w-[2.5rem] text-center border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                :class="{ 'bg-slate-900 text-white border-slate-900 dark:bg-blue-500 dark:border-blue-500 dark:text-white': link.active }"
                v-html="link.label" />
      </div>
    </div>
  </div>
</template>