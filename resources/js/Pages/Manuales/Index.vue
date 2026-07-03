<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  manuales: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({ create: false, delete: false }) },
})

const page = usePage()
const isAdmin = computed(() => !!props.can?.create)
const flashOk = computed(() => page.props.flash?.ok || '')
const flashError = computed(() => page.props.flash?.error || '')

function destroyManual(manual) {
  if (!confirm(`Eliminar el manual "${manual.titulo}"?`)) return
  router.delete(route('manuales.destroy', manual.id), { preserveScroll: true })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-sky-50 to-emerald-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
      <div class="bg-gradient-to-r from-sky-700 to-emerald-600 rounded-2xl shadow-xl p-6 sm:p-8 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-extrabold text-white mb-2">Manuales de Usuario</h1>
            <p class="text-sky-100">
              {{ isAdmin ? 'Administra los manuales disponibles por rol.' : 'Consulta los manuales disponibles para tu rol.' }}
            </p>
          </div>

          <Link
            v-if="can?.create"
            :href="route('manuales.create')"
            class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white text-sky-700 font-bold rounded-xl hover:bg-sky-50 transition-colors shadow"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Subir manual
          </Link>
        </div>
      </div>

      <div v-if="flashOk" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 font-medium">
        {{ flashOk }}
      </div>
      <div v-if="flashError" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 font-medium">
        {{ flashError }}
      </div>

      <div v-if="manuales.length" class="space-y-6">
        <article
          v-for="manual in manuales"
          :key="manual.id"
          class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 overflow-hidden"
        >
          <div class="p-5 sm:p-6 border-b border-gray-100">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
              <div class="min-w-0">
                <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 break-words">{{ manual.titulo }}</h2>
                <p v-if="manual.descripcion" class="mt-2 text-gray-600 whitespace-pre-line break-words">{{ manual.descripcion }}</p>
                <p v-else class="mt-2 text-gray-400">Sin descripcion.</p>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold">
                  <span
                    v-if="manual.visible_para_todos"
                    class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200"
                  >
                    Visible para todos
                  </span>
                  <template v-else>
                    <span
                      v-for="role in manual.roles"
                      :key="role"
                      class="px-3 py-1 rounded-full bg-sky-100 text-sky-700 border border-sky-200"
                    >
                      {{ role }}
                    </span>
                  </template>
                </div>

                <div v-if="isAdmin" class="mt-3 text-xs text-gray-500">
                  Subido por {{ manual.uploaded_by_name || 'Sistema' }} el {{ manual.created_at }}
                </div>
              </div>

              <div class="flex flex-col sm:flex-row lg:flex-col gap-2 shrink-0">
                <a
                  :href="manual.pdf_url"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-sky-700 text-white font-semibold hover:bg-sky-800 transition-colors"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3h7m0 0v7m0-7L10 14m-2-7H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-3" />
                  </svg>
                  Abrir PDF
                </a>
                <button
                  v-if="can?.delete"
                  type="button"
                  @click="destroyManual(manual)"
                  class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-red-100 text-red-700 font-semibold hover:bg-red-200 transition-colors"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  Eliminar
                </button>
              </div>
            </div>
          </div>

          <div class="bg-gray-50 p-3 sm:p-4">
            <iframe
              :src="manual.pdf_url"
              class="w-full h-[360px] md:h-[560px] rounded-xl border border-gray-200 bg-white"
              :title="`Vista previa de ${manual.titulo}`"
            />
          </div>
        </article>
      </div>

      <div v-else class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-10 sm:p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 2h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V4a2 2 0 012-2zm7 1.5V8h4.5" />
        </svg>
        <p class="text-lg font-semibold text-gray-700">
          {{ isAdmin ? 'No hay manuales registrados.' : 'No tienes manuales asignados por el momento.' }}
        </p>
        <Link
          v-if="can?.create"
          :href="route('manuales.create')"
          class="mt-5 inline-flex items-center justify-center px-5 py-3 rounded-xl bg-sky-700 text-white font-semibold hover:bg-sky-800"
        >
          Subir primer manual
        </Link>
      </div>
    </div>
  </div>
</template>
