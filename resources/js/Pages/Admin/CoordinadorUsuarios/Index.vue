<script setup>
import { computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  centros: Array,
  selectedCentroId: Number,
  coordinadores: Array,
  selectedCoordinadorId: Number,
  usuariosCentro: Array,
  asignadosIds: Array,
  urls: Object,
})

const form = useForm({
  usuario_ids: [...(props.asignadosIds || [])],
  centro_trabajo_id: props.selectedCentroId,
})

watch(
  () => props.asignadosIds,
  (ids) => { form.usuario_ids = [...(ids || [])] },
  { deep: true }
)

function onCentroChange(e) {
  const id = Number(e?.target?.value || 0)
  router.get(route('admin.coordinadores.usuarios.index'), { centro_trabajo_id: id }, { replace: true })
}

function onCoordinadorChange(e) {
  const id = Number(e?.target?.value || 0)
  router.get(
    route('admin.coordinadores.usuarios.index'),
    { centro_trabajo_id: props.selectedCentroId, coordinador_id: id },
    { replace: true }
  )
}

function toggleUsuario(userId) {
  const idx = form.usuario_ids.indexOf(userId)
  if (idx === -1) {
    form.usuario_ids.push(userId)
  } else {
    form.usuario_ids.splice(idx, 1)
  }
}

function save() {
  if (!props.selectedCoordinadorId) return
  form.put(
    route('admin.coordinadores.usuarios.update', props.selectedCoordinadorId),
    {
      preserveScroll: true,
      data: {
        usuario_ids: form.usuario_ids,
        centro_trabajo_id: props.selectedCentroId,
      },
    }
  )
}

const selectedCoord = computed(() =>
  (props.coordinadores || []).find(c => c.id === props.selectedCoordinadorId) || null
)
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto">
      <!-- Header -->
      <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-2xl shadow-xl p-6 sm:p-8 mb-6">
        <h1 class="text-3xl font-extrabold text-white mb-2">👥 Coordinadores de Equipo</h1>
        <p class="text-indigo-100">Asigna qué usuarios puede ver cada coordinador de equipo por centro</p>
      </div>

      <!-- Selector de Centro -->
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Centro de trabajo</label>
        <select :value="selectedCentroId" @change="onCentroChange"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all">
          <option v-for="c in centros" :key="c.id" :value="c.id">
            {{ (c.prefijo ? c.prefijo + ' — ' : '') + c.nombre }}
          </option>
        </select>
      </div>

      <!-- Selector de Coordinador -->
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Coordinador de equipo</label>
        <div v-if="!coordinadores || coordinadores.length === 0" class="text-sm text-gray-500 italic">
          No hay usuarios con rol <strong>coordinador_equipo</strong> en este centro. Asigna ese rol primero desde la sección de Usuarios.
        </div>
        <select v-else :value="selectedCoordinadorId" @change="onCoordinadorChange"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all">
          <option v-for="c in coordinadores" :key="c.id" :value="c.id">
            {{ c.name }} ({{ c.email }})
          </option>
        </select>
      </div>

      <!-- Lista de usuarios -->
      <div v-if="selectedCoordinadorId && coordinadores?.length" class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-gray-700">Usuarios asignados a: <span class="text-indigo-700">{{ selectedCoord?.name }}</span></p>
            <p class="text-xs text-gray-500 mt-0.5">Marca los usuarios cuyas órdenes podrá ver este coordinador</p>
          </div>
          <button @click="save" :disabled="form.processing"
            class="px-6 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold rounded-xl hover:shadow-xl transition-all disabled:opacity-60">
            {{ form.processing ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>

        <div v-if="!usuariosCentro || usuariosCentro.length === 0" class="px-6 py-12 text-center text-gray-500">
          No hay usuarios activos en este centro.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700 uppercase tracking-wider">
              <tr>
                <th class="px-6 py-3 text-left font-bold">Asignado</th>
                <th class="px-6 py-3 text-left font-bold">Nombre</th>
                <th class="px-6 py-3 text-left font-bold">Correo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="u in usuariosCentro" :key="u.id"
                class="hover:bg-indigo-50 transition-colors cursor-pointer"
                @click="toggleUsuario(u.id)">
                <td class="px-6 py-4">
                  <input
                    type="checkbox"
                    :checked="form.usuario_ids.includes(u.id)"
                    @click.stop="toggleUsuario(u.id)"
                    class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-200 cursor-pointer"
                  />
                </td>
                <td class="px-6 py-4 font-semibold text-gray-900">{{ u.name }}</td>
                <td class="px-6 py-4 text-gray-600">{{ u.email }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="form.errors?.usuario_ids" class="px-6 py-3 text-sm text-red-600 border-t border-gray-100">
          {{ form.errors.usuario_ids }}
        </div>
      </div>

      <!-- Info -->
      <div class="mt-6 bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <strong>¿Cómo funciona?</strong><br>
        Un <em>coordinador_equipo</em> verá en el listado de Órdenes únicamente las OTs de los usuarios que le asignes aquí.
        Si no se le asigna ningún usuario, no verá ninguna orden.<br>
        Para ampliar su visibilidad a todo el centro, usa el rol <strong>coordinador</strong> en su lugar.
      </div>
    </div>
  </div>
</template>
