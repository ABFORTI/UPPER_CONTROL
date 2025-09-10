<script setup>
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { usePage } from '@inertiajs/vue3'

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps({
  kpis:    { type: Object, default: () => ({}) },
  series:  { type: Object, default: () => ({ ots_por_dia:[], fact_por_mes:[], top_servicios:[] }) },
  filters: { type: Object, default: () => ({ desde:'', hasta:'', centro:'' }) },
  centros: { type: Array,  default: () => [] },
  urls:    { type: Object, default: () => ({ index:'', export_ots:'', export_facturas:'' }) },
})

const page = usePage()
const filters = page.props.filters || {}

function qs(obj){
  const p = new URLSearchParams()
  Object.entries(obj).forEach(([k,v])=>{
    if (v !== undefined && v !== null && v !== '') p.append(k,v)
  })
  return p.toString()
}

const urlOtsXlsx = `/upper-control/public/dashboard/export/ots?${qs({...filters, format:'xlsx'})}`
const urlOtsCsv  = `/upper-control/public/dashboard/export/ots?${qs({...filters, format:'csv'})}`
const urlFactXlsx = `/upper-control/public/dashboard/export/facturas?${qs({...filters, format:'xlsx'})}`
const urlFactCsv  = `/upper-control/public/dashboard/export/facturas?${qs({...filters, format:'csv'})}`

function submit(e){
  const form = new FormData(e.target)
  const params = Object.fromEntries(form.entries())
  // 👇 usamos la URL que viene del backend para evitar Ziggy
  router.get(props.urls.index, params, { preserveState: true, replace: true })
}

function logout() {
  router.post(route('logout'))
}
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-4 flex items-center gap-4">
      Dashboard
    </h1>

    <form @submit.prevent="submit" class="flex flex-wrap items-end gap-3 mb-5">
      <div>
        <label class="block text-sm mb-1">Desde</label>
        <input type="date" name="desde" :value="filters.desde" class="border p-2 rounded">
      </div>
      <div>
        <label class="block text-sm mb-1">Hasta</label>
        <input type="date" name="hasta" :value="filters.hasta" class="border p-2 rounded">
      </div>
      <div v-if="centros.length">
        <label class="block text-sm mb-1">Centro</label>
        <select name="centro" :value="filters.centro" class="border p-2 rounded min-w-[16rem]">
          <option value="">— Todos —</option>
          <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
      </div>
      <button class="px-3 py-2 rounded bg-black text-white">Aplicar</button>
    </form>

    <div class="flex gap-2 mb-6">
      <a :href="urlOtsXlsx" class="px-3 py-2 rounded bg-emerald-600 text-white">Exportar OTs (XLSX)</a>
      <a :href="urlOtsCsv"  class="px-3 py-2 rounded bg-slate-700 text-white">Exportar OTs (CSV)</a>
      <a :href="urlFactXlsx" class="px-3 py-2 rounded bg-indigo-600 text-white">Exportar Facturas (XLSX)</a>
      <a :href="urlFactCsv"  class="px-3 py-2 rounded bg-gray-700 text-white">Exportar Facturas (CSV)</a>
    </div>

    <!-- KPIs -->
    <div class="grid md:grid-cols-3 gap-3">
      <div class="border rounded p-3">
        <div class="text-sm opacity-70">Solicitudes</div>
        <div class="text-2xl font-bold">{{ kpis.solicitudes ?? 0 }}</div>
      </div>
      <div class="border rounded p-3">
        <div class="text-sm opacity-70">OTs (total / completadas)</div>
        <div class="text-2xl font-bold">{{ kpis.ots ?? 0 }} / {{ kpis.ots_completadas ?? 0 }}</div>
        <div class="text-xs opacity-70">Calidad pendiente: {{ kpis.ots_cal_pend ?? 0 }}</div>
      </div>
      <div class="border rounded p-3">
        <div class="text-sm opacity-70">Monto facturado</div>
        <div class="text-2xl font-bold">$ {{ Number(kpis.monto_facturado || 0).toFixed(2) }}</div>
        <div class="text-xs opacity-70">Facturas pendientes: {{ kpis.fact_pendientes ?? 0 }}</div>
      </div>
    </div>

    <!-- OTs por día -->
    <div class="mt-6">
      <h2 class="font-semibold mb-2">OTs por día</h2>
      <div class="overflow-auto">
        <table class="w-full text-sm border">
          <thead>
            <tr class="bg-gray-50 text-left">
              <th class="p-2">Fecha</th>
              <th class="p-2">Generada</th>
              <th class="p-2">Asignada</th>
              <th class="p-2">En proceso</th>
              <th class="p-2">Completada</th>
              <th class="p-2">Aut. cliente</th>
              <th class="p-2">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in (series.ots_por_dia || [])" :key="r.fecha" class="border-t">
              <td class="p-2">{{ r.fecha }}</td>
              <td class="p-2">{{ r.generada }}</td>
              <td class="p-2">{{ r.asignada }}</td>
              <td class="p-2">{{ r.en_proceso }}</td>
              <td class="p-2">{{ r.completada }}</td>
              <td class="p-2">{{ r.autorizada_cliente }}</td>
              <td class="p-2 font-medium">{{ r.total }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Facturación por mes -->
    <div class="mt-6">
      <h2 class="font-semibold mb-2">Facturación por mes</h2>
      <table class="w-full text-sm border">
        <thead>
          <tr class="bg-gray-50 text-left">
            <th class="p-2">Mes</th>
            <th class="p-2">Total</th>
            <th class="p-2">Barra</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in (series.fact_por_mes || [])" :key="m.ym" class="border-t">
            <td class="p-2">{{ m.ym }}</td>
            <td class="p-2">$ {{ Number(m.t || 0).toFixed(2) }}</td>
            <td class="p-2">
              <div class="h-2 bg-gray-200 rounded">
                <div class="h-2 bg-indigo-600 rounded" :style="{ width: (Number(m.t) ? '100%' : '0%') }"></div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Top servicios -->
    <div class="mt-6">
      <h2 class="font-semibold mb-2">Top servicios (OTs completadas)</h2>
      <table class="w-full text-sm border">
        <thead>
          <tr class="bg-gray-50 text-left">
            <th class="p-2">Servicio</th>
            <th class="p-2">Completadas</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in (series.top_servicios || [])" :key="s.servicio" class="border-t">
            <td class="p-2">{{ s.servicio }}</td>
            <td class="p-2">{{ s.completadas }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
