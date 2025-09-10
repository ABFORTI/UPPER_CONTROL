<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  data: Object,
  filters: Object,
  totales: Object
})

function submit(e){
  const f = new FormData(e.target)
  router.get(route('admin.centros.index'), Object.fromEntries(f.entries()), { preserveState:true, replace:true })
}
function toggle(id){
  router.post(route('admin.centros.toggle', id))
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Centros de trabajo</h1>

    <div class="flex gap-4 text-sm mb-3">
      <div>Activos: <strong>{{ totales.activos }}</strong></div>
      <div>Inactivos: <strong>{{ totales.inactivos }}</strong></div>
    </div>

    <form @submit.prevent="submit" class="flex flex-wrap items-end gap-3 mb-4">
      <input name="search" :value="filters.search || ''" placeholder="Buscar por nombre" class="border p-2 rounded">
      <select name="activo" :value="filters.activo ?? ''" class="border p-2 rounded">
        <option value="">— Todos —</option>
        <option value="1">Solo activos</option>
        <option value="0">Solo inactivos</option>
      </select>
      <button class="px-3 py-2 rounded bg-black text-white">Filtrar</button>
      <a :href="route('admin.centros.create')" class="ml-auto px-3 py-2 rounded bg-indigo-600 text-white">Nuevo centro</a>
    </form>

    <div class="overflow-auto">
      <table class="w-full text-sm border">
        <thead>
          <tr class="bg-gray-50 text-left">
            <th class="p-2">Nombre</th>
            <th class="p-2">Prefijo</th>
            <th class="p-2">Dirección</th>
            <th class="p-2">Activo</th>
            <th class="p-2">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in data.data" :key="c.id" class="border-t">
            <td class="p-2">{{ c.nombre }}</td>
            <td class="p-2">{{ c.prefijo || '—' }}</td>
            <td class="p-2">{{ c.direccion || '—' }}</td>
            <td class="p-2">
              <span :class="c.activo ? 'text-emerald-700' : 'text-red-700'">
                {{ c.activo ? 'Sí' : 'No' }}
              </span>
            </td>
            <td class="p-2 flex gap-2">
              <a :href="route('admin.centros.edit', c.id)" class="text-indigo-700">Editar</a>
              <button @click="toggle(c.id)" class="text-slate-700 underline">
                {{ c.activo ? 'Desactivar' : 'Activar' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-3 flex gap-2">
      <a v-for="link in data.links" :key="link.url || link.label" :href="link.url || '#'"
         v-html="link.label" :class="['px-2 py-1 border rounded', { 'bg-black text-white': link.active }]"></a>
    </div>
  </div>
</template>
