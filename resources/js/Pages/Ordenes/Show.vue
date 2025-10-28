<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import FilePreview from '@/Components/FilePreview.vue'

const props = defineProps({
  orden:       { type: Object, required: true },
  urls:        { type: Object, required: true },   // { asignar_tl, avances_store, evidencias_store, evidencias_destroy, calidad_validar, calidad_rechazar, cliente_autorizar, facturar, pdf }
  can:         { type: Object, default: () => ({}) }, // { reportarAvance:bool, asignar_tl:bool }
  teamLeaders: { type: Array,  default: () => [] },
  cotizacion:  { type: Object, default: () => ({}) },
  precios_tamano: { type: Object, default: () => null },
  unidades: { type: Object, default: () => ({ planeado:0, completado:0, faltante:0, total:0 }) },
})

// colecciones “a prueba de null”
const items   = computed(() => props.orden?.items   ?? [])
const avances = computed(() => props.orden?.avances ?? [])

// Utilidad: formatear fecha/hora a local (CDMX) desde ISO/Date
function fmtDate (input) {
  if (!input) return '—'
  try {
    const d = (typeof input === 'string' || typeof input === 'number') ? new Date(input) : input
    if (isNaN(d?.getTime?.())) return String(input)
    // es-MX: 21 oct 2025 14:35 -> estilo compacto y legible
    return new Intl.DateTimeFormat('es-MX', {
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit'
    }).format(d)
  } catch (e) {
    return String(input)
  }
}

// Devuelve true si el comentario viene marcado como corregido
function isCorregidoComentario(c) {
  return typeof c === 'string' && c.startsWith('[CORREGIDO]')
}

// Extrae el comentario final (evita mostrar el resumen concatenado largo)
function extractComentarioFinal(c) {
  if (!c || typeof c !== 'string') return c
  if (!isCorregidoComentario(c)) return c
  // eliminar prefijo y tomar el último segmento después de ' | '
  const withoutTag = c.replace(/^\[CORREGIDO\]\s*/,'')
  const parts = withoutTag.split(' | ')
  return parts.length ? parts[parts.length-1].trim() : withoutTag
}

// Devuelve true si el comentario viene de un rechazo de calidad
function isRechazoComentario(c) {
  return typeof c === 'string' && c.startsWith('[RECHAZO CALIDAD]')
}

// Extrae el texto del rechazo (quita prefijo y la parte de Acciones si existe)
function extractRechazoComentario(c) {
  if (!c || typeof c !== 'string') return c
  if (!isRechazoComentario(c)) return c
  const withoutTag = c.replace(/^\[RECHAZO CALIDAD\]\s*/,'')
  // separar por ' | Acciones: '
  const parts = withoutTag.split(' | Acciones: ')
  return parts[0].trim()
}

// Devuelve true si el comentario es de tipo FALTANTES
function isFaltantesComentario(c) {
  return typeof c === 'string' && c.startsWith('[FALTANTES]')
}

// ----- Calidad / Cliente -----
const obs = ref('')
const acciones_correctivas = ref('')
const showRechazoModal = ref(false)
const validarCalidad   = () => router.post(props.urls.calidad_validar)
function openRechazo() {
  // debug: confirmar que el handler se ejecuta en el navegador
  console.log('openRechazo called for OT #' + (props.orden?.id || 'unknown'))
  // limpiar valores y abrir modal
  obs.value = ''
  acciones_correctivas.value = ''
  showRechazoModal.value = true
}
function rechazarCalidad() {
  // Enviar observaciones y acciones desde el modal
  console.log('rechazarCalidad sending', { observaciones: obs.value, acciones_correctivas: acciones_correctivas.value })
  router.post(props.urls.calidad_rechazar, { observaciones: obs.value, acciones_correctivas: acciones_correctivas.value })
}
const autorizarCliente = () => router.post(props.urls.cliente_autorizar)

// ----- Asignar TL -----
const tlForm = useForm({ team_leader_id: props.orden?.team_leader_id ?? null })
function asignarTL () { tlForm.patch(props.urls.asignar_tl, { preserveScroll: true }) }

// ----- Registrar avances -----
const avForm = useForm({
  items: items.value.map(i => ({ id_item: i.id, cantidad: '' })), // iniciar vacío para evitar el '0'
  comentario: ''
})
// Faltantes
const faltForm = useForm({
  items: items.value.map(i => ({ id_item: i.id, faltantes: '' })),
  nota: ''
})
const hasFaltantes = computed(() => {
  try {
    return (faltForm.items || []).some((x, idx) => {
      const it = items.value[idx]
      const max = restante(it)
      const val = Number(x?.faltantes || 0)
      return val > 0 && val <= max
    })
  } catch { return false }
})
const restante = (it) => Math.max(0, (it?.cantidad_planeada ?? 0) - (it?.cantidad_real ?? 0))
const hasAvance = computed(() => {
  try {
    return (avForm.items || []).some((x, idx) => {
      const it = items.value[idx]
      const max = restante(it)
      const val = Number(x?.cantidad || 0)
      return val > 0 && val <= max
    })
  } catch { return false }
})
function registrarAvance () {
  avForm.clearErrors('items')
  avForm.items = avForm.items
    .map((x, idx) => {
      const it = items.value[idx]
      const max = restante(it)
      return { ...x, cantidad: Math.max(0, Math.min(Number(x.cantidad || 0), max)) }
    })
    .filter(x => x.cantidad > 0)

  if (!avForm.items.length) {
    avForm.setError('items', 'Ingresa al menos una cantidad mayor a 0 para registrar avance.')
    return
  }
  avForm.post(props.urls.avances_store, {
    preserveScroll: true,
    // Al completar, recargar la data desde el servidor para reflejar cantidades reales actualizadas
    onSuccess: () => {
      // resetear inputs
      avForm.reset('comentario')
      avForm.items = (items.value || []).map(i => ({ id_item: i.id, cantidad: '' }))
      avForm.clearErrors('items')
      // recargar únicamente props necesarias para performance
  // También recargar 'unidades' para que el panel de desglose use el TOTAL vigente
  router.reload({ only: ['orden','cotizacion','unidades'], preserveScroll: true })
    },
  })
}

function aplicarFaltantes(){
  faltForm.clearErrors('items')
  // Limitar faltantes al restante
  faltForm.items = faltForm.items
    .map((x, idx) => {
      const it = items.value[idx]
      const max = restante(it)
      const val = Math.max(0, Math.min(Number(x.faltantes || 0), max))
      return { id_item: it.id, faltantes: val }
    })
    .filter(x => x.faltantes > 0)

  if (!faltForm.items.length) {
    faltForm.setError('items', 'Ingresa al menos un faltante mayor a 0 para aplicar cambios.')
    return
  }
  faltForm.post(props.urls.faltantes_store, {
    preserveScroll: true,
    onSuccess: () => {
      faltForm.reset('nota')
      faltForm.items = (items.value || []).map(i => ({ id_item: i.id, faltantes: '' }))
      faltForm.clearErrors('items')
  // También recargar 'unidades' para que el panel de desglose use el TOTAL vigente
  router.reload({ only: ['orden','cotizacion','unidades'], preserveScroll: true })
    }
  })
}

// ----- Evidencias (archivos por avance / ítem) -----
const evForm = useForm({
  id_item: null,
  evidencias: []
})
function onPickEvidencias(e){ evForm.evidencias = Array.from(e.target.files || []) }
function subirEvidencias(){
  if (!evForm.evidencias.length) return
  const fd = new FormData()
  if (evForm.id_item) fd.append('id_item', evForm.id_item)
  evForm.evidencias.forEach(f => fd.append('evidencias[]', f))
  evForm.post(props.urls.evidencias_store, { forceFormData:true, preserveScroll:true })
}
const vistaEvidencias = computed(() => props.orden?.evidencias ?? [])
function borrarEvidencia(id){
  // urls.evidencias_destroy viene como .../evidencias/0 -> reemplazamos el 0 por el id real
  const url = props.urls.evidencias_destroy.replace(/\/0$/, '/'+id)
  router.delete(url, { preserveScroll:true })
}

// ----- Preview de archivos de solicitud -----
const archivoPreview = ref(null)
const canPreview = (mime) => mime?.startsWith('image/') || mime === 'application/pdf'
const openPreview = (archivo) => { archivoPreview.value = archivo }
const closePreview = () => { archivoPreview.value = null }

// ----- Definir tamaños (flujo diferido) -----
const tamanosForm = ref({ chico: 0, mediano: 0, grande: 0, jumbo: 0 })
// Objetivo dinámico para el desglose: usa el total vigente de la OT (suma de cantidades planeadas tras faltantes)
// fallback al total aprobado de la solicitud si no está disponible
const totalAprobado = computed(() => Number((props.unidades?.total ?? props.orden?.solicitud?.cantidad) || 0))
const sumaTamanos = computed(() =>
  Number(tamanosForm.value.chico||0) + Number(tamanosForm.value.mediano||0) +
  Number(tamanosForm.value.grande||0) + Number(tamanosForm.value.jumbo||0)
)
const tamanosValid = computed(() => totalAprobado.value > 0 && sumaTamanos.value === totalAprobado.value)
const faltanRaw = computed(() => totalAprobado.value - sumaTamanos.value)
const faltanCalc = computed(() => Math.max(0, faltanRaw.value))

// Precios por tamaño y previsualización de totales
const ivaRate = computed(() => Number(props.cotizacion?.iva_rate ?? 0.16))
const precios = computed(() => props.precios_tamano || {})
const subPrev = computed(() => {
  const p = precios.value || {}
  return (Number(tamanosForm.value.chico||0)   * Number(p.chico||0))
       + (Number(tamanosForm.value.mediano||0) * Number(p.mediano||0))
       + (Number(tamanosForm.value.grande||0)  * Number(p.grande||0))
       + (Number(tamanosForm.value.jumbo||0)   * Number(p.jumbo||0))
})
const ivaPrev = computed(() => subPrev.value * ivaRate.value)
const totalPrev = computed(() => subPrev.value + ivaPrev.value)
function definirTamanos() {
  if (!tamanosValid.value) return
  router.post(props.urls.definir_tamanos, {
    chico:   tamanosForm.value.chico || 0,
    mediano: tamanosForm.value.mediano || 0,
    grande:  tamanosForm.value.grande || 0,
    jumbo:   tamanosForm.value.jumbo || 0,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      // Forzar visita a la misma ruta para garantizar render con props nuevas
      router.visit(window.location.href, { replace: true, preserveScroll: true })
    },
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-indigo-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
      
      <!-- Header Card -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-indigo-100 overflow-hidden">
  <div class="bg-[#1E1C8F] px-8 py-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
              <div class="bg-white bg-opacity-20 p-4 rounded-xl backdrop-blur-sm">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div>
                <h1 class="text-4xl font-bold text-white">OT #{{ orden?.id }}</h1>
                <p class="text-indigo-100 text-lg mt-1">{{ orden?.servicio?.nombre }}</p>
              </div>
            </div>
            
            <!-- Status Badges -->
            <div class="flex flex-wrap gap-3">
              <span class="px-4 py-2 rounded-xl font-semibold text-sm backdrop-blur-sm border-2"
                    :class="{
                      'bg-white text-[#1E1C8F] border-white': orden?.estatus === 'generada',
                      'bg-yellow-500 text-white border-yellow-600': orden?.estatus === 'nueva',
                      'bg-blue-100 text-blue-800 border-blue-200': orden?.estatus === 'asignada',
                      'bg-orange-100 text-orange-800 border-orange-200': orden?.estatus === 'en_proceso',
                      'bg-emerald-600 text-white border-emerald-700': orden?.estatus === 'completada',
                      'bg-teal-600 text-white border-teal-700': orden?.estatus === 'validada_calidad',
                      'bg-slate-700 text-white border-slate-800': orden?.estatus === 'validada_cliente' || orden?.estatus === 'autorizada_cliente',
                      'bg-gray-800 text-white border-gray-900': orden?.estatus === 'facturada',
                      'bg-red-600 text-white border-red-700': orden?.estatus === 'cancelada',
                      'bg-gray-200 text-gray-800 border-gray-300': orden?.estatus === 'pendiente'
                    }">
                <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
                {{ orden?.estatus?.replace(/_/g, ' ').toUpperCase() }}
              </span>
              
              <span class="px-4 py-2 rounded-xl font-semibold text-sm backdrop-blur-sm border-2"
                    :class="{
                      'bg-orange-500 text-white border-orange-600': orden?.calidad_resultado === 'pendiente',
                      'bg-emerald-600 text-white border-emerald-700': orden?.calidad_resultado === 'validado',
                      'bg-red-600 text-white border-red-700': orden?.calidad_resultado === 'rechazado'
                    }">
                <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Calidad: {{ orden?.calidad_resultado?.toUpperCase() }}
              </span>

              <a :href="urls.pdf" target="_blank"
                 class="px-4 py-2 rounded-xl bg-white bg-opacity-20 hover:bg-opacity-30 text-white font-semibold text-sm backdrop-blur-sm border-2 border-white border-opacity-30 hover:border-opacity-50 transition-all duration-200 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                PDF
              </a>
            </div>
          </div>
        </div>
        
        <!-- Info Bar -->
  <div class="bg-gradient-to-r from-indigo-50 to-[#eef2ff] px-8 py-4 border-b border-indigo-100">
          <div class="flex flex-wrap items-center gap-6 text-sm">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
              <span class="text-gray-700"><strong>Centro:</strong> {{ orden?.centro?.nombre }}</span>
            </div>
            <div v-if="orden?.solicitud?.centro_costo || orden?.solicitud?.centroCosto" class="flex items-center gap-2">
              <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9h5l3 3v5l-9 9-8-8zM16 6h.01"/>
              </svg>
              <span class="text-gray-700"><strong>Centro de costos:</strong> {{ (orden?.solicitud?.centroCosto?.nombre) || (orden?.solicitud?.centro_costo?.nombre) || '—' }}</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9h5l3 3v5l-9 9-8-8zM16 6h.01"/>
              </svg>
              <span class="text-gray-700"><strong>Marca:</strong> {{ (orden?.solicitud?.marca?.nombre) || '—' }}</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
              <span class="text-gray-700"><strong>Team Leader:</strong> {{ orden?.team_leader?.name ?? 'No asignado' }}</span>
            </div>
            <div v-if="orden?.area" class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
              </svg>
              <span class="text-gray-700"><strong>Área:</strong> {{ orden.area.nombre }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Items, Avances, Evidencias -->
        <div class="lg:col-span-2 space-y-6">
          
          <!-- Asignar Team Leader (Admin/Coordinador) -->
          <div v-if="can?.asignar_tl" class="bg-white rounded-2xl shadow-lg border-2 border-blue-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-4">
              <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Asignar Team Leader
              </h2>
            </div>
            <div class="p-6">
              <div class="flex gap-3">
                <div class="flex-1 relative">
                  <select v-model="tlForm.team_leader_id" 
                          class="w-full px-4 py-3 pr-10 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-400 transition-all duration-200 appearance-none bg-white text-gray-800 font-medium">
                    <option :value="null">— Selecciona un Team Leader —</option>
                    <option v-for="u in teamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </div>
                </div>
                <button @click="asignarTL" :disabled="tlForm.processing"
                        class="px-6 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100">
                  {{ tlForm.processing ? 'Asignando...' : 'Asignar' }}
                </button>
              </div>
              <p v-if="tlForm.errors.team_leader_id" class="mt-3 text-sm text-red-600 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ tlForm.errors.team_leader_id }}
              </p>
            </div>
          </div>

          <!-- Ítems de la OT -->
          <div class="bg-white rounded-2xl shadow-lg border-2 border-emerald-100 overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
              <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Ítems de la Orden
              </h2>
            </div>
            
            <div v-if="items.length" class="overflow-x-auto">
              <!-- Resumen de unidades -->
              <div class="px-6 pt-5 pb-2 flex flex-wrap gap-3">
                <div class="px-3 py-2 bg-blue-50 border-2 border-blue-200 rounded-full text-blue-800 text-sm font-bold">
                  Planeado: <span class="ml-1">{{ unidades?.planeado ?? 0 }}</span>
                </div>
                <div class="px-3 py-2 bg-emerald-50 border-2 border-emerald-200 rounded-full text-emerald-800 text-sm font-bold">
                  Completado: <span class="ml-1">{{ unidades?.completado ?? 0 }}</span>
                </div>
                <div class="px-3 py-2 bg-rose-50 border-2 border-rose-300 rounded-full text-rose-700 text-sm font-bold">
                  Faltante: <span class="ml-1">{{ unidades?.faltante ?? 0 }}</span>
                </div>
                <div class="px-3 py-2 bg-indigo-50 border-2 border-indigo-200 rounded-full text-indigo-800 text-sm font-bold">
                  Total: <span class="ml-1">{{ unidades?.total ?? 0 }}</span>
                </div>
              </div>
              <table class="min-w-full">
                <thead class="bg-gradient-to-r from-emerald-50 to-teal-50">
                  <tr>
                    <th class="px-6 py-4 text-left text-sm font-bold text-emerald-700 uppercase tracking-wider">Descripción</th>
                    <th class="px-6 py-4 text-center text-sm font-bold text-emerald-700 uppercase tracking-wider">Planeado</th>
                    <th class="px-6 py-4 text-center text-sm font-bold text-emerald-700 uppercase tracking-wider">Completado</th>
                    <th class="px-6 py-4 text-center text-sm font-bold text-emerald-700 uppercase tracking-wider">Progreso</th>
                    <th v-if="can?.reportarAvance" class="px-6 py-4 text-center text-sm font-bold text-emerald-700 uppercase tracking-wider">Registrar</th>
                    <th v-if="can?.reportarAvance" class="px-6 py-4 text-center text-sm font-bold text-emerald-700 uppercase tracking-wider">Faltantes</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="(it, idx) in items" :key="it.id" class="hover:bg-emerald-50 transition-colors duration-150">
                    <td class="px-6 py-4">
                      <div class="flex items-center gap-3">
                        <div class="bg-emerald-100 p-2 rounded-lg">
                          <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                          </svg>
                        </div>
                        <div>
                          <div class="font-semibold text-gray-800">
                            <span v-if="it?.tamano">{{ it.tamano }}</span>
                            <span v-else>{{ it?.descripcion || 'Sin descripción' }}</span>
                          </div>
                          <div v-if="it?.tamano && it?.descripcion" class="text-sm text-gray-500 mt-1">{{ it.descripcion }}</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                      <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full font-bold text-sm">
                        {{ it?.cantidad_planeada }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                      <span class="px-3 py-1 rounded-full font-bold text-sm"
                            :class="(it?.cantidad_real || 0) >= (it?.cantidad_planeada || 0) ? 'bg-emerald-100 text-emerald-800' : 'bg-orange-100 text-orange-800'">
                        {{ it?.cantidad_real || 0 }}
                      </span>
                    </td>
                    <td class="px-6 py-4">
                      <div class="flex items-center gap-3">
                        <div class="flex-1 bg-gray-200 rounded-full h-3 overflow-hidden">
                          <div class="h-full rounded-full transition-all duration-500"
                               :class="(it?.cantidad_real || 0) >= (it?.cantidad_planeada || 0) ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : 'bg-gradient-to-r from-orange-500 to-amber-500'"
                               :style="{ width: Math.min(100, ((it?.cantidad_real || 0) / (it?.cantidad_planeada || 1)) * 100) + '%' }">
                          </div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 min-w-[3rem] text-right">
                          {{ Math.round(((it?.cantidad_real || 0) / (it?.cantidad_planeada || 1)) * 100) }}%
                        </span>
                      </div>
                    </td>
                    
                    <!-- Captura de avance -->
                    <td v-if="can?.reportarAvance" class="px-6 py-4">
                      <div class="flex flex-col items-center gap-2">
         <input type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*"
                               :max="restante(it)"
           v-model.number="avForm.items[idx].cantidad"
           placeholder="0"
           @focus="if(avForm.items[idx].cantidad===0 || avForm.items[idx].cantidad===''){ avForm.items[idx].cantidad=''; }"
           @input="avForm.clearErrors('items')"
                               class="w-24 px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-center font-semibold" />
                        <span class="text-xs text-gray-500">máx: {{ restante(it) }}</span>
                      </div>
                    </td>
                    <!-- Captura de faltantes -->
                    <td v-if="can?.reportarAvance" class="px-6 py-4">
                      <div class="flex flex-col items-center gap-2">
                        <input type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*"
                               :max="restante(it)"
                               v-model.number="faltForm.items[idx].faltantes"
                               placeholder="0"
                               @focus="if(faltForm.items[idx].faltantes===0 || faltForm.items[idx].faltantes===''){ faltForm.items[idx].faltantes=''; }"
                               @input="faltForm.clearErrors('items')"
                               class="w-24 px-3 py-2 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 text-center font-semibold" />
                        <span class="text-xs text-gray-500">máx: {{ restante(it) }}</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="p-8 text-center text-gray-500">
              <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
              </svg>
              <p>No hay ítems registrados</p>
            </div>
          </div>

          <!-- Registrar Avance -->
          <div v-if="can?.reportarAvance" class="bg-white rounded-2xl shadow-lg border-2 border-blue-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
              <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Registrar Avance
              </h3>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Comentario (opcional)</label>
                <textarea v-model="avForm.comentario" rows="3"
                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-400 transition-all duration-200 resize-none"
                          placeholder="Describe el progreso realizado..."></textarea>
              </div>
              <button @click="registrarAvance" :disabled="avForm.processing || !hasAvance"
                      class="w-full px-6 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center justify-center gap-2">
                <svg v-if="!avForm.processing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ avForm.processing ? 'Guardando Avance...' : 'Guardar Avance' }}
              </button>
              <p v-if="avForm.errors.items" class="text-sm text-red-600 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ avForm.errors.items }}
              </p>
              <div class="pt-4 border-t">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nota por faltantes (opcional)</label>
                <textarea v-model="faltForm.nota" rows="2"
                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-400 transition-all duration-200 resize-none"
                          placeholder="Ej: Faltaron piezas por daño o falta de material..."></textarea>
    <button @click="aplicarFaltantes" :disabled="faltForm.processing || !hasFaltantes"
                        class="mt-3 w-full px-6 py-3 bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center justify-center gap-2">
                  <svg v-if="!faltForm.processing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                  <svg v-else class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                  </svg>
                  {{ faltForm.processing ? 'Aplicando...' : 'Aplicar faltantes' }}
                </button>
                <p v-if="faltForm.errors.items" class="text-sm text-red-600 flex items-center gap-1 mt-2">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ faltForm.errors.items }}
                </p>
              </div>
            </div>
          </div>

          <!-- Subir Evidencias -->
          <div v-if="can?.reportarAvance" class="bg-white rounded-2xl shadow-lg border-2 border-orange-100 overflow-hidden">
            <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-6 py-4">
              <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Subir Evidencias
              </h3>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Asociar a ítem (opcional)</label>
                <div class="relative">
      <select v-model="evForm.id_item" 
        class="w-full px-4 py-3 pr-10 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-orange-100 focus:border-orange-400 transition-all duration-200 appearance-none bg-white text-gray-800 font-medium">
                    <option :value="null">— Sin asociar a ítem específico —</option>
                    <option v-for="it in orden.items" :key="it.id" :value="it.id">
                      #{{it.id}} — {{ it.tamano ? ('Tamaño: '+it.tamano) : it.descripcion }}
                    </option>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </div>
                </div>
              </div>
              
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Seleccionar archivos</label>
      <input type="file" multiple accept="image/*,application/pdf,video/mp4" @change="onPickEvidencias"
        class="block w-full text-sm text-gray-700 file:mr-4 file:py-3 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200 file:transition-all file:duration-200 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-orange-100" />
              </div>

        <button @click="subirEvidencias" :disabled="evForm.processing"
          class="w-full px-6 py-3 bg-gradient-to-r from-orange-600 to-amber-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center justify-center gap-2">
                <svg v-if="!evForm.processing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ evForm.processing ? 'Subiendo...' : 'Subir Evidencias' }}
              </button>
              
              <p v-if="evForm.errors.evidencias" class="text-sm text-red-600 flex items-center gap-1">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ evForm.errors.evidencias }}
              </p>
            </div>
          </div>

          <!-- Archivos de la Solicitud -->
          <div v-if="orden?.solicitud?.archivos?.length" class="bg-white rounded-2xl shadow-lg border-2 border-orange-100 overflow-hidden">
            <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-6 py-4">
              <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                Archivos de la Solicitud
              </h3>
            </div>
            <div class="p-4 space-y-2">
              <div v-for="archivo in orden.solicitud.archivos" :key="archivo.id" 
                   class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-orange-50 rounded-xl border border-gray-200 hover:border-orange-300 hover:shadow-md transition-all duration-200">
                <div class="flex items-center gap-4">
                  <div class="bg-gradient-to-br from-orange-500 to-amber-500 p-3 rounded-xl">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div>
                    <div class="font-semibold text-gray-800">{{ archivo.nombre_original || archivo.path?.split('/').pop() || 'Archivo' }}</div>
                    <div class="text-sm text-gray-500 flex items-center gap-2 mt-1">
                      <span class="px-2 py-0.5 bg-gray-200 rounded text-xs font-medium">
                        {{ archivo.size ? (archivo.size / 1024).toFixed(0) : '0' }} KB
                      </span>
                      <span v-if="archivo.mime" class="text-xs">{{ archivo.mime }}</span>
                    </div>
                  </div>
                </div>
                <div class="flex gap-2">
                  <button v-if="canPreview(archivo.mime)"
                          @click="openPreview(archivo)"
                          class="px-4 py-2 rounded-xl bg-gradient-to-r from-gray-600 to-gray-700 text-white font-medium hover:from-gray-700 hover:to-gray-800 shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver
                  </button>
                  <a :href="route('archivos.download', archivo.id)" 
                     class="px-4 py-2 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 text-white font-medium hover:from-orange-700 hover:to-amber-700 shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Galería de Evidencias -->
          <div class="bg-white rounded-2xl shadow-lg border-2 border-indigo-100 overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-600 to-[#1E1C8F] px-6 py-4">
              <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Galería de Evidencias
              </h3>
            </div>
            
            <div v-if="vistaEvidencias.length" class="p-5">
              <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="ev in vistaEvidencias" :key="ev.id" 
                     class="bg-gradient-to-br from-gray-50 to-indigo-50 rounded-xl border-2 border-indigo-100 overflow-hidden hover:shadow-lg transition-all duration-200">
                  
                  <!-- Media Preview -->
                  <div class="bg-gray-900 aspect-video flex items-center justify-center">
                    <a v-if="ev.mime && ev.mime.startsWith('image/')" :href="ev.url" target="_blank" class="w-full h-full">
                      <img :src="ev.url" class="w-full h-full object-cover" />
                    </a>
                    <a v-else-if="ev.mime==='application/pdf'" :href="ev.url" target="_blank"
                       class="flex flex-col items-center justify-center gap-2 text-white hover:text-indigo-300 transition-colors">
                      <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                      </svg>
                      <span class="text-sm font-medium">Ver PDF</span>
                    </a>
                    <video v-else-if="ev.mime==='video/mp4'" controls class="w-full h-full">
                      <source :src="ev.url" type="video/mp4" />
                    </video>
                    <a v-else :href="ev.url" target="_blank"
                       class="flex flex-col items-center justify-center gap-2 text-white hover:text-indigo-300 transition-colors">
                      <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                      </svg>
                      <span class="text-sm font-medium">Descargar</span>
                    </a>
                  </div>

                  <!-- Info -->
                  <div class="p-4">
                    <div class="text-xs text-gray-600 mb-2 flex items-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                      </svg>
                      {{ fmtDate(ev.created_at) }}
                    </div>
                    <div class="text-sm font-semibold text-gray-800 mb-1 flex items-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                      </svg>
                      {{ ev.usuario?.name || 'Usuario' }}
                    </div>
                    <div v-if="ev.id_item" class="text-xs text-indigo-600 font-medium mb-3">Ítem #{{ ev.id_item }}</div>
                    
                    <button v-if="can?.reportarAvance" @click="borrarEvidencia(ev.id)"
                            class="w-full px-3 py-2 bg-red-50 text-red-700 font-semibold rounded-lg hover:bg-red-600 hover:text-white transition-all duration-200 flex items-center justify-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                      Eliminar
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="p-8 text-center text-gray-500">
              <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <p>No hay evidencias registradas</p>
            </div>
          </div>

          <!-- Historial de Avances -->
          <div class="bg-white rounded-2xl shadow-lg border-2 border-cyan-100 overflow-hidden">
            <div class="bg-gradient-to-r from-cyan-600 to-blue-600 px-6 py-4">
              <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Historial de Avances
                </h3>
                <span v-if="avances && avances.length > 0" class="px-3 py-1 bg-white bg-opacity-20 rounded-full text-white text-sm font-bold">
                  {{ avances.length }}
                </span>
              </div>
            </div>
            <div v-if="avances && avances.length > 0" class="p-5">
              <div class="space-y-3">
       <div v-for="(a, idx) in avances" :key="a?.id || idx" 
         :class="['flex items-start gap-4 p-4 rounded-xl border transition-all duration-150', 
            isRechazoComentario(a?.comentario)
              ? 'bg-red-50 border-2 border-red-300'
              : (isFaltantesComentario(a?.comentario)
                  ? 'bg-rose-50 border-2 border-rose-300'
                  : ((a?.isCorregido || a?.es_corregido)
                      ? 'bg-emerald-50 border-2 border-emerald-300'
                      : 'bg-gradient-to-r from-cyan-50 to-blue-50 border-cyan-100')) ]">
                  
                  <!-- Icon -->
                  <div :class="['p-2 rounded-full flex-shrink-0',
                               isRechazoComentario(a?.comentario)
                                 ? 'bg-red-500'
                                 : (isFaltantesComentario(a?.comentario)
                                     ? 'bg-red-500'
                                     : ((a?.isCorregido || a?.es_corregido) ? 'bg-emerald-500' : 'bg-cyan-500')) ]">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  
                  <!-- Content -->
                  <div class="flex-1">
                    <!-- Badge RECHAZO / CORREGIDO (si aplica) -->
                    <div v-if="isRechazoComentario(a?.comentario)" 
                         class="inline-flex items-center gap-1 px-3 py-1 bg-red-500 text-white text-xs font-bold rounded-full mb-2">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.36 6.64a9 9 0 11-12.73 0 9 9 0 0112.73 0z"/>
                      </svg>
                      RECHAZO
                    </div>
        <div v-else-if="isFaltantesComentario(a?.comentario)" 
             class="inline-flex items-center gap-1 px-3 py-1 bg-red-500 text-white text-xs font-bold rounded-full mb-2">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/>
                      </svg>
                      FALTANTES
                    </div>
        <div v-else-if="(a?.isCorregido || a?.es_corregido)" 
          class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-500 text-white text-xs font-bold rounded-full mb-2">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                      </svg>
                      CORREGIDO
                    </div>
                    
                    <!-- Cantidad y nombre del Item -->
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                      <span class="font-bold text-cyan-700">+{{ a?.cantidad || 0 }}</span>
                      <span v-if="a?.id_item" class="px-2 py-0.5 bg-cyan-200 text-cyan-800 rounded text-xs font-medium">
                        Ítem #{{ a.id_item }}
                      </span>
                      <span v-if="a?.item" class="text-sm font-semibold text-gray-700">
                        {{ a.item.tamano || a.item.descripcion || 'Sin descripción' }}
                      </span>
                    </div>
                    
                    <!-- Comentario -->
                    <div v-if="a?.comentario" class="text-sm mb-2">
                      <template v-if="isRechazoComentario(a?.comentario)">
                        <div class="text-xs text-red-800 font-semibold mb-1">RECHAZO POR CALIDAD</div>
                        <div class="italic text-gray-700">"{{ extractRechazoComentario(a.comentario) }}"</div>
                      </template>
                      <template v-else-if="(a?.isCorregido || a?.es_corregido)">
                        <div class="text-xs text-emerald-800 font-semibold mb-1">CORREGIDO</div>
                        <div class="italic text-gray-700">"{{ a.comentario }}"</div>
                      </template>
                      <template v-else>
                        <div class="italic text-gray-700">"{{ a.comentario }}"</div>
                      </template>
                    </div>
                    
                    <!-- Usuario -->
                    <div class="text-sm text-gray-600 flex items-center gap-2">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                      </svg>
                      {{ a?.usuario?.name || 'Usuario' }}
                    </div>
                    
                    <!-- Fecha -->
                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                      </svg>
                      {{ fmtDate(a?.created_at) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="p-8 text-center text-gray-500">
              <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
              <p class="font-semibold mb-1">No hay avances registrados</p>
              <p class="text-sm">Los avances aparecerán aquí cuando se reporten</p>
            </div>
          </div>
        </div>

        <!-- Right Column: Acciones -->
        <div class="lg:col-span-1 space-y-6">
          <!-- Panel: Desglose por Tamaños (pendiente) -->
          <div v-if="orden?.servicio?.usa_tamanos && (!orden?.solicitud?.tamanos || orden?.solicitud?.tamanos.length === 0)"
               class="bg-white rounded-2xl shadow-lg border-2 border-blue-100 overflow-hidden sticky top-6">
            <div class="bg-blue-700 px-6 py-4">
              <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h8"/>
                </svg>
                Definir desglose por tamaños
              </h3>
            </div>
            <div class="p-5 space-y-4">
              <div class="text-sm text-blue-800 bg-blue-50 border-2 border-blue-200 rounded-xl p-3">
                La suma de piezas por tamaño debe ser <strong>{{ totalAprobado }}</strong>.
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                  <label class="block text-xs font-semibold text-gray-600 uppercase">Chico</label>
                  <input type="number" min="0" v-model.number="tamanosForm.chico" class="w-full px-3 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                </div>
                <div class="space-y-1">
                  <label class="block text-xs font-semibold text-gray-600 uppercase">Mediano</label>
                  <input type="number" min="0" v-model.number="tamanosForm.mediano" class="w-full px-3 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                </div>
                <div class="space-y-1">
                  <label class="block text-xs font-semibold text-gray-600 uppercase">Grande</label>
                  <input type="number" min="0" v-model.number="tamanosForm.grande" class="w-full px-3 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                </div>
                <div class="space-y-1">
                  <label class="block text-xs font-semibold text-gray-600 uppercase">Jumbo</label>
                  <input type="number" min="0" v-model.number="tamanosForm.jumbo" class="w-full px-3 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                </div>
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600">Suma actual:</span>
                  <span class="font-bold" :class="{ 'text-red-600': sumaTamanos !== totalAprobado, 'text-emerald-700': sumaTamanos === totalAprobado }">{{ sumaTamanos }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                  <span class="text-gray-500">Faltantes:</span>
                  <span :class="{
                          'text-emerald-700 font-semibold': faltanRaw === 0,
                          'text-orange-600 font-semibold': faltanRaw > 0,
                          'text-red-700 font-semibold': faltanRaw < 0
                        }">
                    <template v-if="faltanRaw > 0">{{ faltanCalc }}</template>
                    <template v-else-if="faltanRaw < 0">Exceso: {{ Math.abs(faltanRaw) }}</template>
                    <template v-else>Listo</template>
                  </span>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-2">
                  <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl p-3 border-2 border-indigo-200">
                    <div class="text-[11px] font-semibold text-indigo-700 uppercase">Subtotal</div>
                    <div class="text-lg font-bold text-indigo-900">${{ subPrev.toFixed(2) }}</div>
                  </div>
                  <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-xl p-3 border-2 border-emerald-200">
                    <div class="text-[11px] font-semibold text-emerald-700 uppercase">Total (IVA {{ (ivaRate*100).toFixed(0) }}%)</div>
                    <div class="text-lg font-bold text-emerald-900">${{ totalPrev.toFixed(2) }}</div>
                  </div>
                </div>
              </div>
              <button @click="definirTamanos" :disabled="!tamanosValid"
                      class="w-full px-5 py-3 rounded-xl font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 disabled:opacity-60 disabled:cursor-not-allowed">
                Aplicar desglose
              </button>
              <div class="text-xs text-gray-500">Se recalcularán los precios y totales de la OT y la Solicitud.</div>
            </div>
          </div>
          
          <!-- Acciones de Calidad/Cliente/Facturación -->
          <div class="bg-white rounded-2xl shadow-lg border-2 border-indigo-100 overflow-hidden sticky top-6">
            <div class="bg-gradient-to-r from-indigo-600 to-[#1E1C8F] px-6 py-4">
              <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Acciones Disponibles
              </h3>
            </div>
            <div class="p-5 space-y-3">
              <!-- Validar Calidad -->
              <button v-if="can?.calidad_validar"
                      @click="validarCalidad" 
                      class="w-full px-5 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Validar Calidad
              </button>

              <!-- Rechazar Calidad (modal) -->
              <div v-if="can?.calidad_validar" class="space-y-2">
    <button @click="openRechazo" 
                        class="w-full px-5 py-3 bg-red-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 flex items-center justify-center gap-2">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Rechazar Calidad
                </button>
              </div>

              <!-- Autorizar Cliente -->
              <button v-if="can?.cliente_autorizar"
                      @click="autorizarCliente" 
                      class="w-full px-5 py-3 bg-gradient-to-r from-gray-800 to-black text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Autorizar como Cliente
              </button>

              <!-- Ir a Facturación -->
              <a v-if="can?.facturar" :href="urls.facturar"
                 class="block w-full px-5 py-3 bg-gradient-to-r from-indigo-600 to-[#1E1C8F] text-white font-bold text-center rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Ir a Facturación
              </a>

              <!-- Mensaje cuando no hay acciones disponibles -->
              <div v-if="!can?.calidad_validar && !can?.cliente_autorizar && !can?.facturar" 
                   class="text-center py-8 text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <p class="font-semibold">No hay acciones disponibles</p>
                <p class="text-sm mt-1">Las acciones aparecerán según el estado de la OT</p>
              </div>
            </div>
          </div>

          <!-- Motivo de Rechazo / Acciones Correctivas (si existen) -->
          <div v-if="orden?.motivo_rechazo || orden?.acciones_correctivas" class="bg-white rounded-2xl shadow-lg border-2 border-red-100 overflow-hidden">
            <div class="bg-red-600 px-6 py-4">
              <h4 class="text-lg font-bold text-white">Rechazo por Calidad</h4>
            </div>
            <div class="p-4 space-y-3">
              <div v-if="orden?.motivo_rechazo">
                <div class="text-sm font-semibold text-red-700">Motivo</div>
                <div class="text-gray-700 whitespace-pre-wrap">{{ orden.motivo_rechazo }}</div>
              </div>
              <div v-if="orden?.acciones_correctivas">
                <div class="text-sm font-semibold text-red-700">Acciones Correctivas</div>
                <div class="text-gray-700 whitespace-pre-wrap">{{ orden.acciones_correctivas }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de preview -->
    <FilePreview v-if="archivoPreview" :archivo="archivoPreview" @close="closePreview" />

    <!-- Modal Rechazo Calidad -->
    <div v-if="showRechazoModal" class="fixed inset-0 z-[9999] flex items-center justify-center px-4">
      <div class="fixed inset-0 bg-black/40 z-40" @click="showRechazoModal = false"></div>
      <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-xl p-6 z-50">
        <h3 class="text-lg font-semibold">Rechazar OT #{{ orden.id }}</h3>
        <p class="text-sm text-gray-500 mt-1">Servicio: <strong>{{ orden.servicio?.nombre }}</strong></p>
        <div v-if="orden?.descripcion_general" class="mt-3 p-3 bg-gray-50 border border-gray-100 rounded-lg">
          <div class="text-xs text-gray-500">Producto/Servicio (general)</div>
          <div class="text-sm font-semibold text-gray-800">{{ orden.descripcion_general }}</div>
        </div>

        <div class="mt-4">
          <label class="text-sm font-semibold">Motivo del Rechazo</label>
          <textarea v-model="obs" rows="4" class="w-full mt-2 p-3 border rounded-md" placeholder="Describe el motivo del rechazo (requerido)"></textarea>
        </div>

        <div class="mt-4">
          <label class="text-sm font-semibold">Acciones Correctivas (opcional)</label>
          <textarea v-model="acciones_correctivas" rows="3" class="w-full mt-2 p-3 border rounded-md" placeholder="Describe acciones sugeridas para corregir la OT"></textarea>
        </div>

        <div class="mt-4 flex justify-end gap-3">
          <button @click="showRechazoModal = false" class="px-4 py-2 rounded bg-gray-200">Cancelar</button>
          <button @click="rechazarCalidad" class="px-4 py-2 rounded bg-red-600 text-white">Enviar Rechazo</button>
        </div>
      </div>
    </div>
  </div>
</template>


