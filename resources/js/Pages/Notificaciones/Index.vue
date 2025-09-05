<script setup>
defineProps({ items: Array })
import { router } from '@inertiajs/vue3'

function markAll() {
  router.post(route('notificaciones.read_all'), {}, { preserveScroll:true })
}
</script>
<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-4 flex items-center gap-4">
      Notificaciones
      <button @click="markAll" class="ml-auto text-xs underline text-blue-700">Marcar todas como leídas</button>
    </h1>
    <div v-for="n in items" :key="n.id" class="border rounded p-3 mb-2">
      <div class="text-sm opacity-70">{{ n.created_at }}</div>
      <div class="font-medium">{{ n.data?.mensaje }}</div>
      <a v-if="n.data?.url" :href="n.data.url" class="text-indigo-600 text-sm">Abrir</a>
      <span v-if="!n.read_at" class="ml-2 text-amber-700 text-xs">• nueva</span>
    </div>
  </div>
</template>
