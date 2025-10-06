<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  centros:  { type: Array,  required: true },
  centroId: { type: Number, required: true },
  urls:     { type: Object, required: true },
})

const crear = useForm({
  nombre: '', usa_tamanos: false, id_centro: props.centroId,
  precio_base: null,
  tamanos: { chico: null, mediano: null, grande: null, jumbo: null },
})
function submitCrear(){ crear.post(props.urls.crear, { preserveScroll: true }) }
</script>

<template>
  <div class="p-4 md:p-6">
    <h1 class="text-2xl md:text-3xl font-semibold font-display tracking-wide mb-4">NUEVO SERVICIO</h1>

    <div class="card">
      <div class="px-4 py-3 rounded-t-xl text-white" style="background:#1f2a44">
        <h2 class="font-display font-semibold">Crear servicio</h2>
      </div>
      <div class="card-section grid md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm mb-1">Nombre</label>
          <input v-model="crear.nombre" class="border p-2 rounded w-full" />
          <p v-if="crear.errors.nombre" class="text-red-600 text-sm">{{ crear.errors.nombre }}</p>
        </div>

        <div class="flex items-center gap-2 mt-6">
          <input id="usa" type="checkbox" v-model="crear.usa_tamanos" />
          <label for="usa">Usa tamaños</label>
        </div>

        <div>
          <label class="block text-sm mb-1">Centro</label>
          <select v-model.number="crear.id_centro" class="border p-2 rounded w-full">
            <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
          <p v-if="crear.errors.id_centro" class="text-red-600 text-sm">{{ crear.errors.id_centro }}</p>
        </div>

        <div v-if="!crear.usa_tamanos">
          <label class="block text-sm mb-1">Precio unitario</label>
          <input type="number" step="0.01" min="0" v-model.number="crear.precio_base" class="border p-2 rounded w-full" />
          <p v-if="crear.errors.precio_base" class="text-red-600 text-sm">{{ crear.errors.precio_base }}</p>
        </div>

        <template v-else>
          <div>
            <label class="block text-sm mb-1">Chico</label>
            <input type="number" step="0.01" min="0" v-model.number="crear.tamanos.chico" class="border p-2 rounded w-full" />
          </div>
          <div>
            <label class="block text-sm mb-1">Mediano</label>
            <input type="number" step="0.01" min="0" v-model.number="crear.tamanos.mediano" class="border p-2 rounded w-full" />
          </div>
          <div>
            <label class="block text-sm mb-1">Grande</label>
            <input type="number" step="0.01" min="0" v-model.number="crear.tamanos.grande" class="border p-2 rounded w-full" />
          </div>
          <div>
            <label class="block text-sm mb-1">Jumbo</label>
            <input type="number" step="0.01" min="0" v-model.number="crear.tamanos.jumbo" class="border p-2 rounded w-full" />
          </div>
        </template>

        <div class="md:col-span-2 flex gap-2">
          <button @click="submitCrear" :disabled="crear.processing" class="btn btn-primary">
            {{ crear.processing ? 'Guardando…' : 'Crear servicio' }}
          </button>
          <a :href="urls.index" class="btn btn-secondary">Cancelar</a>
        </div>
      </div>
    </div>
  </div>
  
</template>

<style scoped>
</style>
