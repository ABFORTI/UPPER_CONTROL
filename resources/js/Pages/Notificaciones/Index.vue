<script setup>
defineProps({ items: Array })
import { router } from '@inertiajs/vue3'
import { reactive, computed } from 'vue'

function markAll () {
  router.post(route('notificaciones.read_all'), {}, { preserveScroll: true })
}

/* ===== UI local (búsqueda + filtros) ===== */
const ui = reactive({ tab: 'unread', q: '' }) // unread | read

function openNotification(n){
  const url = n?.data?.url
  if (!url) return
  // Si no está leída, marcarla y luego navegar
  if (!n.read_at) {
    try {
      router.post(route('notificaciones.read', n.id), {}, {
        preserveScroll: true,
        onFinish: () => { window.location.href = url }
      })
    } catch (e) {
      // Fallback por si la ruta no existe
      window.location.href = url
    }
  } else {
    window.location.href = url
  }
}

function labelForDate(iso) {
  const d = new Date(iso)
  const today = new Date(); today.setHours(0,0,0,0)
  const target = new Date(d); target.setHours(0,0,0,0)
  const diff = Math.round((today - target)/(1000*60*60*24))
  if (diff === 0) return 'Hoy'
  if (diff === 1) return 'Ayer'
  return d.toLocaleDateString('es-MX', { weekday:'long', year:'numeric', month:'short', day:'numeric' })
}

const filtered = computed(()=>{
  let list = [...(Array.isArray(__props.items) ? __props.items : [])]
  // Filtro por pestaña
  if (ui.tab === 'unread') list = list.filter(n => !n.read_at)
  else if (ui.tab === 'read') list = list.filter(n => !!n.read_at)
  // Búsqueda
  if (ui.q) {
    const q = ui.q.toLowerCase()
    list = list.filter(n => (`${n.data?.mensaje ?? ''} ${n.created_at ?? ''}`).toLowerCase().includes(q))
  }
  // Orden descendente por fecha
  list = list.sort((a,b)=> new Date(b.created_at) - new Date(a.created_at))
  // Limitar a últimas 5 cuando es pestaña de 'read'
  if (ui.tab === 'read') list = list.slice(0, 10)
  return list
})

const grouped = computed(()=>{
  const map = new Map()
  for (const n of filtered.value) {
    const k = labelForDate(n.created_at)
    if (!map.has(k)) map.set(k, [])
    map.get(k).push(n)
  }
  return Array.from(map.entries()) // [label, items[]]
})

// Colores de marca
const BRAND = { green:'#006657', gold:'#BC955C', teal:'#0ea5e9' }
</script>

<template>
  <div class="min-h-screen w-full bg-[#F9FAFB] px-4 sm:px-6 lg:px-10 py-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
      <div class="flex items-center gap-3">
        <span class="inline-flex h-9 w-9 rounded-2xl shadow-lg shadow-teal-500/20"
              :style="{ background:'linear-gradient(135deg,#0ea5e9,#006657)' }"></span>
        <div>
          <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Upper Logistics</p>
          <h1 class="text-[22px] leading-6 font-semibold text-slate-900">Notificaciones</h1>
        </div>
      </div>

      <div class="flex items-center gap-2 w-full md:w-auto">
        <!-- search -->
        <div class="relative flex-1 md:w-80">
          <input v-model="ui.q" type="search" placeholder="Buscar…"
                 class="w-full rounded-2xl border border-slate-300 bg-white px-10 py-2 text-sm text-slate-800 shadow-sm
                        focus:outline-none focus:ring-2 focus:ring-[#006657]/30 focus:border-[#006657]" />
          <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
        </div>
        <button @click="markAll"
                class="px-3 py-2 rounded-2xl text-sm font-medium text-white shadow-sm hover:brightness-105"
                :style="{ background:`linear-gradient(135deg,${BRAND.green},${BRAND.teal})` }">
          Marcar todas como leídas
        </button>
      </div>
    </div>

    <!-- Tabs: No leídas | Leídas (máx 5) -->
    <div class="mt-4 flex flex-wrap items-center gap-2">
      <button @click="ui.tab='unread'"
              :class="['px-3 py-1.5 rounded-full text-sm border transition',
                       ui.tab==='unread' ? 'bg-slate-900 text-white border-slate-900'
                                         : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50']">
        No leídas
      </button>
      <button @click="ui.tab='read'"
              :class="['px-3 py-1.5 rounded-full text-sm border transition',
                       ui.tab==='read' ? 'bg-slate-900 text-white border-slate-900'
                                       : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50']">
        Leídas
      </button>
    </div>

    <!-- Lista agrupada -->
    <div class="mt-6 max-w-5xl">
      <template v-for="[label, items] in grouped" :key="label">
        <div class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ label }}</div>

        <div class="space-y-3 mb-6">
          <div v-for="n in items" :key="n.id"
               class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 hover:shadow-md transition">
            <!-- barra lateral si está no leída -->
            <div v-if="!n.read_at" class="absolute left-0 top-0 h-full w-1" :style="{ background: BRAND.green }"></div>

            <div class="text-xs text-slate-500">{{ new Date(n.created_at).toLocaleString('es-MX') }}</div>
            <div class="mt-1 flex items-start gap-2">
              <p class="font-medium text-slate-900 leading-6">{{ n.data?.mensaje }}</p>
              <span v-if="!n.read_at"
                    class="px-2 py-0.5 rounded-full text-[11px] font-medium ml-auto"
                    :style="{ background: '#F6EDE2', color: BRAND.gold, border:`1px solid ${BRAND.gold}33` }">
                nuevo
              </span>
            </div>

            <div class="mt-2">
              <a v-if="n.data?.url" href="#" @click.prevent="openNotification(n)" class="text-[#0b63ce] text-sm hover:underline">Abrir</a>
            </div>
          </div>
        </div>
      </template>

      <!-- vacío -->
      <div v-if="grouped.length===0"
           class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
        No hay notificaciones.
      </div>
    </div>
  </div>
</template>
