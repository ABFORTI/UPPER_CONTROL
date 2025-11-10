<script setup>
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { computed } from 'vue'

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps({
  kpis: { type: Object, default: () => ({}) },
  series: { type: Object, default: () => ({ ots_por_dia: [], top_servicios: [] }) },
  distribuciones: { type: Object, default: () => ({ estatus_ots: {}, calidad: {}, facturacion: {} }) },
  filters: { type: Object, default: () => ({ year:'', week:'', desde:'', hasta:'', centro:'' }) },
  centros: { type: Array, default: () => [] },
  usuarios_centro: { type: Array, default: () => [] },
  urls: { type: Object, default: () => ({ index:'', export_ots:'' }) },
})

const BRAND = { green: '#006657', gold: '#BC955C' }

// Usar siempre los filtros reactivos provenientes de props

function qs (obj) {
  const p = new URLSearchParams()
  Object.entries(obj).forEach(([k,v]) => { if (v !== undefined && v !== null && v !== '') p.append(k, v) })
  return p.toString()
}

const basePath = computed(() => {
  const p = window.location.pathname || ''
  const m = p.match(/^(.*)\/dashboard(\/.*)?$/)
  return m ? m[1] : p.replace(/\/?[^\/]*$/, '')
})

const urlOtsXlsx = computed(() => `${basePath.value}/dashboard/export/ots?${qs({ ...props.filters, format: 'xlsx' })}`)
const urlOtsCsv  = computed(() => `${basePath.value}/dashboard/export/ots?${qs({ ...props.filters, format: 'csv' })}`)

function submit (e) {
  const form = new FormData(e.target)
  const params = Object.fromEntries(form.entries())
  router.get(`${basePath.value}/dashboard`, params, { preserveState: true, replace: true })
}

const maxTopServ = computed(() => Math.max(1, ...(props.series.top_servicios || []).map(r => Number(r.completadas) || 0)))
function pct (value, max) { return (max === 0 ? 0 : (value / max) * 100).toFixed(2) }

// Formato de moneda local
function fmtCurrency (v) {
  try {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v || 0))
  } catch (e) { return String(v) }
}

const calidadProg = computed(() => ({
  pend:  props.distribuciones.calidad?.pendiente || 0,
  val:   props.distribuciones.calidad?.validado  || 0,
  rech:  props.distribuciones.calidad?.rechazado || 0,
  total: (props.distribuciones.calidad?.pendiente||0)
       + (props.distribuciones.calidad?.validado ||0)
       + (props.distribuciones.calidad?.rechazado||0)
}))

function nav (path) {
  if (!path) return
  if (/^https?:\/\//i.test(path)) return router.get(path, {}, { preserveState: false })
  const p = String(path).replace(/^\/+/, '')
  router.get(`${basePath.value}/${p}`, {}, { preserveState: false })
}
</script>

<template>
<div class="-mx-4 sm:-mx-6 lg:-mx-8"> 
  <div class="min-h-screen bg-[#F9FAFB]">
    <div class="w-full max-w-none px-4 sm:px-6 lg:px-8 py-6 space-y-8">


      <!-- Header + filtros -->
      <!-- HEADER igual al de la imagen -->
<div class="border-b border-slate-200 pb-3">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <!-- Marca + título -->
      <div class="flex items-center gap-3">
      <div class="flex items-center gap-3">
        <img src="img/upper_control.png" alt="Upper Control" loading="lazy" class="h-12 w-auto" />
        <h1 class="text-[22px] leading-6 font-semibold text-slate-900">Dashboard</h1>
      </div>
    </div>

    <!-- Controles a la derecha -->
  <form @submit.prevent="submit" class="flex flex-wrap items-center gap-2 gap-y-2">
      <!-- Año -->
      <select
        name="year"
        :value="props.filters.year"
        class="w-full sm:w-auto min-w-0 sm:min-w-[5rem] px-3 py-2 text-sm border border-slate-300 rounded-2xl bg-white text-slate-900 shadow-sm
               focus:outline-none focus:ring-2 focus:ring-[#006657]/30 focus:border-[#006657]"
      >
        <option v-for="y in [props.filters.year-2, props.filters.year-1, props.filters.year, props.filters.year+1]" :key="y" :value="y">
          {{ y }}
        </option>
      </select>

      <!-- Semana -->
      <select
        name="week"
        :value="props.filters.week"
        class="w-full sm:w-auto min-w-0 sm:min-w-[7rem] px-3 py-2 text-sm border border-slate-300 rounded-2xl bg-white text-slate-900 shadow-sm
               focus:outline-none focus:ring-2 focus:ring-[#006657]/30 focus:border-[#006657]"
      >
        <option v-for="w in 53" :key="w" :value="w">Semana {{ w }}</option>
      </select>

      <!-- Centro -->
      <select
        v-if="centros.length"
        name="centro"
        :value="props.filters.centro"
        class="w-full sm:w-auto min-w-0 sm:min-w-[15rem] px-3 py-2 text-sm border border-slate-300 rounded-2xl bg-white text-slate-900 shadow-sm
               focus:outline-none focus:ring-2 focus:ring-[#006657]/30 focus:border-[#006657]"
      >
        <option value="">— Todos los Centros —</option>
        <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
      </select>

      <!-- Botones -->
      <button
        type="submit"
        class="w-full sm:w-auto px-4 py-2 rounded-2xl text-white font-medium shadow-sm transition
               hover:brightness-105"
        :style="{ background: '#006657' }"
      >
        Aplicar
      </button>

      <a
        :href="urlOtsXlsx"
        class="w-full sm:w-auto px-3 py-2 rounded-2xl text-sm font-medium border border-slate-300 bg-white text-slate-700
               hover:bg-slate-50 shadow-sm"
      >
        OTs XLSX
      </a>

      <a
        :href="urlOtsCsv"
        class="w-full sm:w-auto px-3 py-2 rounded-2xl text-sm font-medium border border-slate-300 bg-white text-slate-700
               hover:bg-slate-50 shadow-sm"
      >
        OTs CSV
      </a>
    </form>
  </div>
</div>


  <!-- KPIs con marco degradado -->
  <div class="grid md:grid-cols-5 gap-4">
        <!-- KPI base (reutilizamos estructura; cambiamos el degradado en cada una) -->
        <div role="button" @click="nav('solicitudes')" title="Ver solicitudes"
             class="cursor-pointer rounded-2xl p-[1px] shadow-md hover:shadow-lg active:scale-[0.995] transition bg-gradient-to-br"
             :style="{ backgroundImage: `linear-gradient(135deg, ${BRAND.green}, #0ea5e9)` }">
          <div class="rounded-2xl bg-white p-5 h-full">
            <div class="flex items-center justify-between">
              <div class="text-[11px] uppercase tracking-wide text-slate-600">Solicitudes</div>
              <span class="h-2 w-2 rounded-full" :style="{ background: BRAND.gold }"></span>
            </div>
            <div class="mt-2 text-4xl font-bold text-slate-900">{{ kpis.solicitudes ?? 0 }}</div>
          </div>
        </div>

        <div role="button" @click="nav('ordenes')" title="Ver órdenes de trabajo"
             class="cursor-pointer rounded-2xl p-[1px] shadow-md hover:shadow-lg active:scale-[0.995] transition"
             style="background-image:linear-gradient(135deg,#5b5bd6,#c026d3)">
          <div class="rounded-2xl bg-white p-5 h-full">
            <div class="flex items-center justify-between">
              <div class="text-[11px] uppercase tracking-wide text-slate-600">OTs Totales</div>
              <span class="h-2 w-2 rounded-full" :style="{ background: BRAND.gold }"></span>
            </div>
            <div class="mt-2 text-4xl font-bold text-slate-900">{{ kpis.ots ?? 0 }}</div>
            <div class="text-[11px] text-slate-600 mt-1">Completadas: {{ kpis.ots_completadas ?? 0 }} · Aut Cliente: {{ kpis.ots_aut_cliente ?? 0 }}</div>
          </div>
        </div>

        <div role="button" @click="nav('calidad')" title="Ir a Calidad"
             class="cursor-pointer rounded-2xl p-[1px] shadow-md hover:shadow-lg active:scale-[0.995] transition"
             style="background-image:linear-gradient(135deg,#f59e0b,#f43f5e)">
          <div class="rounded-2xl bg-white p-5 h-full">
            <div class="text-[11px] uppercase tracking-wide text-slate-600">Calidad Pendiente</div>
            <div class="mt-2 text-4xl font-bold text-slate-900">{{ kpis.ots_cal_pend ?? 0 }}</div>
            <div class="mt-3 h-2 bg-slate-200 rounded-full overflow-hidden">
              <div class="h-2 rounded-full"
                   style="background:#111827"
                   :style="{ width: (kpis.ots_completadas>0 ? ((kpis.ots_cal_pend / kpis.ots_completadas)*100).toFixed(1) : 0)+'%' }"></div>
            </div>
            <div class="text-[11px] text-slate-600 mt-1">
              {{ kpis.ots_completadas > 0 ? ((kpis.ots_cal_pend / kpis.ots_completadas) * 100).toFixed(1) : 0 }}% de OTs completas
            </div>
          </div>
        </div>

        <div role="button" @click="nav('gastos')" title="Ver Gastos"
             class="cursor-pointer rounded-2xl p-[1px] shadow-md hover:shadow-lg active:scale-[0.995] transition"
             style="background-image:linear-gradient(135deg,#10b981,#06b6d4)">
          <div class="rounded-2xl bg-white p-5 h-full">
            <div class="flex items-center justify-between">
              <div class="text-[11px] uppercase tracking-wide text-slate-600">Gastos (Periodo)</div>
              <span class="h-2 w-2 rounded-full" :style="{ background: BRAND.gold }"></span>
            </div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ fmtCurrency(kpis.gastos || 0) }}</div>
            <div class="text-[11px] text-slate-600 mt-1">Suma de totales de OTs</div>
          </div>
        </div>

        <div role="button" @click="nav('notificaciones')" title="Ver notificaciones"
             class="cursor-pointer rounded-2xl p-[1px] shadow-md hover:shadow-lg active:scale-[0.995] transition"
             :style="{ backgroundImage: `linear-gradient(135deg, ${BRAND.gold}, #0ea5e9)` }">
          <div class="rounded-2xl bg-white p-5 h-full">
            <div class="flex items-center justify-between">
              <div class="text-[11px] uppercase tracking-wide text-slate-600">Notificaciones</div>
              <span class="h-2 w-2 rounded-full" :style="{ background: BRAND.green }"></span>
            </div>
            <div class="mt-2 text-4xl font-bold text-slate-900">{{ Number(kpis.notificaciones || 0) }}</div>
            <div class="text-[11px] text-slate-600 mt-1">No leídas</div>
          </div>
        </div>
      </div>

      <!-- Top servicios pedidos -->
      <div class="grid lg:grid-cols-1 gap-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
          <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase text-slate-700">Servicios más pedidos</h2>
          <div v-if="(series.top_servicios_pedidos || []).length === 0" class="text-sm text-slate-500">No hay datos en el periodo.</div>
          <ul v-else class="space-y-3">
            <li v-for="(s,idx) in series.top_servicios_pedidos" :key="s.servicio" class="flex items-center gap-3">
              <div class="flex-1">
                <div class="text-sm font-medium text-slate-900">{{ s.servicio }}</div>
                <div class="text-xs text-slate-500">Pedidos: {{ s.pedidos }}</div>
                <div class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-2 rounded-full bg-indigo-600" :style="{ width: ((s.pedidos / Math.max(1, series.top_servicios_pedidos[0].pedidos)) * 100) + '%' }"></div>
                </div>
              </div>
              <div class="w-16 text-right font-semibold text-slate-700">{{ s.pedidos }}</div>
            </li>
          </ul>
        </div>
      </div>

      <!-- Distribuciones (sin la tarjeta de Distribución OTs) -->
      <div class="grid lg:grid-cols-3 gap-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
          <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-sm tracking-wide uppercase text-slate-700">Calidad y Facturación</h2>
            <div class="flex items-center gap-2 text-xs">
              <button type="button" @click="nav('calidad')"
                      class="px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100">Calidad</button>
              <button type="button" @click="nav('facturas')"
                      class="px-2 py-1 rounded-full bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200 hover:bg-fuchsia-100">Facturas</button>
            </div>
          </div>

          <div class="space-y-2 text-sm mb-4">
            <div class="flex items-center gap-2">
              <span class="w-32 text-slate-600">Calidad Pend.</span>
              <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                <div class="h-2 rounded-full bg-amber-500"
                     :style="{ width: calidadProg.total ? ((calidadProg.pend / calidadProg.total) * 100)+'%' : '0%' }"></div>
              </div>
              <span class="w-10 text-right tabular-nums">{{ calidadProg.pend }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-32 text-slate-600">Calidad Val.</span>
              <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                <div class="h-2 rounded-full"
                     :style="{ width: calidadProg.total ? ((calidadProg.val / calidadProg.total) * 100)+'%' : '0%', background: BRAND.green }"></div>
              </div>
              <span class="w-10 text-right tabular-nums">{{ calidadProg.val }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-32 text-slate-600">Calidad Rech.</span>
              <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                <div class="h-2 rounded-full bg-rose-500"
                     :style="{ width: calidadProg.total ? ((calidadProg.rech / calidadProg.total) * 100)+'%' : '0%' }"></div>
              </div>
              <span class="w-10 text-right tabular-nums">{{ calidadProg.rech }}</span>
            </div>
          </div>

          <div class="space-y-2 text-sm">
            <div class="flex items-center gap-2" v-for="(val,label) in distribuciones.facturacion" :key="label">
              <span class="w-32 capitalize text-slate-600">{{ label.replace('_',' ') }}</span>
              <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                <div class="h-2 rounded-full"
                     :class="{
                       'bg-slate-500': label==='sin_factura',
                       'bg-amber-500': label==='pendiente',
                       'bg-sky-600':   label==='facturado',
                       'bg-purple-600':label==='cobrado',
                     }"
                     :style="{ width: pct(val, (kpis.ots||1))+'%', background: label==='pagado' ? BRAND.gold : undefined }"></div>
              </div>
              <span class="w-10 text-right tabular-nums">{{ val }}</span>
            </div>
          </div>
        </div>

        <!-- Actividad de la semana (mini barras) -->
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
          <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase text-slate-700">Actividad de la semana</h2>
          <div v-if="(series.ots_por_dia || []).length === 0" class="text-sm text-slate-500">Sin datos en el periodo.</div>
          <div v-else class="grid grid-cols-7 gap-3">
            <div v-for="d in series.ots_por_dia" :key="d.fecha" class="flex flex-col items-center">
              <div class="w-full h-24 bg-slate-100 rounded-md overflow-hidden flex items-end">
                <div class="w-full bg-gradient-to-t from-indigo-600 to-sky-400" :style="{ height: pct(d.total, Math.max(1, ...series.ots_por_dia.map(x=>x.total))) + '%' }"></div>
              </div>
              <div class="mt-1 text-[10px] text-slate-500">{{ new Date(d.fecha).toLocaleDateString('es-MX',{weekday:'short'}) }}</div>
              <div class="text-xs font-semibold text-slate-700">{{ d.total }}</div>
            </div>
          </div>
        </div>

        <!-- Usuarios del centro -->
        <div role="button" @click="nav('admin/users')" title="Administrar usuarios"
             class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
          <h2 class="font-semibold mb-3 text-sm tracking-wide uppercase text-slate-700">Usuarios del centro</h2>
          <div v-if="!usuarios_centro.length" class="text-sm text-slate-500">Selecciona un centro para ver sus usuarios.</div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="u in usuarios_centro" :key="u.id" class="py-2 flex items-start justify-between gap-4">
              <div>
                <div class="font-medium leading-5 text-slate-900">{{ u.nombre }}</div>
                <div class="text-xs text-slate-500">{{ u.email }}</div>
              </div>
              <div class="flex flex-wrap gap-1">
                <span v-for="r in u.roles" :key="r"
                      class="px-2 py-0.5 rounded-full text-xs bg-slate-100 border border-slate-200 text-slate-700">
                  {{ r }}
                </span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>   
</template>
