<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  ordenId: {
    type: Number,
    required: true
  },
  archivoExistente: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['prefill-loaded'])

// Estado del componente
const isUploading = ref(false)
const file = ref(null)
const fileInput = ref(null)
const error = ref(null)
const warnings = ref([])
const archivo = ref(props.archivoExistente)

// Manejar selección de archivo
function handleFileSelect(event) {
  const selectedFile = event.target.files[0]
  
  if (!selectedFile) return
  
  // Validar extensión
  const extension = selectedFile.name.split('.').pop().toLowerCase()
  if (!['xlsx', 'xls'].includes(extension)) {
    error.value = 'El archivo debe ser formato .xlsx o .xls'
    fileInput.value.value = null
    return
  }
  
  // Validar tamaño (10MB)
  if (selectedFile.size > 10 * 1024 * 1024) {
    error.value = 'El archivo no puede superar 10MB'
    fileInput.value.value = null
    return
  }
  
  file.value = selectedFile
  error.value = null
}

// Cargar y precargar formulario
async function cargarYPrecargar() {
  if (!file.value) {
    error.value = 'Seleccione un archivo Excel primero'
    return
  }
  
  isUploading.value = true
  error.value = null
  warnings.value = []
  
  const formData = new FormData()
  formData.append('excel', file.value)
  
  try {
    const response = await fetch(route('ordenes.archivo.upload', props.ordenId), {
      method: 'POST',
      body: formData,
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      throw new Error(data.error || 'Error al procesar el archivo')
    }
    
    if (data.success) {
      // Guardar info del archivo
      archivo.value = data.archivo
      
      // Emitir evento con datos para precargar
      emit('prefill-loaded', {
        prefill: data.prefill,
        warnings: data.warnings
      })
      
      // Mostrar warnings si existen
      if (data.warnings && data.warnings.length > 0) {
        warnings.value = data.warnings
      }
      
      // Limpiar input
      file.value = null
      if (fileInput.value) fileInput.value.value = null
    }
    
  } catch (err) {
    error.value = err.message || 'Error al subir archivo'
  } finally {
    isUploading.value = false
  }
}

// Descargar archivo
function descargarArchivo() {
  if (archivo.value && archivo.value.url_descarga) {
    window.location.href = archivo.value.url_descarga
  }
}

// Resetear
function reset() {
  file.value = null
  error.value = null
  warnings.value = []
  if (fileInput.value) fileInput.value.value = null
}

defineExpose({ reset })
</script>

<template>
  <div class="bg-white rounded-2xl shadow-lg border-2 border-purple-100 overflow-hidden hover:shadow-xl transition-shadow duration-300 dark:bg-slate-900/75 dark:border-purple-500/30">
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 dark:from-purple-500 dark:to-indigo-500">
      <h2 class="text-xl font-bold text-white flex items-center gap-2">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
        </svg>
        Cargar Archivo Excel
      </h2>
      <p class="text-purple-100 text-sm mt-1">Sube un Excel para precargar automáticamente el formulario</p>
    </div>
    
    <div class="p-6 space-y-4">
      
      <!-- Info -->
      <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 dark:bg-blue-900/25 dark:border-blue-500/40">
        <div class="flex gap-3">
          <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5 dark:text-blue-300" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
          <div class="text-sm text-blue-800 dark:text-blue-200">
            <p class="font-semibold mb-1">📊 ¿Cómo funciona?</p>
            <p>Sube un archivo Excel (.xlsx) con los datos de la orden. El sistema leerá automáticamente información como Centro de Trabajo, Centro de Costos, Marca, Servicio, etc., y precargará los campos del formulario.</p>
          </div>
        </div>
      </div>
      
      <!-- Archivo existente -->
      <div v-if="archivo" class="bg-green-50 border-2 border-green-200 rounded-xl p-4 dark:bg-green-900/20 dark:border-green-500/40">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <div>
              <p class="font-bold text-green-900 dark:text-green-100">{{ archivo.nombre }}</p>
              <p class="text-xs text-green-700 dark:text-green-300">
                Subido el {{ archivo.fecha_subida }} por {{ archivo.subido_por }}
              </p>
            </div>
          </div>
          <button @click="descargarArchivo" type="button"
                  class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-colors duration-200 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Descargar
          </button>
        </div>
      </div>
      
      <!-- Input de archivo -->
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-3 dark:text-slate-200">
          Seleccionar archivo Excel
          <span class="text-gray-400 font-normal ml-1 dark:text-slate-400">(.xlsx, máx. 10MB)</span>
        </label>
        <div class="relative">
          <input 
            ref="fileInput"
            type="file" 
            accept=".xlsx,.xls"
            @change="handleFileSelect"
            :disabled="isUploading"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-400 transition-all duration-200 bg-white text-gray-800 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100"
          />
        </div>
        <p v-if="file" class="mt-2 text-sm text-gray-600 dark:text-slate-400">
          ✓ {{ file.name }} ({{ (file.size / 1024).toFixed(2) }} KB)
        </p>
      </div>
      
      <!-- Error -->
      <div v-if="error" class="bg-red-50 border-2 border-red-200 rounded-xl p-4 dark:bg-red-900/30 dark:border-red-500/40">
        <div class="flex gap-2">
          <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
          </svg>
          <p class="text-sm text-red-800 font-semibold dark:text-red-200">{{ error }}</p>
        </div>
      </div>
      
      <!-- Warnings -->
      <div v-if="warnings.length > 0" class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4 dark:bg-yellow-900/20 dark:border-yellow-500/40">
        <div class="flex gap-2">
          <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
          <div class="flex-1">
            <p class="text-sm font-semibold text-yellow-800 mb-1 dark:text-yellow-200">Advertencias:</p>
            <ul class="text-sm text-yellow-700 space-y-1 dark:text-yellow-200">
              <li v-for="(warning, index) in warnings" :key="index">• {{ warning }}</li>
            </ul>
          </div>
        </div>
      </div>
      
      <!-- Botón cargar -->
      <button 
        @click="cargarYPrecargar" 
        type="button"
        :disabled="!file || isUploading"
        class="w-full px-6 py-4 rounded-xl font-bold text-white transition-all duration-200 transform hover:scale-[1.02] shadow-lg disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
        :class="isUploading 
          ? 'bg-gray-400 cursor-wait' 
          : 'bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700'">
        <span v-if="!isUploading" class="flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
          </svg>
          Cargar y Precargar Formulario
        </span>
        <span v-else class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Procesando...
        </span>
      </button>
    </div>
  </div>
</template>
