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
  <div class="p-6 max-w-2xl">
    <h1 class="text-2xl font-bold mb-4">{{ props.user ? 'Editar usuario' : 'Nuevo usuario' }}</h1>

    <div class="grid gap-3">
      <div>
        <label class="block text-sm mb-1">Nombre</label>
        <input v-model="form.name" class="border p-2 rounded w-full">
        <p class="text-red-600 text-sm" v-if="form.errors.name">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="block text-sm mb-1">Email</label>
        <input v-model="form.email" class="border p-2 rounded w-full">
        <p class="text-red-600 text-sm" v-if="form.errors.email">{{ form.errors.email }}</p>
      </div>
      <div>
        <label class="block text-sm mb-1">Teléfono</label>
        <input v-model="form.phone" class="border p-2 rounded w-full">
      </div>
      <div>
        <label class="block text-sm mb-1">Centro</label>
        <select v-model="form.centro_trabajo_id" class="border p-2 rounded w-full">
          <option value="">— Selecciona —</option>
          <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
        <p class="text-red-600 text-sm" v-if="form.errors.centro_trabajo_id">{{ form.errors.centro_trabajo_id }}</p>
      </div>
      <div>
        <label class="block text-sm mb-1">Rol</label>
        <select v-model="form.role" class="border p-2 rounded w-full">
          <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
        </select>
        <p class="text-red-600 text-sm" v-if="form.errors.role">{{ form.errors.role }}</p>
      </div>

      <!-- Multiselección de centros para roles con múltiples centros -->
      <div v-if="['admin','calidad','facturacion'].includes(form.role)">
        <label class="block text-sm mb-1">Centros asignados (múltiples)</label>
        <select v-model="form.centros_ids" class="border p-2 rounded w-full" multiple size="5">
          <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
        <p class="text-gray-500 text-xs mt-1">Mantén Ctrl (Windows) o Cmd (Mac) para seleccionar varios.</p>
        <p class="text-red-600 text-sm" v-if="form.errors.centros_ids">{{ form.errors.centros_ids }}</p>
      </div>

      <div class="grid md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm mb-1">Password {{ props.user ? '(opcional)' : '' }}</label>
          <input type="password" v-model="form.password" class="border p-2 rounded w-full">
          <p class="text-red-600 text-sm" v-if="form.errors.password">{{ form.errors.password }}</p>
        </div>
        <div>
          <label class="block text-sm mb-1">Confirmación</label>
          <input type="password" v-model="form.password_confirmation" class="border p-2 rounded w-full">
        </div>
      </div>

      <div class="mt-3">
        <button @click="save" :disabled="form.processing"
                class="px-3 py-2 rounded bg-black text-white">
          {{ form.processing ? 'Guardando…' : 'Guardar' }}
        </button>
      </div>
    </div>
  </div>
</template>
