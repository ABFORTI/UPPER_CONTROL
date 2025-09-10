<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  data:Object, filters:Object, centros:Array, usuarios:Array, logs:Array, events:Array, urls:Object
})

function submit(e){
  const f = new FormData(e.target)
  router.get(props.urls.index, Object.fromEntries(f.entries()), { preserveState:true, replace:true })
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Auditoría</h1>

    <form @submit.prevent="submit" class="flex flex-wrap items-end gap-3 mb-4">
      <div>
        <label class="block text-sm mb-1">Desde</label>
        <input type="date" name="desde" :value="filters.desde" class="border p-2 rounded">
      </div>
      <div>
        <label class="block text-sm mb-1">Hasta</label>
        <input type="date" name="hasta" :value="filters.hasta" class="border p-2 rounded">
      </div>
      <div>
        <label class="block text-sm mb-1">Log</label>
        <select name="log" :value="filters.log || ''" class="border p-2 rounded">
          <option value="">— Todos —</option>
          <option v-for="l in logs" :key="l" :value="l">{{ l }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Evento</label>
        <select name="event" :value="filters.event || ''" class="border p-2 rounded">
          <option value="">— Todos —</option>
          <option v-for="e in events" :key="e" :value="e">{{ e }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Usuario</label>
        <select name="user" :value="filters.user || ''" class="border p-2 rounded min-w-[14rem]">
          <option value="">— Todos —</option>
          <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Centro</label>
        <select name="centro" :value="filters.centro || ''" class="border p-2 rounded min-w-[14rem]">
          <option value="">— Todos —</option>
          <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Buscar</label>
        <input name="q" :value="filters.q || ''" placeholder="Texto" class="border p-2 rounded">
      </div>
      <button class="px-3 py-2 rounded bg-black text-white">Aplicar</button>

      <a :href="props.urls.export" class="ml-auto px-3 py-2 rounded bg-indigo-600 text-white">Exportar CSV</a>
    </form>

    <div class="overflow-auto">
      <table class="w-full text-sm border">
        <thead>
          <tr class="bg-gray-50 text-left">
            <th class="p-2">Fecha</th>
            <th class="p-2">Usuario</th>
            <th class="p-2">Log</th>
            <th class="p-2">Evento</th>
            <th class="p-2">Subject</th>
            <th class="p-2">Centro</th>
            <th class="p-2">Descripción</th>
            <th class="p-2">Propiedades</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in data.data" :key="a.id" class="border-t align-top">
            <td class="p-2 whitespace-nowrap">{{ a.created_at }}</td>
            <td class="p-2">{{ a.causer?.name || '—' }}</td>
            <td class="p-2">{{ a.log_name }}</td>
            <td class="p-2">{{ a.event }}</td>
            <td class="p-2">{{ a.subject_type?.split('\\').pop() }} #{{ a.subject_id }}</td>
            <td class="p-2">{{ a.properties?.centro_trabajo_id ?? '—' }}</td>
            <td class="p-2">{{ a.description }}</td>
            <td class="p-2">
              <pre class="text-[11px] max-w-[420px] overflow-auto bg-gray-50 p-2 rounded">
{{ JSON.stringify(a.properties, null, 2) }}
              </pre>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-3 flex gap-2">
      <a v-for="link in data.links" :key="link.url || link.label"
         :href="link.url || '#'" v-html="link.label"
         :class="['px-2 py-1 border rounded', { 'bg-black text-white': link.active }]"></a>
    </div>
  </div>
</template>
