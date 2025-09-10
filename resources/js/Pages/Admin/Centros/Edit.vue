<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ centro: Object })

const form = useForm({
  nombre: props.centro?.nombre || '',
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
  <div class="p-6 max-w-xl">
    <h1 class="text-2xl font-bold mb-4">{{ props.centro ? 'Editar centro' : 'Nuevo centro' }}</h1>

    <div class="grid gap-3">
      <div>
        <label class="block text-sm mb-1">Nombre</label>
        <input v-model="form.nombre" class="border p-2 rounded w-full">
        <p class="text-red-600 text-sm" v-if="form.errors.nombre">{{ form.errors.nombre }}</p>
      </div>
      <div>
        <label class="block text-sm mb-1">Prefijo (opcional)</label>
        <input v-model="form.prefijo" class="border p-2 rounded w-full" placeholder="Ej. UPR">
        <p class="text-xs opacity-60">Se usa para folios. Máx 10 caracteres.</p>
      </div>
      <div>
        <label class="block text-sm mb-1">Dirección (opcional)</label>
        <input v-model="form.direccion" class="border p-2 rounded w-full">
      </div>
      <div class="flex items-center gap-2">
        <input type="checkbox" v-model="form.activo" :true-value="1" :false-value="0">
        <label>Activo</label>
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
