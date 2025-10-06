<script setup>
import { router } from '@inertiajs/vue3'
const props = defineProps({
  tabs: { type: Array, required: true },   // [{label,value}]
  modelValue: { type: String, default: '' },
  url: { type: String, required: true },   // base URL del índice
  extra: { type: Object, default: ()=>({}) }, // otros filtros que quieras conservar
})
function go(val){
  // Construye URL de forma robusta (acepta absoluta o relativa)
  const u = new URL(props.url, window.location.origin)
  const sp = u.searchParams
  // Copia extra
  Object.entries(props.extra || {}).forEach(([k,v]) => {
    if (v === undefined || v === null || v === '') sp.delete(k)
    else sp.set(k, String(v))
  })
  // Estatus actual del tab
  if (!val) sp.delete('estatus'); else sp.set('estatus', String(val))
  // Ejecuta navegación (usando pathname + search para evitar issues de origen)
  const href = `${u.pathname}?${sp.toString()}`
  router.get(href, {}, { preserveState:true, replace:true })
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
