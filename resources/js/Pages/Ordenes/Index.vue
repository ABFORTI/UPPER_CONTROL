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
          <div class="flex flex-wrap items-center gap-2">
            <button @click="sel=''; applyFilter()" :class="['px-3 py-1 rounded-full text-sm border', sel==='' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300']">Todos</button>
            <button v-for="e in estatuses" :key="e" @click="sel=e; applyFilter()" :class="['px-3 py-1 rounded-full text-sm border capitalize', sel===e ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300']">{{ e }}</button>
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
                <td class="px-4 py-3">{{ o.estatus }}</td>
                <td class="px-4 py-3">{{ o.calidad_resultado }}</td>
                <td class="px-4 py-3">
                  <span class="px-2 py-1 rounded text-xs font-medium" :class="factBadgeClass(o.facturacion)">{{ o.facturacion }}</span>
                </td>
                <td class="px-4 py-3">{{ o.team_leader?.name || '—' }}</td>
                <td class="px-4 py-3">{{ o.created_at }}</td>
                <td class="px-4 py-3 space-x-2">
                  <a :href="o.urls.show" class="text-blue-600 underline">Ver</a>
                  <span v-if="o.estatus==='completada' && o.calidad_resultado==='pendiente'">
                    · <a :href="o.urls.calidad" class="text-emerald-700 underline">Calidad</a>
                  </span>
                  <span v-if="o.estatus==='autorizada_cliente'">
                    · <a :href="o.urls.facturar" class="text-indigo-700 underline">Facturar</a>
                  </span>
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
