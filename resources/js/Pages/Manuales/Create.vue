<script setup>
import { computed, ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  roles: { type: Array, default: () => [] },
  maxUploadMb: { type: Number, default: 20 },
})

const form = useForm({
  titulo: '',
  descripcion: '',
  archivo_pdf: null,
  visible_para_todos: false,
  roles: [],
})

const localFileError = ref('')
const disableSubmit = computed(() => form.processing || !!localFileError.value)

watch(() => form.visible_para_todos, (visible) => {
  if (visible) {
    form.roles = []
  }
})

function onFileChange(event) {
  const file = event.target.files?.[0] || null
  localFileError.value = ''

  if (file) {
    const maxBytes = Number(props.maxUploadMb || 20) * 1024 * 1024
    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')

    if (!isPdf) {
      form.archivo_pdf = null
      event.target.value = ''
      localFileError.value = 'El archivo debe ser PDF.'
      return
    }

    if (file.size > maxBytes) {
      form.archivo_pdf = null
      event.target.value = ''
      localFileError.value = `El archivo supera el limite de ${props.maxUploadMb} MB.`
      return
    }
  }

  form.archivo_pdf = file
}

function submit() {
  form.post(route('manuales.store'), {
    forceFormData: true,
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-sky-50 to-emerald-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
          <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">Subir manual</h1>
            <p class="mt-2 text-gray-500">Carga un PDF y define quienes podran verlo.</p>
          </div>
          <Link
            :href="route('manuales.index')"
            class="inline-flex items-center justify-center px-4 py-2 rounded-lg border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50"
          >
            Volver
          </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Titulo *</label>
            <input
              v-model="form.titulo"
              type="text"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
              placeholder="Ej. Manual de captura de solicitudes"
            >
            <InputError class="mt-2" :message="form.errors.titulo" />
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Descripcion</label>
            <textarea
              v-model="form.descripcion"
              rows="4"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
              placeholder="Descripcion opcional del contenido del manual"
            />
            <InputError class="mt-2" :message="form.errors.descripcion" />
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Archivo PDF *</label>
            <input
              type="file"
              accept="application/pdf,.pdf"
              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl"
              @change="onFileChange"
            >
            <p class="mt-2 text-sm text-gray-500">Formato: PDF. Maximo: {{ maxUploadMb }} MB.</p>
            <InputError class="mt-2" :message="form.errors.archivo_pdf" />
            <p v-if="localFileError" class="mt-2 text-sm text-red-600">{{ localFileError }}</p>
          </div>

          <div class="rounded-2xl border-2 border-gray-100 p-4 sm:p-5 bg-gray-50">
            <label class="flex items-start gap-3 cursor-pointer">
              <input
                v-model="form.visible_para_todos"
                type="checkbox"
                class="mt-1 rounded border-gray-300 text-sky-700 shadow-sm focus:ring-sky-500"
              >
              <span>
                <span class="block font-semibold text-gray-800">Visible para todos</span>
                <span class="block text-sm text-gray-500">Si esta activo, cualquier usuario autenticado podra ver este manual.</span>
              </span>
            </label>
            <InputError class="mt-2" :message="form.errors.visible_para_todos" />
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Roles aplicables</label>
            <div
              :class="[
                'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 rounded-2xl border-2 border-gray-100 p-4',
                form.visible_para_todos ? 'bg-gray-100 opacity-70' : 'bg-white'
              ]"
            >
              <label
                v-for="role in roles"
                :key="role.name"
                class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-3"
              >
                <input
                  v-model="form.roles"
                  :disabled="form.visible_para_todos"
                  type="checkbox"
                  :value="role.name"
                  class="rounded border-gray-300 text-sky-700 shadow-sm focus:ring-sky-500 disabled:opacity-40"
                >
                <span class="text-sm font-medium text-gray-700 break-all">{{ role.name }}</span>
              </label>
            </div>
            <p class="mt-2 text-sm text-gray-500">Selecciona uno o varios roles, o marca visible para todos.</p>
            <InputError class="mt-2" :message="form.errors.roles" />
            <InputError class="mt-2" :message="form.errors['roles.0']" />
          </div>

          <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
            <Link
              :href="route('manuales.index')"
              class="inline-flex items-center justify-center px-5 py-3 rounded-xl border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              :disabled="disableSubmit"
              class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-sky-700 text-white font-semibold hover:bg-sky-800 disabled:opacity-60"
            >
              {{ form.processing ? 'Subiendo...' : 'Guardar manual' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
