<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
  class: { type: String, default: '' },
})

const paths = {
  home: 'M3 10.5l9-7 9 7V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z',
  document: 'M7 2h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V4a2 2 0 012-2zm7 1.5V8h4.5',
  clipboard: 'M9 3h6a2 2 0 012 2v2H7V5a2 2 0 012-2zm8 6H7v10a2 2 0 002 2h6a2 2 0 002-2V9z',
  chart: 'M4 19h16M6 17V9m6 8V5m6 12v-7',
  checkBadge: 'M12 2l2.39 4.84 5.34.78-3.86 3.76.91 5.32L12 14.77 6.22 16.7l.91-5.32L3.27 7.62l5.34-.78L12 2z',
  currency: 'M12 3v18m-4-5h8m-8-4h8',
  building: 'M4 21h16M6 21V5a1 1 0 011-1h10a1 1 0 011 1v16M8 9h2m4 0h2m-8 4h2m4 0h2',
  puzzle: 'M11 5a2 2 0 114 0h2a1 1 0 011 1v3h-2a2 2 0 100 4h2v3a1 1 0 01-1 1h-2a2 2 0 11-4 0H9a1 1 0 01-1-1v-3h2a2 2 0 100-4H8V6a1 1 0 011-1h2z',
  tag: 'M3 12l9-9h5l3 3v5l-9 9-8-8zM16 6h.01',
  dollar: 'M12 3v18m-3-4a3 3 0 006 0c0-1.657-1.343-2.5-3-3s-3-1.343-3-3a3 3 0 016 0',
  bell: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m1 0v1a2 2 0 104 0v-1',
  moon: 'M20 15.5A8.38 8.38 0 1110.5 4 6.5 6.5 0 0020 15.5z',
  sun: 'M12 5a7 7 0 100 14 7 7 0 000-14zm0-3v2m0 16v2m9-9h-2M5 12H3m15.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414m0-11.313l1.414 1.414M16.95 16.95l1.414 1.414',
  logout: 'M13 16l4-4m0 0l-4-4m4 4H7m6 9a9 9 0 110-18',
}

const path = computed(() => paths[props.name] || paths.document)
const sizePx = computed(() => typeof props.size === 'number' ? `${props.size}px` : props.size)
</script>

<template>
  <svg :width="sizePx" :height="sizePx" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" :class="props.class">
    <path :d="path" />
  </svg>
</template>

<style scoped>
/* no-op */
</style>
