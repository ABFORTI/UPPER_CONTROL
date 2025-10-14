<script setup>
import { router, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
  filtros: { type: Object, default: () => ({}) },
  urls: { type: Object, default: () => ({}) },
  estatuses: { type: Array, default: () => [] }
})

// Filtros
const sel = ref(props.filtros?.estatus || '')
const yearSel = ref(props.filtros?.year || new Date().getFullYear())
const weekSel = ref(props.filtros?.week || '')
function applyFilter(){
  const params = {}
  if (sel.value) params.estatus = sel.value
  if (yearSel.value) params.year = yearSel.value
  if (weekSel.value) params.week = weekSel.value
  currentPage.value = 1
  router.get(props.urls.base, params, { preserveState: true, replace: true })
}
// Nota: el buscador fue removido; para limpiar filtros usa la píldora "Todos"

// Utilidades UI
function badgeClass(estatus){
  const e = String(estatus||'').toLowerCase()
  if (e === 'pagado') return 'bg-green-100 text-green-700'
  if (e === 'facturado') return 'bg-emerald-100 text-emerald-700'
  if (e === 'por_pagar') return 'bg-amber-100 text-amber-700'
  if (e === 'autorizada_cliente') return 'bg-blue-100 text-blue-700'
  return 'bg-gray-100 text-gray-700'
}

// Exportar/Copy (cliente)
function toCsv(items){
  const headers = ['ID','OT','Servicio','Centro','Total','Estatus','Folio','Fecha']
  const rows = items.map(f => [
    f.id,
    `OT ${f.orden_id ?? ''}`,
    f.servicio ?? '',
    f.centro ?? '',
    f.total ?? '',
    f.estatus ?? '',
    f.folio ?? '',
    (f.created_at||'').slice(0,16)
  ])
  const csv = [headers, ...rows].map(r => r.map(v => `"${String(v??'').replaceAll('"','""')}"`).join(',')).join('\n')
  return csv
}
function downloadExcel(){
  const csv = toCsv(props.items || [])
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'facturacion.csv'
  a.click()
  URL.revokeObjectURL(url)
}
async function copyTable(){
  try{
    const tsv = (props.items||[]).map(f => [f.id, `OT ${f.orden_id??''}`, f.servicio??'', f.centro??'', f.total??'', f.estatus??'', f.folio??'', (f.created_at||'').slice(0,16)].join('\t')).join('\n')
    await navigator.clipboard.writeText(tsv)
    // opcional: feedback simple
    // alert('Copiado al portapapeles')
  }catch(e){ console.warn('No se pudo copiar:', e) }
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
  params.page = String(currentPage.value)
  router.get(props.urls.base, params, { preserveState: true, replace: true })
}
</script>

<template>
  <div class="p-6 max-w-none mx-auto">
    <!-- Header -->
    <h1 class="text-3xl font-extrabold tracking-tight mb-2 uppercase">Facturación</h1>

    <!-- Contenedor principal en tarjeta blanca -->
    <div class="rounded-xl border bg-white">
      <!-- Acciones y filtros dentro de la tarjeta -->
  <div class="px-8 py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex items-center gap-2">
          <button @click="downloadExcel" class="px-4 py-2 rounded text-white" style="background:#22c55e">Excel</button>
          <button @click="copyTable" class="px-4 py-2 rounded text-white" style="background:#64748b">Copiar</button>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
          <!-- Año -->
          <select v-model="yearSel" @change="applyFilter" class="border p-2 rounded min-w-[100px]">
            <option v-for="y in [yearSel-2, yearSel-1, yearSel, yearSel+1]" :key="y" :value="y">{{ y }}</option>
          </select>
          
          <!-- Semana -->
          <select v-model="weekSel" @change="applyFilter" class="border p-2 rounded min-w-[120px]">
            <option value="">Periodos</option>
            <option v-for="w in 53" :key="w" :value="w">Periodo {{ w }}</option>
          </select>
          
          <!-- Filtros de estatus (píldoras) -->
          <div class="flex flex-wrap items-center gap-2">
            <button @click="sel=''; applyFilter()" :class="['px-4 py-2 rounded-full text-base border', sel==='' ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel==='' ? 'background-color: #1A73E8' : ''">Todos</button>
            <button v-for="e in estatuses" :key="e" @click="sel=e; applyFilter()" :class="['px-4 py-2 rounded-full text-base border capitalize', sel===e ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel===e ? 'background-color: #1A73E8' : ''">{{ e }}</button>
          </div>
        </div>
      </div>

      <!-- Tabla -->
      <div class="px-8 pb-4">
        <div class="rounded-lg overflow-hidden shadow-sm">
        <table class="min-w-full text-base">
        <thead class="bg-slate-800 text-white uppercase text-sm">
          <tr>
            <th class="px-4 py-3 text-left">ID</th>
            <th class="px-4 py-3 text-left">OT</th>
            <th class="px-4 py-3 text-left">Servicio</th>
            <th class="px-4 py-3 text-left">Centro</th>
            <th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3 text-left">Estatus</th>
            <th class="px-4 py-3 text-left">Folio</th>
            <th class="px-4 py-3 text-left">Fecha</th>
            <th class="px-4 py-3 text-right">Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in pageItems" :key="f.id" class="border-t even:bg-slate-50 hover:bg-slate-100/60">
            <td class="px-4 py-3 font-mono">#{{ f.id }}</td>
            <td class="px-4 py-3">OT #{{ f.orden_id }}</td>
            <td class="px-4 py-3">{{ f.servicio || '—' }}</td>
            <td class="px-4 py-3">{{ f.centro || '—' }}</td>
            <td class="px-4 py-3 text-right">${{ f.total }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 rounded text-xs font-medium" :class="badgeClass(f.estatus)">{{ f.estatus }}</span>
            </td>
            <td class="px-4 py-3">{{ f.folio || '—' }}</td>
            <td class="px-4 py-3 whitespace-nowrap">{{ f.created_at?.slice(0,16) }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="f.url" :class="['inline-flex items-center gap-2 px-3 py-1.5 rounded text-white', f.estatus === 'autorizada_cliente' ? 'bg-blue-500 hover:bg-blue-600' : 'bg-slate-700 hover:bg-slate-800']">
                <span v-if="f.estatus === 'autorizada_cliente'">Generar</span>
                <span v-else>Ver</span>
              </Link>
            </td>
          </tr>
          <tr v-if="!(pageItems?.length) && !(props.items?.length)">
            <td colspan="9" class="p-6 text-center text-slate-500">Sin registros</td>
          </tr>
        </tbody>
        </table>
        </div>
      </div>

      <!-- Paginación dentro de la tarjeta -->
      <div class="px-8 py-3 flex items-center justify-end gap-2">
        <button class="px-3 py-1.5 rounded border text-sm disabled:opacity-50" :disabled="currentPage<=1" @click="goToPage(currentPage-1)">Anterior</button>
        <span class="text-sm">Página {{ currentPage }} de {{ totalPages }}</span>
        <button class="px-3 py-1.5 rounded border text-sm disabled:opacity-50" :disabled="currentPage>=totalPages" @click="goToPage(currentPage+1)">Siguiente</button>
      </div>
    </div>
  </div>
</template>
