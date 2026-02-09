<template>
  <div class="bg-white rounded-2xl shadow-lg border-2 border-blue-100 overflow-hidden hover:shadow-xl transition-shadow duration-300 dark:bg-slate-900/75 dark:border-blue-500/30">
    <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4 dark:from-blue-500 dark:to-cyan-500">
      <h2 class="text-xl font-bold text-white flex items-center gap-2">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
        </svg>
        Cargar Datos desde Excel
      </h2>
    </div>

    <div class="p-6 space-y-4">
      <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 dark:bg-blue-900/30 dark:border-blue-500/40">
        <p class="text-sm text-blue-800 dark:text-blue-200">
          💡 <strong>¿Tienes los datos en Excel?</strong> Súbelo aquí y precargaremos automáticamente los campos del formulario.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <label class="flex-1 relative">
          <input 
            type="file" 
            @change="handleFileSelect"
            accept=".xlsx,.xls"
            class="hidden"
            ref="fileInput"
          />
          <button
            type="button"
            @click="$refs.fileInput.click()"
            class="w-full px-6 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-cyan-700 transition-all duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg"
            :disabled="cargando"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <span v-if="!cargando">{{ archivoSeleccionado ? '✓ Cambiar Archivo' : 'Seleccionar Archivo Excel' }}</span>
            <span v-else>Procesando...</span>
          </button>
        </label>
      </div>

      <div v-if="archivoSeleccionado" class="bg-green-50 border-2 border-green-200 rounded-xl p-4 dark:bg-green-900/30 dark:border-green-500/40">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <div>
              <p class="font-semibold text-green-800 dark:text-green-200">{{ archivoSeleccionado.name }}</p>
              <p class="text-xs text-green-600 dark:text-green-300">{{ formatBytes(archivoSeleccionado.size) }}</p>
            </div>
          </div>
          <button
            type="button"
            @click="limpiarArchivo"
            class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-colors dark:hover:bg-red-900/30"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div v-if="archivoProcesado?.ruta" class="mt-3 text-sm">
          <a
            :href="archivoProcesado.ruta"
            class="text-blue-700 hover:underline font-medium dark:text-blue-300"
            target="_blank"
            rel="noopener"
          >
            Descargar archivo guardado
          </a>
        </div>
      </div>

      <div v-if="warnings.length > 0" class="bg-yellow-50 border-2 border-yellow-300 rounded-xl p-4 dark:bg-yellow-900/30 dark:border-yellow-500/40">
        <div class="flex items-start gap-3">
          <svg class="w-6 h-6 text-yellow-600 flex-shrink-0 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          <div class="flex-1">
            <h4 class="font-semibold text-yellow-800 mb-2 dark:text-yellow-200">Advertencias:</h4>
            <ul class="text-sm text-yellow-700 space-y-1 dark:text-yellow-300">
              <li v-for="(w, i) in warnings" :key="i">• {{ w }}</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-slate-400">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>Máximo 10MB • Formatos: .xlsx, .xls</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const emit = defineEmits(['prefill-loaded'])

const fileInput = ref(null)
const archivoSeleccionado = ref(null)
const archivoProcesado = ref(null)
const cargando = ref(false)
const warnings = ref([])

function buildWebUrl(path) {
  // Cuando Laravel NO está como DocumentRoot (p.ej. /upper-control/public),
  // las rutas viven bajo ese prefijo. Si sí lo está, el prefijo será vacío.
  const pathname = window.location.pathname || ''
  const idx = pathname.indexOf('/public/')
  const base = idx >= 0 ? pathname.slice(0, idx) + '/public' : ''
  return base + path
}

function handleFileSelect(event) {
  const file = event.target.files[0]
  if (!file) return

  // Validar tipo
  const validTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel'
  ]
  if (!validTypes.includes(file.type)) {
    alert('Error: Solo se permiten archivos Excel (.xlsx, .xls)')
    return
  }

  // Validar tamaño (10MB)
  if (file.size > 10 * 1024 * 1024) {
    alert('Error: El archivo no puede superar los 10MB')
    return
  }

  archivoSeleccionado.value = file
  archivoProcesado.value = null
  cargarYParsear()
}

async function cargarYParsear() {
  if (!archivoSeleccionado.value) return

  cargando.value = true
  warnings.value = []

  const formData = new FormData()
  formData.append('archivo', archivoSeleccionado.value)

  try {
    // Usar router de Inertia para obtener el CSRF token
    const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content 
      || router.page?.props?.csrf_token

    // Fallback adicional: Laravel acepta _token en el body
    if (csrfToken) {
      formData.append('_token', csrfToken)
    }

    const url = buildWebUrl('/solicitudes/parse-excel')

    const { data } = await window.axios.post(url, formData, {
      headers: {
        'Accept': 'application/json',
      },
    })

    if (!data?.success) {
      throw new Error(data?.message || 'Error desconocido')
    }

    archivoProcesado.value = data.archivo || null
    warnings.value = []

    emit('prefill-loaded', {
      prefill: data.prefill || {},
      archivo: archivoProcesado.value,
      servicios: data.servicios || [],
      is_multi: !!data.is_multi,
      warnings: [],
    })

    console.log('✅ Excel procesado exitosamente:', data)

  } catch (error) {
    console.error('❌ Error al cargar el archivo:', error)

    const status = error?.response?.status
    const payload = error?.response?.data

    if (status === 422 && payload?.errors) {
      const messages = Object.values(payload.errors).flat().filter(Boolean)
      alert('Errores de validación:\n\n' + messages.join('\n'))
      return
    }

    alert('Error al cargar el archivo: ' + (payload?.message || error.message || 'Error desconocido'))
  } finally {
    cargando.value = false
  }
}

function limpiarArchivo() {
  archivoSeleccionado.value = null
  archivoProcesado.value = null
  warnings.value = []
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function formatBytes(bytes) {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}
</script>
