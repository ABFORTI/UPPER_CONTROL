<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
const props = defineProps({ orden:Object, urls:Object })
const obs = ref('')
const acciones = ref('')
const showRechazoModal = ref(false)
const validar  = () => router.post(props.urls.validar)
function openRechazo() {
  obs.value = ''
  acciones.value = ''
  showRechazoModal.value = true
}
function submitRechazo() {
  if (!obs.value || obs.value.trim().length < 3) return
  router.post(props.urls.rechazar, { observaciones: obs.value, acciones_correctivas: acciones.value })
}
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
        <button @click="openRechazo" class="px-4 py-2 rounded bg-red-600 text-white">Rechazar</button>
      </div>
    </div>

    <!-- Modal Rechazo Calidad -->
    <div v-if="showRechazoModal" class="fixed inset-0 z-[9999] flex items-center justify-center px-4">
      <div class="fixed inset-0 bg-black/40 z-40" @click="showRechazoModal = false"></div>
      <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-xl p-6 z-50">
        <h3 class="text-lg font-semibold">Rechazar OT #{{ orden.id }}</h3>
        <p class="text-sm text-gray-500 mt-1">Servicio: <strong>{{ orden.servicio?.nombre }}</strong></p>

        <div class="mt-4">
          <label class="text-sm font-semibold">Motivo del Rechazo</label>
          <textarea v-model="obs" rows="4" class="w-full mt-2 p-3 border rounded-md" placeholder="Describe el motivo del rechazo (requerido)"></textarea>
        </div>

        <div class="mt-4">
          <label class="text-sm font-semibold">Acciones Correctivas (opcional)</label>
          <textarea v-model="acciones" rows="3" class="w-full mt-2 p-3 border rounded-md" placeholder="Describe acciones sugeridas para corregir la OT"></textarea>
        </div>

        <div class="mt-4 flex justify-end gap-3">
          <button @click="showRechazoModal = false" class="px-4 py-2 rounded bg-gray-200">Cancelar</button>
          <button @click="submitRechazo" class="px-4 py-2 rounded bg-red-600 text-white">Enviar Rechazo</button>
        </div>
      </div>
    </div>
  </div>

  
</template>
