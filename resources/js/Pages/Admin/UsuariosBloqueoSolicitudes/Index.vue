<script setup>
import { ref, computed } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  data:        Object,
  filters:     Object,
  centros:     Array,
  centrosCosto: Array,
  urls:        Object,
})

const page    = usePage()
const flashOk = computed(() => page.props.flash?.ok ?? null)
const flashErr= computed(() => page.props.flash?.error ?? null)

// ──────────────────────────────────────────────
// Búsqueda / filtros
// ──────────────────────────────────────────────
const centrosCostoFiltrados = computed(() => {
  const centroId = parseInt(props.filters?.centro)
  if (!centroId) return props.centrosCosto ?? []
  return (props.centrosCosto ?? []).filter(cc => cc.id_centrotrabajo === centroId)
})

function submitFiltros(e) {
  const f = new FormData(e.target)
  router.get(
    route('admin.usuarios-bloqueo-solicitudes.index'),
    Object.fromEntries(f.entries()),
    { preserveState: true, replace: true }
  )
}

function limpiarFiltros() {
  router.get(route('admin.usuarios-bloqueo-solicitudes.index'), {}, { replace: true })
}

// ──────────────────────────────────────────────
// Modal de bloqueo
// ──────────────────────────────────────────────
const modalAbierto   = ref(false)
const usuarioActivo  = ref(null)

const formBloqueo = useForm({
  motivo_bloqueo_solicitudes: '',
})

function abrirBloquear(usuario) {
  usuarioActivo.value  = usuario
  formBloqueo.reset()
  modalAbierto.value   = true
}

function cerrarModal() {
  modalAbierto.value  = false
  usuarioActivo.value = null
  formBloqueo.reset()
}

function confirmarBloqueo() {
  formBloqueo.post(
    route('admin.usuarios-bloqueo-solicitudes.bloquear', usuarioActivo.value.id),
    {
      preserveScroll: true,
      onSuccess: () => cerrarModal(),
    }
  )
}

// ──────────────────────────────────────────────
// Desbloquear
// ──────────────────────────────────────────────
function confirmarDesbloquear(usuario) {
  if (!confirm(`¿Desbloquear a ${usuario.name} para generar solicitudes?`)) return
  router.post(
    route('admin.usuarios-bloqueo-solicitudes.desbloquear', usuario.id),
    {},
    { preserveScroll: true }
  )
}

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────
function formatFecha(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('es-MX', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-blue-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">

      <!-- ── Header ──────────────────────────────────── -->
      <div class="bg-gradient-to-r from-indigo-600 to-[#1E1C8F] rounded-2xl shadow-xl p-6 sm:p-8 mb-6">
        <div>
          <h1 class="text-3xl font-extrabold text-white mb-2">🔒 Bloqueo de usuarios para solicitudes</h1>
          <p class="text-indigo-100 text-sm leading-relaxed max-w-3xl">
            Desde aquí puedes bloquear usuarios específicos para impedir que generen nuevas solicitudes.
            El bloqueo no afecta a otros usuarios del mismo centro de trabajo ni a solicitudes anteriores.
          </p>
        </div>
      </div>

      <!-- ── Flash messages ──────────────────────────── -->
      <div v-if="flashOk"
           class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-emerald-800 font-medium shadow-sm">
        <svg class="w-5 h-5 flex-shrink-0 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        {{ flashOk }}
      </div>
      <div v-if="flashErr"
           class="mb-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-3 text-red-800 font-medium shadow-sm">
        <svg class="w-5 h-5 flex-shrink-0 text-red-600" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
        </svg>
        {{ flashErr }}
      </div>

      <!-- ── Filtros ──────────────────────────────────── -->
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 mb-6">
        <form @submit.prevent="submitFiltros" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">

          <div class="xl:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Buscar</label>
            <input
              name="search"
              :value="filters.search || ''"
              placeholder="Nombre o email…"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200"
            >
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Centro de trabajo</label>
            <select
              name="centro"
              :value="filters.centro || ''"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200"
            >
              <option value="">Todos</option>
              <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Centro de costo</label>
            <select
              name="centro_costo"
              :value="filters.centro_costo || ''"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200"
            >
              <option value="">Todos</option>
              <option v-for="cc in centrosCostoFiltrados" :key="cc.id" :value="cc.id">{{ cc.nombre }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Estado</label>
            <select
              name="estado"
              :value="filters.estado || ''"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200"
            >
              <option value="">Todos</option>
              <option value="activos">Activos</option>
              <option value="bloqueados">Bloqueados</option>
            </select>
          </div>

          <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-5">
            <button
              type="submit"
              class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-[#1E1C8F] text-white font-bold rounded-xl hover:shadow-xl transition-all duration-200 transform hover:scale-105"
            >
              Filtrar
            </button>
            <button
              type="button"
              @click="limpiarFiltros"
              class="px-6 py-3 bg-gray-100 text-gray-600 font-semibold rounded-xl hover:bg-gray-200 transition-all duration-200"
            >
              Limpiar
            </button>
          </div>

        </form>
      </div>

      <!-- ── Tabla (desktop) ─────────────────────────── -->
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 overflow-hidden">
        <div class="hidden lg:block overflow-x-auto">
          <table class="w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700 uppercase tracking-wider text-xs">
              <tr>
                <th class="px-5 py-3 text-left font-bold">Usuario</th>
                <th class="px-5 py-3 text-left font-bold">Rol</th>
                <th class="px-5 py-3 text-left font-bold">Centro de trabajo</th>
                <th class="px-5 py-3 text-left font-bold">Centro de costo</th>
                <th class="px-5 py-3 text-center font-bold">Estado solicitudes</th>
                <th class="px-5 py-3 text-left font-bold">Motivo de bloqueo</th>
                <th class="px-5 py-3 text-left font-bold">Fecha bloqueo</th>
                <th class="px-5 py-3 text-left font-bold">Bloqueado por</th>
                <th class="px-5 py-3 text-right font-bold">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr
                v-for="u in data.data"
                :key="u.id"
                class="hover:bg-indigo-50 transition-colors duration-150"
                :class="{ 'bg-red-50/40': u.bloqueado_solicitudes }"
              >
                <!-- Usuario -->
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 h-9 w-9 rounded-full flex items-center justify-center text-white font-bold text-sm"
                         :class="u.bloqueado_solicitudes ? 'bg-red-500' : 'bg-[#1E1C8F]'">
                      {{ u.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div>
                      <div class="font-semibold text-gray-900 leading-tight">{{ u.name }}</div>
                      <div class="text-xs text-gray-500">{{ u.email }}</div>
                    </div>
                  </div>
                </td>

                <!-- Roles -->
                <td class="px-5 py-3">
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="r in u.roles" :key="r"
                      class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-xs font-semibold"
                    >{{ r }}</span>
                    <span v-if="!u.roles?.length" class="text-gray-400 text-xs">—</span>
                  </div>
                </td>

                <!-- Centro de trabajo -->
                <td class="px-5 py-3 text-gray-600 text-sm">{{ u.centro?.nombre || '—' }}</td>

                <!-- Centros de costo -->
                <td class="px-5 py-3">
                  <div v-if="u.centros_costo?.length" class="flex flex-wrap gap-1">
                    <span
                      v-for="cc in u.centros_costo" :key="cc.id"
                      class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full text-xs"
                    >{{ cc.nombre }}</span>
                  </div>
                  <span v-else class="text-gray-400 text-xs">—</span>
                </td>

                <!-- Estado -->
                <td class="px-5 py-3 text-center">
                  <span
                    v-if="!u.bloqueado_solicitudes"
                    class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold whitespace-nowrap"
                  >
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Puede generar solicitudes
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold whitespace-nowrap"
                  >
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                    Bloqueado para solicitudes
                  </span>
                </td>

                <!-- Motivo -->
                <td class="px-5 py-3 text-sm text-gray-600 max-w-[200px]">
                  <span v-if="u.motivo_bloqueo_solicitudes" class="line-clamp-2" :title="u.motivo_bloqueo_solicitudes">
                    {{ u.motivo_bloqueo_solicitudes }}
                  </span>
                  <span v-else class="text-gray-400">—</span>
                </td>

                <!-- Fecha -->
                <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                  {{ formatFecha(u.bloqueado_solicitudes_en) }}
                </td>

                <!-- Bloqueado por -->
                <td class="px-5 py-3 text-sm text-gray-600">
                  {{ u.bloqueado_por?.name || '—' }}
                </td>

                <!-- Acciones -->
                <td class="px-5 py-3">
                  <div class="flex justify-end">
                    <button
                      v-if="!u.bloqueado_solicitudes"
                      @click="abrirBloquear(u)"
                      class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition-colors duration-200"
                    >
                      <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                      </svg>
                      Bloquear
                    </button>
                    <button
                      v-else
                      @click="confirmarDesbloquear(u)"
                      class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors duration-200"
                    >
                      <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
                      </svg>
                      Desbloquear
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="!data.data?.length">
                <td colspan="9" class="px-6 py-14 text-center text-gray-400">
                  <svg class="w-14 h-14 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                  </svg>
                  <p class="font-medium">No se encontraron usuarios clientes</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ── Cards (mobile / tablet) ──────────────── -->
        <div class="lg:hidden p-4 space-y-4">
          <div
            v-for="u in data.data"
            :key="`m-${u.id}`"
            class="border rounded-2xl p-4 shadow-sm bg-white"
            :class="u.bloqueado_solicitudes ? 'border-red-200 bg-red-50/30' : 'border-gray-200'"
          >
            <div class="flex items-start gap-3 mb-3">
              <div class="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center text-white font-bold"
                   :class="u.bloqueado_solicitudes ? 'bg-red-500' : 'bg-[#1E1C8F]'">
                {{ u.name?.charAt(0)?.toUpperCase() || '?' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-gray-900 truncate">{{ u.name }}</div>
                <div class="text-xs text-gray-500 truncate">{{ u.email }}</div>
              </div>
              <!-- Badge estado -->
              <span
                v-if="!u.bloqueado_solicitudes"
                class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold"
              >
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Activo
              </span>
              <span
                v-else
                class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-semibold"
              >
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
                Bloqueado
              </span>
            </div>

            <div class="space-y-1.5 text-sm text-gray-600">
              <div class="flex gap-1 flex-wrap">
                <span class="font-semibold text-gray-700">Rol:</span>
                <span v-for="r in u.roles" :key="r" class="px-1.5 py-0.5 bg-indigo-100 text-indigo-700 rounded text-xs font-medium">{{ r }}</span>
                <span v-if="!u.roles?.length" class="text-gray-400">—</span>
              </div>
              <div><span class="font-semibold text-gray-700">Centro:</span> {{ u.centro?.nombre || '—' }}</div>
              <div v-if="u.bloqueado_solicitudes">
                <span class="font-semibold text-gray-700">Motivo:</span>
                <span class="ml-1 text-red-700">{{ u.motivo_bloqueo_solicitudes || '—' }}</span>
              </div>
              <div v-if="u.bloqueado_solicitudes">
                <span class="font-semibold text-gray-700">Fecha:</span> {{ formatFecha(u.bloqueado_solicitudes_en) }}
              </div>
              <div v-if="u.bloqueado_por">
                <span class="font-semibold text-gray-700">Por:</span> {{ u.bloqueado_por.name }}
              </div>
            </div>

            <div class="mt-3 flex justify-end">
              <button
                v-if="!u.bloqueado_solicitudes"
                @click="abrirBloquear(u)"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition-colors"
              >
                Bloquear
              </button>
              <button
                v-else
                @click="confirmarDesbloquear(u)"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors"
              >
                Desbloquear
              </button>
            </div>
          </div>

          <div v-if="!data.data?.length" class="py-10 text-center text-gray-400 font-medium">
            No se encontraron usuarios clientes
          </div>
        </div>

        <!-- ── Paginación ────────────────────────────── -->
        <div v-if="data.links && data.links.length > 3" class="px-6 py-4 bg-gray-50 border-t border-gray-100">
          <div class="flex items-center justify-center gap-2 flex-wrap">
            <a
              v-for="link in data.links"
              :key="link.url || link.label"
              :href="link.url || '#'"
              v-html="link.label"
              :class="[
                'px-4 py-2 rounded-lg font-medium transition-all duration-200 text-sm',
                link.active
                  ? 'bg-indigo-600 text-white shadow-lg'
                  : link.url
                    ? 'bg-white text-gray-700 hover:bg-indigo-50 border-2 border-gray-200'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none'
              ]"
            />
          </div>
        </div>
      </div>

    </div><!-- /max-w -->
  </div><!-- /min-h -->

  <!-- ── Modal Bloquear ────────────────────────────────── -->
  <Teleport to="body">
    <div
      v-if="modalAbierto"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      @keydown.esc="cerrarModal"
    >
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="cerrarModal"/>

      <!-- Panel -->
      <div class="relative z-10 w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden">

        <!-- Cabecera modal -->
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
              </svg>
              <h2 class="text-lg font-bold text-white">Bloquear usuario para solicitudes</h2>
            </div>
            <button @click="cerrarModal" class="text-red-200 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Cuerpo modal -->
        <div class="p-6">
          <!-- Info usuario -->
          <div class="mb-5 flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
            <div class="flex-shrink-0 h-11 w-11 bg-[#1E1C8F] rounded-full flex items-center justify-center text-white font-bold text-lg">
              {{ usuarioActivo?.name?.charAt(0)?.toUpperCase() || '?' }}
            </div>
            <div>
              <div class="font-semibold text-gray-900">{{ usuarioActivo?.name }}</div>
              <div class="text-sm text-gray-500">{{ usuarioActivo?.email }}</div>
            </div>
          </div>

          <!-- Error de usuario (no cliente, protegido, etc.) -->
          <div v-if="formBloqueo.errors.usuario"
               class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ formBloqueo.errors.usuario }}
          </div>

          <!-- Campo motivo -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              Motivo del bloqueo <span class="text-red-500">*</span>
            </label>
            <textarea
              v-model="formBloqueo.motivo_bloqueo_solicitudes"
              rows="4"
              placeholder="Describe el motivo del bloqueo (adeudo, incumplimiento, etc.)…"
              :class="[
                'w-full px-4 py-3 border-2 rounded-xl resize-none focus:ring-4 focus:ring-red-100 transition-all duration-200',
                formBloqueo.errors.motivo_bloqueo_solicitudes
                  ? 'border-red-400 bg-red-50 focus:border-red-500'
                  : 'border-gray-200 focus:border-red-400'
              ]"
            />
            <p v-if="formBloqueo.errors.motivo_bloqueo_solicitudes"
               class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
              </svg>
              {{ formBloqueo.errors.motivo_bloqueo_solicitudes }}
            </p>
            <p class="mt-1 text-xs text-gray-400 text-right">
              {{ formBloqueo.motivo_bloqueo_solicitudes.length }} / 1000
            </p>
          </div>
        </div>

        <!-- Footer modal -->
        <div class="px-6 pb-6 flex items-center justify-end gap-3">
          <button
            type="button"
            @click="cerrarModal"
            class="px-5 py-2.5 bg-gray-100 text-gray-700 font-semibold rounded-xl hover:bg-gray-200 transition-colors"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="formBloqueo.processing || !formBloqueo.motivo_bloqueo_solicitudes.trim()"
            @click="confirmarBloqueo"
            class="inline-flex items-center gap-2 px-6 py-2.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg v-if="formBloqueo.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            Confirmar bloqueo
          </button>
        </div>

      </div>
    </div>
  </Teleport>
</template>
