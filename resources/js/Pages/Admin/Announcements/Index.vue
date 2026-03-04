<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  data: Object,
  filters: Object,
})

function submit(e) {
  const formData = new FormData(e.target)
  router.get(route('admin.announcements.index'), Object.fromEntries(formData.entries()), { preserveState: true, replace: true })
}

function destroyAnnouncement(id) {
  if (!confirm('¿Eliminar este anuncio?')) return
  router.delete(route('admin.announcements.destroy', id))
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-blue-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
      <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-2xl shadow-xl p-6 sm:p-8 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-extrabold text-white mb-2">📹 Video de actualizaciones</h1>
            <p class="text-indigo-100">Publicaciones activas para clientes y empleados</p>
          </div>
          <a
            :href="route('admin.announcements.create')"
            class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white text-indigo-700 font-bold rounded-xl hover:bg-indigo-50"
          >
            Nuevo anuncio
          </a>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 mb-6">
        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Buscar</label>
            <input
              name="search"
              :value="filters.search || ''"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl"
              placeholder="Título o texto"
            >
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Estado</label>
            <select name="active" :value="filters.active ?? ''" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl">
              <option value="">Todos</option>
              <option value="1">Activos</option>
              <option value="0">Inactivos</option>
            </select>
          </div>
          <div class="flex items-end">
            <button class="w-full px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700">Filtrar</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50 text-gray-700 uppercase tracking-wider text-xs">
              <tr>
                <th class="px-6 py-3 text-left">Título</th>
                <th class="px-6 py-3 text-left">Tipo</th>
                <th class="px-6 py-3 text-left">Vigencia</th>
                <th class="px-6 py-3 text-left">Destinatarios</th>
                <th class="px-6 py-3 text-center">Estado</th>
                <th class="px-6 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="item in data.data" :key="item.id" class="hover:bg-indigo-50">
                <td class="px-6 py-4">
                  <div class="font-semibold text-gray-900">{{ item.title }}</div>
                  <div class="text-xs text-gray-500">Creado: {{ item.created_at }} · {{ item.created_by_name || 'Sistema' }}</div>
                </td>
                <td class="px-6 py-4 uppercase text-xs font-semibold text-indigo-700">{{ item.video_type }}</td>
                <td class="px-6 py-4 text-sm text-gray-600">
                  <div>Inicio: {{ item.starts_at || 'Sin inicio' }}</div>
                  <div>Fin: {{ item.ends_at || 'Sin fin' }}</div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                  <div>
                    <span class="font-semibold text-gray-700">Roles:</span>
                    {{ item.target_roles?.length ? item.target_roles.join(', ') : 'Todos' }}
                  </div>
                  <div>
                    <span class="font-semibold text-gray-700">Centros:</span>
                    {{ item.target_centros?.length ? item.target_centros.join(', ') : 'Todos' }}
                  </div>
                </td>
                <td class="px-6 py-4 text-center">
                  <span
                    :class="item.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                    class="px-3 py-1 rounded-full text-xs font-semibold"
                  >
                    {{ item.is_active ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex justify-end gap-2">
                    <a :href="route('admin.announcements.edit', item.id)" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700">Editar</a>
                    <button @click="destroyAnnouncement(item.id)" class="px-4 py-2 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700">Eliminar</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!data.data || data.data.length === 0">
                <td colspan="6" class="px-6 py-10 text-center text-gray-500">No hay anuncios registrados.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="data.links && data.links.length > 3" class="px-6 py-4 bg-gray-50 border-t border-gray-100">
          <div class="flex items-center justify-center gap-2">
            <a
              v-for="link in data.links"
              :key="link.url || link.label"
              :href="link.url || '#'"
              v-html="link.label"
              :class="[
                'px-4 py-2 rounded-lg font-medium transition-all duration-200',
                link.active
                  ? 'bg-indigo-600 text-white'
                  : link.url
                    ? 'bg-white text-gray-700 hover:bg-indigo-50 border border-gray-200'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'
              ]"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
