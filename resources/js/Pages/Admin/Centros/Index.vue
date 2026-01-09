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
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
      
      <!-- Header con gradiente -->
      <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-2xl shadow-xl p-6 sm:p-8 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div class="w-full md:w-auto">
            <h1 class="text-3xl font-extrabold text-white mb-2">🏢 Centros de Trabajo</h1>
            <p class="text-indigo-100">Administra los centros de trabajo del sistema</p>
          </div>
          <a :href="route('admin.centros.create')" 
             class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white text-indigo-600 font-bold rounded-xl hover:bg-indigo-50 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105 w-full sm:w-auto">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo Centro
          </a>
        </div>
        
        <!-- Totales -->
        <div class="mt-6 grid grid-cols-2 md:grid-cols-2 gap-4">
          <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-xl p-4">
            <div class="text-indigo-100 text-sm font-medium">Activos</div>
            <div class="text-3xl font-bold text-white mt-1">{{ totales.activos }}</div>
          </div>
          <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-xl p-4">
            <div class="text-indigo-100 text-sm font-medium">Inactivos</div>
            <div class="text-3xl font-bold text-white mt-1">{{ totales.inactivos }}</div>
          </div>
        </div>
      </div>

      <!-- Filtros -->
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 mb-6">
        <form @submit.prevent="submit" class="flex flex-wrap items-end gap-4">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Buscar</label>
            <input name="search" 
                   :value="filters.search || ''" 
                   placeholder="Nombre del centro..."
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200">
          </div>
          <div class="min-w-[180px]">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Estado</label>
            <select name="activo" 
                    :value="filters.activo ?? ''" 
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200">
              <option value="">Todos</option>
              <option value="1">Solo activos</option>
              <option value="0">Solo inactivos</option>
            </select>
          </div>
          <button class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold rounded-xl hover:shadow-xl transition-all duration-200 transform hover:scale-105">
            Filtrar
          </button>
        </form>
      </div>

      <!-- Tabla -->
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 overflow-hidden">
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-[0.75rem] lg:text-sm divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700 uppercase tracking-wider">
              <tr>
                <th class="px-4 lg:px-6 py-3 text-left font-bold">Nombre</th>
                <th class="px-4 lg:px-6 py-3 text-left font-bold">Número</th>
                <th class="px-4 lg:px-6 py-3 text-left font-bold">Prefijo</th>
                <th class="px-4 lg:px-6 py-3 text-left font-bold">Dirección</th>
                <th class="px-4 lg:px-6 py-3 text-center font-bold">Estado</th>
                <th class="px-4 lg:px-6 py-3 text-right font-bold">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="c in data.data" :key="c.id" class="hover:bg-indigo-50 transition-colors duration-150">
                <td class="px-4 lg:px-6 py-3 align-top">
                  <div class="font-semibold text-gray-900">{{ c.nombre }}</div>
                </td>
                <td class="px-4 lg:px-6 py-3">
                  <span class="inline-flex px-3 py-1 bg-slate-100 text-slate-700 rounded-full font-medium">
                    {{ c.numero_centro || '—' }}
                  </span>
                </td>
                <td class="px-4 lg:px-6 py-3">
                  <span class="inline-flex px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full font-medium">
                    {{ c.prefijo || '—' }}
                  </span>
                </td>
                <td class="px-4 lg:px-6 py-3 text-gray-600">{{ c.direccion || '—' }}</td>
                <td class="px-4 lg:px-6 py-3 text-center">
                  <span v-if="c.activo" class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full font-semibold">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Activo
                  </span>
                  <span v-else class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 text-red-700 rounded-full font-semibold">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    Inactivo
                  </span>
                </td>
                <td class="px-4 lg:px-6 py-3">
                  <div class="flex flex-wrap items-center justify-end gap-2">
                    <a :href="route('admin.centros.edit', c.id)" 
                       class="inline-flex items-center gap-1 px-3 sm:px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors duration-200 text-xs sm:text-sm">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                      Editar
                    </a>
                    <button @click="toggle(c.id)" 
                            :class="[
                              'inline-flex items-center gap-1 px-3 sm:px-4 py-2 font-medium rounded-lg transition-colors duration-200 text-xs sm:text-sm',
                              c.activo ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-emerald-600 text-white hover:bg-emerald-700'
                            ]">
                      <svg v-if="c.activo" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                      </svg>
                      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      {{ c.activo ? 'Desactivar' : 'Activar' }}
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!data.data || data.data.length === 0">
                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                  <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                  </svg>
                  <p class="font-medium">No hay centros de trabajo</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="md:hidden p-4 space-y-4">
          <div v-for="c in data.data" :key="`m-${c.id}`" class="border border-gray-200 rounded-2xl p-4 shadow-sm bg-white">
            <div class="flex flex-col gap-2">
              <div>
                <div class="text-xs uppercase tracking-wide text-gray-500">Centro</div>
                <div class="text-lg font-semibold text-gray-900">{{ c.nombre }}</div>
              </div>
              <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <span class="font-semibold text-gray-700">Número:</span>
                <span class="inline-flex px-3 py-1 bg-slate-100 text-slate-700 rounded-full font-medium">{{ c.numero_centro || '—' }}</span>
              </div>
              <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <span class="font-semibold text-gray-700">Prefijo:</span>
                <span class="inline-flex px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full font-medium">{{ c.prefijo || '—' }}</span>
              </div>
              <div class="text-sm text-gray-600">
                <div class="text-xs font-semibold text-gray-500">Dirección</div>
                <div>{{ c.direccion || '—' }}</div>
              </div>
              <span v-if="c.activo" class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200 inline-flex items-center gap-2 self-start">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Activo
              </span>
              <span v-else class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-200 inline-flex items-center gap-2 self-start">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                Inactivo
              </span>
            </div>

            <div class="mt-4 flex flex-col sm:flex-row gap-2">
              <a :href="route('admin.centros.edit', c.id)"
                 class="flex-1 px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition-colors duration-200 flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar
              </a>
              <button @click="toggle(c.id)"
                      :class="[
                        'flex-1 px-4 py-2 rounded-lg font-semibold transition-colors duration-200 flex items-center justify-center gap-2',
                        c.activo ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-emerald-600 text-white hover:bg-emerald-700'
                      ]">
                <svg v-if="c.activo" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ c.activo ? 'Desactivar' : 'Activar' }}
              </button>
            </div>
          </div>

          <div v-if="(!data.data || data.data.length === 0)" class="border border-dashed border-gray-200 rounded-2xl p-8 text-center text-gray-500">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
            <p class="font-medium">No hay centros de trabajo</p>
          </div>
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
