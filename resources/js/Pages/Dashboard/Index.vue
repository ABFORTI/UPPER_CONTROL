<script setup>
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps({
  kpis:    { type: Object, default: () => ({}) },
  series:  { type: Object, default: () => ({ ots_por_dia:[], fact_por_mes:[], top_servicios:[], ingresos_diarios:[] }) },
  distribuciones: { type: Object, default: () => ({ estatus_ots:{}, calidad:{}, facturas:{} }) },
  filters: { type: Object, default: () => ({ desde:'', hasta:'', centro:'' }) },
  centros: { type: Array,  default: () => [] },
  urls:    { type: Object, default: () => ({ index:'', export_ots:'', export_facturas:'' }) },
})

const page = usePage()
const filters = page.props.filters || {}

function qs(obj){
  const p = new URLSearchParams()
  Object.entries(obj).forEach(([k,v])=>{ if (v !== undefined && v !== null && v !== '') p.append(k,v) })
  return p.toString()
}

const urlOtsXlsx = `/dashboard/export/ots?${qs({...filters, format:'xlsx'})}`
const urlOtsCsv  = `/dashboard/export/ots?${qs({...filters, format:'csv'})}`
const urlFactXlsx = `/dashboard/export/facturas?${qs({...filters, format:'xlsx'})}`
const urlFactCsv  = `/dashboard/export/facturas?${qs({...filters, format:'csv'})}`

function submit(e){
  const form = new FormData(e.target)
  const params = Object.fromEntries(form.entries())
  router.get(props.urls.index, params, { preserveState:true, replace:true })
}

// Helpers para minigráficos
const maxFactMes = computed(()=> Math.max(1, ...(props.series.fact_por_mes||[]).map(r=>Number(r.t)||0)))
const maxTopServ = computed(()=> Math.max(1, ...(props.series.top_servicios||[]).map(r=>Number(r.completadas)||0)))
const maxIngresosDia = computed(()=> Math.max(1, ...(props.series.ingresos_diarios||[]).map(r=>Number(r.t)||0)))

function pct(value, max){ return (max === 0 ? 0 : (value / max) * 100).toFixed(2) }

const calidadProg = computed(()=> ({
  pend: props.distribuciones.calidad?.pendiente || 0,
  val:  props.distribuciones.calidad?.validado || 0,
  rech: props.distribuciones.calidad?.rechazado || 0,
  total: (props.distribuciones.calidad?.pendiente||0)+(props.distribuciones.calidad?.validado||0)+(props.distribuciones.calidad?.rechazado||0)
}))

</script>

<template>
  <div class="p-6 max-w-7xl mx-auto space-y-8">
    <div>
      <h1 class="text-2xl font-bold mb-4 flex items-center gap-4">Dashboard</h1>
      <form @submit.prevent="submit" class="flex flex-wrap items-end gap-3 mb-5">
        <div>
          <label class="block text-xs uppercase tracking-wide opacity-70 mb-1">Desde</label>
          <input type="date" name="desde" :value="filters.desde" class="border p-2 rounded bg-white" />
        </div>
        <div>
          <label class="block text-xs uppercase tracking-wide opacity-70 mb-1">Hasta</label>
          <input type="date" name="hasta" :value="filters.hasta" class="border p-2 rounded bg-white" />
        </div>
        <div v-if="centros.length">
          <label class="block text-xs uppercase tracking-wide opacity-70 mb-1">Centro</label>
          <select name="centro" :value="filters.centro" class="border p-2 rounded bg-white min-w-[14rem]">
            <option value="">— Todos —</option>
            <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
        </div>
        <button class="px-4 py-2 rounded bg-black text-white font-medium">Aplicar</button>
        <div class="flex flex-wrap gap-2 ml-auto">
          <a :href="urlOtsXlsx" class="px-3 py-2 rounded bg-emerald-600 text-white text-sm">OTs XLSX</a>
          <a :href="urlOtsCsv"  class="px-3 py-2 rounded bg-emerald-700 text-white text-sm">OTs CSV</a>
          <a :href="urlFactXlsx" class="px-3 py-2 rounded bg-indigo-600 text-white text-sm">Fact XLSX</a>
          <a :href="urlFactCsv"  class="px-3 py-2 rounded bg-indigo-700 text-white text-sm">Fact CSV</a>
        </div>
      </form>
    </div>

    <!-- KPIs principales -->
    <div class="grid md:grid-cols-4 gap-4">
      <div class="bg-white shadow-sm rounded-lg p-4 border">
        <div class="text-xs uppercase tracking-wide text-slate-500">Solicitudes</div>
        <div class="mt-1 text-3xl font-semibold">{{ kpis.solicitudes ?? 0 }}</div>
      </div>
      <div class="bg-white shadow-sm rounded-lg p-4 border">
        <div class="text-xs uppercase tracking-wide text-slate-500">OTs Totales</div>
        <div class="mt-1 text-3xl font-semibold">{{ kpis.ots ?? 0 }}</div>
        <div class="text-[11px] mt-1 text-slate-500">Completadas: {{ kpis.ots_completadas ?? 0 }} · Aut Cliente: {{ kpis.ots_aut_cliente ?? 0 }}</div>
      </div>
      <div class="bg-white shadow-sm rounded-lg p-4 border">
        <div class="text-xs uppercase tracking-wide text-slate-500">Calidad Pendiente</div>
        <div class="mt-1 text-3xl font-semibold">{{ kpis.ots_cal_pend ?? 0 }}</div>
        <div class="mt-2 h-2 bg-slate-200 rounded overflow-hidden">
          <div class="h-2 bg-amber-500" :style="{ width: (kpis.ots_completadas>0 ? ((kpis.ots_cal_pend / kpis.ots_completadas)*100).toFixed(1) : 0)+'%' }"></div>
        </div>
  <div class="text-[11px] mt-1 text-slate-500">{{ kpis.ots_completadas > 0 ? ((kpis.ots_cal_pend / kpis.ots_completadas) * 100).toFixed(1) : 0 }}% de OTs completas</div>
      </div>
      <div class="bg-white shadow-sm rounded-lg p-4 border">
        <div class="text-xs uppercase tracking-wide text-slate-500">Monto Facturado</div>
        <div class="mt-1 text-3xl font-semibold">$ {{ Number(kpis.monto_facturado || 0).toFixed(2) }}</div>
        <div class="text-[11px] mt-1 text-slate-500">Facturas Pend: {{ kpis.fact_pendientes ?? 0 }}</div>
      </div>
    </div>

    <!-- Distribuciones y progreso calidad -->
    <div class="grid lg:grid-cols-3 gap-6">
      <div class="bg-white border rounded p-4">
        <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase">Distribución OTs</h2>
        <div class="space-y-2 text-sm">
          <div v-for="(val,label) in distribuciones.estatus_ots" :key="label" class="flex items-center gap-2">
            <span class="w-28 capitalize text-slate-600">{{ label.replace('_',' ') }}</span>
            <div class="flex-1 h-2 bg-slate-200 rounded overflow-hidden">
              <div class="h-2 bg-blue-600" :style="{ width: pct(val, (kpis.ots||1))+'%' }"></div>
            </div>
            <span class="w-10 text-right tabular-nums">{{ val }}</span>
          </div>
        </div>
      </div>
      <div class="bg-white border rounded p-4">
        <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase">Calidad</h2>
        <div class="text-sm mb-2">Tasa Validación: <strong>{{ kpis.tasa_validacion }}%</strong></div>
        <div class="flex gap-2 text-xs mb-3">
          <div class="flex-1"><div class="h-2 bg-amber-400" :style="{ width: calidadProg.total? ( (calidadProg.pend / calidadProg.total) * 100)+'%' : '0%' }"></div></div>
          <div class="flex-1"><div class="h-2 bg-emerald-500" :style="{ width: calidadProg.total? ( (calidadProg.val / calidadProg.total) * 100)+'%' : '0%' }"></div></div>
          <div class="flex-1"><div class="h-2 bg-rose-500" :style="{ width: calidadProg.total? ( (calidadProg.rech / calidadProg.total) * 100)+'%' : '0%' }"></div></div>
        </div>
        <ul class="text-sm space-y-1">
          <li>Pendiente: <strong>{{ calidadProg.pend }}</strong></li>
          <li>Validado: <strong>{{ calidadProg.val }}</strong></li>
          <li>Rechazado: <strong>{{ calidadProg.rech }}</strong></li>
        </ul>
      </div>
      <div class="bg-white border rounded p-4">
        <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase">Facturas</h2>
        <div class="space-y-2 text-sm">
          <div v-for="(val,label) in distribuciones.facturas" :key="label" class="flex items-center gap-2">
            <span class="w-24 capitalize text-slate-600">{{ label }}</span>
            <div class="flex-1 h-2 bg-slate-200 rounded overflow-hidden">
              <div class="h-2 bg-indigo-600" :style="{ width: pct(val, Object.values(distribuciones.facturas).reduce((a,b)=>a+b,0)||1)+'%' }"></div>
            </div>
            <span class="w-10 text-right tabular-nums">{{ val }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Series principales -->
    <div class="grid lg:grid-cols-2 gap-6">
      <div class="bg-white border rounded p-4 overflow-auto">
        <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase">OTs por día</h2>
        <table class="w-full text-xs border">
          <thead>
            <tr class="bg-gray-50 text-left">
              <th class="p-2">Fecha</th>
              <th class="p-2">Gen</th>
              <th class="p-2">Asig</th>
              <th class="p-2">Proc</th>
              <th class="p-2">Comp</th>
              <th class="p-2">AutCli</th>
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
      <div class="bg-white border rounded p-4">
        <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase">Ingresos diarios</h2>
        <div class="space-y-1 max-h-72 overflow-auto pr-1 text-xs">
          <div v-for="d in (series.ingresos_diarios||[])" :key="d.d" class="flex items-center gap-2">
            <span class="w-20 text-slate-600">{{ d.d }}</span>
            <div class="flex-1 h-2 bg-slate-200 rounded overflow-hidden">
              <div class="h-2 bg-green-600" :style="{ width: pct(Number(d.t||0), maxIngresosDia)+'%' }"></div>
            </div>
            <span class="w-20 text-right tabular-nums">$ {{ Number(d.t||0).toFixed(2) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
      <div class="bg-white border rounded p-4">
        <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase">Facturación por mes</h2>
        <div class="space-y-2 text-xs">
          <div v-for="m in (series.fact_por_mes||[])" :key="m.ym" class="flex items-center gap-2">
            <span class="w-16 text-slate-600">{{ m.ym }}</span>
            <div class="flex-1 h-2 bg-slate-200 rounded overflow-hidden">
              <div class="h-2 bg-indigo-600" :style="{ width: pct(Number(m.t||0), maxFactMes)+'%' }"></div>
            </div>
            <span class="w-24 text-right tabular-nums">$ {{ Number(m.t||0).toFixed(2) }}</span>
          </div>
        </div>
      </div>
      <div class="bg-white border rounded p-4">
        <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase">Top servicios</h2>
        <div class="space-y-2 text-xs">
            <div v-for="s in (series.top_servicios||[])" :key="s.servicio" class="flex items-center gap-2">
              <span class="w-40 truncate" :title="s.servicio">{{ s.servicio }}</span>
              <div class="flex-1 h-2 bg-slate-200 rounded overflow-hidden">
                <div class="h-2 bg-emerald-600" :style="{ width: pct(Number(s.completadas||0), maxTopServ)+'%' }"></div>
              </div>
              <span class="w-10 text-right tabular-nums">{{ s.completadas }}</span>
            </div>
        </div>
      </div>
    </div>
  </div>
</template>
