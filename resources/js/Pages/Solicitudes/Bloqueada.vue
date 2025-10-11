<script setup>
import { router } from '@inertiajs/vue3'

defineProps({
  mensaje: String,
  ordenes_vencidas: Array,
  tiempo_limite: String,
})

const BRAND = { green: '#006657', gold: '#BC955C', teal: '#0ea5e9' }

function verOrden(url) {
  if (url) router.visit(url)
}
</script>

<template>
  <div class="min-h-screen w-full bg-[#F9FAFB] px-4 sm:px-6 lg:px-10 py-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
      <div class="flex items-center gap-3">
        <span class="inline-flex h-9 w-9 rounded-2xl shadow-lg shadow-red-500/20"
              style="background:linear-gradient(135deg,#f43f5e,#dc2626)"></span>
        <div>
          <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Upper Logistics</p>
          <h1 class="text-[22px] leading-6 font-semibold text-slate-900">Solicitudes Bloqueadas</h1>
        </div>
      </div>
    </div>

    <!-- Contenido principal -->
    <div class="mt-8 max-w-3xl mx-auto">
      <!-- Alerta principal -->
      <div class="rounded-2xl border-2 border-red-200 bg-red-50 p-6 shadow-sm">
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h2 class="text-lg font-semibold text-red-900 mb-2">Creación de Solicitudes Bloqueada</h2>
            <p class="text-sm text-red-800 leading-relaxed">{{ mensaje }}</p>
          </div>
        </div>
      </div>

      <!-- Lista de órdenes pendientes -->
      <div v-if="ordenes_vencidas && ordenes_vencidas.length" class="mt-6">
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">
          Órdenes Pendientes de Autorización
        </h3>
        
        <div class="space-y-3">
          <div v-for="orden in ordenes_vencidas" :key="orden.id"
               @click="verOrden(orden.url)"
               class="group cursor-pointer rounded-2xl border border-slate-200 bg-white p-4 hover:shadow-md hover:border-slate-300 transition">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-semibold text-slate-900">{{ orden.folio }}</div>
                <div class="text-sm text-slate-600 mt-1">
                  Completada hace: <span class="font-medium text-red-600">{{ orden.completada_hace }}</span>
                </div>
              </div>
              <div>
                <svg class="h-5 w-5 text-slate-400 group-hover:text-slate-600 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Instrucciones -->
      <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">¿Qué debo hacer?</h3>
        <ol class="space-y-2 text-sm text-slate-600">
          <li class="flex items-start gap-2">
            <span class="flex-shrink-0 inline-flex h-5 w-5 items-center justify-center rounded-full text-xs font-semibold text-white"
                  :style="{ background: BRAND.green }">1</span>
            <span>Revisa las órdenes de trabajo listadas arriba haciendo clic en cada una.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="flex-shrink-0 inline-flex h-5 w-5 items-center justify-center rounded-full text-xs font-semibold text-white"
                  :style="{ background: BRAND.green }">2</span>
            <span>Autoriza cada orden de trabajo desde la página de detalles.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="flex-shrink-0 inline-flex h-5 w-5 items-center justify-center rounded-full text-xs font-semibold text-white"
                  :style="{ background: BRAND.green }">3</span>
            <span>Una vez autorizadas todas las órdenes pendientes, podrás crear nuevas solicitudes.</span>
          </li>
        </ol>
      </div>

      <!-- Botones de acción -->
      <div class="mt-6 flex gap-3">
        <button @click="$inertia.visit('/ordenes')"
                class="px-4 py-2 rounded-2xl text-white font-medium shadow-sm hover:brightness-105 transition"
                :style="{ background: `linear-gradient(135deg,${BRAND.green},${BRAND.teal})` }">
          Ver todas las Órdenes
        </button>
        <button @click="$inertia.visit('/dashboard')"
                class="px-4 py-2 rounded-2xl text-slate-700 font-medium border border-slate-300 bg-white hover:bg-slate-50 shadow-sm transition">
          Volver al Dashboard
        </button>
      </div>
    </div>
  </div>
</template>
