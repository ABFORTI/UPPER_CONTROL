<script setup>
import { computed } from 'vue'
import OtCortes from '@/Components/OtCortes.vue'

const props = defineProps({
  orden: Object,
  servicios: Array,
  cortes: { type: Array, default: () => [] },
})

// Estado visual basado en el estatus de la OT
const estatusConfig = computed(() => {
  const configs = {
    generada: { color: 'bg-blue-100 text-blue-800', icon: '🆕', label: 'Generada' },
    asignada: { color: 'bg-purple-100 text-purple-800', icon: '👤', label: 'Asignada' },
    en_proceso: { color: 'bg-yellow-100 text-yellow-800', icon: '⚙️', label: 'En Proceso' },
    completada: { color: 'bg-green-100 text-green-800', icon: '✅', label: 'Completada' },
    autorizada_cliente: { color: 'bg-emerald-100 text-emerald-800', icon: '✓', label: 'Autorizada' },
    facturada: { color: 'bg-gray-100 text-gray-800', icon: '📄', label: 'Facturada' },
  }
  return configs[props.orden.estatus] || configs.generada
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-purple-50 py-8 px-4 sm:px-6 lg:px-8 dark:bg-gradient-to-br dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
    <div class="max-w-7xl mx-auto">
      
      <!-- Header -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-indigo-100 overflow-hidden mb-6 dark:bg-slate-900/80 dark:border-slate-800">
        <div class="px-8 py-6 bg-gradient-to-r from-indigo-600 to-purple-600">
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-4">
              <div class="bg-white bg-opacity-20 p-3 rounded-xl backdrop-blur-sm">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div>
                <h1 class="text-3xl font-bold text-white">Orden de Trabajo #{{ orden.id }}</h1>
                <p class="text-indigo-100 text-sm mt-1">{{ orden.created_at }}</p>
              </div>
            </div>
            <div>
              <span :class="estatusConfig.color" class="px-4 py-2 rounded-full text-sm font-bold">
                {{ estatusConfig.icon }} {{ estatusConfig.label }}
              </span>
            </div>
          </div>
        </div>

        <!-- Info General -->
        <div class="px-8 py-6 bg-indigo-50 border-b border-indigo-100 dark:bg-slate-800/50 dark:border-slate-700">
          <div class="grid md:grid-cols-3 gap-4">
            <div>
              <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wide dark:text-indigo-400">Centro de Trabajo</span>
              <p class="text-lg font-bold text-gray-800 dark:text-slate-100">{{ orden.centro.nombre }}</p>
            </div>
            <div v-if="orden.area">
              <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wide dark:text-indigo-400">Área</span>
              <p class="text-lg font-bold text-gray-800 dark:text-slate-100">{{ orden.area.nombre }}</p>
            </div>
            <div v-if="orden.team_leader">
              <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wide dark:text-indigo-400">Team Leader</span>
              <p class="text-lg font-bold text-gray-800 dark:text-slate-100">{{ orden.team_leader.name }}</p>
            </div>
          </div>
          <div class="mt-4">
            <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wide dark:text-indigo-400">Descripción General</span>
            <p class="text-base text-gray-700 dark:text-slate-200">{{ orden.descripcion_general }}</p>
          </div>
        </div>

        <!-- Totales OT -->
        <div class="px-8 py-6 bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20">
          <div class="grid md:grid-cols-4 gap-4">
            <div class="text-center">
              <span class="block text-xs font-semibold text-gray-600 uppercase tracking-wide dark:text-slate-400">Subtotal</span>
              <p class="text-2xl font-bold text-gray-800 dark:text-slate-100">${{ parseFloat(orden.subtotal || 0).toFixed(2) }}</p>
            </div>
            <div class="text-center">
              <span class="block text-xs font-semibold text-gray-600 uppercase tracking-wide dark:text-slate-400">IVA (16%)</span>
              <p class="text-2xl font-bold text-gray-800 dark:text-slate-100">${{ parseFloat(orden.iva || 0).toFixed(2) }}</p>
            </div>
            <div class="text-center">
              <span class="block text-xs font-semibold text-gray-600 uppercase tracking-wide dark:text-slate-400">Total</span>
              <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">${{ parseFloat(orden.total || 0).toFixed(2) }}</p>
            </div>
            <div class="text-center">
              <span class="block text-xs font-semibold text-gray-600 uppercase tracking-wide dark:text-slate-400">Servicios</span>
              <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ servicios.length }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Servicios (Cards) - Sin card contenedora -->
      <div class="space-y-6">
        <div v-for="(servicio, index) in servicios" :key="servicio.id"
             class="bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden dark:bg-slate-900/75 dark:border-slate-700">
          
          <!-- Header del Servicio - Compacto -->
          <div class="bg-gradient-to-br from-emerald-600 via-emerald-600 to-emerald-700 px-5 py-3 border-b border-emerald-700/30">
            <div class="flex items-center justify-between gap-4">
              <div class="flex items-center gap-2.5 flex-1 min-w-0">
                <div class="w-7 h-7 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <h2 class="text-lg font-bold text-white tracking-tight truncate">
                    {{ servicio.servicio.nombre }}
                  </h2>
                  <p class="text-emerald-50/90 text-xs">
                    #{{ index + 1 }} • {{ servicio.tipo_cobro }} • {{ servicio.cantidad }} u
                  </p>
                </div>
              </div>
              <div class="bg-white/95 backdrop-blur-sm rounded-lg px-3 py-1.5 shadow-sm flex-shrink-0">
                <p class="text-[9px] uppercase tracking-wider font-bold text-slate-500 mb-0.5">Subtotal</p>
                <p class="text-base font-bold text-slate-900">${{ parseFloat(servicio.subtotal || 0).toFixed(2) }}</p>
              </div>
            </div>
          </div>

          <!-- Métricas del Servicio - Mini Stat Tiles -->
          <div class="px-5 py-3 bg-slate-50/50 border-b border-slate-200 dark:bg-slate-900/30 dark:border-slate-700">
            <div class="grid grid-cols-2 md:grid-cols-6 gap-2.5">
              <!-- Solicitado -->
              <div class="bg-white border-l-3 border-blue-500 rounded-md p-2.5 shadow-sm dark:bg-slate-800/50 dark:border-blue-400">
                <p class="text-[9px] uppercase tracking-wide font-bold text-slate-500 dark:text-slate-400">Solicitado</p>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100 leading-none mt-1">{{ servicio.solicitado || servicio.planeado || 0 }}</p>
              </div>
              <!-- Extra -->
              <div class="bg-white border-l-3 border-orange-500 rounded-md p-2.5 shadow-sm dark:bg-slate-800/50 dark:border-orange-400">
                <p class="text-[9px] uppercase tracking-wide font-bold text-slate-500 dark:text-slate-400">Extra</p>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100 leading-none mt-1">{{ servicio.extra || 0 }}</p>
              </div>
              <!-- Faltantes -->
              <div class="bg-white border-l-3 border-amber-500 rounded-md p-2.5 shadow-sm dark:bg-slate-800/50 dark:border-amber-400">
                <p class="text-[9px] uppercase tracking-wide font-bold text-slate-500 dark:text-slate-400">Faltantes</p>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100 leading-none mt-1">{{ servicio.faltantes_registrados || 0 }}</p>
              </div>
              <!-- Total cobrable -->
              <div class="bg-white border-l-3 border-indigo-500 rounded-md p-2.5 shadow-sm dark:bg-slate-800/50 dark:border-indigo-400">
                <p class="text-[9px] uppercase tracking-wide font-bold text-slate-500 dark:text-slate-400">Total cobrable</p>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100 leading-none mt-1">{{ servicio.total_cobrable || servicio.total || 0 }}</p>
              </div>
              <!-- Completado -->
              <div class="bg-white border-l-3 border-emerald-500 rounded-md p-2.5 shadow-sm dark:bg-slate-800/50 dark:border-emerald-400">
                <p class="text-[9px] uppercase tracking-wide font-bold text-slate-500 dark:text-slate-400">Completado</p>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100 leading-none mt-1">{{ servicio.completado || 0 }}</p>
              </div>
              <!-- Pendiente -->
              <div class="bg-white border-l-3 border-purple-500 rounded-md p-2.5 shadow-sm dark:bg-slate-800/50 dark:border-purple-400">
                <p class="text-[9px] uppercase tracking-wide font-bold text-slate-500 dark:text-slate-400">Pendiente</p>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100 leading-none mt-1">{{ servicio.pendiente || 0 }}</p>
              </div>
            </div>
          </div>

          <!-- Ítems de la Orden -->
          <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <!-- Section Header Inline -->
            <div class="flex items-center gap-2 mb-3 pb-2 border-b border-slate-200 dark:border-slate-700">
              <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
              <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wide">Ítems de la Orden</h3>
            </div>
            
            <div v-if="servicio.items && servicio.items.length > 0" class="overflow-x-auto rounded-md border border-slate-200 dark:border-slate-700">
              <table class="w-full">
                <thead>
                  <tr class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700">
                    <th class="px-3 py-2 text-left text-[9px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-300">Descripción</th>
                    <th class="px-3 py-2 text-center text-[9px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-300">Planeado</th>
                    <th class="px-3 py-2 text-center text-[9px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-300">Completado</th>
                    <th class="px-3 py-2 text-center text-[9px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-300">Faltante</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="item in servicio.items" :key="item.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                    <td class="px-3 py-2 text-sm font-medium text-slate-800 dark:text-slate-200">{{ item.descripcion_item }}</td>
                    <td class="px-3 py-2 text-center">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">{{ item.planeado }}</span>
                    </td>
                    <td class="px-3 py-2 text-center">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ item.completado }}</span>
                    </td>
                    <td class="px-3 py-2 text-center">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ item.faltante }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-center py-8 text-gray-500 dark:text-slate-400">
              <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
              </svg>
              <p>No hay ítems registrados</p>
            </div>
          </div>

          <!-- Avances Registrados -->
          <div class="px-5 py-4">
            <!-- Section Header Inline -->
            <div class="flex items-center gap-2 mb-3 pb-2 border-b border-slate-200 dark:border-slate-700">
              <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
              <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wide">Avances Registrados</h3>
            </div>

            <div v-if="servicio.avances && servicio.avances.length > 0" class="space-y-2.5">
              <div v-for="avance in servicio.avances" :key="avance.id"
                   class="bg-slate-50 border border-slate-200 rounded-md p-3 hover:border-slate-300 transition-colors dark:bg-slate-800/30 dark:border-slate-700 dark:hover:border-slate-600">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                            :class="avance.tarifa === 'NORMAL' ? 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300' : 
                                    avance.tarifa === 'EXTRA' ? 'bg-orange-100 text-orange-800 dark:bg-orange-500/20 dark:text-orange-300' : 
                                    'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300'">
                        {{ avance.tarifa || 'NORMAL' }}
                      </span>
                      <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ avance.created_at }}</span>
                      <span class="text-[10px] text-slate-500 dark:text-slate-400">• {{ avance.created_by }}</span>
                    </div>
                    <div class="text-xs text-slate-700 dark:text-slate-300 flex items-center gap-3">
                      <span><strong class="font-semibold">Cant:</strong> {{ avance.cantidad_registrada }}</span>
                      <span v-if="avance.precio_unitario_aplicado">
                        <strong class="font-semibold">P.U.:</strong> 
                        <span class="font-mono">${{ parseFloat(avance.precio_unitario_aplicado).toFixed(2) }}</span>
                      </span>
                    </div>
                    <p v-if="avance.comentario" class="text-xs text-slate-600 mt-2 p-1.5 bg-white rounded border-l-2 border-blue-400 dark:bg-slate-900/30 dark:text-slate-400 dark:border-blue-500">
                      {{ avance.comentario }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500 dark:text-slate-400">
              <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
              <p>No hay avances registrados</p>
            </div>

            <!-- Botón para registrar nuevo avance -->
            <div class="mt-4 flex justify-end">
              <button type="button"
                      class="w-full md:w-auto py-2.5 px-5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold text-sm rounded-lg transition-colors duration-150 shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                <span class="flex items-center justify-center gap-2">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                  </svg>
                  <span>Registrar Avance</span>
                </span>
              </button>
            </div>
          </div>

        </div>
      </div>

      <!-- Cortes de OT -->
      <OtCortes :orden="orden" :cortes="cortes" />

    </div>
  </div>
</template>
