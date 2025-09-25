<script setup>
import { router, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  items: Array,
  filtros: Object,
  urls: Object,
  estatuses: Array
})

const sel = ref(props.filtros?.estatus || '')
function applyFilter(){
  const params = {}
  if (sel.value) params.estatus = sel.value
  router.get(props.urls.base, params, { preserveState: true, replace: true })
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-4">
      <h1 class="text-2xl font-bold">Facturación</h1>
      <div class="flex items-center gap-2">
        <select v-model="sel" @change="applyFilter" class="border rounded p-2 text-sm">
          <option value="">Todos los estatus</option>
          <option v-for="e in estatuses" :key="e" :value="e">{{ e }}</option>
        </select>
      </div>
    </div>

    <div class="overflow-auto rounded border bg-white dark:bg-slate-800">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-700 text-left">
          <tr>
            <th class="p-2">ID</th>
            <th class="p-2">OT</th>
            <th class="p-2">Servicio</th>
            <th class="p-2">Centro</th>
            <th class="p-2">Total</th>
            <th class="p-2">Estatus</th>
            <th class="p-2">Folio</th>
            <th class="p-2">Fecha</th>
            <th class="p-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in items" :key="f.id" class="border-t hover:bg-gray-50 dark:hover:bg-slate-700/50">
            <td class="p-2 font-mono">#{{ f.id }}</td>
            <td class="p-2">OT #{{ f.orden_id }}</td>
            <td class="p-2">{{ f.servicio || '—' }}</td>
            <td class="p-2">{{ f.centro || '—' }}</td>
            <td class="p-2">${{ f.total }}</td>
            <td class="p-2">
              <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 dark:bg-slate-600">{{ f.estatus }}</span>
            </td>
            <td class="p-2">{{ f.folio || '—' }}</td>
            <td class="p-2 whitespace-nowrap">{{ f.created_at?.slice(0,16) }}</td>
            <td class="p-2 text-right">
              <Link :href="f.url" class="text-indigo-600 hover:underline">Ver</Link>
            </td>
          </tr>
          <tr v-if="!items?.length">
            <td colspan="9" class="p-6 text-center text-slate-500">Sin registros</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
