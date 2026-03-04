<script setup>
import { computed, ref } from 'vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  form: { type: Object, required: true },
  isEdit: { type: Boolean, default: false },
  maxUploadMb: { type: Number, default: 200 },
  currentVideoSrc: { type: String, default: null },
  roles: { type: Array, default: () => [] },
  centros: { type: Array, default: () => [] },
})

const emit = defineEmits(['submit'])

const isUpload = computed(() => props.form.video_type === 'upload')
const localVideoError = ref('')
const disableSubmit = computed(() => props.form.processing || (!!localVideoError.value && isUpload.value))

function onFileChange(event) {
  const file = event.target.files?.[0] || null
  localVideoError.value = ''

  if (file) {
    const maxBytes = Number(props.maxUploadMb || 200) * 1024 * 1024
    if (file.size > maxBytes) {
      props.form.video_file = null
      event.target.value = ''
      localVideoError.value = `El archivo supera el límite de ${props.maxUploadMb} MB.`
      return
    }
  }

  props.form.video_file = file
}
</script>

<template>
  <form @submit.prevent="emit('submit')" class="space-y-6">
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-2">Título *</label>
      <input
        v-model="form.title"
        type="text"
        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400"
        placeholder="Ej. Actualización de proceso de facturación"
      >
      <InputError class="mt-2" :message="form.errors.title" />
    </div>

    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-2">Descripción</label>
      <textarea
        v-model="form.body"
        rows="4"
        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400"
        placeholder="Texto que verá el usuario al abrir el video"
      />
      <InputError class="mt-2" :message="form.errors.body" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de video *</label>
        <select
          v-model="form.video_type"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400"
        >
          <option value="upload">Upload</option>
          <option value="youtube">YouTube</option>
          <option value="vimeo">Vimeo</option>
          <option value="url">URL</option>
        </select>
        <InputError class="mt-2" :message="form.errors.video_type" />
      </div>

      <div class="flex items-end">
        <label class="inline-flex items-center gap-3 px-4 py-3 border-2 border-gray-200 rounded-xl w-full">
          <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm">
          <span class="font-medium text-gray-700">Activo</span>
        </label>
      </div>
    </div>

    <div v-if="isUpload">
      <label class="block text-sm font-semibold text-gray-700 mb-2">
        Archivo de video {{ isEdit ? '(opcional para reemplazar)' : '*' }}
      </label>
      <input
        type="file"
        accept="video/mp4,video/webm,video/ogg"
        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl"
        @change="onFileChange"
      >
      <p class="mt-2 text-sm text-gray-500">Formatos: mp4/webm/ogg. Máximo: {{ maxUploadMb }} MB.</p>
      <InputError class="mt-2" :message="form.errors.video_file" />
      <p v-if="localVideoError" class="mt-2 text-sm text-red-600">{{ localVideoError }}</p>

      <div v-if="isEdit && currentVideoSrc" class="mt-4 rounded-xl border border-gray-200 p-3 bg-gray-50">
        <div class="text-sm font-semibold text-gray-700 mb-2">Video actual</div>
        <video controls :src="currentVideoSrc" class="w-full rounded-lg max-h-72" />
      </div>
    </div>

    <div v-else>
      <label class="block text-sm font-semibold text-gray-700 mb-2">URL del video *</label>
      <input
        v-model="form.video_url"
        type="url"
        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400"
        placeholder="https://..."
      >
      <InputError class="mt-2" :message="form.errors.video_url" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Inicio de vigencia</label>
        <input
          v-model="form.starts_at"
          type="datetime-local"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400"
        >
        <InputError class="mt-2" :message="form.errors.starts_at" />
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Fin de vigencia</label>
        <input
          v-model="form.ends_at"
          type="datetime-local"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400"
        >
        <InputError class="mt-2" :message="form.errors.ends_at" />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Roles objetivo</label>
        <select
          v-model="form.target_roles"
          multiple
          size="6"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400"
        >
          <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
        </select>
        <p class="mt-2 text-xs text-gray-500">Si no seleccionas roles, se mostrará para cualquier rol.</p>
        <InputError class="mt-2" :message="form.errors.target_roles" />
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Centros objetivo</label>
        <select
          v-model="form.target_centros"
          multiple
          size="6"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400"
        >
          <option v-for="centro in centros" :key="centro.id" :value="centro.id">{{ centro.nombre }}</option>
        </select>
        <p class="mt-2 text-xs text-gray-500">Si no seleccionas centros, se mostrará para todos los centros.</p>
        <InputError class="mt-2" :message="form.errors.target_centros" />
      </div>
    </div>

    <div class="flex justify-end gap-3">
      <a :href="route('admin.announcements.index')" class="px-5 py-3 rounded-xl border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50">Cancelar</a>
      <button
        type="submit"
        :disabled="disableSubmit"
        class="px-6 py-3 bg-indigo-600 text-white rounded-xl font-semibold hover:bg-indigo-700 disabled:opacity-60"
      >
        {{ form.processing ? 'Guardando...' : (isEdit ? 'Actualizar' : 'Crear') }}
      </button>
    </div>
  </form>
</template>
