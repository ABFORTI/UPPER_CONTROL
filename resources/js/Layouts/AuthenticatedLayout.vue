<script setup>
import { ref, computed } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'
import { useAssetUrl } from '@/Support/useAssetUrl'
import { useTheme } from '@/Support/useTheme'

const page  = usePage()
const mobileOpen = ref(false)
const asset = useAssetUrl()

const labelVisibilityClasses = computed(() =>
  mobileOpen.value
    ? 'w-auto opacity-100'
    : 'w-0 opacity-0 sm:group-hover:w-auto sm:group-hover:opacity-100'
)
const navArrangementClasses = computed(() =>
  mobileOpen.value
    ? 'justify-start gap-3'
    : 'justify-center gap-0 sm:group-hover:justify-start sm:group-hover:gap-3'
)

const { currentTheme, toggleTheme } = useTheme()
const isDarkTheme = computed(() => currentTheme.value === 'dark')
const themeToggleText = computed(() => (isDarkTheme.value ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'))

// Fallbacks seguros
const url   = computed(() => page.url || '')
const user  = computed(() => page.props.auth?.user || null)
const roles = computed(() => user.value?.roles ?? [])
const mainRole = computed(() => roles.value?.[0] || '')

// Features habilitadas del centro del usuario (UI). La seguridad real está en backend.
const features = computed(() => page.props.auth?.features ?? [])
function hasFeature(key) {
  return (features.value || []).includes(key)
}

const isAdmin  = computed(() => roles.value.includes('admin'))
const isTeamLeader = computed(() => roles.value.includes('team_leader'))
const isCoord  = computed(() => roles.value.includes('coordinador'))
const isCalidad = computed(() => roles.value.includes('calidad'))
const isControl = computed(() => roles.value.includes('control'))
const isComercial = computed(() => roles.value.includes('comercial'))
const isGerente = computed(() => roles.value.includes('gerente_upper'))
const isGerenteCentro = computed(() => roles.value.includes('Cliente_Gerente'))
const isOnlyCalidad = computed(() => isCalidad.value && roles.value.length === 1)
const isOnlyTeamLeader = computed(() => isTeamLeader.value && roles.value.length === 1)
const isOnlyControlOrComercial = computed(() => {
  const rs = roles.value || []
  if (rs.length === 0) return false
  return rs.every(r => r === 'control' || r === 'comercial')
})

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

function closeMobile () {
  mobileOpen.value = false
}

function logout (e) {
  // Solo permite logout si fue disparado por un click real del usuario.
  // e.isTrusted = true en eventos del usuario; false en eventos sintéticos/programáticos.
  if (!e || !e.isTrusted) {
    console.warn('[upper-control] logout() bloqueado: no es un evento confiable del usuario.', e);
    return;
  }
  e.preventDefault();
  try { window.__lastExplicitLogoutAt = Date.now(); } catch {}
  router.post(route('logout'), {}, { replace: true });
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
  <aside :class="[
          'group peer fixed top-0 left-0 h-screen bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 transition-all duration-200 z-30 text-slate-700 dark:text-slate-200 flex flex-col',
          'sm:w-16 sm:hover:w-64 sm:translate-x-0',
          mobileOpen ? 'translate-x-0 w-64' : '-translate-x-full w-64'
        ]">
        <!-- Header brand -->
        <div class="h-16 flex items-center px-3 gap-3 shrink-0">
            <div class="w-8 h-8 shrink-0">
            <img :src="asset('img/upper_control.png')" alt="Upper Control" class="w-full h-full object-contain rounded-md" loading="lazy" />
          </div>
          <div :class="['overflow-hidden transition-all duration-200 whitespace-nowrap font-semibold', labelVisibilityClasses]">Upper Control</div>
        </div>

        <!-- Usuario / Rol / Notificaciones -->
        <div class="px-3 py-2 shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-200 font-semibold shrink-0">
              {{ (user?.name || '?').slice(0,1).toUpperCase() }}
            </div>
            <div :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">
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
              <Link :href="route('dashboard')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/dashboard') }
              ]">
                <Icon name="home" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Dashboard</span>
              </Link>
            </li>
            <li v-if="!isOnlyCalidad && !isOnlyControlOrComercial && !isOnlyTeamLeader">
              <Link :href="route('solicitudes.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/solicitudes') }
              ]">
                <Icon name="document" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Solicitudes</span>
              </Link>
            </li>

            <!-- Cotizaciones: visible solo si la feature está habilitada para el centro -->
            <li v-if="hasFeature('ver_cotizacion') && !isOnlyCalidad && !isOnlyControlOrComercial && (isAdmin || isCoord || roles.includes('Cliente_Supervisor') || roles.includes('Cliente_Gerente') || isGerente || roles.includes('facturacion'))">
              <Link :href="route('cotizaciones.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/cotizaciones') }
              ]">
                <Icon name="document" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Cotizaciones</span>
              </Link>
            </li>
            <li v-if="!isOnlyCalidad && !isOnlyControlOrComercial">
              <Link :href="route('ordenes.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/ordenes') }
              ]">
                <Icon name="clipboard" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Órdenes</span>
              </Link>
            </li>
            <!-- Calidad -->
            <li v-if="isAdmin || isCalidad || isGerente">
              <Link :href="route('calidad.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/calidad') }
              ]">
                <Icon name="checkBadge" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Calidad</span>
              </Link>
            </li>
            <!-- Facturación - Visible para facturacion, admin, Cliente_Supervisor, gerente_upper y Cliente_Gerente -->
            <li v-if="!isOnlyCalidad && !isOnlyControlOrComercial && (roles.includes('facturacion') || roles.includes('Cliente_Supervisor') || roles.includes('Cliente_Gerente') || isAdmin || isGerente)">
              <Link :href="route('facturas.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/facturas') }
              ]">
                <Icon name="currency" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Facturación</span>
              </Link>
            </li>

            <li v-if="isAdmin || isCoord || isControl || isComercial || isGerente">
              <Link :href="route('servicios.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/servicios') }
              ]">
                <Icon name="dollar" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Servicios</span>
              </Link>
            </li>
            <li v-if="isAdmin || isCoord || isControl || isComercial || isGerente">
              <Link :href="route('areas.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/areas') }
              ]">
                <Icon name="folder" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Áreas</span>
              </Link>
            </li>
            <!-- Centros de costos -->
            <li v-if="isAdmin || isCoord || isControl || isComercial || isGerente">
              <Link :href="route('centros_costos.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/centros-costos') }
              ]">
                <Icon name="chart" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Centros de costos</span>
              </Link>
            </li>
            <!-- Marcas -->
            <li v-if="isAdmin || isCoord || isControl || isComercial || isGerente">
              <Link :href="route('marcas.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/marcas') }
              ]">
                <Icon name="tag" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Marcas</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.users.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/admin/users') }
              ]">
                <Icon name="document" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Usuarios</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.centros.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses
              ]">
                <Icon name="building" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Centros</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.centros.features.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses,
                { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/admin/centros/features') }
              ]">
                <Icon name="checkBadge" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Funcionalidades</span>
              </Link>
            </li>
            <li v-if="isAdmin">
              <Link :href="route('admin.backups.index')" :class="[
                'flex items-center p-3 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-all',
                navArrangementClasses
              ]">
                <Icon name="document" :size="24" />
                <span :class="['overflow-hidden transition-all duration-200', labelVisibilityClasses]">Backups</span>
              </Link>
            </li>
          </ul>
        </nav>

        <!-- Footer del sidebar -->
        <div class="p-3 space-y-2 border-t border-gray-200 dark:border-slate-800 shrink-0">
          <button
            type="button"
            @click="toggleTheme"
            :class="[
              'w-full flex items-center p-3 rounded-md border transition-all',
              navArrangementClasses,
              isDarkTheme
                ? 'bg-slate-900 text-slate-100 border-slate-700 hover:bg-slate-800'
                : 'bg-white text-slate-700 border-gray-200 hover:bg-slate-100'
            ]"
          >
            <Icon :name="isDarkTheme ? 'sun' : 'moon'" :size="24" class="shrink-0" />
            <span
              :class="[
                'overflow-hidden transition-all duration-200 whitespace-nowrap flex items-center gap-2',
                labelVisibilityClasses
              ]"
            >
              {{ themeToggleText }}
              <span
                class="text-[10px] font-semibold uppercase tracking-wide"
                :class="isDarkTheme ? 'text-emerald-400' : 'text-slate-400'"
              >
                {{ isDarkTheme ? 'ON' : 'OFF' }}
              </span>
            </span>
          </button>
          <!-- Notificaciones arriba de Logout -->
          <Link :href="route('notificaciones.index')" :class="[
            'w-full relative flex items-center p-3 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition-all',
            navArrangementClasses,
            { 'bg-blue-50 text-blue-700 dark:bg-slate-800': url.includes('/notificaciones') }
          ]">
            <span class="relative inline-flex shrink-0">
              <Icon name="bell" :size="24" />
              <span v-if="unread" class="absolute -top-1 -right-1 text-[10px] leading-none bg-red-600 text-white rounded-full px-1">{{ unread }}</span>
            </span>
            <span :class="['overflow-hidden transition-all duration-200 whitespace-nowrap', labelVisibilityClasses]">Notificaciones</span>
          </Link>
          <button type="button" @click="logout" data-action="logout" :class="[
            'w-full flex items-center p-3 rounded-md bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-300 transition-all',
            navArrangementClasses
          ]">
            <Icon name="logout" :size="24" class="shrink-0" />
            <span :class="['overflow-hidden transition-all duration-200 whitespace-nowrap', labelVisibilityClasses]">Logout</span>
          </button>
        </div>
      </aside>

      <!-- Overlay móvil -->
      <div v-if="mobileOpen" @click="closeMobile" class="fixed inset-0 bg-black/40 z-20 sm:hidden"></div>

      <!-- Contenido principal -->
      <div class="flex-1 min-h-screen sm:ml-16 ml-0 transition-all duration-200 text-slate-800 dark:text-slate-100 relative z-0">
        <!-- Barra superior móvil -->
        <div class="sm:hidden sticky top-0 z-20 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-b border-gray-200 dark:border-slate-800">
          <div class="px-4 py-3 flex items-center justify-between">
            <button @click="mobileOpen = true" class="p-2 rounded-md border border-gray-200 dark:border-slate-700 text-slate-700 dark:text-slate-200">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            <div class="flex items-center gap-2">
              <img :src="asset('img/upper_control.png')" alt="Upper Control" class="h-8 w-auto object-contain" loading="lazy" />
            </div>
          </div>
        </div>
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
