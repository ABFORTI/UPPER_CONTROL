<script setup>
import { computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  centros: Array,
  selectedCentroId: Number,
  features: Array,
  urls: Object,
})

const centroId = computed(() => Number(props.selectedCentroId || 0))

const form = useForm({
  enabled_features: (props.features || []).filter(f => !!f.enabled).map(f => f.id),
})

watch(
  () => props.features,
  (fs) => {
    form.enabled_features = (fs || []).filter(f => !!f.enabled).map(f => f.id)
  },
  { deep: true }
)

function onCentroChange(e) {
  const id = Number(e?.target?.value || 0)
  router.get(route('admin.centros.features.index'), { centro_trabajo_id: id }, { replace: true })
}

function save() {
  if (!centroId.value) return
  form.put(route('admin.centros.features.update', centroId.value), {
    preserveScroll: true,
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto">
      <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-2xl shadow-xl p-6 sm:p-8 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-extrabold text-white mb-2">⚙️ Funcionalidades por Centro</h1>
            <p class="text-indigo-100">Activa o desactiva accesos por centro de trabajo</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Centro de trabajo</label>
        <select :value="centroId" @change="onCentroChange"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200">
          <option v-for="c in centros" :key="c.id" :value="c.id">
            {{ (c.prefijo ? (c.prefijo + ' — ') : '') + c.nombre }}
          </option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
          <div class="text-sm text-gray-600">Selecciona las funcionalidades habilitadas</div>
          <button @click="save" :disabled="form.processing"
            class="px-6 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold rounded-xl hover:shadow-xl transition-all duration-200 disabled:opacity-60">
            Guardar
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700 uppercase tracking-wider">
              <tr>
                <th class="px-6 py-3 text-left font-bold">Habilitada</th>
                <th class="px-6 py-3 text-left font-bold">Nombre</th>
                <th class="px-6 py-3 text-left font-bold">Key</th>
                <th class="px-6 py-3 text-left font-bold">Descripción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="f in features" :key="f.id" class="hover:bg-indigo-50 transition-colors duration-150">
                <td class="px-6 py-4">
                  <input type="checkbox" :value="f.id" v-model="form.enabled_features"
                    class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-200" />
                </td>
                <td class="px-6 py-4 font-semibold text-gray-900">{{ f.nombre }}</td>
                <td class="px-6 py-4"><span class="inline-flex px-3 py-1 bg-slate-100 text-slate-700 rounded-full font-medium">{{ f.key }}</span></td>
                <td class="px-6 py-4 text-gray-600">{{ f.descripcion || '—' }}</td>
              </tr>
              <tr v-if="!features || features.length === 0">
                <td colspan="4" class="px-6 py-12 text-center text-gray-500">No hay funcionalidades registradas en el catálogo.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="form.errors?.enabled_features" class="px-6 py-3 text-sm text-red-600 border-t border-gray-100">
          {{ form.errors.enabled_features }}
        </div>
      </div>
    </div>
  </div>
</template>
