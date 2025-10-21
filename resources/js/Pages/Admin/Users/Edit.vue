<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ user:Object, centros:Array, roles:Array })

const form = useForm({
  name: props.user?.name || '',
  email: props.user?.email || '',
  phone: props.user?.phone || '',
  centro_trabajo_id: props.user?.centro_trabajo_id || '',
  role: props.user?.role || 'cliente',
  centros_ids: props.user?.centros_ids || [],
  password: '',
  password_confirmation: ''
})

function save(){
  if (props.user) form.patch(route('admin.users.update', props.user.id))
  else form.post(route('admin.users.store'))
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-purple-50 to-pink-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
      
      <!-- Header -->
      <div class="bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-xl p-8 mb-6">
        <div class="flex items-center gap-4">
          <a :href="route('admin.users.index')" 
             class="p-2 bg-white bg-opacity-20 rounded-xl hover:bg-opacity-30 transition-all duration-200">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
          </a>
          <div>
            <h1 class="text-3xl font-extrabold text-white">
              {{ props.user ? '✏️ Editar Usuario' : '👤 Nuevo Usuario' }}
            </h1>
            <p class="text-purple-100 mt-1">{{ props.user ? 'Actualiza la información del usuario' : 'Crea una nueva cuenta de usuario' }}</p>
          </div>
        </div>
      </div>

      <!-- Formulario -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-gray-100 overflow-hidden">
        <div class="p-8">
          <form @submit.prevent="save" class="space-y-6">
            
            <!-- Grid para información básica -->
            <div class="grid md:grid-cols-2 gap-6">
              
              <!-- Nombre -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Nombre completo
                  <span class="text-red-500">*</span>
                </label>
                <input v-model="form.name" 
                       type="text"
                       required
                       placeholder="Ej: Juan Pérez García"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-400 transition-all duration-200"
                       :class="{ 'border-red-300 bg-red-50': form.errors.name }">
                <p v-if="form.errors.name" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ form.errors.name }}
                </p>
              </div>

              <!-- Email -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Email
                  <span class="text-red-500">*</span>
                </label>
                <input v-model="form.email" 
                       type="email"
                       required
                       placeholder="usuario@ejemplo.com"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-400 transition-all duration-200"
                       :class="{ 'border-red-300 bg-red-50': form.errors.email }">
                <p v-if="form.errors.email" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ form.errors.email }}
                </p>
              </div>

            </div>

            <!-- Teléfono -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                Teléfono
                <span class="text-gray-400 text-xs font-normal ml-1">(opcional)</span>
              </label>
              <input v-model="form.phone" 
                     type="tel"
                     placeholder="Ej: 555-123-4567"
                     class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-400 transition-all duration-200">
            </div>

            <!-- Separador -->
            <div class="border-t-2 border-gray-100 pt-6">
              <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Asignación organizacional
              </h2>
            </div>

            <!-- Grid para Centro y Rol -->
            <div class="grid md:grid-cols-2 gap-6">
              
              <!-- Centro principal -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Centro de trabajo principal
                  <span class="text-red-500">*</span>
                </label>
                <select v-model="form.centro_trabajo_id"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-400 transition-all duration-200"
                        :class="{ 'border-red-300 bg-red-50': form.errors.centro_trabajo_id }">
                  <option value="">— Selecciona un centro —</option>
                  <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
                <p v-if="form.errors.centro_trabajo_id" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ form.errors.centro_trabajo_id }}
                </p>
              </div>

              <!-- Rol -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Rol del usuario
                  <span class="text-red-500">*</span>
                </label>
                <select v-model="form.role"
                        required
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-400 transition-all duration-200 capitalize"
                        :class="{ 'border-red-300 bg-red-50': form.errors.role }">
                  <option v-for="r in roles" :key="r" :value="r" class="capitalize">{{ r }}</option>
                </select>
                <p v-if="form.errors.role" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ form.errors.role }}
                </p>
              </div>

            </div>

      <!-- Multiselección de centros (solo para admin/calidad/facturacion/control/comercial) -->
      <div v-if="['admin','calidad','facturacion','control','comercial'].includes(form.role)" 
                 class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl p-6 border-2 border-purple-200">
              <label class="block text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Centros de trabajo asignados (acceso múltiple)
              </label>
              <select v-model="form.centros_ids" 
                      class="w-full px-4 py-3 border-2 border-purple-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-400 transition-all duration-200 bg-white" 
                      multiple 
                      size="6">
                <option v-for="c in centros" :key="c.id" :value="c.id" class="py-2">
                  {{ c.nombre }}
                </option>
              </select>
              <p class="text-purple-700 text-sm mt-3 flex items-center gap-2 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Mantén presionado <kbd class="px-2 py-1 bg-white rounded border border-purple-300 text-xs">Ctrl</kbd> (Windows) o <kbd class="px-2 py-1 bg-white rounded border border-purple-300 text-xs">⌘ Cmd</kbd> (Mac) para seleccionar múltiples centros.
              </p>
              <p v-if="form.errors.centros_ids" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ form.errors.centros_ids }}
              </p>
            </div>

            <!-- Separador -->
            <div class="border-t-2 border-gray-100 pt-6">
              <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Credenciales de acceso
                <span v-if="props.user" class="text-sm font-normal text-gray-500">(opcional para edición)</span>
              </h2>
            </div>

            <!-- Grid para contraseñas -->
            <div class="grid md:grid-cols-2 gap-6">
              
              <!-- Password -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Contraseña {{ props.user ? '' : '*' }}
                </label>
                <input v-model="form.password" 
                       type="password"
                       placeholder="••••••••"
                       :required="!props.user"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-400 transition-all duration-200"
                       :class="{ 'border-red-300 bg-red-50': form.errors.password }">
                <p v-if="form.errors.password" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ form.errors.password }}
                </p>
                <p v-else-if="props.user" class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Dejar en blanco para mantener la contraseña actual
                </p>
              </div>

              <!-- Confirmación -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Confirmar contraseña {{ props.user ? '' : '*' }}
                </label>
                <input v-model="form.password_confirmation" 
                       type="password"
                       placeholder="••••••••"
                       :required="!props.user"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-400 transition-all duration-200">
              </div>

            </div>

          </form>
        </div>

        <!-- Footer con botones -->
        <div class="bg-gray-50 px-8 py-6 border-t-2 border-gray-100 flex items-center justify-between gap-4">
          <a :href="route('admin.users.index')" 
             class="px-6 py-3 rounded-xl border-2 border-gray-300 font-semibold text-gray-700 hover:bg-gray-100 transition-all duration-200">
            Cancelar
          </a>
          <button @click="save" 
                  :disabled="form.processing"
                  class="px-8 py-3 rounded-xl bg-gradient-to-r from-purple-600 to-pink-600 font-bold text-white hover:shadow-xl transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed transform hover:scale-105 flex items-center gap-2">
            <svg v-if="!form.processing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ form.processing ? 'Guardando...' : 'Guardar Usuario' }}
          </button>
        </div>
      </div>

    </div>
  </div>
</template>
