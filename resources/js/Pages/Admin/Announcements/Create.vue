<script setup>
import { useForm } from '@inertiajs/vue3'
import Form from './Form.vue'

const props = defineProps({
  maxUploadMb: Number,
})

const form = useForm({
  title: '',
  body: '',
  video_type: 'upload',
  video_url: '',
  video_file: null,
  starts_at: '',
  ends_at: '',
  is_active: true,
})

function submit() {
  form.post(route('admin.announcements.store'), {
    forceFormData: true,
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-blue-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
      <div class="bg-white rounded-2xl shadow-lg border-2 border-gray-100 p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold text-gray-900 mb-2">Nuevo anuncio</h1>
        <p class="text-gray-500 mb-6">Define un video y texto para mostrar en login/dashboard autenticado.</p>

        <Form :form="form" :max-upload-mb="props.maxUploadMb" @submit="submit" />
      </div>
    </div>
  </div>
</template>
