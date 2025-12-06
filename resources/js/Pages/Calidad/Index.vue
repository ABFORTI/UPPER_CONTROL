<!-- resources/js/Pages/Calidad/Index.vue -->
<script setup>
import { computed, ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({
  data: Object,
  estado: String,
  filters: Object,
  urls: Object,
  centros: Array,
})

const rows = computed(()=> props.data?.data ?? [])

// Filtros unificados por estatus (píldoras) con conjunto fijo
const sel = ref((props.estado && props.estado !== 'todos') ? props.estado : '')
const centroSel = ref(props.filters?.centro || '')
const yearSel = ref(props.filters?.year || new Date().getFullYear())
const weekSel = ref(props.filters?.week || '')
const estatuses = computed(() => ['pendiente', 'validado', 'rechazado'])
function applyFilter(){
  const params = { estado: sel.value || 'todos' }
  if (centroSel.value) params.centro = centroSel.value
  if (yearSel.value) params.year = yearSel.value
  if (weekSel.value) params.week = weekSel.value
  router.get(props.urls.index, params, { preserveState:true, preserveScroll:true, replace:true })
}

</script>

<template>
  <div class="p-6 max-w-none">
    <h1 class="text-3xl font-extrabold tracking-tight mb-4 uppercase">Calidad</h1>

    <div class="rounded-xl border bg-white">
      <!-- Filtros responsivos -->
      <div class="px-4 sm:px-6 lg:px-8 py-4 space-y-3 lg:space-y-0 lg:flex lg:flex-wrap lg:items-center lg:justify-start lg:gap-3">
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full lg:w-auto">
          <select v-model="centroSel" @change="applyFilter" class="border p-2 rounded w-full sm:w-auto sm:min-w-[180px]">
            <option value="">Todos los centros</option>
            <option v-for="c in (props.centros||[])" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
          <select v-model="yearSel" @change="applyFilter" class="border p-2 rounded w-full sm:w-auto sm:min-w-[110px]">
            <option v-for="y in [yearSel-2, yearSel-1, yearSel, yearSel+1]" :key="y" :value="y">{{ y }}</option>
          </select>
          <select v-model="weekSel" @change="applyFilter" class="border p-2 rounded w-full sm:w-auto sm:min-w-[130px]">
            <option value="">Periodos</option>
            <option v-for="w in 53" :key="w" :value="w">Periodo {{ w }}</option>
          </select>
        </div>

        <div class="flex flex-wrap gap-2 overflow-x-auto lg:overflow-visible w-full py-1 lg:w-auto">
          <button @click="sel=''; applyFilter()" :class="['flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border transition-colors', sel==='' ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel==='' ? 'background-color: #1A73E8' : ''">Todos</button>
          <button v-for="e in estatuses" :key="e" @click="sel=e; applyFilter()" :class="['flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold border capitalize transition-colors', sel===e ? 'text-white border-[#1A73E8]' : 'bg-white text-slate-700 border-slate-300']" :style="sel===e ? 'background-color: #1A73E8' : ''">{{ e }}</button>
        </div>
      </div>

      <!-- Tabla desktop -->
      <div class="px-4 sm:px-6 lg:px-8 pb-4 hidden md:block">
        <div class="rounded-lg shadow-sm border border-slate-200">
          <div class="overflow-x-auto">
            <table class="w-full text-[0.7rem] md:text-sm xl:text-xs 2xl:text-sm table-auto">
              <thead class="bg-slate-800 text-white uppercase text-xs">
                <tr>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal 2xl:whitespace-nowrap">ID</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal xl:max-w-[16rem] 2xl:max-w-none 2xl:whitespace-nowrap">Producto</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal xl:max-w-[12rem] 2xl:max-w-none 2xl:whitespace-nowrap">Servicio</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal xl:max-w-[11rem] 2xl:max-w-none 2xl:whitespace-nowrap">Centro</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal xl:max-w-[11rem] 2xl:max-w-none 2xl:whitespace-nowrap">Área</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal xl:max-w-[11rem] 2xl:max-w-none 2xl:whitespace-nowrap">Marca</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal xl:max-w-[11rem] 2xl:max-w-none 2xl:whitespace-nowrap">TL</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal xl:max-w-[10rem] 2xl:max-w-none 2xl:whitespace-nowrap">Calidad</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal xl:max-w-[10rem] 2xl:max-w-none 2xl:whitespace-nowrap">Fecha</th>
                  <th class="px-1.5 sm:px-2 py-2 text-left align-top break-words whitespace-normal xl:max-w-[12rem] 2xl:max-w-none 2xl:whitespace-nowrap">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="o in rows" :key="o.id" class="border-t even:bg-slate-50 hover:bg-slate-100/60">
                  <td class="px-1.5 sm:px-2 py-3 leading-snug font-mono whitespace-normal break-words max-w-[6rem] xl:whitespace-nowrap">#{{ o.id }}</td>
                  <td class="px-1.5 sm:px-2 py-3 leading-snug whitespace-normal break-words max-w-[14rem] xl:max-w-[16rem] xl:whitespace-nowrap" :title="o.producto || ''">{{ o.producto || '-' }}</td>
                  <td class="px-1.5 sm:px-2 py-3 leading-snug whitespace-normal break-words max-w-[11rem] xl:max-w-[12rem] xl:whitespace-nowrap">{{ o.servicio?.nombre || '-' }}</td>
                  <td class="px-1.5 sm:px-2 py-3 leading-snug whitespace-normal break-words max-w-[10rem] xl:max-w-[11rem] xl:whitespace-nowrap">{{ o.centro?.nombre || '-' }}</td>
                  <td class="px-1.5 sm:px-2 py-3 leading-snug whitespace-normal break-words max-w-[10rem] xl:max-w-[11rem] xl:whitespace-nowrap">{{ o.area?.nombre || '-' }}</td>
                  <td class="px-1.5 sm:px-2 py-3 leading-snug whitespace-normal break-words max-w-[10rem] xl:max-w-[11rem] xl:whitespace-nowrap">{{ o.marca?.nombre || '-' }}</td>
                  <td class="px-1.5 sm:px-2 py-3 leading-snug whitespace-normal break-words max-w-[10rem] xl:max-w-[11rem] xl:whitespace-nowrap">{{ o.team_leader?.name || '-' }}</td>
                  <td class="px-1.5 sm:px-2 py-3 leading-snug whitespace-normal break-words max-w-[9rem] xl:max-w-[10rem] xl:whitespace-nowrap">
                    <span class="px-2.5 py-1 rounded-full text-[0.65rem] font-semibold uppercase tracking-wide"
                          :class="{
                            'bg-amber-100 text-amber-700 border border-amber-200': o.calidad_resultado==='pendiente',
                            'bg-green-100 text-green-700 border border-green-200': o.calidad_resultado==='validado',
                            'bg-red-100 text-red-700 border border-red-200': o.calidad_resultado==='rechazado'
                          }">{{ o.calidad_resultado }}</span>
                  </td>
                  <td class="px-1.5 sm:px-2 py-3 leading-snug whitespace-normal break-words max-w-[9rem] xl:max-w-[10rem] xl:whitespace-nowrap">{{ o.fecha || '—' }}</td>
                  <td class="px-1.5 sm:px-2 py-3 leading-snug whitespace-normal break-words max-w-[12rem] xl:max-w-[13rem] xl:whitespace-nowrap">
                    <div class="flex flex-wrap items-center justify-start gap-2">
                      <a :href="o.urls.show" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Ver OT
                      </a>
                    </div>
                  </td>
                </tr>
                <tr v-if="rows.length===0">
                  <td colspan="10" class="p-4 text-center opacity-70">No hay registros.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tarjetas móviles -->
      <div class="md:hidden px-4 sm:px-6 lg:px-8 pb-4">
        <div class="space-y-4">
          <div v-for="o in rows" :key="o.id" class="border border-slate-200 rounded-xl p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-wide text-slate-500">Folio</div>
                <div class="text-lg font-semibold text-slate-900">#{{ o.id }}</div>
              </div>
              <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide"
                    :class="{
                      'bg-amber-100 text-amber-700 border border-amber-200': o.calidad_resultado==='pendiente',
                      'bg-green-100 text-green-700 border border-green-200': o.calidad_resultado==='validado',
                      'bg-red-100 text-red-700 border border-red-200': o.calidad_resultado==='rechazado'
                    }">{{ o.calidad_resultado }}</span>
            </div>
            <div class="mt-3 space-y-2 text-sm">
              <div class="flex justify-between gap-3"><span class="text-slate-500">Producto</span><span class="font-medium text-right text-slate-800">{{ o.producto || '-' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Servicio</span><span class="font-medium text-right text-slate-800">{{ o.servicio?.nombre || '-' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Centro</span><span class="font-medium text-right text-slate-800">{{ o.centro?.nombre || '-' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Área</span><span class="font-medium text-right text-slate-800">{{ o.area?.nombre || '-' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Marca</span><span class="font-medium text-right text-slate-800">{{ o.marca?.nombre || '-' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">TL</span><span class="font-medium text-right text-slate-800">{{ o.team_leader?.name || '-' }}</span></div>
              <div class="flex justify-between gap-3"><span class="text-slate-500">Fecha</span><span class="font-medium text-right text-slate-800">{{ o.fecha || '—' }}</span></div>
            </div>
            <div class="mt-4 flex justify-end">
              <a :href="o.urls.show" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Ver OT
              </a>
            </div>
          </div>
          <div v-if="rows.length===0" class="text-center text-sm text-slate-500">No hay registros.</div>
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
