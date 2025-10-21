<script setup>
import { computed } from 'vue'

const props = defineProps({
  archivo: {
    type: Object,
    required: true
  },
  showDownload: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['close'])

const fileName = computed(() => props.archivo?.nombre_original || props.archivo?.path?.split('/').pop() || 'Archivo')
const fileSize = computed(() => props.archivo?.size ? (props.archivo.size / 1024).toFixed(0) : '0')
const filePath = computed(() => route('archivos.view', props.archivo?.id))
const downloadPath = computed(() => route('archivos.download', props.archivo?.id))

const isImage = computed(() => props.archivo?.mime?.startsWith('image/'))
const isPDF = computed(() => props.archivo?.mime === 'application/pdf')
const isPreviewable = computed(() => isImage.value || isPDF.value)
</script>

<template>
  <div v-if="isPreviewable" 
       @click="emit('close')"
       class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4">
    <div @click.stop class="relative bg-white rounded-lg max-w-6xl max-h-[90vh] w-full overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between p-4 border-b bg-gray-50">
        <div class="flex items-center gap-3">
          <h3 class="text-lg font-semibold">{{ fileName }}</h3>
          <span class="text-sm text-gray-500">{{ fileSize }} KB</span>
        </div>
        <div class="flex items-center gap-2">
          <a v-if="showDownload"
             :href="downloadPath" 
             class="px-3 py-2 rounded text-sm bg-blue-600 text-white hover:bg-blue-700">
            Descargar
          </a>
          <button @click="emit('close')"
                  class="p-2 hover:bg-gray-200 rounded-full transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Contenido -->
      <div class="overflow-auto max-h-[calc(90vh-80px)] bg-gray-100 flex items-center justify-center p-4">
        <!-- Imagen -->
        <img v-if="isImage" 
             :src="filePath" 
             :alt="fileName"
             class="max-w-full h-auto rounded shadow-lg" />

        <!-- PDF -->
        <iframe v-else-if="isPDF"
                :src="filePath"
                class="w-full h-[calc(90vh-100px)] rounded border-0"
                frameborder="0">
        </iframe>
      </div>
    </div>
  </div>
</template>
