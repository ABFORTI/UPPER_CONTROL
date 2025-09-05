<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
const props = defineProps({ orden:Object, urls:Object })
const obs = ref('')
const validar  = () => router.post(props.urls.validar)
const rechazar = () => router.post(props.urls.rechazar, { observaciones: obs.value })
</script>


<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-2">Revisión de Calidad — OT #{{ orden.id }}</h1>
    <p class="opacity-70 mb-4">Servicio: {{ orden.servicio?.nombre }} | Centro: {{ orden.centro?.nombre }}</p>

    <h2 class="font-semibold mt-4">Ítems</h2>
    <ul class="list-disc pl-6">
      <li v-for="it in orden.items" :key="it.id">
        {{ it.tamano ? ('Tamaño: '+it.tamano) : it.descripcion }} — {{ it.cantidad_real }}/{{ it.cantidad_planeada }}
      </li>
    </ul>

    <div class="mt-6 flex gap-3">
    <button @click="validar" class="px-4 py-2 rounded bg-green-600 text-white">Validar</button>
    <div class="flex gap-2">
      <input v-model="obs" placeholder="Motivo del rechazo" class="border p-2 rounded" />
      <button @click="rechazar" class="px-4 py-2 rounded bg-red-600 text-white">Rechazar</button>
    </div>
  </div>
  </div>

  
</template>
