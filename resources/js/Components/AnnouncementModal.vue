<script setup>
import Modal from '@/Components/Modal.vue'

defineProps({
  show: { type: Boolean, default: false },
  announcement: { type: Object, default: null },
  processing: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'dismiss'])
</script>

<template>
  <Modal :show="show" max-width="2xl" :closeable="false">
    <div class="p-6">
      <h2 class="text-xl font-bold text-gray-900 mb-2">{{ announcement?.title }}</h2>
      <p v-if="announcement?.body" class="text-gray-600 mb-4 whitespace-pre-line">{{ announcement.body }}</p>

      <div class="rounded-xl bg-black overflow-hidden mb-6">
        <video
          v-if="announcement?.video_type === 'upload' && announcement?.video_src"
          :src="announcement.video_src"
          controls
          class="w-full max-h-[60vh]"
        />

        <iframe
          v-else-if="announcement?.video_src"
          :src="announcement.video_src"
          class="w-full h-[380px]"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
        />

        <div v-else class="p-6 text-white/80 text-sm">No se encontró una fuente de video válida.</div>
      </div>

      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 disabled:opacity-60"
          :disabled="processing"
          @click="emit('close')"
        >
          Cerrar
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 disabled:opacity-60"
          :disabled="processing"
          @click="emit('dismiss')"
        >
          No volver a mostrar
        </button>
      </div>
    </div>
  </Modal>
</template>
