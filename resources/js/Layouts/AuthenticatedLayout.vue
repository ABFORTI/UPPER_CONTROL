<script setup>
import { ref, computed } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'

const page  = usePage()

// Fallbacks seguros
const url   = computed(() => page.url || '')
const user  = computed(() => page.props.auth?.user || null)
const roles = computed(() => user.value?.roles ?? [])
const mainRole = computed(() => roles.value?.[0] || '')

const isAdmin  = computed(() => roles.value.includes('admin'))
const isCoord  = computed(() => roles.value.includes('coordinador'))
const isCalidad = computed(() => roles.value.includes('calidad'))
const isControl = computed(() => roles.value.includes('control'))
const isComercial = computed(() => roles.value.includes('comercial'))
const isOnlyCalidad = computed(() => isCalidad.value && roles.value.length === 1)
const isOnlyControlOrComercial = computed(() => (isControl.value || isComercial.value) && !isAdmin.value && !isCoord.value && !isCalidad.value)

const unread = computed(() => page.props.auth?.user?.unread_count || 0)
function markAll () {
  router.post(route('notificaciones.read_all'), {}, { preserveScroll: true })
}

// Órdenes pendientes de validación (solo para clientes)
const pendingValidation = computed(() => page.props.pending_validation || 0)
const showValidationAlert = computed(() => pendingValidation.value > 0 && !isAdmin.value && !isCoord.value && !isCalidad.value)

// Impersonación
const isImpersonating = computed(() => !!page.props.impersonation?.active)
const leaveUrl = computed(() => page.props.globals?.impersonate_leave ?? 'admin/impersonate/leave')
function leave (e) {
  e?.preventDefault()
  router.post(leaveUrl.value, {}, { preserveScroll: true, replace: true })
}

</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Contenedor fijo para alertas persistentes (por encima de la navbar) -->
    <div class="fixed inset-x-0 top-0 z-50 flex flex-col pointer-events-auto">
      <!-- Barra de aviso cuando estás impersonando -->
      <div v-if="isImpersonating" class="bg-yellow-100 text-yellow-900 px-4 py-2 text-sm flex justify-between border-b border-yellow-200">
        <div>⚠️ Estás impersonando la sesión de otro usuario.</div>
        <button @click="leave" class="underline">Salir de impersonación</button>
      </div>

      <!-- Alerta de órdenes pendientes de validación (solo para clientes) -->
      <div v-if="showValidationAlert" class="bg-orange-500 text-white px-4 py-3 text-sm flex items-center justify-between shadow-md">
        <div class="flex items-center gap-3">
          <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          <div>
            <span class="font-semibold">¡Atención!</span> 
            Tienes <strong>{{ pendingValidation }}</strong> {{ pendingValidation === 1 ? 'orden de trabajo terminada' : 'órdenes de trabajo terminadas' }} pendiente{{ pendingValidation === 1 ? '' : 's' }} de validación.
          </div>
        </div>
        <Link :href="route('ordenes.index')" class="px-4 py-2 bg-white text-orange-600 font-semibold rounded-lg hover:bg-orange-50 transition-colors whitespace-nowrap">
          Ver Órdenes
        </Link>
      </div>
    </div>

    <div class="flex">
      <!-- Sidebar -->
  <aside class="group peer fixed top-0 left-0 h-screen bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 transition-all duration-200 w-16 hover:w-64 z-30 text-slate-700 dark:text-slate-200 flex flex-col">
        <!-- Header brand -->
        <div class="h-16 flex items-center px-3 gap-3 shrink-0">
          <div class="w-8 h-8 bg-blue-500 rounded-md shrink-0"></div>
          <div class="overflow-hidden w-0 group-hover:w-auto group-hover:opacity-100 opacity-0 transition-all duration-200 whitespace-nowrap font-semibold">Upper Control</div>
        </div>

        <!-- Usuario / Rol / Notificaciones -->
        <div class="px-3 py-2 shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-200 font-semibold shrink-0">
              {{ (user?.name || '?').slice(0,1).toUpperCase() }}
            </div>
            <div class="overflow-hidden w-0 group-hover:w-auto group-hover:opacity-100 opacity-0 transition-all duration-200">
              <div class="text-xs text-slate-500 dark:text-slate-400">{{ mainRole || 'Usuario' }}</div>
              <div class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate max-w-[10rem]">{{ user?.name }}</div>
            </div>
            <!-- Sin botón de notificaciones aquí; se movió al footer -->
          </div>
        </div>

        <!-- Menú -->
        <nav class="mt-4 flex-1 overflow-y-hidden group-hover:overflow-y-auto px-2">
          <ul class="space-y-1 text-slate-600 dark:text-slate-300">
            <li v-if="!isOnlyCalidad">
              <Link :href="route('dashboard')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/dashboard') }">
                <Icon name="home" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Dashboard</span>
              </Link>
            </li>
            <li v-if="!isOnlyCalidad && !isOnlyControlOrComercial">
              <Link :href="route('solicitudes.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/solicitudes') }">
                <Icon name="document" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Solicitudes</span>
              </Link>
            </li>
            <li v-if="!isOnlyCalidad && !isOnlyControlOrComercial">
              <Link :href="route('ordenes.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/ordenes') }">
                <Icon name="clipboard" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Órdenes</span>
              </Link>
            </li>
            <!-- Calidad -->
            <li v-if="isAdmin || isCalidad">
              <Link :href="route('calidad.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/calidad') }">
                <Icon name="checkBadge" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Calidad</span>
              </Link>
            </li>
            <!-- Facturación - Visible para facturacion, admin Y cliente -->
            <li v-if="!isOnlyCalidad && !isOnlyControlOrComercial && (roles.includes('facturacion') || roles.includes('cliente') || isAdmin)">
              <Link :href="route('facturas.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/facturas') }">
                <Icon name="currency" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Facturación</span>
              </Link>
            </li>

            <li v-if="isAdmin || isCoord || isControl || isComercial">
              <Link :href="route('servicios.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/servicios') }">
                <Icon name="dollar" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Servicios</span>
              </Link>
            </li>
            <li v-if="isAdmin || isCoord || isControl || isComercial">
              <Link :href="route('areas.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/areas') }">
                <Icon name="folder" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Áreas</span>
              </Link>
            </li>
            <!-- Centros de costos -->
            <li v-if="isAdmin || isCoord || isControl || isComercial">
              <Link :href="route('centros_costos.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/centros-costos') }">
                <Icon name="chart" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Centros de costos</span>
              </Link>
            </li>
            <!-- Marcas -->
            <li v-if="isAdmin || isCoord || isControl || isComercial">
              <Link :href="route('marcas.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/marcas') }">
                <Icon name="tag" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Marcas</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.users.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/admin/users') }">
                <Icon name="document" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Usuarios</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.centros.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                <Icon name="building" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Centros</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.backups.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                <Icon name="document" :size="24" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Backups</span>
              </Link>
            </li>
          </ul>
        </nav>

        <!-- Footer del sidebar -->
        <div class="p-3 space-y-2 border-t border-gray-200 dark:border-slate-800 shrink-0">
          <!-- Notificaciones arriba de Logout -->
          <Link :href="route('notificaciones.index')" class="w-full relative flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/notificaciones') }">
            <span class="relative inline-flex shrink-0">
              <Icon name="bell" :size="24" />
              <span v-if="unread" class="absolute -top-1 -right-1 text-[10px] leading-none bg-red-600 text-white rounded-full px-1">{{ unread }}</span>
            </span>
            <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200 whitespace-nowrap">Notificaciones</span>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-300 transition-all">
            <Icon name="logout" :size="24" class="shrink-0" />
            <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200 whitespace-nowrap">Logout</span>
          </Link>
        </div>
      </aside>

      <!-- Overlay sobre el contenido cuando el sidebar está desplegado -->
      <div class="fixed inset-0 left-16 bg-black/20 opacity-0 transition-opacity duration-200 pointer-events-none peer-hover:opacity-100 z-10"></div>

      <!-- Contenido principal -->
      <div class="flex-1 min-h-screen ml-16 transition-all duration-200 text-slate-800 dark:text-slate-100 relative z-0">
        <header v-if="$slots.header" class="sticky top-0 z-10 bg-white/70 dark:bg-slate-900/70 backdrop-blur border-b border-gray-200 dark:border-slate-800 text-slate-800 dark:text-slate-100">
          <div class="px-4 py-4">
            <slot name="header" />
          </div>
        </header>
        <main class="p-4 md:p-6 text-slate-800 dark:text-slate-100">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
