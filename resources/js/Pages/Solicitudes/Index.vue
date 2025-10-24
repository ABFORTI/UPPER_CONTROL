<script setup>
import { router } from '@inertiajs/vue3'
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

</script>


<template>
  <div class="p-6 max-w-none">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h1 class="font-display text-3xl font-semibold tracking-wide uppercase">Solicitudes</h1>
      <a href="./solicitudes/create" class="btn btn-primary">AGREGAR +</a>
    </div>

    <!-- Tarjeta principal -->
    <div class="rounded-xl border bg-white">
      <!-- Acciones y filtros (alineados a la derecha) -->
      <div class="px-8 py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex items-center gap-2">
          <button @click="downloadExcel" class="px-4 py-2 rounded text-white" style="background:#22c55e">Excel</button>
          <button @click="copyTable" class="px-4 py-2 rounded text-white" style="background:#64748b">Copiar</button>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto justify-end">
          <!-- Año -->
          <select v-model="yearSel" @change="applyFilter" class="border p-2 rounded min-w-[100px]">
            <option v-for="y in [yearSel-2, yearSel-1, yearSel, yearSel+1]" :key="y" :value="y">{{ y }}</option>
          </select>
          
          <!-- Semana -->
          <select v-model="weekSel" @change="applyFilter" class="border p-2 rounded min-w-[120px]">
            <option value="">Periodos</option>
            <option v-for="w in 53" :key="w" :value="w">Periodo {{ w }}</option>
          </select>
          
          <!-- Píldoras de estatus -->
          <div class="flex flex-wrap items-center gap-2">
            <button @click="sel=''; applyFilter()" :class="['px-4 py-2 rounded-full text-base border', sel==='' ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel==='' ? 'background-color: #1A73E8' : ''">Todos</button>
            <button v-for="e in estatuses" :key="e" @click="sel=e; applyFilter()" :class="['px-4 py-2 rounded-full text-base border capitalize', sel===e ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel===e ? 'background-color: #1A73E8' : ''">{{ e }}</button>
          </div>
          <!-- Select de servicio -->
          <div>
            <select v-model="servicioSel" @change="applyFilter" class="border p-2 rounded min-w-[220px] md:min-w-[260px] lg:min-w-[150px]">
              <option value="">Servicio: Todos</option>
              <option v-for="s in servicios" :key="s.id" :value="s.id">{{ s.nombre }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Tabla -->
      <div class="px-8 pb-4">
        <div class="rounded-lg overflow-hidden shadow-sm">
          <table class="min-w-full text-base">
            <thead class="bg-slate-800 text-white uppercase text-sm">
              <tr>
                <th class="p-2">Folio</th>
                <th class="p-2">Usuario</th>
                <th class="p-2">Producto</th>
                <th class="p-2">Servicio</th>
                <th class="p-2">Centro</th>
                <th class="p-2">Centro de costos</th>
                <th class="p-2">Marca</th>
                <th class="p-2">Cantidad</th>
                <th class="p-2">Archivo</th>
                <th class="p-2">Estatus</th>
                <th class="p-2">Fecha</th>
                <th class="p-2">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in data.data" :key="s.id" class="border-t even:bg-slate-50 hover:bg-slate-100/60">
                <td class="px-4 py-3">{{ s.folio || s.id }}</td>
                <td class="px-4 py-3">{{ s.cliente?.name || '-' }}</td>
                <td class="px-4 py-3">{{ s.producto || '-' }}</td>
                <td class="px-4 py-3">{{ s.servicio?.nombre || '-' }}</td>
                <td class="px-4 py-3">{{ s.centro?.nombre || '-' }}</td>
                <td class="px-4 py-3">{{ s.centroCosto?.nombre || '-' }}</td>
                <td class="px-4 py-3">{{ s.marca?.nombre || '-' }}</td>
                <td class="px-4 py-3">{{ s.cantidad }}</td>
                <td class="px-4 py-3 text-center">
                  <span v-if="s.archivos?.length > 0" class="inline-flex items-center gap-1 text-green-600">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    {{ s.archivos.length }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-4 py-3">
                  <span class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide"
                        :class="{
                          'bg-green-100 text-green-700': s.estatus==='aprobada',
                          'bg-amber-100 text-amber-700': s.estatus==='pendiente',
                          'bg-red-100 text-red-700': s.estatus==='rechazada'
                        }">{{ s.estatus }}</span>
                </td>
                <td class="px-4 py-3">{{ s.fecha || '' }}</td>
                <td class="px-4 py-3">
                  <a :href="`./solicitudes/${s.id}`" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
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

      <!-- Paginación -->
      <div class="px-8 py-3 flex items-center justify-end gap-2">
        <button v-for="link in data.links" :key="link.label"
                @click="toPage(link)"
                class="px-3 py-1.5 rounded border text-sm"
                :class="{ 'bg-slate-900 text-white border-slate-900': link.active }"
                v-html="link.label" />
      </div>
    </div>
  </div>
</template>