<script setup>
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js';
const props = defineProps({ solicitud: Object, can: Object })

function aprobar()  { router.post(route('solicitudes.aprobar', props.solicitud.id)) }
function rechazar() { router.post(route('solicitudes.rechazar', props.solicitud.id)) }
</script>


<template>
  <div class="p-6 max-w-3xl">
     <h1 class="text-2xl font-bold mb-4">{{ solicitud.folio }}</h1>

    <!-- Botones de aprobar/rechazar: solo coordinador/admin y si está pendiente -->
    <div v-if="can?.aprobar" class="flex gap-2 mb-4">
      <button @click="aprobar" class="px-3 py-2 rounded bg-green-600 text-white">Aprobar</button>
      <button @click="rechazar" class="px-3 py-2 rounded bg-red-600 text-white">Rechazar</button>
    </div>
    <h1 class="text-2xl font-bold mb-4">{{ solicitud.folio }}</h1>
    <div class="mb-2">Servicio: {{ solicitud.servicio?.nombre }}</div>
    <div class="mb-2">Centro: {{ solicitud.centro?.nombre }}</div>
    <div class="mb-2">Estatus: <span class="px-2 py-1 rounded bg-gray-100">{{ solicitud.estatus }}</span></div>
    <div class="mb-2" v-if="solicitud.tamano">Tamaño: {{ solicitud.tamano }}</div>
    <div class="mb-2" v-if="solicitud.descripcion">Descripción: {{ solicitud.descripcion }}</div>
    <div class="mb-2">Cantidad: {{ solicitud.cantidad }}</div>
    <div class="mb-2" v-if="solicitud.notas">Notas: {{ solicitud.notas }}</div>

    <div class="mt-4">
      <h2 class="font-semibold">Adjuntos</h2>
      <ul class="list-disc pl-6">
        <li v-for="a in solicitud.archivos" :key="a.id">
          <a :href="`/storage/${a.path}`" target="_blank" class="text-blue-600 underline">Descargar</a>
        </li>
      </ul>
      <!-- Botón de Generar OT (solo cuando ya está aprobada) -->
    <div v-if="solicitud.estatus === 'aprobada'" class="mt-3">
      <a :href="route('ordenes.createFromSolicitud', solicitud.id)"
         class="inline-block px-3 py-2 rounded bg-black text-white">
        Generar OT
      </a>
    </div>
    </div>
  </div>
</template>
