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
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-blue-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
      
      <!-- Header con gradiente -->
  <div class="bg-gradient-to-r from-indigo-600 to-[#1E1C8F] rounded-2xl shadow-xl p-8 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-extrabold text-white mb-2">👥 Usuarios</h1>
            <p class="text-indigo-100">Administra los usuarios del sistema</p>
          </div>
       <a :href="route('admin.users.create')" 
         class="inline-flex items-center gap-2 px-6 py-3 bg-white text-[#1E1C8F] font-bold rounded-xl hover:bg-indigo-50 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo Usuario
          </a>
        </div>
      </div>

      <!-- Filtros -->
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 mb-6">
        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Buscar</label>
            <input name="search" 
                   :value="filters.search || ''" 
                   placeholder="Nombre o email..."
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Rol</label>
            <select name="role" 
                    :value="filters.role || ''" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200">
              <option value="">Todos los roles</option>
              <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Centro</label>
            <select name="centro" 
                    :value="filters.centro || ''" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200">
              <option value="">Todos los centros</option>
              <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
            </select>
          </div>
          <div class="flex items-end">
            <button class="w-full px-6 py-3 bg-gradient-to-r from-indigo-600 to-[#1E1C8F] text-white font-bold rounded-xl hover:shadow-xl transition-all duration-200 transform hover:scale-105">
              Filtrar
            </button>
          </div>
        </form>
      </div>

      <!-- Tabla -->
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Usuario</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Centro Principal</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Centros Asignados</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Rol</th>
                <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="u in data.data" :key="u.id" class="hover:bg-indigo-50 transition-colors duration-150">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 h-10 w-10 bg-[#1E1C8F] rounded-full flex items-center justify-center text-white font-bold">
                      {{ u.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900">{{ u.name }}</div>
                      <div class="text-sm text-gray-500">{{ u.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 text-gray-600 text-sm">
                  {{ u.centro_nombre || '—' }}
                </td>
                <td class="px-6 py-4">
                  <div v-if="u.centros_nombres?.length" class="flex flex-wrap gap-1">
        <span v-for="(centro, idx) in u.centros_nombres" :key="idx"
          class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">
                      {{ centro }}
                    </span>
                  </div>
                  <span v-else class="text-gray-400 text-sm">—</span>
                </td>
                <td class="px-6 py-4">
                  <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-semibold uppercase">
                    {{ u.roles?.[0]?.name || '—' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-center">
                  <span v-if="u.activo" class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-sm font-semibold">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Activo
                  </span>
                  <span v-else class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-semibold">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    Inactivo
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-end gap-2">
              <a :href="route('admin.users.edit', u.id)" 
                class="inline-flex items-center gap-1 px-3 py-2 bg-[#1E1C8F] text-white text-sm font-medium rounded-lg hover:bg-indigo-800 transition-colors duration-200">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                      Editar
                    </a>
                    <button @click="toggle(u.id)" 
                            :class="[
                              'inline-flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200',
                              u.activo ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-emerald-600 text-white hover:bg-emerald-700'
                            ]">
                      <svg v-if="u.activo" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                      </svg>
                      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      {{ u.activo ? 'Desactivar' : 'Activar' }}
                    </button>
                    <button @click="impersonate(u.urls.impersonate)" 
                            class="inline-flex items-center gap-1 px-3 py-2 bg-amber-100 text-amber-700 text-sm font-medium rounded-lg hover:bg-amber-200 transition-colors duration-200">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                      </svg>
                      Impersonar
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!data.data || data.data.length === 0">
                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                  <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                  </svg>
                  <p class="font-medium">No hay usuarios</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Paginación -->
        <div v-if="data.links && data.links.length > 3" class="px-6 py-4 bg-gray-50 border-t border-gray-100">
          <div class="flex items-center justify-center gap-2">
            <a v-for="link in data.links" 
               :key="link.url || link.label" 
               :href="link.url || '#'"
               v-html="link.label" 
               :class="[
                 'px-4 py-2 rounded-lg font-medium transition-all duration-200',
                 link.active 
                   ? 'bg-indigo-600 text-white shadow-lg' 
                   : link.url 
                     ? 'bg-white text-gray-700 hover:bg-indigo-50 border-2 border-gray-200' 
                     : 'bg-gray-100 text-gray-400 cursor-not-allowed'
               ]">
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
