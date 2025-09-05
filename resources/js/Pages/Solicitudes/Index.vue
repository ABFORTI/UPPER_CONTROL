<script setup>
import { reactive } from 'vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({
  data: Object,
  filters: Object,
  servicios: Array,
  urls: Object
})

// Estado local con defaults desde props
const f = reactive({
  estatus:  props.filters?.estatus  ?? '',
  servicio: props.filters?.servicio ?? '',
  folio:    props.filters?.folio    ?? '',
  desde:    props.filters?.desde    ?? '',
  hasta:    props.filters?.hasta    ?? '',
})

function aplicar() {
  router.get(props.urls.index, f, { preserveState:true, preserveScroll:true, replace:true })
}
function limpiar() {
  f.estatus=''; f.servicio=''; f.folio=''; f.desde=''; f.hasta='';
  router.get(props.urls.index, {}, { replace:true })
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Solicitudes</h1>

    <!-- Filtros -->
    <div class="mb-4 grid gap-2 md:grid-cols-5 items-end">
      <div>
        <label class="block text-sm mb-1">Estatus</label>
        <select v-model="f.estatus" class="border p-2 rounded w-full">
          <option value="">Todos</option>
          <option value="pendiente">Pendiente</option>
          <option value="aprobada">Aprobada</option>
          <option value="rechazada">Rechazada</option>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Servicio</label>
        <select v-model="f.servicio" class="border p-2 rounded w-full">
          <option value="">Todos</option>
          <option v-for="s in servicios" :key="s.id" :value="s.id">{{ s.nombre }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Folio</label>
        <input v-model="f.folio" class="border p-2 rounded w-full" placeholder="Buscar folio" />
      </div>
      <div>
        <label class="block text-sm mb-1">Desde</label>
        <input type="date" v-model="f.desde" class="border p-2 rounded w-full" />
      </div>
      <div>
        <label class="block text-sm mb-1">Hasta</label>
        <input type="date" v-model="f.hasta" class="border p-2 rounded w-full" />
      </div>

      <div class="md:col-span-5 flex gap-2">
        <button @click="aplicar" class="px-3 py-2 rounded bg-black text-white">Filtrar</button>
        <button @click="limpiar" class="px-3 py-2 rounded bg-gray-100">Limpiar</button>
        <a href="./solicitudes/create" class="ml-auto px-3 py-2 rounded bg-emerald-600 text-white">Nueva solicitud</a>
      </div>
    </div>

    <!-- Lista -->
    <div class="space-y-2">
      <div v-for="s in data.data" :key="s.id" class="border p-3 rounded">
        <div class="font-semibold">{{ s.folio }} — {{ s.servicio?.nombre }}</div>
        <div class="text-sm opacity-70">
          Centro: {{ s.centro?.nombre }} | Estatus: {{ s.estatus }} | Fecha: {{ s.created_at }}
        </div>
        <a :href="`./solicitudes/${s.id}`" class="text-blue-600 underline text-sm">Ver detalle</a>
      </div>
    </div>

    <!-- Paginación -->
    <div class="mt-4 flex gap-2">
      <Link v-for="link in data.links" :key="link.label" :href="link.url || '#'"
            class="px-2 py-1 border rounded"
            :class="{'bg-black text-white': link.active}"
            v-html="link.label" />
    </div>
  </div>
</template>
