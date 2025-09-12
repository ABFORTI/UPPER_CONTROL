<script setup>
import { router } from '@inertiajs/vue3'
const props = defineProps({
  tabs: { type: Array, required: true },   // [{label,value}]
  modelValue: { type: String, default: '' },
  url: { type: String, required: true },   // base URL del índice
  extra: { type: Object, default: ()=>({}) }, // otros filtros que quieras conservar
})
function go(val){
  let base = props.url
  // Corrige si la URL base no tiene el prefijo absoluto
  if (!base.startsWith('/')) base = '/' + base
  // Si tu app está en una subcarpeta, ajusta aquí:
  if (!base.startsWith('/upper-control')) base = '/upper-control' + base
  const q = new URLSearchParams({ ...props.extra, estatus: val || '' })
  router.get(`${base}?${q.toString()}`, {}, { preserveState:true, replace:true })
}
</script>
<template>
  <div class="flex flex-wrap gap-2">
    <button v-for="t in tabs" :key="t.value" @click="go(t.value)"
      class="px-4 py-2 rounded-full text-sm font-medium transition-colors"
      :class="modelValue===t.value
        ? 'text-white bg-brand-primary'
        : 'bg-white text-brand-primary border border-brand-primary'">
      {{ t.label }}
    </button>
  </div>
</template>
