<!-- resources/js/Pages/Calidad/Index.vue -->
<script setup>
defineProps({ data: Object })
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Calidad — Pendientes de validar</h1>
    <div v-if="data.data.length===0" class="opacity-70">No hay OTs pendientes.</div>
    <div v-for="o in data.data" :key="o.id" class="border rounded p-3 mb-2">
      <div class="font-semibold">OT #{{ o.id }} — {{ o.servicio?.nombre }}</div>
      <div class="text-sm opacity-70">Centro: {{ o.centro?.nombre }}</div>
      <a :href="`/ordenes/${o.id}/calidad`"
         class="mt-2 inline-block px-3 py-2 rounded bg-emerald-600 text-white">Revisar</a>
    </div>
  </div>
</template>
