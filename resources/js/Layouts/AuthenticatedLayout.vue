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
const isOnlyCalidad = computed(() => isCalidad.value && roles.value.length === 1)

const unread = computed(() => page.props.auth?.user?.unread_count || 0)
function markAll () {
  router.post(route('notificaciones.read_all'), {}, { preserveScroll: true })
}

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
    <!-- Barra de aviso cuando estás impersonando -->
    <div v-if="isImpersonating" class="bg-yellow-100 text-yellow-900 px-4 py-2 text-sm flex justify-between">
      <div>⚠️ Estás impersonando la sesión de otro usuario.</div>
      <button @click="leave" class="underline">Salir de impersonación</button>
    </div>

    <div class="flex">
      <!-- Sidebar -->
  <aside class="group peer fixed top-0 left-0 h-screen bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 transition-all duration-200 w-16 hover:w-64 z-30 text-slate-700 dark:text-slate-200">
        <!-- Header brand -->
        <div class="h-16 flex items-center px-3 gap-3">
          <div class="w-8 h-8 bg-blue-500 rounded-md"></div>
          <div class="overflow-hidden w-0 group-hover:w-auto group-hover:opacity-100 opacity-0 transition-all duration-200 whitespace-nowrap font-semibold">Upper Control</div>
        </div>

        <!-- Usuario / Rol / Notificaciones -->
        <div class="px-3 py-2">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-200 font-semibold">
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
        <nav class="mt-4 flex-1 overflow-y-auto">
          <ul class="px-2 space-y-1 text-slate-600 dark:text-slate-300">
            <li v-if="!isOnlyCalidad">
              <Link :href="route('dashboard')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/dashboard') }">
                <Icon name="home" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Dashboard</span>
              </Link>
            </li>
            <li v-if="!isOnlyCalidad">
              <Link :href="route('solicitudes.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/solicitudes') }">
                <Icon name="document" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Solicitudes</span>
              </Link>
            </li>
            <li v-if="!isOnlyCalidad">
              <Link :href="route('ordenes.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/ordenes') }">
                <Icon name="clipboard" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Órdenes</span>
              </Link>
            </li>
            <!-- Calidad -->
            <li v-if="isAdmin || isCalidad">
              <Link :href="route('calidad.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/calidad') }">
                <Icon name="checkBadge" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Calidad</span>
              </Link>
            </li>
            <li v-if="!isOnlyCalidad && (roles.includes('facturacion') || isAdmin)">
              <Link :href="route('facturas.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/facturas') }">
                <Icon name="currency" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Facturación</span>
              </Link>
            </li>

            <li v-if="isAdmin || isCoord">
              <Link :href="route('servicios.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/servicios') }">
                <Icon name="dollar" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Servicios</span>
              </Link>
            </li>
            <li v-if="isAdmin || isCoord">
              <Link :href="route('areas.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/areas') }">
                <Icon name="folder" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Áreas</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.users.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" :class="{ 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/admin/users') }">
                <Icon name="document" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Usuarios</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.centros.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                <Icon name="building" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Centros</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.backups.index')" class="flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                <Icon name="document" />
                <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Backups</span>
              </Link>
            </li>
          </ul>
        </nav>

        <!-- Footer del sidebar -->
        <div class="absolute bottom-0 left-0 right-0 p-3 space-y-2">
          <!-- Notificaciones arriba de Logout -->
          <Link :href="route('notificaciones.index')" class="w-full relative flex items-center justify-center group-hover:justify-start gap-0 group-hover:gap-3 p-3 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition-all">
            <span class="relative inline-flex">
              <Icon name="bell" />
              <span v-if="unread" class="absolute -top-1 -right-1 text-[10px] leading-none bg-red-600 text-white rounded-full px-1">{{ unread }}</span>
            </span>
            <span class="w-0 opacity-0 group-hover:w-auto group-hover:opacity-100 overflow-hidden transition-all duration-200">Notificaciones</span>
          </Link>
          <form :action="route('logout')" method="post" class="hidden"></form>
          <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center gap-3 p-3 rounded-md bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-300">
            <Icon name="logout" />
            <span class="opacity-0 group-hover:opacity-100 transition-opacity duration-200">Logout</span>
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
