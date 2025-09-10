<script setup>
import { router } from '@inertiajs/vue3'
const props = defineProps({ files:Array })

function runNow(){ router.post(route('admin.backups.run')) }
function fmtBytes(n){
  if (!n && n !== 0) return '-'
  const k = 1024, u = ['B','KB','MB','GB','TB']; let i=0
  while (n >= k && i < u.length-1){ n/=k; i++ }
  return n.toFixed(1)+' '+u[i]
}
function fmtDate(ts){
  const d = new Date(ts*1000)
  return d.toLocaleString()
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Respaldos</h1>

    <div class="mb-4">
      <button @click="runNow" class="px-3 py-2 rounded bg-black text-white">Ejecutar backup ahora</button>
    </div>

    <div class="overflow-auto">
      <table class="w-full text-sm border">
        <thead>
          <tr class="bg-gray-50 text-left">
            <th class="p-2">Archivo</th>
            <th class="p-2">Tamaño</th>
            <th class="p-2">Fecha</th>
            <th class="p-2">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in files" :key="f.path" class="border-t">
            <td class="p-2">{{ f.name }}</td>
            <td class="p-2">{{ fmtBytes(f.size) }}</td>
            <td class="p-2">{{ fmtDate(f.last_modified) }}</td>
            <td class="p-2">
              <a :href="f.url" class="text-indigo-700">Descargar</a>
            </td>
          </tr>
          <tr v-if="!files?.length">
            <td colspan="4" class="p-4 text-center opacity-70">Aún no hay respaldos</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
