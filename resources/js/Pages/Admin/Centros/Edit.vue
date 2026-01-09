<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ centro: Object })

const form = useForm({
  nombre: props.centro?.nombre || '',
  numero_centro: props.centro?.numero_centro || '',
  prefijo: props.centro?.prefijo || '',
  direccion: props.centro?.direccion || '',
  activo: props.centro?.activo ?? true,
})

function save(){
  if (props.centro) form.patch(route('admin.centros.update', props.centro.id))
  else form.post(route('admin.centros.store'))
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
      
      <!-- Header -->
      <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-2xl shadow-xl p-8 mb-6">
        <div class="flex items-center gap-4">
          <a :href="route('admin.centros.index')" 
             class="p-2 bg-white bg-opacity-20 rounded-xl hover:bg-opacity-30 transition-all duration-200">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
          </a>
          <div>
            <h1 class="text-3xl font-extrabold text-white">
              {{ props.centro ? '✏️ Editar Centro' : '➕ Nuevo Centro' }}
            </h1>
            <p class="text-indigo-100 mt-1">{{ props.centro ? 'Actualiza la información del centro de trabajo' : 'Crea un nuevo centro de trabajo' }}</p>
          </div>
        </div>
      </div>

      <!-- Formulario -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-gray-100 overflow-hidden">
        <div class="p-8">
          <form @submit.prevent="save" class="space-y-6">
            
            <!-- Nombre -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                Nombre del Centro
                <span class="text-red-500">*</span>
              </label>
              <input v-model="form.nombre" 
                     type="text"
                     required
                     placeholder="Ej: Centro de Producción Principal"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200"
                     :class="{ 'border-red-300 bg-red-50': form.errors.nombre }">
              <p v-if="form.errors.nombre" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ form.errors.nombre }}
              </p>
            </div>

            <!-- Número de centro (interno) -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                Número de centro
                <span class="text-gray-400 text-xs font-normal ml-1">(interno)</span>
              </label>
              <input v-model="form.numero_centro"
                     type="text"
                     inputmode="numeric"
                     placeholder="Ej: 1001"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200"
                     :class="{ 'border-red-300 bg-red-50': form.errors.numero_centro }">
              <p v-if="form.errors.numero_centro" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ form.errors.numero_centro }}
              </p>
            </div>

            <!-- Prefijo -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                Prefijo
                <span class="text-gray-400 text-xs font-normal ml-1">(opcional)</span>
              </label>
              <input v-model="form.prefijo" 
                     type="text"
                     maxlength="10"
                     placeholder="Ej: UPR, CTL, MAT"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200 uppercase">
              <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Se usa para generar folios. Máximo 10 caracteres.
              </p>
            </div>

            <!-- Dirección -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                Dirección
                <span class="text-gray-400 text-xs font-normal ml-1">(opcional)</span>
              </label>
              <input v-model="form.direccion" 
                     type="text"
                     placeholder="Ej: Av. Principal #123, Col. Industrial"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200">
            </div>

            <!-- Estado Activo -->
            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl p-6 border-2 border-indigo-200">
              <label class="flex items-center gap-4 cursor-pointer">
                <div class="relative">
                  <input type="checkbox" 
                         v-model="form.activo" 
                         :true-value="1" 
                         :false-value="0"
                         class="sr-only peer">
                  <div class="w-14 h-8 bg-gray-300 rounded-full peer peer-checked:bg-indigo-600 transition-all duration-200"></div>
                  <div class="absolute left-1 top-1 w-6 h-6 bg-white rounded-full transition-all duration-200 peer-checked:translate-x-6 shadow-md"></div>
                </div>
                <div class="flex-1">
                  <div class="text-sm font-semibold text-gray-800">Centro activo</div>
                  <p class="text-xs text-gray-600 mt-0.5">El centro estará disponible para asignación de usuarios y operaciones</p>
                </div>
              </label>
            </div>

          </form>
        </div>

        <!-- Footer con botones -->
        <div class="bg-gray-50 px-8 py-6 border-t-2 border-gray-100 flex items-center justify-between gap-4">
          <a :href="route('admin.centros.index')" 
             class="px-6 py-3 rounded-xl border-2 border-gray-300 font-semibold text-gray-700 hover:bg-gray-100 transition-all duration-200">
            Cancelar
          </a>
          <button @click="save" 
                  :disabled="form.processing"
                  class="px-8 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 font-bold text-white hover:shadow-xl transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed transform hover:scale-105 flex items-center gap-2">
            <svg v-if="!form.processing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ form.processing ? 'Guardando...' : 'Guardar Centro' }}
          </button>
        </div>
      </div>

    </div>
  </div>
</template>
