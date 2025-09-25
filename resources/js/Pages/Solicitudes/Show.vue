<script setup>
import { router } from '@inertiajs/vue3'
const props = defineProps({ solicitud: Object, can: Object, urls: Object, flags: Object, cotizacion: Object })

function aprobar()  { router.post(props.urls.aprobar) }
function rechazar() { router.post(props.urls.rechazar) }
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

    <!-- Desglose por tamaño -->
    <div v-if="solicitud.tamanos?.length" class="mt-4">
      <h2 class="font-semibold mb-2">Desglose por tamaño</h2>
      <table class="min-w-full text-sm border">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-2 border">Tamaño</th>
            <th class="text-right p-2 border">Cantidad</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in solicitud.tamanos" :key="t.id">
            <td class="p-2 border capitalize">{{ t.tamano }}</td>
            <td class="p-2 border text-right">{{ t.cantidad }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Cotización -->
    <div class="mt-6">
      <h2 class="font-semibold mb-2">Cotización</h2>
      <div v-if="cotizacion?.lines?.length" class="border rounded">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left p-2 border">Concepto</th>
              <th class="text-right p-2 border">Cantidad</th>
              <th class="text-right p-2 border">P. Unitario</th>
              <th class="text-right p-2 border">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(l,i) in cotizacion.lines" :key="i">
              <td class="p-2 border">{{ l.label }}</td>
              <td class="p-2 border text-right">{{ l.cantidad }}</td>
              <td class="p-2 border text-right">${{ (l.pu||0).toFixed(2) }}</td>
              <td class="p-2 border text-right">${{ (l.subtotal||0).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
        <div class="p-3 grid gap-1 justify-end">
          <div class="text-sm">Subtotal: <strong>${{ (cotizacion.subtotal||0).toFixed(2) }}</strong></div>
          <div class="text-sm">IVA ({{ ((cotizacion.iva_rate||0)*100).toFixed(0) }}%): <strong>${{ (cotizacion.iva||0).toFixed(2) }}</strong></div>
          <div class="text-base font-semibold">Total: <strong>${{ (cotizacion.total||0).toFixed(2) }}</strong></div>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <h2 class="font-semibold">Adjuntos</h2>
      <ul class="list-disc pl-6">
        <li v-for="a in solicitud.archivos" :key="a.id">
          <a :href="`/storage/${a.path}`" target="_blank" class="text-blue-600 underline">Descargar</a>
        </li>
      </ul>
      <!-- Botón de Generar OT (solo cuando ya está aprobada) -->
    <div v-if="solicitud.estatus === 'aprobada'" class="mt-3">
      <template v-if="!flags?.tiene_ot">
        <a :href="urls.generar_ot"
           class="inline-block px-3 py-2 rounded bg-black text-white">
          Generar OT
        </a>
      </template>
      <template v-else>
        <div class="px-3 py-2 rounded bg-yellow-100 text-yellow-800 inline-block">
          Ya existe una Orden de Trabajo para esta solicitud
        </div>
      </template>
    </div>
    </div>
  </div>
</template>
