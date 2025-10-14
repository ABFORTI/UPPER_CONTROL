<script setup>
import { computed, ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({
  data: Object,
  filters: Object,
  servicios: Array,
  urls: Object
})

const rows = computed(()=> props.data?.data ?? [])

// Filtros unificados por estatus (píldoras) con conjunto fijo
const sel = ref(props.filters?.estatus || '')
const yearSel = ref(props.filters?.year || new Date().getFullYear())
const weekSel = ref(props.filters?.week || '')
const estatuses = computed(() => [
  'generada',
  'asignada',
  'en_proceso',
  'completada',
  'autorizada_cliente'
])
function applyFilter(){
  const params = {}
  if (sel.value) params.estatus = sel.value
  if (yearSel.value) params.year = yearSel.value
  if (weekSel.value) params.week = weekSel.value
  router.get(props.urls.index, params, { preserveState: true, replace: true })
}

// Badge para estatus de facturación
function factBadgeClass(v){
  const e = String(v||'').toLowerCase()
  if (e === 'pagado') return 'bg-green-100 text-green-700'
  if (e === 'facturado') return 'bg-emerald-100 text-emerald-700'
  if (e === 'por_pagar') return 'bg-amber-100 text-amber-700'
  return 'bg-gray-100 text-gray-700'
}

// Exportar/Copy (cliente)
function toCsv(items){
  const headers = ['ID','Servicio','Centro','Área','Estatus','Calidad','Facturación','TL','Fecha']
  const rows = items.map(o => [
    o.id,
    o.servicio?.nombre || '-',
    o.centro?.nombre || '-',
    o.area?.nombre || '-',
    o.estatus || '-',
    o.calidad_resultado || '-',
    o.facturacion || '-',
    o.team_leader?.name || '—',
    o.created_at || ''
  ])
  const csv = [headers, ...rows].map(r => r.map(v => `"${String(v??'').replaceAll('"','""')}"`).join(',')).join('\n')
  return csv
}
function downloadExcel(){
  const csv = toCsv(rows.value || [])
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'ordenes.csv'
  a.click()
  URL.revokeObjectURL(url)
}
async function copyTable(){
  try{
    const tsv = (rows.value||[]).map(o => [o.id, o.servicio?.nombre||'-', o.centro?.nombre||'-', o.area?.nombre||'-', o.estatus||'-', o.calidad_resultado||'-', o.facturacion||'-', o.team_leader?.name||'—', o.created_at||''].join('\t')).join('\n')
    await navigator.clipboard.writeText(tsv)
  }catch(e){ console.warn('No se pudo copiar:', e) }
}
</script>

<template>
  <div class="p-6 max-w-none">
    <h1 class="text-3xl font-extrabold tracking-tight mb-4 uppercase">Órdenes de Trabajo</h1>

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
            <th class="p-2">ID</th>
            <th class="p-2">Servicio</th>
            <th class="p-2">Centro</th>
            <th class="p-2">Área</th>
            <th class="p-2">Estatus</th>
            <th class="p-2">Calidad</th>
            <th class="p-2">Facturación</th>
            <th class="p-2">TL</th>
            <th class="p-2">Fecha</th>
            <th class="p-2">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in rows" :key="o.id" class="border-t even:bg-slate-50 hover:bg-slate-100/60">
                <td class="px-4 py-3 font-mono">#{{ o.id }}</td>
                <td class="px-4 py-3">{{ o.servicio?.nombre }}</td>
                <td class="px-4 py-3">{{ o.centro?.nombre }}</td>
                <td class="px-4 py-3">{{ o.area?.nombre || '-' }}</td>
                <td class="px-4 py-3">
                  <span class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide"
                        :class="{
                          'bg-blue-100 text-blue-700': o.estatus==='generada',
                          'bg-indigo-100 text-indigo-700': o.estatus==='asignada',
                          'bg-yellow-100 text-yellow-700': o.estatus==='en_proceso',
                          'bg-green-100 text-green-700': o.estatus==='completada',
                          'bg-emerald-100 text-emerald-700': o.estatus==='autorizada_cliente'
                        }">{{ o.estatus }}</span>
                </td>
                <td class="px-4 py-3">
                  <span class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide"
                        :class="{
                          'bg-amber-100 text-amber-700': o.calidad_resultado==='pendiente',
                          'bg-green-100 text-green-700': o.calidad_resultado==='validado',
                          'bg-red-100 text-red-700': o.calidad_resultado==='rechazado'
                        }">{{ o.calidad_resultado }}</span>
                </td>
                <td class="px-4 py-3">
                  <span class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide" :class="factBadgeClass(o.facturacion)">{{ o.facturacion }}</span>
                </td>
                <td class="px-4 py-3">{{ o.team_leader?.name || '—' }}</td>
                <td class="px-4 py-3">{{ o.created_at }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <a :href="o.urls.show" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                      Ver
                    </a>
                    <a v-if="o.estatus==='completada' && o.calidad_resultado==='pendiente'" :href="o.urls.calidad" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      Calidad
                    </a>
                    <a v-if="o.estatus==='autorizada_cliente'" :href="o.urls.facturar" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition-colors">
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

      <!-- Paginación -->
      <div class="px-8 py-3 flex gap-2 justify-end">
        <Link v-for="link in data.links" :key="link.label" :href="link.url || '#'"
              class="px-3 py-1.5 rounded border text-sm"
              :class="{'bg-slate-900 text-white border-slate-900': link.active}"
              v-html="link.label" />
      </div>
    </div>
  </div>
</template>
