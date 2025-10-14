<!-- resources/js/Pages/Calidad/Index.vue -->
<script setup>
import { computed, ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({
  data: Object,
  estado: String,
  filters: Object,
  urls: Object,
})

const rows = computed(()=> props.data?.data ?? [])

// Filtros unificados por estatus (píldoras) con conjunto fijo
const sel = ref((props.estado && props.estado !== 'todos') ? props.estado : '')
const yearSel = ref(props.filters?.year || new Date().getFullYear())
const weekSel = ref(props.filters?.week || '')
const estatuses = computed(() => ['pendiente', 'validado', 'rechazado'])
function applyFilter(){
  const params = { estado: sel.value || 'todos' }
  if (yearSel.value) params.year = yearSel.value
  if (weekSel.value) params.week = weekSel.value
  router.get(props.urls.index, params, { preserveState:true, preserveScroll:true, replace:true })
}

// Exportar/Copy (cliente)
function toCsv(items){
  const headers = ['ID','Servicio','Centro','Calidad','Fecha']
  const rows = items.map(o => [
    o.id,
    o.servicio?.nombre || '-',
    o.centro?.nombre || '-',
    o.calidad_resultado || '-',
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
  a.download = 'calidad.csv'
  a.click()
  URL.revokeObjectURL(url)
}
async function copyTable(){
  try{
    const tsv = (rows.value||[]).map(o => [o.id, o.servicio?.nombre||'-', o.centro?.nombre||'-', o.calidad_resultado||'-', o.created_at||''].join('\t')).join('\n')
    await navigator.clipboard.writeText(tsv)
  }catch(e){ console.warn('No se pudo copiar:', e) }
}
</script>

<template>
  <div class="p-6 max-w-none">
    <h1 class="text-3xl font-extrabold tracking-tight mb-4 uppercase">Calidad</h1>

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
                <th class="px-4 py-3 text-left">ID</th>
                <th class="px-4 py-3 text-left">Servicio</th>
                <th class="px-4 py-3 text-left">Centro</th>
                <th class="px-4 py-3 text-left">Calidad</th>
                <th class="px-4 py-3 text-left">Fecha</th>
                <th class="px-4 py-3 text-left">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in rows" :key="o.id" class="border-t even:bg-slate-50 hover:bg-slate-100/60">
                <td class="px-4 py-3 font-mono">#{{ o.id }}</td>
                <td class="px-4 py-3">{{ o.servicio?.nombre }}</td>
                <td class="px-4 py-3">{{ o.centro?.nombre }}</td>
                <td class="px-4 py-3">
                  <span class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide"
                        :class="{
                          'bg-amber-100 text-amber-700': o.calidad_resultado==='pendiente',
                          'bg-green-100 text-green-700': o.calidad_resultado==='validado',
                          'bg-red-100 text-red-700': o.calidad_resultado==='rechazado'
                        }">{{ o.calidad_resultado }}</span>
                </td>
                <td class="px-4 py-3">{{ o.created_at }}</td>
                <td class="px-4 py-3">
                  <a :href="o.urls.show" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver OT
                  </a>
                </td>
              </tr>
              <tr v-if="rows.length===0">
                <td colspan="6" class="p-4 text-center opacity-70">No hay registros.</td>
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
