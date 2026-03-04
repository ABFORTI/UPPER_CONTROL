<script setup>
import { useForm } from '@inertiajs/vue3'
import Form from './Form.vue'

const props = defineProps({
  announcement: { type: Object, required: true },
  maxUploadMb: Number,
  roles: { type: Array, default: () => [] },
  centros: { type: Array, default: () => [] },
})

const form = useForm({
  title: props.announcement.title || '',
  body: props.announcement.body || '',
  video_type: props.announcement.video_type || 'upload',
  video_url: props.announcement.video_url || '',
  video_file: null,
  starts_at: props.announcement.starts_at || '',
  ends_at: props.announcement.ends_at || '',
  is_active: !!props.announcement.is_active,
  target_roles: props.announcement.target_roles || [],
  target_centros: props.announcement.target_centros || [],
})

function submit() {
  form.transform((data) => ({ ...data, _method: 'patch' })).post(route('admin.announcements.update', props.announcement.id), {
    forceFormData: true,
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-blue-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold text-gray-900 mb-2">Editar anuncio</h1>
        <p class="text-gray-500 mb-6">Actualiza vigencia, estado o contenido del video.</p>

        <Form
          :form="form"
          :is-edit="true"
          :max-upload-mb="props.maxUploadMb"
          :current-video-src="props.announcement.video_src"
          :roles="props.roles"
          :centros="props.centros"
          @submit="submit"
        />
      </div>
    </div>
  </div>
</template>
