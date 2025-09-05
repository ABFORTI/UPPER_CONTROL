<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  orden: Object,
  total_sugerido: Number,
  urls: Object
})

const form = reactive({
  total: Number(props.total_sugerido ?? 0),
  folio_externo: ''
})

function submit () {
  router.post(props.urls.store, form)
}
</script>

<template>
  <div class="p-6 max-w-md">
    <h1 class="text-2xl font-bold mb-4">Registrar Factura — OT #{{ orden?.id }}</h1>

    <label class="block mb-2">Total</label>
    <input type="number" step="0.01" v-model.number="form.total"
           class="border p-2 rounded w-full mb-3" />

    <label class="block mb-2">Folio externo</label>
    <input v-model="form.folio_externo" class="border p-2 rounded w-full mb-6" />

    <button @click="submit" class="px-4 py-2 bg-black text-white rounded">Guardar</button>
  </div>
</template>
