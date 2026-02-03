<script setup>
import { computed } from 'vue'

const props = defineProps({
  orden: Object,
  servicios: Array,
})

// Calcular el porcentaje de completitud por servicio
const calcularPorcentaje = (servicio) => {
  if (servicio.planeado === 0) return 0
  return Math.min(100, Math.round((servicio.completado / servicio.planeado) * 100))
}

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

      <!-- Servicios (Cards) -->
      <div class="space-y-6">
        <div v-for="(servicio, index) in servicios" :key="servicio.id"
             class="bg-white rounded-2xl shadow-lg border-2 border-gray-200 overflow-hidden dark:bg-slate-900/75 dark:border-slate-700">
          
          <!-- Header del Servicio -->
          <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                  {{ servicio.servicio.nombre }}
                </h2>
                <p class="text-emerald-100 text-sm mt-1">
                  Servicio #{{ index + 1 }} • {{ servicio.tipo_cobro }} • {{ servicio.cantidad }} unidades
                </p>
              </div>
              <div class="text-right">
                <span class="block text-emerald-100 text-sm">Subtotal</span>
                <span class="block text-3xl font-bold text-white">${{ parseFloat(servicio.subtotal || 0).toFixed(2) }}</span>
              </div>
            </div>
          </div>

          <!-- Métricas del Servicio -->
          <div class="px-6 py-4 bg-emerald-50 border-b border-emerald-100 dark:bg-emerald-900/20 dark:border-emerald-500/40">
            <div class="grid grid-cols-4 gap-4">
              <div class="text-center">
                <span class="block text-xs font-semibold text-emerald-700 uppercase tracking-wide dark:text-emerald-300">Planeado</span>
                <p class="text-2xl font-bold text-emerald-800 dark:text-emerald-200">{{ servicio.planeado }}</p>
              </div>
              <div class="text-center">
                <span class="block text-xs font-semibold text-blue-700 uppercase tracking-wide dark:text-blue-300">Completado</span>
                <p class="text-2xl font-bold text-blue-800 dark:text-blue-200">{{ servicio.completado }}</p>
              </div>
              <div class="text-center">
                <span class="block text-xs font-semibold text-red-700 uppercase tracking-wide dark:text-red-300">Faltante</span>
                <p class="text-2xl font-bold text-red-800 dark:text-red-200">{{ servicio.faltante }}</p>
              </div>
              <div class="text-center">
                <span class="block text-xs font-semibold text-purple-700 uppercase tracking-wide dark:text-purple-300">Progreso</span>
                <p class="text-2xl font-bold text-purple-800 dark:text-purple-200">{{ calcularPorcentaje(servicio) }}%</p>
              </div>
            </div>

            <!-- Barra de Progreso -->
            <div class="mt-4 bg-gray-200 rounded-full h-3 overflow-hidden dark:bg-slate-700">
              <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-full transition-all duration-500"
                   :style="{ width: calcularPorcentaje(servicio) + '%' }"></div>
            </div>
          </div>

          <!-- Ítems de la Orden -->
          <div class="p-6 border-b border-gray-200 dark:border-slate-700">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2 dark:text-slate-100">
              <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
              Ítems de la Orden
            </h3>
            
            <div v-if="servicio.items && servicio.items.length > 0" class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-100 dark:bg-slate-800">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide dark:text-slate-300">Descripción</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide dark:text-slate-300">Planeado</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide dark:text-slate-300">Completado</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide dark:text-slate-300">Faltante</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                  <tr v-for="item in servicio.items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-slate-800/50">
                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-slate-200">{{ item.descripcion_item }}</td>
                    <td class="px-4 py-3 text-center text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ item.planeado }}</td>
                    <td class="px-4 py-3 text-center text-sm font-semibold text-blue-700 dark:text-blue-300">{{ item.completado }}</td>
                    <td class="px-4 py-3 text-center text-sm font-semibold text-red-700 dark:text-red-300">{{ item.faltante }}</td>
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
          <div class="p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2 dark:text-slate-100">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
              Avances Registrados
            </h3>

            <div v-if="servicio.avances && servicio.avances.length > 0" class="space-y-3">
              <div v-for="avance in servicio.avances" :key="avance.id"
                   class="bg-gray-50 border border-gray-200 rounded-lg p-4 dark:bg-slate-800/50 dark:border-slate-700">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-bold dark:bg-blue-900/40 dark:text-blue-200">
                        {{ avance.tarifa || 'Normal' }}
                      </span>
                      <span class="text-sm text-gray-600 dark:text-slate-400">{{ avance.created_at }}</span>
                      <span class="text-sm text-gray-600 dark:text-slate-400">por {{ avance.created_by }}</span>
                    </div>
                    <div class="text-sm text-gray-700 dark:text-slate-300">
                      <span class="font-semibold">Cantidad registrada:</span> {{ avance.cantidad_registrada }}
                      <span v-if="avance.precio_unitario_aplicado" class="ml-4">
                        <span class="font-semibold">Precio aplicado:</span> ${{ parseFloat(avance.precio_unitario_aplicado).toFixed(2) }}
                      </span>
                    </div>
                    <p v-if="avance.comentario" class="text-sm text-gray-600 mt-2 dark:text-slate-400">
                      💬 {{ avance.comentario }}
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

            <!-- TODO: Botón para registrar nuevo avance -->
            <div class="mt-4">
              <button type="button"
                      class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 transform hover:scale-[1.01] shadow-md">
                <span class="flex items-center justify-center gap-2">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                  </svg>
                  Registrar Avance en este Servicio
                </span>
              </button>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>
</template>
