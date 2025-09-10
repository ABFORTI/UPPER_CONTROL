<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  data: Object, filters: Object, centros: Array, roles: Array
})

function submit(e){
  const f = new FormData(e.target)
  router.get(route('admin.users.index'), Object.fromEntries(f.entries()), { preserveState:true, replace:true })
}
function toggle(id){
  router.post(route('admin.users.toggle', id))
}
function impersonate(url){
  router.post(url)
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Usuarios</h1>

    <form @submit.prevent="submit" class="flex flex-wrap items-end gap-3 mb-4">
      <input name="search" :value="filters.search || ''" placeholder="Buscar" class="border p-2 rounded" />
      <select name="role" :value="filters.role || ''" class="border p-2 rounded">
        <option value="">— Rol —</option>
        <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
      </select>
      <select name="centro" :value="filters.centro || ''" class="border p-2 rounded min-w-[14rem]">
        <option value="">— Centro —</option>
        <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
      </select>
      <button class="px-3 py-2 bg-black text-white rounded">Filtrar</button>

      <a :href="route('admin.users.create')" class="ml-auto px-3 py-2 rounded bg-indigo-600 text-white">Nuevo</a>
    </form>

    <div class="overflow-auto">
      <table class="w-full text-sm border">
        <thead>
          <tr class="bg-gray-50 text-left">
            <th class="p-2">Nombre</th><th class="p-2">Email</th><th class="p-2">Centro</th>
            <th class="p-2">Rol</th><th class="p-2">Activo</th><th class="p-2">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in data.data" :key="u.id" class="border-t">
            <td class="p-2">{{ u.name }}</td>
            <td class="p-2">{{ u.email }}</td>
            <td class="p-2">{{ u.centro_trabajo_id }}</td>
            <td class="p-2">{{ u.roles?.[0]?.name || '—' }}</td>
            <td class="p-2">
              <span :class="u.activo ? 'text-emerald-700' : 'text-red-700'">
                {{ u.activo ? 'Sí' : 'No' }}
              </span>
            </td>
            <td class="p-2 flex gap-2">
              <a :href="route('admin.users.edit', u.id)" class="text-indigo-700">Editar</a>
              <button @click="toggle(u.id)" class="text-slate-700 underline">
                {{ u.activo ? 'Desactivar' : 'Activar' }}
              </button>
              <button @click="impersonate(u.urls.impersonate)" class="text-amber-700 underline">
                Impersonar
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
