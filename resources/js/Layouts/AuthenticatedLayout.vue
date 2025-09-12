<script setup>
import { ref, computed } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import NavLink from '@/Components/NavLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'

const page  = usePage()

// Fallbacks seguros
const url   = computed(() => page.url || '')
const user  = computed(() => page.props.auth?.user || null)
const roles = computed(() => user.value?.roles ?? [])

const isAdmin  = computed(() => roles.value.includes('admin'))
const isCoord  = computed(() => roles.value.includes('coordinador'))
const isTL     = computed(() => roles.value.includes('team_leader'))
const isClient = computed(() => roles.value.includes('cliente'))

const showingNavigationDropdown = ref(false)
const unread = computed(() => page.props.auth?.user?.unread_count || 0)

function markAll () {
  router.post(route('notificaciones.read_all'), {}, { preserveScroll: true })
}

// --- Impersonación (usa globals para evitar colisiones con 'urls' de cada página)
const isImpersonating = computed(() => !!page.props.impersonation?.active)
const leaveUrl = computed(() => page.props.globals?.impersonate_leave ?? 'admin/impersonate/leave')

function leave (e) {
  e?.preventDefault()
  // IMPORTANTE: pasa una string válida; usa .value del computed
  router.post(leaveUrl.value, {}, { preserveScroll: true, replace: true })
}
</script>

<template>
  <div>
    <!-- Barra de aviso cuando estás impersonando -->
    <div v-if="isImpersonating" class="bg-yellow-100 text-yellow-900 px-4 py-2 text-sm flex justify-between">
      <div>⚠️ Estás impersonando la sesión de otro usuario.</div>
      <button @click="leave" class="underline">Salir de impersonación</button>
    </div>

    <div class="min-h-screen bg-gray-100">
      <nav class="border-b border-gray-100 bg-white">
        <!-- Primary Navigation Menu -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="flex h-16 justify-between">
            <div class="flex">
              <!-- Logo -->
              <div class="flex shrink-0 items-center">
                <Link :href="route('dashboard')">
                  <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
                </Link>
              </div>

              <!-- Navigation Links -->
              <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                <Link :href="route('dashboard')" :class="{ 'text-black font-semibold': url.includes('/dashboard') }">
                  Dashboard
                </Link>
                <Link :href="route('solicitudes.index')">
                  Solicitudes
                </Link>
                <Link v-if="isAdmin || isCoord" :href="route('precios.index')" :class="{ 'text-black font-semibold': url.includes('/precios') }">
                  Precios
                </Link>
                <Link v-if="isAdmin" :href="route('admin.users.index')" :class="{ 'text-black font-semibold': url.includes('/admin/users') }">
                  Usuarios
                </Link>
                <Link v-if="isAdmin" :href="route('admin.centros.index')">Centros</Link> 
                <Link v-if="$page.props.auth?.user?.roles?.includes('admin')" :href="route('admin.backups.index')">Backups</Link>
              </div>
            </div>

            <div class="hidden sm:ms-6 sm:flex sm:items-center">
              <!-- Settings Dropdown -->
              <div class="relative ms-3">
                <Dropdown align="right" width="48">
                  <template #trigger>
                    <span class="inline-flex rounded-md">
                      <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                      >
                        {{ user?.name }}
                        <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                      </button>
                    </span>
                  </template>

                  <template #content>
                    <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                    <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                  </template>
                </Dropdown>
              </div>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
              <button
                @click="showingNavigationDropdown = !showingNavigationDropdown"
                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
              >
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                  <path
                    :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                  <path
                    :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Responsive Navigation Menu -->
        <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
          <div class="space-y-1 pb-3 pt-2">
            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
              Dashboard
            </ResponsiveNavLink>
          </div>

          <!-- Responsive Settings Options -->
          <div class="border-t border-gray-200 pb-1 pt-4">
            <div class="px-4">
              <div class="text-base font-medium text-gray-800">
                {{ user?.name }}
              </div>
              <div class="text-sm font-medium text-gray-500">
                {{ user?.email }}
              </div>
            </div>

            <div class="mt-3 space-y-1">
              <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
              <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
            </div>
          </div>
        </div>
      </nav>

      <!-- Page Heading -->
      <header class="bg-white shadow" v-if="$slots.header">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <!-- Page Content -->
      <main>
        <slot />
      </main>
    </div>
  </div>

  <!-- Notificaciones -->
  <nav class="flex items-center gap-4">
    <a :href="route('notificaciones.index')" class="relative inline-flex items-center">
      🔔
      <span v-if="unread" class="absolute -top-2 -right-2 text-xs bg-red-600 text-white rounded-full px-1">
        {{ unread }}
      </span>
    </a>
    <button v-if="unread" @click="markAll" class="text-xs underline">Marcar leídas</button>
  </nav>
</template>
