<script setup>
import { reactive, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({
  data: Object,
  filters: Object,
  servicios: Array,
  urls: Object
})

const f = reactive({
  id:        props.filters?.id        ?? '',
  estatus:   props.filters?.estatus   ?? '',
  calidad:   props.filters?.calidad   ?? '',
  servicio:  props.filters?.servicio  ?? '',
  desde:     props.filters?.desde     ?? '',
  hasta:     props.filters?.hasta     ?? '',
})

function aplicar () {
  router.get(props.urls.index, f, { preserveState:true, preserveScroll:true, replace:true })
}
function limpiar () {
  f.id=''; f.estatus=''; f.calidad=''; f.servicio=''; f.desde=''; f.hasta=''
  router.get(props.urls.index, {}, { replace:true })
}

const rows = computed(()=> props.data?.data ?? [])
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Órdenes de Trabajo</h1>

    <!-- Filtros -->
    <div class="mb-4 grid gap-2 md:grid-cols-6 items-end">
      <div>
        <label class="block text-sm mb-1">ID OT</label>
        <input v-model="f.id" class="border p-2 rounded w-full" placeholder="Ej. 120" />
      </div>
      <div>
        <label class="block text-sm mb-1">Estatus</label>
        <select v-model="f.estatus" class="border p-2 rounded w-full">
          <option value="">Todos</option>
          <option value="generada">Generada</option>
          <option value="asignada">Asignada</option>
          <option value="en_proceso">En proceso</option>
          <option value="completada">Completada</option>
          <option value="autorizada_cliente">Autorizada cliente</option>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Calidad</label>
        <select v-model="f.calidad" class="border p-2 rounded w-full">
          <option value="">Todas</option>
          <option value="pendiente">Pendiente</option>
          <option value="validado">Validado</option>
          <option value="rechazado">Rechazado</option>
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
        <label class="block text-sm mb-1">Desde</label>
        <input type="date" v-model="f.desde" class="border p-2 rounded w-full" />
      </div>
      <div>
        <label class="block text-sm mb-1">Hasta</label>
        <input type="date" v-model="f.hasta" class="border p-2 rounded w-full" />
      </div>

      <div class="md:col-span-6 flex gap-2">
        <button @click="aplicar" class="px-3 py-2 rounded bg-black text-white">Filtrar</button>
        <button @click="limpiar" class="px-3 py-2 rounded bg-gray-100">Limpiar</button>
      </div>
    </div>

    <!-- Tabla -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm border">
        <thead>
          <tr class="bg-gray-50 text-left">
            <th class="p-2">ID</th>
            <th class="p-2">Servicio</th>
            <th class="p-2">Centro</th>
            <th class="p-2">Estatus</th>
            <th class="p-2">Calidad</th>
            <th class="p-2">TL</th>
            <th class="p-2">Fecha</th>
            <th class="p-2">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in rows" :key="o.id" class="border-t">
            <td class="p-2">#{{ o.id }}</td>
            <td class="p-2">{{ o.servicio?.nombre }}</td>
            <td class="p-2">{{ o.centro?.nombre }}</td>
            <td class="p-2">{{ o.estatus }}</td>
            <td class="p-2">{{ o.calidad_resultado }}</td>
            <td class="p-2">{{ o.team_leader?.name ?? '—' }}</td>
            <td class="p-2">{{ o.created_at }}</td>
            <td class="p-2">
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

    <!-- Paginación -->
    <div class="mt-4 flex gap-2">
      <Link v-for="link in data.links" :key="link.label" :href="link.url || '#'"
            class="px-2 py-1 border rounded"
            :class="{'bg-black text-white': link.active}"
            v-html="link.label" />
    </div>
  </div>
</template>
