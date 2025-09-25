<!-- resources/js/Pages/Calidad/Index.vue -->
<script setup>
import { computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({
  data: Object,
  estado: String,
  urls: Object,
})

const rows = computed(()=> props.data?.data ?? [])
const estadoSafe = computed(() => props.estado || 'pendiente')

function goEstado(e) {
  router.get(props.urls.index, { estado: e }, { preserveState:true, preserveScroll:true, replace:true })
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Calidad</h1>

    <!-- Tabs de estado -->
    <div class="flex gap-2 mb-4">
      <button @click="goEstado('pendiente')"
              class="px-3 py-1.5 rounded border"
        :class="{ 'bg-black text-white': estadoSafe==='pendiente' }">Pendientes</button>
      <button @click="goEstado('validado')"
              class="px-3 py-1.5 rounded border"
        :class="{ 'bg-black text-white': estadoSafe==='validado' }">Validadas</button>
      <button @click="goEstado('rechazado')"
              class="px-3 py-1.5 rounded border"
        :class="{ 'bg-black text-white': estadoSafe==='rechazado' }">Rechazadas</button>
    </div>

    <!-- Tabla -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm border">
        <thead>
          <tr class="bg-gray-50 text-left">
            <th class="p-2">ID</th>
            <th class="p-2">Servicio</th>
            <th class="p-2">Centro</th>
            <th class="p-2">Calidad</th>
            <th class="p-2">Fecha</th>
            <th class="p-2">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in rows" :key="o.id" class="border-t">
            <td class="p-2">#{{ o.id }}</td>
            <td class="p-2">{{ o.servicio?.nombre }}</td>
            <td class="p-2">{{ o.centro?.nombre }}</td>
            <td class="p-2">{{ o.calidad_resultado }}</td>
            <td class="p-2">{{ o.created_at }}</td>
            <td class="p-2 space-x-2">
              <template v-if="estadoSafe==='pendiente'">
                <a :href="o.urls.review" class="px-3 py-1.5 rounded bg-emerald-600 text-white">Revisar</a>
              </template>
              <a :href="o.urls.show" class="text-blue-600 underline">Ver OT</a>
            </td>
          </tr>
          <tr v-if="rows.length===0">
            <td colspan="6" class="p-4 text-center opacity-70">No hay registros.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="mt-4 flex gap-2">
      <Link v-for="link in data.links" :key="link.label" :href="link.url || '#'"
            class="px-2 py-1 border rounded"
            :class="{'bg-black text-white': link.active}"
            v-html="link.label" />
    </div>
  </div>
</template>
