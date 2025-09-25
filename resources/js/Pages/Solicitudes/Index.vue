<script setup>
import PillTabs from '@/Components/ui/PillTabs.vue'
import { router } from '@inertiajs/vue3'
import { reactive } from 'vue'


const props = defineProps({
data: Object, // paginator
filters: Object, // { estatus, servicio, folio, desde, hasta }
servicios: Array,
urls: Object // { index }
})


const tabs = [
{ label:'TODOS', value:'' },
{ label:'APROBADAS', value:'aprobada' },
{ label:'PENDIENTES', value:'pendiente' },
{ label:'RECHAZADAS', value:'rechazada' },
]


function toPage(link){ if(link.url){ router.get(link.url, {}, {preserveState:true}) } }

const f = reactive({
  estatus: props.filters?.estatus || '',
  servicio: props.filters?.servicio || '',
  folio: props.filters?.folio || '',
  desde: props.filters?.desde || '',
  hasta: props.filters?.hasta || '',
})

function applyFilters(){
  const u = new URL(props.urls.index, window.location.origin)
  const sp = u.searchParams
  const payload = { ...f }
  Object.entries(payload).forEach(([k,v])=>{
    if (!v) sp.delete(k); else sp.set(k, String(v))
  })
  const href = `${u.pathname}?${sp.toString()}`
  router.get(href, {}, { preserveState:true, replace:true })
}

function clearFilters(){
  f.estatus=''; f.servicio=''; f.folio=''; f.desde=''; f.hasta=''
  router.get(props.urls.index, {}, { preserveState:false, replace:true })
}
</script>


<template>
<div class="p-4 md:p-6">
<!-- Header -->
<div class="flex items-center justify-between mb-4">
<h1 class="font-display text-2xl md:text-3xl font-semibold tracking-wide">SOLICITUDES</h1>
<a href="./solicitudes/create" class="btn btn-primary">AGREGAR +</a>
</div>


<div class="card">
<div class="card-section">
<h2 class="text-sm md:text-base font-semibold mb-4">HISTORIAL DE SOLICITUDES</h2>


<!-- Tabs de estatus -->
<div class="mb-4">
<PillTabs :tabs="tabs" :model-value="filters?.estatus || ''" :url="urls.index" :extra="{...filters, estatus: undefined}" />
</div>


<!-- Filtros avanzados -->
<div class="mb-4 grid gap-3 md:grid-cols-5 items-end">
  <div>
    <label class="block text-xs mb-1">Servicio</label>
    <select v-model="f.servicio" class="border p-2 rounded w-full">
      <option value="">— Todos —</option>
      <option v-for="s in servicios" :key="s.id" :value="s.id">{{ s.nombre }}</option>
    </select>
  </div>
  <div>
    <label class="block text-xs mb-1">Folio</label>
    <input v-model="f.folio" class="border p-2 rounded w-full" placeholder="Ej. UPR-202509-0001" />
  </div>
  <div>
    <label class="block text-xs mb-1">Desde</label>
    <input type="date" v-model="f.desde" class="border p-2 rounded w-full" />
  </div>
  <div>
    <label class="block text-xs mb-1">Hasta</label>
    <input type="date" v-model="f.hasta" class="border p-2 rounded w-full" />
  </div>
  <div class="flex gap-2">
    <button @click="applyFilters" class="btn btn-primary w-full">Aplicar</button>
    <button @click="clearFilters" class="btn btn-outline w-full">Limpiar</button>
  </div>
  <div class="md:col-span-5 text-xs text-gray-500">
    Consejo: puedes combinar estatus de las pestañas con estos filtros.
  </div>
</div>


<div class="overflow-auto rounded-lg border">
<table class="min-w-full text-sm">
<thead class="table-head">
<tr>
<th class="table-cell">Folio</th>
<th class="table-cell">Usuario</th>
<th class="table-cell">Servicio</th>
<th class="table-cell">Centro</th>
<th class="table-cell">Cantidad</th>
<th class="table-cell">Estatus</th>
<th class="table-cell">Fecha</th>
<th class="table-cell">Acciones</th>
</tr>
</thead>
<tbody>
<tr v-for="s in data.data" :key="s.id" class="odd:bg-white even:bg-gray-50">
<td class="table-cell font-medium">{{ s.folio || s.id }}</td>
<td class="table-cell">{{ s.cliente?.name || '-' }}</td>
<td class="table-cell">{{ s.servicio?.nombre || '-' }}</td>
<td class="table-cell">{{ s.centro?.nombre || '-' }}</td>
<td class="table-cell">{{ s.cantidad }}</td>
<td class="table-cell">
<span class="badge"
  :class="{
	'badge-green': s.estatus==='aprobada',
	'badge-yellow': s.estatus==='pendiente',
	'badge-red': s.estatus==='rechazada'
  }"
>
  {{ s.estatus }}
</span>
</td>
<td class="table-cell">{{ s.fecha }}</td>
<td class="table-cell">
  <a :href="`./solicitudes/${s.id}`" class="btn btn-xs btn-outline">Ver</a>
</td>
</tr>
</tbody>
</table>
</div>
</div>
</div>
</div>
</template>