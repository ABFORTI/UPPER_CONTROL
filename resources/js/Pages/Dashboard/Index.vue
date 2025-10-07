<script setup>
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps({
  kpis:    { type: Object, default: () => ({}) },
  series:  { type: Object, default: () => ({ ots_por_dia:[], top_servicios:[] }) },
  distribuciones: { type: Object, default: () => ({ estatus_ots:{}, calidad:{} }) },
  filters: { type: Object, default: () => ({ year:'', week:'', desde:'', hasta:'', centro:'' }) },
  centros: { type: Array,  default: () => [] },
  usuarios_centro: { type: Array, default: () => [] },
  urls:    { type: Object, default: () => ({ index:'', export_ots:'' }) },
})

const page = usePage()
const filters = page.props.filters || {}

function qs(obj){
  const p = new URLSearchParams()
  Object.entries(obj).forEach(([k,v])=>{ if (v !== undefined && v !== null && v !== '') p.append(k,v) })
  return p.toString()
}

// Base path (cuando la app corre en subcarpeta, p.ej. /UPPER_CONTROL/public)
const basePath = computed(() => {
  const p = window.location.pathname || ''
  // quitar /dashboard y lo que siga para obtener la base
  const m = p.match(/^(.*)\/dashboard(\/.*)?$/)
  return m ? m[1] : p.replace(/\/?[^\/]*$/, '')
})

const urlOtsXlsx = computed(() => `${basePath.value}/dashboard/export/ots?${qs({...filters, format:'xlsx'})}`)
const urlOtsCsv  = computed(() => `${basePath.value}/dashboard/export/ots?${qs({...filters, format:'csv'})}`)

function submit(e){
  const form = new FormData(e.target)
  const params = Object.fromEntries(form.entries())
  // construir URL relativa a la base
  router.get(`${basePath.value}/dashboard`, params, { preserveState:true, replace:true })
}

// Helpers para minigráficos
const maxTopServ = computed(()=> Math.max(1, ...(props.series.top_servicios||[]).map(r=>Number(r.completadas)||0)))

function pct(value, max){ return (max === 0 ? 0 : (value / max) * 100).toFixed(2) }

const calidadProg = computed(()=> ({
  pend: props.distribuciones.calidad?.pendiente || 0,
  val:  props.distribuciones.calidad?.validado || 0,
  rech: props.distribuciones.calidad?.rechazado || 0,
  total: (props.distribuciones.calidad?.pendiente||0)+(props.distribuciones.calidad?.validado||0)+(props.distribuciones.calidad?.rechazado||0)
}))

// Navegación desde cards
function nav(path){
  if (!path) return
  // normalizar (soportar rutas absolutas http y relativas)
  if (/^https?:\/\//i.test(path)) return router.get(path, {}, { preserveState: false })
  const p = String(path).replace(/^\/+/, '')
  router.get(`${basePath.value}/${p}`, {}, { preserveState: false })
}

</script>

<template>
  <div class="p-6 max-w-7xl mx-auto space-y-8">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
      <div>
        <h1 class="text-3xl font-bold flex items-center gap-3 tracking-tight">Dashboard</h1>
        <p class="text-slate-600">Resumen operativo del periodo seleccionado.</p>
      </div>
      <form @submit.prevent="submit" class="flex flex-wrap items-end gap-4">
        <!-- Tarjeta de periodo (Año + Semana) -->
        <div class="bg-white border rounded p-3 flex flex-col gap-2">
          <div class="text-xs uppercase tracking-wide opacity-70">Periodo</div>
          <div class="flex items-end gap-3">
            <div class="min-w-[7rem]">
              <label class="block text-xs uppercase tracking-wide opacity-70 mb-1">Año</label>
              <select name="year" :value="filters.year" class="border p-2 rounded bg-white w-28">
                <option v-for="y in [filters.year-2, filters.year-1, filters.year, filters.year+1]" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
            <div class="min-w-[8.5rem]">
              <label class="block text-xs uppercase tracking-wide opacity-70 mb-1">Semana</label>
              <select name="week" :value="filters.week" class="border p-2 rounded bg-white w-36">
                <option v-for="w in 53" :key="w" :value="w">Semana {{ w }}</option>
              </select>
            </div>
          </div>
          <div class="text-[11px] text-slate-500">{{ filters.desde }} — {{ filters.hasta }}</div>
        </div>

        <!-- Select de centro -->
        <div v-if="centros.length" class="bg-white border rounded p-3">
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
        </div>
     </form>
    </div>
    
   <!-- KPIs principales (colores y navegación) -->
    <div class="grid md:grid-cols-4 gap-4">
      <!-- Solicitudes -->
  <div role="button" @click="nav('solicitudes')" title="Ver solicitudes"
           class="cursor-pointer bg-gradient-to-br from-sky-500 to-cyan-600 text-white shadow-sm rounded-lg p-4 hover:shadow-md active:scale-[0.99] transition">
        <div class="text-xs uppercase tracking-wide opacity-90">Solicitudes</div>
        <div class="mt-1 text-3xl font-semibold">{{ kpis.solicitudes ?? 0 }}</div>
      </div>

      <!-- OTs Totales -->
  <div role="button" @click="nav('ordenes')" title="Ver órdenes de trabajo"
           class="cursor-pointer bg-gradient-to-br from-violet-600 to-fuchsia-600 text-white shadow-sm rounded-lg p-4 hover:shadow-md active:scale-[0.99] transition">
        <div class="text-xs uppercase tracking-wide opacity-90">OTs Totales</div>
        <div class="mt-1 text-3xl font-semibold">{{ kpis.ots ?? 0 }}</div>
        <div class="text-[11px] mt-1 opacity-90">Completadas: {{ kpis.ots_completadas ?? 0 }} · Aut Cliente: {{ kpis.ots_aut_cliente ?? 0 }}</div>
      </div>

      <!-- Calidad Pendiente -->
  <div role="button" @click="nav('calidad')" title="Ir a Calidad"
           class="cursor-pointer bg-gradient-to-br from-amber-500 to-rose-500 text-white shadow-sm rounded-lg p-4 hover:shadow-md active:scale-[0.99] transition">
        <div class="text-xs uppercase tracking-wide opacity-90">Calidad Pendiente</div>
        <div class="mt-1 text-3xl font-semibold">{{ kpis.ots_cal_pend ?? 0 }}</div>
        <div class="mt-2 h-2 bg-white/30 rounded overflow-hidden">
          <div class="h-2 bg-white/80" :style="{ width: (kpis.ots_completadas>0 ? ((kpis.ots_cal_pend / kpis.ots_completadas)*100).toFixed(1) : 0)+'%' }"></div>
        </div>
        <div class="text-[11px] mt-1 opacity-90">{{ kpis.ots_completadas > 0 ? ((kpis.ots_cal_pend / kpis.ots_completadas) * 100).toFixed(1) : 0 }}% de OTs completas</div>
      </div>

      <!-- Tasa Validación -->
  <div role="button" @click="nav('calidad')" title="Ir a Calidad"
           class="cursor-pointer bg-gradient-to-br from-indigo-600 to-fuchsia-600 text-white shadow-sm rounded-lg p-4 hover:shadow-md active:scale-[0.99] transition">
        <div class="text-xs uppercase tracking-wide opacity-90">Tasa Validación</div>
        <div class="mt-1 text-3xl font-semibold">{{ kpis.tasa_validacion }}%</div>
        <div class="text-[11px] mt-1 opacity-90">OTs completadas evaluables</div>
      </div>
    </div>

    <!-- Distribuciones: OTs + Calidad + Facturación en tarjetas -->
    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Distribución OTs -->
  <div role="button" @click="nav('ordenes')" title="Ver órdenes"
           class="cursor-pointer bg-white border rounded p-4 hover:shadow-md transition">
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

      <!-- Calidad + Facturación combinadas -->
      <div class="bg-white border rounded p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-semibold text-sm tracking-wide uppercase">Calidad y Facturación</h2>
          <div class="flex items-center gap-2 text-xs">
            <button type="button" @click="nav('calidad')" class="px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100">Calidad</button>
            <button type="button" @click="nav('facturas')" class="px-2 py-1 rounded-full bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200 hover:bg-fuchsia-100">Facturas</button>
          </div>
        </div>
        <!-- Calidad barras -->
        <div class="space-y-2 text-sm mb-4">
          <div class="flex items-center gap-2">
            <span class="w-28 text-slate-600">Calidad Pend.</span>
            <div class="flex-1 h-2 bg-slate-200 rounded overflow-hidden">
              <div class="h-2 bg-amber-500" :style="{ width: calidadProg.total? ( (calidadProg.pend / calidadProg.total) * 100)+'%' : '0%' }"></div>
            </div>
            <span class="w-10 text-right tabular-nums">{{ calidadProg.pend }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-28 text-slate-600">Calidad Val.</span>
            <div class="flex-1 h-2 bg-slate-200 rounded overflow-hidden">
              <div class="h-2 bg-emerald-500" :style="{ width: calidadProg.total? ( (calidadProg.val / calidadProg.total) * 100)+'%' : '0%' }"></div>
            </div>
            <span class="w-10 text-right tabular-nums">{{ calidadProg.val }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-28 text-slate-600">Calidad Rech.</span>
            <div class="flex-1 h-2 bg-slate-200 rounded overflow-hidden">
              <div class="h-2 bg-rose-500" :style="{ width: calidadProg.total? ( (calidadProg.rech / calidadProg.total) * 100)+'%' : '0%' }"></div>
            </div>
            <span class="w-10 text-right tabular-nums">{{ calidadProg.rech }}</span>
          </div>
        </div>
        <!-- Facturación barras -->
        <div class="space-y-2 text-sm">
          <div class="flex items-center gap-2" v-for="(val,label) in distribuciones.facturacion" :key="label">
            <span class="w-28 capitalize text-slate-600">{{ label.replace('_',' ') }}</span>
            <div class="flex-1 h-2 bg-slate-200 rounded overflow-hidden">
              <div class="h-2" :class="{
                    'bg-slate-500': label==='sin_factura',
                    'bg-amber-500': label==='pendiente',
                    'bg-blue-600':  label==='facturado',
                    'bg-purple-600':label==='cobrado',
                    'bg-emerald-600':label==='pagado',
                }" :style="{ width: pct(val, (kpis.ots||1))+'%' }"></div>
            </div>
            <span class="w-10 text-right tabular-nums">{{ val }}</span>
          </div>
        </div>
      </div>

      <!-- Usuarios del centro -->
  <div role="button" @click="nav('admin/users')" title="Administrar usuarios"
     class="cursor-pointer bg-white border rounded p-4 hover:shadow-md transition">
        <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase">Usuarios del centro</h2>
        <div v-if="!usuarios_centro.length" class="text-sm text-slate-500">Selecciona un centro para ver sus usuarios.</div>
        <ul v-else class="divide-y">
          <li v-for="u in usuarios_centro" :key="u.id" class="py-2 flex items-start justify-between gap-4">
            <div>
              <div class="font-medium leading-5">{{ u.nombre }}</div>
              <div class="text-xs text-slate-500">{{ u.email }}</div>
            </div>
            <div class="flex flex-wrap gap-1">
              <span v-for="r in u.roles" :key="r" class="px-2 py-0.5 rounded-full text-xs bg-slate-100 border">{{ r }}</span>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Se removieron las dos tarjetas inferiores (OTs por día y Top servicios) -->
  </div>
</template>
