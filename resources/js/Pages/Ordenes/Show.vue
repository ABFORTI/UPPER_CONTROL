<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import FilePreview from '@/Components/FilePreview.vue'

const props = defineProps({
  orden:       { type: Object, required: true },
  urls:        { type: Object, required: true },   // { asignar_tl, avances_store, evidencias_store, evidencias_destroy, calidad_validar, calidad_rechazar, cliente_autorizar, facturar, pdf }
  can:         { type: Object, default: () => ({}) }, // { reportarAvance:bool, asignar_tl:bool }
  teamLeaders: { type: Array,  default: () => [] },
  cotizacion:  { type: Object, default: () => ({}) },
  precios_tamano: { type: Object, default: () => null },
  unidades: { type: Object, default: () => ({ planeado:0, completado:0, faltante:0, total:0 }) },
  servicios_con_tamanos: { type: Object, default: () => ({}) },
  precios_por_servicio: { type: Object, default: () => ({}) },
})

// Alias para acceder fácilmente en el template
const serviciosConTamanos = computed(() => props.servicios_con_tamanos)
const preciosPorServicio = computed(() => props.precios_por_servicio)

// colecciones “a prueba de null”
const items   = computed(() => props.orden?.items   ?? [])
const avances = computed(() => props.orden?.avances ?? [])
const servicios = computed(() => props.orden?.ot_servicios ?? [])

// Helper para números seguros
const toNum = (v) => {
  const n = Number.parseFloat(v)
  return Number.isFinite(n) ? n : 0
}

// Formatter de moneda
const money = (v) => new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
}).format(toNum(v))

// Detectar si es multi-servicio
const esMultiServicio = computed(() => (props.orden?.ot_servicios?.length ?? 0) > 0)

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
  comentario: '',
  tarifa_tipo: 'NORMAL',
  precio_unitario_manual: ''
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
    const okQty = (avForm.items || []).some((x, idx) => {
      const it = items.value[idx]
      const max = restante(it)
      const val = Number(x?.cantidad || 0)
      return val > 0 && val <= max
    })
    if (!okQty) return false
    if ((avForm.tarifa_tipo || 'NORMAL') === 'NORMAL') return true
    const pu = Number(avForm.precio_unitario_manual || 0)
    return pu > 0
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

  if ((avForm.tarifa_tipo || 'NORMAL') !== 'NORMAL') {
    const pu = Number(avForm.precio_unitario_manual || 0)
    if (!(pu > 0)) {
      avForm.setError('precio_unitario_manual', 'Captura un precio unitario válido para EXTRA / FIN_DE_SEMANA.')
      return
    }
  }
  avForm.post(props.urls.avances_store, {
    preserveScroll: true,
    // Al completar, recargar la data desde el servidor para reflejar cantidades reales actualizadas
    onSuccess: () => {
      // resetear inputs
      avForm.reset('comentario')
      avForm.tarifa_tipo = 'NORMAL'
      avForm.precio_unitario_manual = ''
      avForm.items = (items.value || []).map(i => ({ id_item: i.id, cantidad: '' }))
      avForm.clearErrors('items')
      // recargar únicamente props necesarias para performance
  // También recargar 'unidades' para que el panel de desglose use el TOTAL vigente
  router.reload({ only: ['orden','cotizacion','unidades'], preserveScroll: true })
    },
  })
}

// ----- Segmentos de producción -----
const canEditSegmentPrices = computed(() =>
  !!props.can?.reportarAvance && !['autorizada_cliente','facturada'].includes(String(props.orden?.estatus || ''))
)
const segPriceDraft = ref({}) // { [id]: number|string }
const segNotaDraft = ref({})
function segsOf(it) { return it?.segmentos_produccion || [] }
const itemsConSegs = computed(() => (items.value || []).filter(it => (segsOf(it) || []).length > 0))
function segLabel(tipo) {
  if (!tipo) return '—'
  return String(tipo).replace(/_/g, ' ')
}
function updateSegmento(seg) {
  const baseUrl = props.urls?.segmentos_update
  if (!baseUrl || !seg?.id) return
  const url = baseUrl.replace(/\/0$/, '/' + seg.id)
  const precio = Number(segPriceDraft.value?.[seg.id] ?? seg?.precio_unitario ?? 0)
  const nota = (segNotaDraft.value?.[seg.id] ?? seg?.nota ?? '')
  router.patch(url, { precio_unitario: precio, nota }, {
    preserveScroll: true,
    onSuccess: () => {
      router.reload({ only: ['orden','cotizacion'], preserveScroll: true })
    }
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
const cotSubtotal = computed(() => Number(props.cotizacion?.subtotal ?? 0))
const cotIva = computed(() => Number(props.cotizacion?.iva ?? (cotSubtotal.value * ivaRate.value)))
const cotTotal = computed(() => Number(props.cotizacion?.total ?? (cotSubtotal.value + cotIva.value)))
const cotCalcMode = computed(() => String(props.cotizacion?.calc_mode || 'FIJO'))
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

// ----- Definir tamaños para servicios individuales en multi-servicio -----
const servicioSeleccionadoTamanos = ref(null)
const tamanosFormServicio = ref({})

// Funciones para tamaños por servicio
const sumaTamanosServicio = computed(() => (servicioId) => {
  const form = tamanosFormServicio.value[servicioId]
  if (!form) return 0
  return Number(form.chico || 0) + Number(form.mediano || 0) + Number(form.grande || 0) + Number(form.jumbo || 0)
})

const tamanosValidServicio = computed(() => (servicioId) => {
  const info = serviciosConTamanos.value[servicioId]
  if (!info) return false
  const suma = sumaTamanosServicio.value(servicioId)
  return suma === info.cantidad_total
})

function definirTamanosServicio(servicioId) {
  if (!tamanosValidServicio.value(servicioId)) return
  
  const form = tamanosFormServicio.value[servicioId]
  router.post(route('ordenes.servicios.definirTamanos', { orden: props.orden.id, servicio: servicioId }), {
    chico: form.chico || 0,
    mediano: form.mediano || 0,
    grande: form.grande || 0,
    jumbo: form.jumbo || 0,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      servicioSeleccionadoTamanos.value = null
      router.visit(window.location.href, { replace: true, preserveScroll: true })
    },
  })
}

// Inicializar forms de tamaños para servicios pendientes
const inicializarTamanosServicios = () => {
  Object.keys(serviciosConTamanos.value).forEach(servicioId => {
    const info = serviciosConTamanos.value[servicioId]
    if (info?.pendiente_definir && !tamanosFormServicio.value[servicioId]) {
      tamanosFormServicio.value[servicioId] = { chico: 0, mediano: 0, grande: 0, jumbo: 0 }
    }
  })
}
inicializarTamanosServicios()

// ----- Avances para multi-servicio -----
const avancesMultiServicio = ref({})
const avancesFormsServicio = ref({})

// Faltantes para multi-servicio
const faltantesMultiServicio = ref({})

// Inicializar formularios de avances por servicio
const inicializarAvancesServicios = () => {
  servicios.value.forEach(servicio => {
    if (!avancesMultiServicio.value[servicio.id]) {
      avancesMultiServicio.value[servicio.id] = {
        items: (servicio.items || []).map(item => ({ 
          id_item: item.id, 
          cantidad: '' 
        })),
        comentario: '',
        tarifa_tipo: 'NORMAL',
        precio_unitario_manual: ''
      }
    }
    // Inicializar faltantes
    if (!faltantesMultiServicio.value[servicio.id]) {
      faltantesMultiServicio.value[servicio.id] = {
        items: (servicio.items || []).map(item => ({
          id_item: item.id,
          faltantes: ''
        })),
        nota: ''
      }
    }
  })
}
inicializarAvancesServicios()

// Función para guardar avance de un servicio
function guardarAvanceServicio(servicioId) {
  const formData = avancesMultiServicio.value[servicioId]
  if (!formData) return
  
  // Filtrar solo items con cantidad > 0
  const itemsConCantidad = formData.items
    .map(item => ({
      id_item: item.id_item,
      cantidad: Number(item.cantidad || 0)
    }))
    .filter(item => item.cantidad > 0)
  
  if (itemsConCantidad.length === 0) {
    alert('Ingresa al menos una cantidad mayor a 0')
    return
  }
  
  // Preparar payload con id_servicio
  const payload = {
    id_servicio: servicioId,
    items: itemsConCantidad,
    comentario: formData.comentario || '',
    tarifa_tipo: formData.tarifa_tipo || 'NORMAL',
    precio_unitario_manual: formData.precio_unitario_manual || ''
  }
  
  console.log('📤 Enviando avance:', payload)
  
  // Usar router.post directamente
  router.post(props.urls.avances_store, payload, {
    preserveScroll: true,
    onSuccess: () => {
      console.log('✅ Avance guardado exitosamente')
      // Limpiar formulario
      avancesMultiServicio.value[servicioId].items.forEach(i => i.cantidad = '')
      avancesMultiServicio.value[servicioId].comentario = ''
      router.visit(window.location.href, { replace: true, preserveScroll: true })
    },
    onError: (errors) => {
      console.error('❌ Error al guardar avance:', errors)
      alert('Error al guardar el avance. Revisa los datos ingresados.')
    }
  })
}

// Calcular restante para un item de servicio
function restanteServicio(item) {
  return Math.max(0, (item?.planeado || 0) - (item?.completado || 0))
}

// Verificar si hay faltantes válidos para un servicio
function hasFaltantesServicio(servicioId) {
  const formData = faltantesMultiServicio.value[servicioId]
  if (!formData) return false
  
  return formData.items.some((item, idx) => {
    const val = Number(item.faltantes || 0)
    return val > 0
  })
}

// Aplicar faltantes para un servicio
function aplicarFaltantesServicio(servicioId) {
  const formData = faltantesMultiServicio.value[servicioId]
  if (!formData) return
  
  // Filtrar solo items con faltantes > 0
  const itemsConFaltantes = formData.items
    .map(item => ({
      id_item: item.id_item,
      faltantes: Number(item.faltantes || 0)
    }))
    .filter(item => item.faltantes > 0)
  
  if (itemsConFaltantes.length === 0) {
    alert('Ingresa al menos un faltante mayor a 0')
    return
  }
  
  const payload = {
    items: itemsConFaltantes,
    nota: formData.nota || ''
  }
  
  console.log('📤 Enviando faltantes:', payload)
  
  // Construir URL con route helper de Ziggy
  const url = route('ot-multi-servicio.servicios.faltantes', { orden: props.orden.id, servicio: servicioId })
  
  router.post(url, payload, {
    preserveScroll: true,
    onSuccess: () => {
      console.log('✅ Faltantes registrados exitosamente')
      // Limpiar formulario
      faltantesMultiServicio.value[servicioId].items.forEach(i => i.faltantes = '')
      faltantesMultiServicio.value[servicioId].nota = ''
      router.visit(window.location.href, { replace: true, preserveScroll: true })
    },
    onError: (errors) => {
      console.error('❌ Error al registrar faltantes:', errors)
      alert('Error al registrar los faltantes. Revisa los datos ingresados.')
    }
  })
}

</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-indigo-50 to-indigo-50 py-8 px-4 sm:px-6 lg:px-8 dark:bg-gradient-to-br dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
    <div class="max-w-7xl mx-auto space-y-6">
      
      <!-- Header Card -->
      <div class="bg-white rounded-2xl shadow-xl border-2 border-indigo-100 overflow-hidden dark:bg-slate-900/85 dark:border-slate-800 dark:shadow-[0_20px_45px_rgba(0,0,0,0.55)]">
  <div class="bg-[#1E1C8F] px-4 sm:px-6 py-3 dark:bg-gradient-to-r dark:from-[#1E1C8F] dark:to-indigo-600">
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-start sm:items-center gap-4">
              <div class="bg-white bg-opacity-20 p-3 rounded-xl backdrop-blur-sm">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white leading-tight">OT #{{ orden?.id }}</h1>
                <p class="text-indigo-100 text-sm sm:text-base mt-0.5">{{ orden?.servicio?.nombre }}</p>
              </div>
            </div>
            
            <!-- Status Badges -->
            <div class="flex flex-wrap gap-3 w-full lg:w-auto justify-start lg:justify-end">
              <span class="px-3 py-1.5 rounded-lg font-semibold text-xs backdrop-blur-sm border-2"
                    :class="{
                      'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800/70 dark:text-slate-200 dark:border-slate-700': orden?.estatus === 'generada',
                      'bg-violet-100 text-violet-800 border-violet-300 dark:bg-violet-500/20 dark:text-violet-200 dark:border-violet-500/40': orden?.estatus === 'nueva',
                      'bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-500/20 dark:text-blue-200 dark:border-blue-500/40': orden?.estatus === 'asignada',
                      'bg-orange-100 text-orange-700 border-orange-300 dark:bg-orange-500/20 dark:text-orange-200 dark:border-orange-500/40': orden?.estatus === 'en_proceso',
                      'bg-emerald-600 text-white border-emerald-700 dark:bg-emerald-600 dark:text-emerald-50 dark:border-emerald-700': orden?.estatus === 'completada',
                      'bg-teal-500 text-white border-teal-600 dark:bg-teal-500 dark:text-teal-50 dark:border-teal-600': orden?.estatus === 'validada_calidad',
                      'bg-indigo-600 text-white border-indigo-700 dark:bg-indigo-600 dark:text-indigo-50 dark:border-indigo-700': orden?.estatus === 'validada_cliente',
                      'bg-lime-600 text-white border-lime-700 dark:bg-lime-500 dark:text-lime-50 dark:border-lime-600': orden?.estatus === 'autorizada_cliente',
                      'bg-gray-800 text-white border-gray-900 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-900': orden?.estatus === 'facturada',
                      'bg-red-600 text-white border-red-700 dark:bg-red-600 dark:text-red-50 dark:border-red-700': orden?.estatus === 'cancelada',
                      'bg-gray-200 text-gray-800 border-gray-300 dark:bg-slate-700/80 dark:text-slate-200 dark:border-slate-600': orden?.estatus === 'pendiente'
                    }">
                <svg class="w-3.5 h-3.5 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
                {{ orden?.estatus?.replace(/_/g, ' ').toUpperCase() }}
              </span>
              
              <span class="px-3 py-1.5 rounded-lg font-semibold text-xs backdrop-blur-sm border-2"
                    :class="{
                      'bg-orange-500 text-white border-orange-600 dark:bg-orange-500 dark:border-orange-600': orden?.calidad_resultado === 'pendiente',
                      'bg-emerald-600 text-white border-emerald-700 dark:bg-emerald-600 dark:border-emerald-700': orden?.calidad_resultado === 'validado',
                      'bg-red-600 text-white border-red-700 dark:bg-red-600 dark:border-red-700': orden?.calidad_resultado === 'rechazado'
                    }">
                <svg class="w-3.5 h-3.5 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Calidad: {{ orden?.calidad_resultado?.toUpperCase() }}
              </span>

              <a :href="urls.pdf" target="_blank"
                 class="px-3 py-1.5 rounded-lg bg-white bg-opacity-20 hover:bg-opacity-30 text-white font-semibold text-xs backdrop-blur-sm border-2 border-white border-opacity-30 hover:border-opacity-50 transition-all duration-200 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                PDF
              </a>
            </div>
          </div>
        </div>
        
        <!-- Info Bar -->
        <div class="bg-gradient-to-r from-indigo-50 to-[#eef2ff] px-4 sm:px-6 py-2.5 border-b border-indigo-100 dark:bg-gradient-to-r dark:from-slate-900/80 dark:via-slate-900/60 dark:to-slate-900/40 dark:border-slate-800">
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 text-xs">
            <div class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Centro:</strong> {{ orden?.centro?.nombre }}</span>
            </div>
            <div v-if="orden?.solicitud?.centro_costo || orden?.solicitud?.centroCosto" class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9h5l3 3v5l-9 9-8-8zM16 6h.01"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Centro de costos:</strong> {{ (orden?.solicitud?.centroCosto?.nombre) || (orden?.solicitud?.centro_costo?.nombre) || '—' }}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9h5l3 3v5l-9 9-8-8zM16 6h.01"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Marca:</strong> {{ (orden?.solicitud?.marca?.nombre) || '—' }}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-[#1E1C8F] dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Team Leader:</strong> {{ orden?.team_leader?.name ?? 'No asignado' }}</span>
            </div>
            <div v-if="orden?.area" class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-[#1E1C8F] dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
              </svg>
              <span class="text-gray-700 dark:text-slate-200"><strong>Área:</strong> {{ orden.area.nombre }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Items, Avances, Evidencias -->
        <div class="lg:col-span-2 space-y-6">
          
          <!-- Asignar Team Leader (Admin/Coordinador) -->
          <div v-if="can?.asignar_tl" class="bg-white rounded-2xl shadow-lg border-2 border-blue-100 overflow-hidden dark:bg-slate-900/80 dark:border-blue-500/30">
            <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-4 py-2 dark:from-blue-500 dark:to-cyan-500">
              <h2 class="text-base font-bold text-white flex items-center gap-1.5 leading-tight">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Asignar Team Leader
              </h2>
            </div>
            <div class="p-6">
              <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                  <select v-model="tlForm.team_leader_id" 
                          class="w-full px-4 py-3 pr-10 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-400 transition-all duration-200 appearance-none bg-white text-gray-800 font-medium dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100 dark:focus:ring-blue-400/40 dark:focus:border-blue-400/60">
                    <option :value="null">— Selecciona un Team Leader —</option>
                    <option v-for="u in teamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 dark:text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </div>
                </div>
                <button @click="asignarTL" :disabled="tlForm.processing"
                  class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100 dark:from-blue-500 dark:to-cyan-500">
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

          <!-- Ítems de la OT - OT Tradicionales -->
          <div v-if="items.length" class="bg-white rounded-2xl shadow-lg border-2 border-emerald-100 overflow-hidden dark:bg-slate-900/80 dark:border-emerald-500/30">
            <!-- Header Compacto -->
            <div class="bg-emerald-600 px-4 py-2.5 border-b-2 border-emerald-700/30 dark:bg-emerald-700 dark:border-emerald-800">
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                  <div class="w-7 h-7 rounded-lg bg-white/15 backdrop-blur-sm flex items-center justify-center flex-shrink-0 ring-1 ring-white/20">
                    <span class="text-white font-bold text-sm">1</span>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-2 flex-wrap">
                      <h2 class="text-base font-bold text-white leading-none tracking-tight truncate">
                        {{ orden?.servicio?.nombre || 'Servicio' }}
                      </h2>
                      <span class="text-emerald-50/70 text-[11px] font-medium leading-none whitespace-nowrap">
                        {{ unidades?.planeado ?? 0 }} uds.
                      </span>
                    </div>
                  </div>
                </div>
                <div class="bg-white/95 backdrop-blur-sm rounded-md px-2.5 py-1 shadow-sm ring-1 ring-emerald-900/5 flex-shrink-0">
                  <p class="text-[9px] uppercase tracking-wider font-extrabold text-slate-500 leading-none">Subtotal</p>
                  <p class="text-sm font-bold text-slate-900 leading-none mt-0.5">{{ money(orden.subtotal) }}</p>
                </div>
              </div>
            </div>

            <!-- Contenido -->
            <div class="px-4 sm:px-5 py-4 space-y-4">
            
              <!-- KPIs Mini Stat Tiles -->
              <div class="grid grid-cols-2 md:grid-cols-5 gap-2.5">
                <!-- Planeado -->
                <div class="relative bg-gradient-to-br from-blue-50 to-blue-50/50 border-l-4 border-blue-500 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-blue-950/40 dark:to-blue-950/20 dark:border-blue-400 dark:ring-blue-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-blue-600 dark:text-blue-400 leading-none">Planeado</p>
                  <p class="text-2xl font-black text-blue-900 dark:text-blue-100 leading-none mt-1.5">{{ unidades?.planeado ?? 0 }}</p>
                </div>
                
                <!-- Completado -->
                <div class="relative bg-gradient-to-br from-emerald-50 to-emerald-50/50 border-l-4 border-emerald-500 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-emerald-950/40 dark:to-emerald-950/20 dark:border-emerald-400 dark:ring-emerald-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-emerald-600 dark:text-emerald-400 leading-none">Completado</p>
                  <p class="text-2xl font-black text-emerald-900 dark:text-emerald-100 leading-none mt-1.5">{{ unidades?.completado ?? 0 }}</p>
                </div>
                
                <!-- Faltantes -->
                <div class="relative bg-gradient-to-br from-amber-50 to-amber-50/50 border-l-4 border-amber-500 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-amber-950/40 dark:to-amber-950/20 dark:border-amber-400 dark:ring-amber-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-amber-600 dark:text-amber-400 leading-none">Faltantes</p>
                  <p class="text-2xl font-black text-amber-900 dark:text-amber-100 leading-none mt-1.5">{{ unidades?.faltante ?? 0 }}</p>
                </div>
                
                <!-- Pendiente -->
                <div class="relative bg-gradient-to-br from-purple-50 to-purple-50/50 border-l-4 border-purple-500 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-purple-950/40 dark:to-purple-950/20 dark:border-purple-400 dark:ring-purple-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-purple-600 dark:text-purple-400 leading-none">Pendiente</p>
                  <p class="text-2xl font-black text-purple-900 dark:text-purple-100 leading-none mt-1.5">{{ (unidades?.planeado ?? 0) - ((unidades?.completado ?? 0) + (unidades?.faltante ?? 0)) }}</p>
                </div>
                
                <!-- Total -->
                <div class="relative bg-gradient-to-br from-slate-50 to-slate-50/50 border-l-4 border-slate-400 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-slate-800/40 dark:to-slate-800/20 dark:border-slate-500 dark:ring-slate-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-slate-600 dark:text-slate-400 leading-none">Total</p>
                  <p class="text-2xl font-black text-slate-900 dark:text-slate-100 leading-none mt-1.5">{{ unidades?.total ?? 0 }}</p>
                </div>
              </div>
              
              <!-- Tabla de distribución / progreso -->
              <div v-if="items && items.length > 0">
                <!-- Divider con título inline -->
                <div class="flex items-center gap-3 pb-2.5 border-b border-slate-200 dark:border-slate-700">
                  <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  <h5 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Distribución por Producto</h5>
                </div>
                <div class="overflow-x-auto mt-2.5">
                <table class="w-full text-sm">
                  <thead class="bg-slate-100/60 dark:bg-slate-800/60">
                    <tr>
                      <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Descripción</th>
                      <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Planeado</th>
                      <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Completado</th>
                      <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Progreso</th>
                      <th v-if="can?.reportarAvance" class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Registrar</th>
                      <th v-if="can?.reportarAvance" class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-indigo-600 dark:text-indigo-400">Faltantes</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <tr v-for="(it, idx) in items" :key="it.id" 
                        :class="idx % 2 === 0 ? 'bg-white dark:bg-slate-900/20' : 'bg-slate-50/50 dark:bg-slate-800/20'" 
                        class="hover:bg-blue-50/40 dark:hover:bg-blue-950/20 transition-colors">
                      <td class="px-3 py-2">
                        <div class="flex items-center gap-1.5">
                          <div class="w-1 h-1 rounded-full bg-slate-400 dark:bg-slate-500"></div>
                          <span class="font-medium text-slate-900 dark:text-slate-100 text-xs">
                            <span v-if="it?.tamano">{{ it.tamano }}</span>
                            <span v-else>{{ it?.descripcion || 'Sin descripción' }}</span>
                          </span>
                        </div>
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">{{ it?.cantidad_planeada || 0 }}</span>
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
                              :class="(it?.cantidad_real || 0) >= (it?.cantidad_planeada || 0) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'">
                          {{ it?.cantidad_real || 0 }}
                        </span>
                      </td>
                      <td class="px-3 py-2">
                        <div class="flex items-center gap-2">
                          <div class="flex-1 bg-slate-200 rounded-full h-1.5 overflow-hidden dark:bg-slate-700">
                            <div class="h-full rounded-full transition-all"
                                 :class="(it?.cantidad_real || 0) >= (it?.cantidad_planeada || 0) ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : 'bg-gradient-to-r from-amber-500 to-orange-500'"
                                 :style="{ width: Math.min(100, ((it?.cantidad_real || 0) / (it?.cantidad_planeada || 1)) * 100) + '%' }"></div>
                          </div>
                          <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 min-w-[2.5rem] text-right">
                            {{ Math.round(((it?.cantidad_real || 0) / (it?.cantidad_planeada || 1)) * 100) }}%
                          </span>
                        </div>
                      </td>
                      <!-- Captura de avance -->
                      <td v-if="can?.reportarAvance" class="px-3 py-2">
                        <div class="flex items-center justify-center">
                          <input type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*"
                                 :max="restante(it)"
                                 v-model.number="avForm.items[idx].cantidad"
                                 placeholder="0"
                                 @focus="if(avForm.items[idx].cantidad===0 || avForm.items[idx].cantidad===''){ avForm.items[idx].cantidad=''; }"
                                 @input="avForm.clearErrors('items')"
                                 class="w-16 px-2 py-1 border border-slate-300 dark:border-slate-600 rounded-md text-center text-xs font-semibold focus:ring-1 focus:ring-emerald-300 focus:border-emerald-400 dark:bg-slate-900/60 dark:text-slate-100" />
                        </div>
                      </td>
                      <!-- Captura de faltantes -->
                      <td v-if="can?.reportarAvance" class="px-3 py-2">
                        <div class="flex items-center justify-center">
                          <input type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*"
                                 :max="restante(it)"
                                 v-model.number="faltForm.items[idx].faltantes"
                                 placeholder="0"
                                 @focus="if(faltForm.items[idx].faltantes===0 || faltForm.items[idx].faltantes===''){ faltForm.items[idx].faltantes=''; }"
                                 @input="faltForm.clearErrors('items')"
                                 class="w-16 px-2 py-1 border border-slate-300 dark:border-slate-600 rounded-md text-center text-xs font-semibold focus:ring-1 focus:ring-indigo-300 focus:border-indigo-400 dark:bg-slate-900/60 dark:text-slate-100" />
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
                </div>
              </div>
                  
              <!-- Sección: Registrar Avance (Tradicionales) -->
              <div v-if="can?.reportarAvance" class="-mx-4 sm:-mx-5 px-4 sm:px-5 py-3.5 bg-slate-50/80 dark:bg-slate-800/30 border-t border-slate-200 dark:border-slate-700">
                <!-- Título inline con divider -->
                <div class="flex items-center gap-2.5 mb-3">
                  <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <h4 class="font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300">Registrar Avance</h4>
                </div>
                
                <div class="bg-white dark:bg-slate-900/50 rounded-lg p-3 ring-1 ring-slate-200 dark:ring-slate-700">
                  <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                    <!-- Tarifa -->
                    <div class="lg:col-span-3">
                      <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Tarifa</label>
                      <div class="relative">
                        <select v-model="avForm.tarifa_tipo" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm font-semibold focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 dark:bg-slate-900 dark:text-slate-100 transition-all bg-white appearance-none">
                          <option value="NORMAL">🕐 NORMAL</option>
                          <option value="EXTRA">⚡ EXTRA</option>
                          <option value="FIN_DE_SEMANA">🌅 FIN SEMANA</option>
                        </select>
                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                      </div>
                    </div>
                    
                    <!-- Precio Unitario (solo si tarifa != NORMAL) -->
                    <div v-if="avForm.tarifa_tipo !== 'NORMAL'" class="lg:col-span-2">
                      <label class="block text-[10px] font-bold text-orange-600 dark:text-orange-400 mb-1.5 uppercase tracking-wider">Precio</label>
                      <div class="relative">
                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-orange-600 dark:text-orange-400 font-bold text-xs">$</span>
                        <input type="number" step="0.01" min="0" v-model.number="avForm.precio_unitario_manual" placeholder="12.50"
                               class="w-full pl-7 pr-2.5 py-2 border rounded-md text-sm font-bold transition-all
                                      border-orange-300 bg-orange-50/50 text-orange-900 placeholder:text-orange-400/60
                                      dark:border-orange-600 dark:bg-orange-950/30 dark:text-orange-100
                                      focus:border-orange-500 focus:ring-1 focus:ring-orange-200" />
                      </div>
                    </div>
                    
                    <!-- Comentario -->
                    <div :class="avForm.tarifa_tipo !== 'NORMAL' ? 'lg:col-span-5' : 'lg:col-span-7'">
                      <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Comentario <span class="font-normal text-slate-400">(opcional)</span></label>
                      <textarea v-model="avForm.comentario" rows="2" placeholder="Describe el progreso..."
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 dark:bg-slate-900 dark:text-slate-100 transition-all bg-white resize-none min-h-[4rem]"></textarea>
                    </div>
                    
                    <!-- Botón -->
                    <div class="lg:col-span-2 flex items-end">
                      <button @click="registrarAvance" type="button"
                              :disabled="avForm.processing" 
                              class="w-full px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold rounded-lg shadow-md hover:shadow-lg hover:scale-105 transform transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100 text-sm dark:from-emerald-500 dark:to-teal-500">
                        <span v-if="avForm.processing">Guardando...</span>
                        <span v-else>✓ Guardar</span>
                      </button>
                    </div>
                  </div>
                  
                  <p v-if="avForm.errors.items" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ avForm.errors.items }}</p>
                </div>
              </div>
              
              <!-- Sección: Registrar Faltantes (Tradicionales) -->
              <div v-if="can?.reportarAvance && hasFaltantes" class="-mx-4 sm:-mx-5 px-4 sm:px-5 py-3.5 bg-rose-50/50 dark:bg-rose-900/20 border-t border-rose-200 dark:border-rose-700">
                <!-- Título inline con divider -->
                <div class="flex items-center gap-2.5 mb-3">
                  <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                  </svg>
                  <h4 class="font-bold text-xs uppercase tracking-wider text-rose-700 dark:text-rose-300">Registrar Faltantes</h4>
                </div>
                
                <div class="bg-white dark:bg-slate-900/50 rounded-lg p-3 ring-1 ring-rose-200 dark:ring-rose-700">
                  <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                    <!-- Nota -->
                    <div class="lg:col-span-10">
                      <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Nota <span class="font-normal text-slate-400">(opcional)</span></label>
                      <textarea v-model="faltForm.nota" rows="2" placeholder="Describe el motivo de los faltantes..."
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-200 dark:bg-slate-900 dark:text-slate-100 transition-all bg-white resize-none min-h-[4rem]"></textarea>
                    </div>
                    
                    <!-- Botón -->
                    <div class="lg:col-span-2 flex items-end">
                      <button @click="aplicarFaltantes" type="button"
                              :disabled="faltForm.processing" 
                              class="w-full px-4 py-2 bg-gradient-to-r from-rose-600 to-pink-600 text-white font-bold rounded-lg shadow-md hover:shadow-lg hover:scale-105 transform transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100 text-sm dark:from-rose-500 dark:to-pink-500">
                        <span v-if="faltForm.processing">Guardando...</span>
                        <span v-else>⚠ Guardar</span>
                      </button>
                    </div>
                  </div>
                  
                  <p v-if="faltForm.errors.items" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ faltForm.errors.items }}</p>
                </div>
              </div>
              
              <!-- Avances Registrados dentro de la misma card -->
              <div v-if="avances && avances.length > 0" class="-mx-4 sm:-mx-5 px-4 sm:px-5 py-3.5 bg-white dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2 mb-3">
                  <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                  </svg>
                  <h5 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Avances Registrados</h5>
                  <span class="text-xs text-slate-500 dark:text-slate-400">{{ avances.length }} registro(s)</span>
                </div>
                
                <div class="space-y-2.5">
                  <div v-for="(a, idx) in avances" :key="a?.id || idx"
                       class="bg-slate-50 border border-slate-200 rounded-md p-3 hover:border-slate-300 transition-colors dark:bg-slate-800/30 dark:border-slate-700 dark:hover:border-slate-600">
                    <div class="flex items-start justify-between">
                      <div class="flex-1">
                        <!-- Badges y fecha -->
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                          <span v-if="isRechazoComentario(a?.comentario)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300">
                            RECHAZO
                          </span>
                          <span v-else-if="isFaltantesComentario(a?.comentario)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300">
                            FALTANTES
                          </span>
                          <span v-else-if="(a?.isCorregido || a?.es_corregido)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300">
                            CORREGIDO
                          </span>
                          <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300">
                            NORMAL
                          </span>
                          <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ fmtDate(a?.created_at) }}</span>
                          <span class="text-[10px] text-slate-500 dark:text-slate-400">• {{ a?.usuario?.name || 'Usuario' }}</span>
                        </div>
                        
                        <!-- Cantidad e info -->
                        <div class="text-xs text-slate-700 dark:text-slate-300 flex items-center gap-3">
                          <span><strong class="font-semibold">Cant:</strong> {{ a?.cantidad_registrada || a?.cantidad || 0 }}</span>
                          <span v-if="a?.precio_unitario_aplicado">
                            <strong class="font-semibold">P.U.:</strong> 
                            <span class="font-mono">${{ parseFloat(a.precio_unitario_aplicado).toFixed(2) }}</span>
                          </span>
                        </div>
                        
                        <!-- Comentario (solo si no es faltantes) -->
                        <div v-if="a?.comentario && !isFaltantesComentario(a?.comentario)" class="text-xs text-slate-600 dark:text-slate-400 mt-1 italic">
                          "{{ isRechazoComentario(a?.comentario) ? extractRechazoComentario(a.comentario) : a.comentario }}"
                        </div>
                        
                        <!-- Faltantes separados -->
                        <div v-if="isFaltantesComentario(a?.comentario)" class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                          {{ a.comentario }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          

          <!-- Servicios Multi-Servicio -->
          <div v-else-if="esMultiServicio && servicios.length > 0" class="space-y-6">
            <div v-for="(servicio, idx) in servicios" :key="servicio.id" 
                 class="bg-white rounded-2xl shadow-lg border-2 border-emerald-100 overflow-hidden dark:bg-slate-900/80 dark:border-emerald-500/30">
              
              <!-- Header Compacto -->
              <div class="bg-emerald-600 px-4 py-2.5 border-b-2 border-emerald-700/30 dark:bg-emerald-700 dark:border-emerald-800">
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-7 h-7 rounded-lg bg-white/15 backdrop-blur-sm flex items-center justify-center flex-shrink-0 ring-1 ring-white/20">
                      <span class="text-white font-bold text-sm">{{ idx + 1 }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-baseline gap-2 flex-wrap">
                        <h2 class="text-base font-bold text-white leading-none tracking-tight truncate">
                          {{ servicio.servicio?.nombre || 'Servicio' }}
                        </h2>
                        <span class="text-emerald-50/70 text-[11px] font-medium leading-none whitespace-nowrap">
                          {{ servicio.items?.reduce((sum, i) => sum + (i.planeado || 0), 0) || servicio.cantidad }} uds.
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="bg-white/95 backdrop-blur-sm rounded-md px-2.5 py-1 shadow-sm ring-1 ring-emerald-900/5 flex-shrink-0">
                    <p class="text-[9px] uppercase tracking-wider font-extrabold text-slate-500 leading-none">Subtotal</p>
                    <p class="text-sm font-bold text-slate-900 leading-none mt-0.5">{{ money(servicio.subtotal) }}</p>
                  </div>
                </div>
              </div>

              <!-- Contenido -->
              <div class="px-4 sm:px-5 py-4 space-y-4">
              
              <!-- KPIs Mini Stat Tiles -->
              <div class="grid grid-cols-2 md:grid-cols-5 gap-2.5">
                <!-- Planeado -->
                <div class="relative bg-gradient-to-br from-blue-50 to-blue-50/50 border-l-4 border-blue-500 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-blue-950/40 dark:to-blue-950/20 dark:border-blue-400 dark:ring-blue-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-blue-600 dark:text-blue-400 leading-none">Planeado</p>
                  <p class="text-2xl font-black text-blue-900 dark:text-blue-100 leading-none mt-1.5">{{ servicio.planeado || 0 }}</p>
                </div>
                
                <!-- Completado -->
                <div class="relative bg-gradient-to-br from-emerald-50 to-emerald-50/50 border-l-4 border-emerald-500 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-emerald-950/40 dark:to-emerald-950/20 dark:border-emerald-400 dark:ring-emerald-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-emerald-600 dark:text-emerald-400 leading-none">Completado</p>
                  <p class="text-2xl font-black text-emerald-900 dark:text-emerald-100 leading-none mt-1.5">{{ servicio.completado || 0 }}</p>
                </div>
                
                <!-- Faltantes Registrados -->
                <div class="relative bg-gradient-to-br from-amber-50 to-amber-50/50 border-l-4 border-amber-500 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-amber-950/40 dark:to-amber-950/20 dark:border-amber-400 dark:ring-amber-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-amber-600 dark:text-amber-400 leading-none">Faltantes</p>
                  <p class="text-2xl font-black text-amber-900 dark:text-amber-100 leading-none mt-1.5">{{ servicio.faltantes_registrados || 0 }}</p>
                </div>
                
                <!-- Pendiente -->
                <div class="relative bg-gradient-to-br from-purple-50 to-purple-50/50 border-l-4 border-purple-500 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-purple-950/40 dark:to-purple-950/20 dark:border-purple-400 dark:ring-purple-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-purple-600 dark:text-purple-400 leading-none">Pendiente</p>
                  <p class="text-2xl font-black text-purple-900 dark:text-purple-100 leading-none mt-1.5">{{ servicio.pendiente || 0 }}</p>
                </div>
                
                <!-- Total -->
                <div class="relative bg-gradient-to-br from-slate-50 to-slate-50/50 border-l-4 border-slate-400 rounded-lg p-2.5 shadow-sm ring-1 ring-slate-900/5 dark:from-slate-800/40 dark:to-slate-800/20 dark:border-slate-500 dark:ring-slate-500/20">
                  <p class="text-[10px] uppercase tracking-wider font-extrabold text-slate-600 dark:text-slate-400 leading-none">Total</p>
                  <p class="text-2xl font-black text-slate-900 dark:text-slate-100 leading-none mt-1.5">{{ servicio.total || servicio.planeado || 0 }}</p>
                </div>
              </div>
              
              <!-- Tabla de distribución / progreso -->
              <div v-if="servicio.items && servicio.items.length > 0">
                <!-- Divider con título inline -->
                <div class="flex items-center gap-3 pb-2.5 border-b border-slate-200 dark:border-slate-700">
                  <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  <h5 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Distribución por Producto</h5>
                </div>
                <div class="overflow-x-auto mt-2.5">
                <table class="w-full text-sm">
                  <thead class="bg-slate-100/60 dark:bg-slate-800/60">
                    <tr>
                      <th class="px-3 py-2 text-left text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Descripción</th>
                      <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Planeado</th>
                      <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Completado</th>
                      <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-amber-600 dark:text-amber-400">Faltantes</th>
                      <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-purple-600 dark:text-purple-400">Pendiente</th>
                      <th class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Progreso</th>
                      <th v-if="can?.reportarAvance" class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-slate-600 dark:text-slate-400">Registrar</th>
                      <th v-if="can?.reportarAvance" class="px-3 py-2 text-center text-[10px] uppercase tracking-wider font-bold text-indigo-600 dark:text-indigo-400">Faltantes</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                    <tr v-for="(item, itemIdx) in servicio.items" :key="item.id" 
                        :class="itemIdx % 2 === 0 ? 'bg-white dark:bg-slate-900/20' : 'bg-slate-50/50 dark:bg-slate-800/20'" 
                        class="hover:bg-blue-50/40 dark:hover:bg-blue-950/20 transition-colors">
                      <td class="px-3 py-2">
                        <div class="flex items-center gap-1.5">
                          <div class="w-1 h-1 rounded-full bg-slate-400 dark:bg-slate-500"></div>
                          <span class="font-medium text-slate-900 dark:text-slate-100 text-xs">{{ item.descripcion || (item.tamano ? item.tamano.toUpperCase() : 'Distribución') }}</span>
                        </div>
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">{{ item.planeado || 0 }}</span>
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
                              :class="(item.completado || 0) >= (item.planeado || 0) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'">
                          {{ item.completado || 0 }}
                        </span>
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span v-if="(item.faltantes_registrados || 0) > 0" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300">
                          {{ item.faltantes_registrados || 0 }}
                        </span>
                        <span v-else class="text-slate-400 text-xs">—</span>
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
                              :class="(item.pendiente || 0) > 0 ? 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-500/20 dark:text-slate-400'">
                          {{ item.pendiente || 0 }}
                        </span>
                      </td>
                      <td class="px-3 py-2">
                        <div class="flex items-center gap-2">
                          <div class="flex-1 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all bg-emerald-500"
                                 :style="{ width: Math.min(100, item.progreso || 0) + '%' }"></div>
                          </div>
                          <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 min-w-[2.5rem] text-right">
                            {{ item.progreso || 0 }}%
                          </span>
                        </div>
                      </td>
                      <td v-if="can?.reportarAvance" class="px-3 py-2 text-center">
                        <input type="number" min="0" 
                               :max="item.pendiente || 0" 
                               v-model.number="avancesMultiServicio[servicio.id].items.find(i => i.id_item === item.id).cantidad"
                               placeholder="0"
                               class="w-16 px-2 py-1 text-center text-xs font-semibold border border-slate-300 dark:border-slate-600 rounded-md focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 dark:bg-slate-900 dark:text-slate-100 transition-all" />
                      </td>
                      <td v-if="can?.reportarAvance" class="px-3 py-2 text-center">
                        <input type="number" min="0" 
                               :max="item.pendiente || 0" 
                               v-model.number="faltantesMultiServicio[servicio.id].items.find(i => i.id_item === item.id).faltantes"
                               placeholder="0"
                               class="w-16 px-2 py-1 text-center text-xs font-semibold border border-indigo-300 dark:border-indigo-600 rounded-md focus:border-indigo-500 focus:ring-1 focus:ring-indigo-200 dark:bg-slate-900 dark:text-slate-100 transition-all" />
                      </td>
                    </tr>
                  </tbody>
                </table>
                </div>
              </div>
                  
              <!-- Sección: Registrar Avance -->
              <div v-if="can?.reportarAvance" class="-mx-4 sm:-mx-5 px-4 sm:px-5 py-3.5 bg-slate-50/80 dark:bg-slate-800/30 border-t border-slate-200 dark:border-slate-700">
                <!-- Título inline con divider -->
                <div class="flex items-center gap-2.5 mb-3">
                  <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <h4 class="font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300">Registrar Avance</h4>
                </div>
                
                <div class="bg-white dark:bg-slate-900/50 rounded-lg p-3 ring-1 ring-slate-200 dark:ring-slate-700">
                  <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                    <!-- Tarifa -->
                    <div class="lg:col-span-3">
                      <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Tarifa</label>
                      <div class="relative">
                        <select v-model="avancesMultiServicio[servicio.id].tarifa_tipo"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm font-semibold focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 dark:bg-slate-900 dark:text-slate-100 transition-all bg-white appearance-none">
                          <option value="NORMAL">🕐 NORMAL</option>
                          <option value="EXTRA">⚡ EXTRA</option>
                          <option value="FIN_DE_SEMANA">🌅 FIN SEMANA</option>
                        </select>
                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                      </div>
                    </div>
                    
                    <!-- Precio Unitario (solo si tarifa != NORMAL) -->
                    <div v-if="avancesMultiServicio[servicio.id].tarifa_tipo !== 'NORMAL'" class="lg:col-span-2">
                      <label class="block text-[10px] font-bold text-orange-600 dark:text-orange-400 mb-1.5 uppercase tracking-wider">Precio</label>
                      <div class="relative">
                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-orange-600 dark:text-orange-400 font-bold text-xs">$</span>
                        <input type="number" step="0.01" min="0"
                               v-model.number="avancesMultiServicio[servicio.id].precio_unitario_manual"
                               placeholder="12.50"
                               class="w-full pl-7 pr-2.5 py-2 border rounded-md text-sm font-bold transition-all
                                      border-orange-300 bg-orange-50/50 text-orange-900 placeholder:text-orange-400/60
                                      dark:border-orange-600 dark:bg-orange-950/30 dark:text-orange-100
                                      focus:border-orange-500 focus:ring-1 focus:ring-orange-200" />
                      </div>
                    </div>
                    
                    <!-- Comentario -->
                    <div :class="avancesMultiServicio[servicio.id].tarifa_tipo !== 'NORMAL' ? 'lg:col-span-5' : 'lg:col-span-7'">
                      <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Comentario <span class="font-normal text-slate-400">(opcional)</span></label>
                      <textarea v-model="avancesMultiServicio[servicio.id].comentario" rows="2"
                                placeholder="Describe el progreso..."
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 dark:bg-slate-900 dark:text-slate-100 transition-all bg-white resize-none min-h-[4rem]"></textarea>
                    </div>
                    
                    <!-- Botón -->
                    <div class="lg:col-span-2 flex items-end">
                      <button @click="guardarAvanceServicio(servicio.id)" type="button"
                              :disabled="avancesMultiServicio[servicio.id]?.processing"
                              class="w-full px-4 py-2 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 disabled:from-slate-400 disabled:to-slate-500 disabled:cursor-not-allowed text-white font-bold text-sm rounded-md transition-all flex items-center justify-center gap-2 shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1">
                        <svg v-if="!avancesMultiServicio[servicio.id]?.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="hidden sm:inline">{{ avancesMultiServicio[servicio.id]?.processing ? 'Guardando...' : 'Guardar' }}</span>
                      </button>
                    </div>
                  </div>
                  
                  <!-- Sección de Faltantes -->
                  <div v-if="hasFaltantesServicio(servicio.id)" class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                      <!-- Nota -->
                      <div class="lg:col-span-10">
                        <label class="block text-[10px] font-bold text-indigo-600 dark:text-indigo-400 mb-1.5 uppercase tracking-wider">Nota de Faltantes <span class="font-normal text-slate-400">(opcional)</span></label>
                        <textarea v-model="faltantesMultiServicio[servicio.id].nota" rows="2"
                                  placeholder="Describe el motivo de los faltantes..."
                                  class="w-full px-3 py-2 border border-indigo-300 dark:border-indigo-600 rounded-md text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-200 dark:bg-slate-900 dark:text-slate-100 transition-all bg-white resize-none min-h-[3.5rem]"></textarea>
                      </div>
                      
                      <!-- Botón Aplicar Faltantes -->
                      <div class="lg:col-span-2 flex items-end">
                        <button @click="aplicarFaltantesServicio(servicio.id)" type="button"
                                class="w-full px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-bold text-sm rounded-md transition-all flex items-center justify-center gap-2 shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          </svg>
                          <span class="hidden sm:inline">Aplicar</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
                  
                  <!-- Alerta si necesita definir tamaños -->
                  <div v-if="can?.definir_tamanos && serviciosConTamanos[servicio.id]?.pendiente_definir" 
                       class="mt-4 bg-gradient-to-r from-amber-50 via-yellow-50 to-amber-50 border-2 border-amber-300 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                      <svg class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                      </svg>
                      <div class="flex-1">
                        <h4 class="font-bold text-amber-900 mb-1">Este servicio requiere definición de tamaños</h4>
                        <p class="text-sm text-amber-800 mb-3">
                          Total de piezas: <span class="font-bold">{{ serviciosConTamanos[servicio.id].cantidad_total }}</span>
                        </p>
                        <button type="button" @click="servicioSeleccionadoTamanos = servicio.id"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-semibold text-sm transition-colors shadow-md">
                          Definir Tamaños
                        </button>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Avances Registrados (Historial) -->
                  <div v-if="servicio.avances && servicio.avances.length > 0" class="mt-6 rounded-lg overflow-hidden border border-purple-200 dark:border-purple-700/40 shadow-sm">
                    <div class="px-5 py-3 bg-gradient-to-r from-purple-500 to-fuchsia-500 dark:from-purple-600 dark:to-fuchsia-600">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                          </svg>
                          <h3 class="text-sm font-bold text-white tracking-tight">Avances Registrados</h3>
                        </div>
                        <span class="text-xs text-purple-100 font-semibold">{{ servicio.avances.length }} registro(s)</span>
                      </div>
                    </div>
                    
                    <div class="p-4 bg-white dark:bg-slate-900/50 space-y-2.5">
                      <div v-for="avance in servicio.avances" :key="avance.id"
                           class="bg-slate-50 border border-slate-200 rounded-md p-3 hover:border-slate-300 transition-colors dark:bg-slate-800/30 dark:border-slate-700 dark:hover:border-slate-600">
                        <div class="flex items-start justify-between">
                          <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                                    :class="avance.tarifa === 'NORMAL' ? 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300' : 
                                            avance.tarifa === 'EXTRA' ? 'bg-orange-100 text-orange-800 dark:bg-orange-500/20 dark:text-orange-300' : 
                                            'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300'">
                                {{ avance.tarifa || 'NORMAL' }}
                              </span>
                              <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ new Date(avance.created_at).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                              <span class="text-[10px] text-slate-500 dark:text-slate-400">• {{ avance.created_by?.name || 'Usuario' }}</span>
                            </div>
                            <div class="text-xs text-slate-700 dark:text-slate-300 flex items-center gap-3">
                              <span><strong class="font-semibold">Cant:</strong> {{ avance.cantidad_registrada }}</span>
                              <span v-if="avance.precio_unitario_aplicado">
                                <strong class="font-semibold">P.U.:</strong> 
                                <span class="font-mono">${{ parseFloat(avance.precio_unitario_aplicado).toFixed(2) }}</span>
                              </span>
                              <span v-if="avance.tarifa !== 'NORMAL'" class="text-[10px] text-orange-600 dark:text-orange-400 font-semibold">
                                [Tarifa {{ avance.tarifa }}: ${{ parseFloat(avance.precio_unitario_aplicado).toFixed(2) }}]
                              </span>
                            </div>
                            <p v-if="avance.comentario" class="text-xs text-slate-600 mt-2 p-1.5 bg-white rounded border-l-2 border-purple-400 dark:bg-slate-900/30 dark:text-slate-400 dark:border-purple-500">
                              {{ avance.comentario }}
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
              
              </div>
            </div>
          </div>
              
          <!-- Mensaje si no hay items ni servicios -->
          <div v-else class="bg-white rounded-2xl shadow-lg border-2 border-gray-200 overflow-hidden dark:bg-slate-900/80 dark:border-slate-700">
            <div class="p-8 text-center text-gray-500 dark:text-slate-400">
              <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
              </svg>
              <p class="dark:text-slate-200">No hay ítems registrados</p>
            </div>
          </div>

          <!-- Subir Evidencias -->
          <div v-if="can?.reportarAvance" class="bg-white rounded-2xl shadow-lg border-2 border-orange-100 overflow-hidden dark:bg-slate-900/80 dark:border-orange-500/40">
            <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-4 py-2 dark:from-orange-500 dark:to-amber-500">
              <h3 class="text-base font-bold text-white flex items-center gap-1.5 leading-tight">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Subir Evidencias
              </h3>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Asociar a ítem (opcional)</label>
                <div class="relative">
      <select v-model="evForm.id_item" 
        class="w-full px-4 py-3 pr-10 border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-orange-100 focus:border-orange-400 transition-all duration-200 appearance-none bg-white text-gray-800 font-medium dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100 dark:focus:ring-orange-400/40 dark:focus:border-orange-400/60">
                    <option :value="null">— Sin asociar a ítem específico —</option>
                    <option v-for="it in orden.items" :key="it.id" :value="it.id">
                      #{{it.id}} — {{ it.tamano ? ('Tamaño: '+it.tamano) : it.descripcion }}
                    </option>
                  </select>
                  <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 dark:text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </div>
                </div>
              </div>
              
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2 dark:text-slate-200">Seleccionar archivos</label>
            <input type="file" multiple accept="image/*,application/pdf,video/mp4" @change="onPickEvidencias"
              class="block w-full text-sm text-gray-700 file:mr-4 file:py-3 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200 file:transition-all file:duration-200 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-orange-100 dark:text-slate-100 dark:border-slate-700 dark:file:bg-orange-500/20 dark:file:text-orange-200 dark:hover:file:bg-orange-500/30" />
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
          <div v-if="orden?.solicitud?.archivos?.length" class="bg-white rounded-2xl shadow-lg border-2 border-orange-100 overflow-hidden dark:bg-slate-900/80 dark:border-orange-500/40">
            <div class="bg-gradient-to-r from-orange-600 to-amber-600 px-4 py-2 dark:from-orange-500 dark:to-amber-500">
              <h3 class="text-base font-bold text-white flex items-center gap-1.5 leading-tight">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                Archivos de la Solicitud
              </h3>
            </div>
            <div class="p-4 space-y-2">
                <div v-for="archivo in orden.solicitud.archivos" :key="archivo.id" 
                   class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-gradient-to-r from-gray-50 to-orange-50 rounded-xl border border-gray-200 hover:border-orange-300 hover:shadow-md transition-all duration-200 dark:from-slate-900/60 dark:to-orange-500/10 dark:border-slate-700 dark:text-slate-100">
                 <div class="flex items-center gap-4">
                  <div class="bg-gradient-to-br from-orange-500 to-amber-500 p-3 rounded-xl">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div>
                    <div class="font-semibold text-gray-800 dark:text-slate-100">{{ archivo.nombre_original || archivo.path?.split('/').pop() || 'Archivo' }}</div>
                    <div class="text-sm text-gray-500 flex items-center gap-2 mt-1 dark:text-slate-400">
                      <span class="px-2 py-0.5 bg-gray-200 rounded text-xs font-medium dark:bg-slate-700 dark:text-slate-200">
                        {{ archivo.size ? (archivo.size / 1024).toFixed(0) : '0' }} KB
                      </span>
                      <span v-if="archivo.mime" class="text-xs">{{ archivo.mime }}</span>
                    </div>
                  </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <button v-if="canPreview(archivo.mime)"
                      @click="openPreview(archivo)"
                      class="px-4 py-2 rounded-xl bg-gradient-to-r from-gray-600 to-gray-700 text-white font-medium hover:from-gray-700 hover:to-gray-800 shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200 flex items-center gap-2 dark:from-slate-700 dark:to-slate-900 dark:hover:from-slate-600 dark:hover:to-slate-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Ver
                  </button>
                  <a :href="route('archivos.download', archivo.id)" 
                    class="px-4 py-2 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 text-white font-medium hover:from-orange-700 hover:to-amber-700 shadow-md hover:shadow-lg transform hover:scale-105 transition-all duration-200 flex items-center gap-2 dark:from-orange-500 dark:to-amber-500 dark:hover:from-orange-400 dark:hover:to-amber-400">
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
          <div class="bg-white rounded-2xl shadow-lg border-2 border-indigo-100 overflow-hidden dark:bg-slate-900/80 dark:border-indigo-500/40">
            <div class="bg-gradient-to-r from-indigo-600 to-[#1E1C8F] px-4 py-2 dark:from-indigo-500 dark:to-[#1E1C8F]">
              <h3 class="text-base font-bold text-white flex items-center gap-1.5 leading-tight">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Galería de Evidencias
              </h3>
            </div>
            
            <div v-if="vistaEvidencias.length" class="p-5">
              <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="ev in vistaEvidencias" :key="ev.id" 
                     class="bg-gradient-to-br from-gray-50 to-indigo-50 rounded-xl border-2 border-indigo-100 overflow-hidden hover:shadow-lg transition-all duration-200 dark:from-slate-900/60 dark:to-indigo-500/10 dark:border-indigo-500/40">
                  
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
                    <div class="text-xs text-gray-600 mb-2 flex items-center gap-1 dark:text-slate-400">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                      </svg>
                      {{ fmtDate(ev.created_at) }}
                    </div>
                    <div class="text-sm font-semibold text-gray-800 mb-1 flex items-center gap-1 dark:text-slate-100">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                      </svg>
                      {{ ev.usuario?.name || 'Usuario' }}
                    </div>
                        <div v-if="ev.id_item" class="text-xs text-indigo-600 font-medium mb-3 dark:text-indigo-300">Ítem #{{ ev.id_item }}</div>
                    
                    <button v-if="can?.reportarAvance" @click="borrarEvidencia(ev.id)"
                          class="w-full px-3 py-2 bg-red-50 text-red-700 font-semibold rounded-lg hover:bg-red-600 hover:text-white transition-all duration-200 flex items-center justify-center gap-1 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                      Eliminar
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="p-8 text-center text-gray-500 dark:text-slate-400">
              <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <p>No hay evidencias registradas</p>
            </div>
          </div>

        </div>

        <!-- Right Column: Acciones -->
        <div class="lg:col-span-1 space-y-6">

          <!-- Totales / Cotización -->
          <div class="bg-white rounded-2xl shadow-lg border-2 border-emerald-100 overflow-hidden dark:bg-slate-900/80 dark:border-emerald-500/40">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 dark:from-emerald-500 dark:to-teal-500">
              <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-bold text-white flex items-center gap-1.5 leading-tight">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Totales
                </h3>
                <span v-if="cotCalcMode === 'SEGMENTOS'" class="px-2.5 py-1 bg-white/20 border border-white/30 rounded-full text-white text-xs font-bold">
                  Calculado por segmentos
                </span>
                <span v-else class="px-2.5 py-1 bg-white/20 border border-white/30 rounded-full text-white text-xs font-bold">
                  Precio fijo
                </span>
              </div>
            </div>
            <div class="p-5 space-y-3">
              <div class="grid grid-cols-2 gap-3">
                <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl p-3 border-2 border-indigo-200 dark:from-indigo-500/10 dark:to-indigo-500/5 dark:border-indigo-500/40">
                  <div class="text-[11px] font-semibold text-indigo-700 uppercase dark:text-indigo-200">Subtotal</div>
                  <div class="text-lg font-bold text-indigo-900 dark:text-indigo-100">${{ cotSubtotal.toFixed(2) }}</div>
                </div>
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl p-3 border-2 border-amber-200 dark:from-amber-500/10 dark:to-orange-500/10 dark:border-amber-500/40">
                  <div class="text-[11px] font-semibold text-amber-800 uppercase dark:text-amber-200">IVA {{ (ivaRate*100).toFixed(0) }}%</div>
                  <div class="text-lg font-bold text-amber-900 dark:text-amber-100">${{ cotIva.toFixed(2) }}</div>
                </div>
              </div>
              <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-xl p-3 border-2 border-emerald-200 dark:from-emerald-500/10 dark:to-teal-500/10 dark:border-emerald-500/40">
                <div class="text-[11px] font-semibold text-emerald-700 uppercase dark:text-emerald-200">Total</div>
                <div class="text-2xl font-extrabold text-emerald-900 dark:text-emerald-100">${{ cotTotal.toFixed(2) }}</div>
              </div>
              <div v-if="cotCalcMode === 'SEGMENTOS'" class="text-xs text-gray-600 bg-emerald-50 border-2 border-emerald-200 rounded-xl p-3 dark:text-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/40">
                El total refleja la suma de subtotales por tarifa (NORMAL/EXTRA/FIN DE SEMANA) capturados en Producción.
              </div>
            </div>
          </div>

          <!-- Panel: Desglose por Tamaños (pendiente) -->
          <div v-if="orden?.servicio?.usa_tamanos && (!orden?.solicitud?.tamanos || orden?.solicitud?.tamanos.length === 0)"
            class="bg-white rounded-2xl shadow-lg border-2 border-blue-100 overflow-hidden lg:sticky lg:top-6 dark:bg-slate-900/80 dark:border-blue-500/40">
            <div class="bg-blue-700 px-6 py-4 dark:bg-gradient-to-r dark:from-blue-600 dark:to-indigo-600">
              <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h8"/>
                </svg>
                Definir desglose por tamaños
              </h3>
            </div>
            <div class="p-5 space-y-4">
              <div class="text-sm text-blue-800 bg-blue-50 border-2 border-blue-200 rounded-xl p-3 dark:text-blue-200 dark:bg-blue-500/10 dark:border-blue-500/40">
                La suma de piezas por tamaño debe ser <strong>{{ totalAprobado }}</strong>.
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                  <label class="block text-xs font-semibold text-gray-600 uppercase dark:text-slate-300">Chico</label>
                  <input type="number" min="0" v-model.number="tamanosForm.chico" class="w-full px-3 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100" />
                </div>
                <div class="space-y-1">
                  <label class="block text-xs font-semibold text-gray-600 uppercase dark:text-slate-300">Mediano</label>
                  <input type="number" min="0" v-model.number="tamanosForm.mediano" class="w-full px-3 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100" />
                </div>
                <div class="space-y-1">
                  <label class="block text-xs font-semibold text-gray-600 uppercase dark:text-slate-300">Grande</label>
                  <input type="number" min="0" v-model.number="tamanosForm.grande" class="w-full px-3 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100" />
                </div>
                <div class="space-y-1">
                  <label class="block text-xs font-semibold text-gray-600 uppercase dark:text-slate-300">Jumbo</label>
                  <input type="number" min="0" v-model.number="tamanosForm.jumbo" class="w-full px-3 py-2 rounded-lg border-2 border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-100" />
                </div>
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-slate-300">Suma actual:</span>
                  <span class="font-bold" :class="{ 'text-red-600 dark:text-rose-300': sumaTamanos !== totalAprobado, 'text-emerald-700 dark:text-emerald-300': sumaTamanos === totalAprobado }">{{ sumaTamanos }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                  <span class="text-gray-500 dark:text-slate-400">Faltantes:</span>
                  <span :class="{
                          'text-emerald-700 font-semibold dark:text-emerald-300': faltanRaw === 0,
                          'text-orange-600 font-semibold dark:text-amber-300': faltanRaw > 0,
                          'text-red-700 font-semibold dark:text-rose-300': faltanRaw < 0
                        }">
                    <template v-if="faltanRaw > 0">{{ faltanCalc }}</template>
                    <template v-else-if="faltanRaw < 0">Exceso: {{ Math.abs(faltanRaw) }}</template>
                    <template v-else>Listo</template>
                  </span>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-2">
                  <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl p-3 border-2 border-indigo-200 dark:from-indigo-500/10 dark:to-indigo-500/5 dark:border-indigo-500/40">
                    <div class="text-[11px] font-semibold text-indigo-700 uppercase dark:text-indigo-200">Subtotal</div>
                    <div class="text-lg font-bold text-indigo-900 dark:text-indigo-100">${{ subPrev.toFixed(2) }}</div>
                  </div>
                  <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-xl p-3 border-2 border-emerald-200 dark:from-emerald-500/10 dark:to-teal-500/10 dark:border-emerald-500/40">
                    <div class="text-[11px] font-semibold text-emerald-700 uppercase dark:text-emerald-200">Total (IVA {{ (ivaRate*100).toFixed(0) }}%)</div>
                    <div class="text-lg font-bold text-emerald-900 dark:text-emerald-100">${{ totalPrev.toFixed(2) }}</div>
                  </div>
                </div>
              </div>
              <button @click="definirTamanos" :disabled="!tamanosValid"
                      class="w-full px-5 py-3 rounded-xl font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 disabled:opacity-60 disabled:cursor-not-allowed dark:from-blue-500 dark:to-indigo-500">
                Aplicar desglose
              </button>
              <div class="text-xs text-gray-500 dark:text-slate-400">Se recalcularán los precios y totales de la OT y la Solicitud.</div>
            </div>
          </div>
          
          <!-- Acciones de Calidad/Cliente/Facturación -->
          <div class="bg-white rounded-2xl shadow-lg border-2 border-indigo-100 overflow-hidden lg:sticky lg:top-6 dark:bg-slate-900/80 dark:border-indigo-500/40">
            <div class="bg-gradient-to-r from-indigo-600 to-[#1E1C8F] px-4 py-2 dark:from-indigo-500 dark:to-[#1E1C8F]">
              <h3 class="text-base font-bold text-white flex items-center gap-1.5 leading-tight">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Acciones Disponibles
              </h3>
            </div>
            <div class="p-5 space-y-3">
              <!-- Validar Calidad -->
              <button v-if="can?.calidad_validar"
                      @click="validarCalidad" 
                      class="w-full px-5 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 flex items-center justify-center gap-2 dark:from-emerald-500 dark:to-teal-500">
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
                      class="w-full px-5 py-3 bg-gradient-to-r from-gray-800 to-black text-white font-bold rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 flex items-center justify-center gap-2 dark:from-slate-700 dark:to-black">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Autorizar como Cliente
              </button>

              <!-- Ir a Facturación -->
              <a v-if="can?.facturar" :href="urls.facturar"
                 class="block w-full px-5 py-3 bg-gradient-to-r from-indigo-600 to-[#1E1C8F] text-white font-bold text-center rounded-xl shadow-lg hover:shadow-xl hover:scale-105 transform transition-all duration-200 flex items-center justify-center gap-2 dark:from-indigo-500 dark:to-[#1E1C8F]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Ir a Facturación
              </a>

              <!-- Mensaje cuando no hay acciones disponibles -->
                <div v-if="!can?.calidad_validar && !can?.cliente_autorizar && !can?.facturar" 
                   class="text-center py-8 text-gray-500 dark:text-slate-400">
                 <svg class="w-16 h-16 mx-auto mb-3 text-gray-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <p class="font-semibold dark:text-slate-200">No hay acciones disponibles</p>
                <p class="text-sm mt-1 dark:text-slate-400">Las acciones aparecerán según el estado de la OT</p>
              </div>
            </div>
          </div>

          <!-- Motivo de Rechazo / Acciones Correctivas (si existen) -->
          <div v-if="orden?.motivo_rechazo || orden?.acciones_correctivas" class="bg-white rounded-2xl shadow-lg border-2 border-red-100 overflow-hidden dark:bg-slate-900/80 dark:border-rose-500/40">
            <div class="bg-red-600 px-6 py-4 dark:bg-rose-600">
              <h4 class="text-lg font-bold text-white">Rechazo por Calidad</h4>
            </div>
            <div class="p-4 space-y-3">
              <div v-if="orden?.motivo_rechazo">
                <div class="text-sm font-semibold text-red-700 dark:text-rose-300">Motivo</div>
                <div class="text-gray-700 whitespace-pre-wrap dark:text-slate-100">{{ orden.motivo_rechazo }}</div>
              </div>
              <div v-if="orden?.acciones_correctivas">
                <div class="text-sm font-semibold text-red-700 dark:text-rose-300">Acciones Correctivas</div>
                <div class="text-gray-700 whitespace-pre-wrap dark:text-slate-100">{{ orden.acciones_correctivas }}</div>
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
      <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-xl p-6 z-50 dark:bg-slate-900">
        <h3 class="text-lg font-semibold dark:text-slate-100">Rechazar OT #{{ orden.id }}</h3>
        <p class="text-sm text-gray-500 mt-1 dark:text-slate-400">Servicio: <strong>{{ orden.servicio?.nombre }}</strong></p>
        <div v-if="orden?.descripcion_general" class="mt-3 p-3 bg-gray-50 border border-gray-100 rounded-lg dark:bg-slate-800 dark:border-slate-700">
          <div class="text-xs text-gray-500 dark:text-slate-400">Producto/Servicio (general)</div>
          <div class="text-sm font-semibold text-gray-800 dark:text-slate-100">{{ orden.descripcion_general }}</div>
        </div>

        <div class="mt-4">
          <label class="text-sm font-semibold dark:text-slate-100">Motivo del Rechazo</label>
          <textarea v-model="obs" rows="4" class="w-full mt-2 p-3 border rounded-md dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Describe el motivo del rechazo (requerido)"></textarea>
        </div>

        <div class="mt-4">
          <label class="text-sm font-semibold dark:text-slate-100">Acciones Correctivas (opcional)</label>
          <textarea v-model="acciones_correctivas" rows="3" class="w-full mt-2 p-3 border rounded-md dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="Describe acciones sugeridas para corregir la OT"></textarea>
        </div>

        <div class="mt-4 flex justify-end gap-3">
          <button @click="showRechazoModal = false" class="px-4 py-2 rounded bg-gray-200 dark:bg-slate-700 dark:text-slate-100">Cancelar</button>
          <button @click="rechazarCalidad" class="px-4 py-2 rounded bg-red-600 text-white">Enviar Rechazo</button>
        </div>
      </div>
    </div>
    
    <!-- Modal Definir Tamaños para Servicio -->
    <div v-if="servicioSeleccionadoTamanos" class="fixed inset-0 z-[9999] flex items-center justify-center px-4">
      <div class="fixed inset-0 bg-black/50 z-40" @click="servicioSeleccionadoTamanos = null"></div>
      <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-xl p-6 z-50 dark:bg-slate-900">
        <h3 class="text-xl font-bold mb-2 dark:text-slate-100">Definir Tamaños - Servicio #{{ servicioSeleccionadoTamanos }}</h3>
        <p class="text-sm text-gray-600 mb-4 dark:text-slate-400">
          Total a distribuir: <strong>{{ serviciosConTamanos[servicioSeleccionadoTamanos]?.cantidad_total || 0 }}</strong> unidades
        </p>
        
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-semibold mb-2 dark:text-slate-100">Chico</label>
            <input type="number" min="0" v-model.number="tamanosFormServicio[servicioSeleccionadoTamanos].chico"
                   class="w-full px-3 py-2 border rounded-lg dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100" />
            <p v-if="preciosPorServicio[servicioSeleccionadoTamanos]?.chico" class="text-xs text-gray-600 mt-1 dark:text-slate-400">
              Precio: {{ preciosPorServicio[servicioSeleccionadoTamanos].chico }}/ud
            </p>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-2 dark:text-slate-100">Mediano</label>
            <input type="number" min="0" v-model.number="tamanosFormServicio[servicioSeleccionadoTamanos].mediano"
                   class="w-full px-3 py-2 border rounded-lg dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100" />
            <p v-if="preciosPorServicio[servicioSeleccionadoTamanos]?.mediano" class="text-xs text-gray-600 mt-1 dark:text-slate-400">
              Precio: {{ preciosPorServicio[servicioSeleccionadoTamanos].mediano }}/ud
            </p>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-2 dark:text-slate-100">Grande</label>
            <input type="number" min="0" v-model.number="tamanosFormServicio[servicioSeleccionadoTamanos].grande"
                   class="w-full px-3 py-2 border rounded-lg dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100" />
            <p v-if="preciosPorServicio[servicioSeleccionadoTamanos]?.grande" class="text-xs text-gray-600 mt-1 dark:text-slate-400">
              Precio: {{ preciosPorServicio[servicioSeleccionadoTamanos].grande }}/ud
            </p>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-2 dark:text-slate-100">Jumbo</label>
            <input type="number" min="0" v-model.number="tamanosFormServicio[servicioSeleccionadoTamanos].jumbo"
                   class="w-full px-3 py-2 border rounded-lg dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100" />
            <p v-if="preciosPorServicio[servicioSeleccionadoTamanos]?.jumbo" class="text-xs text-gray-600 mt-1 dark:text-slate-400">
              Precio: {{ preciosPorServicio[servicioSeleccionadoTamanos].jumbo }}/ud
            </p>
          </div>
        </div>
        
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg dark:bg-slate-800">
          <div>
            <p class="text-sm text-gray-600 dark:text-slate-400">Suma actual:</p>
            <p class="text-2xl font-bold" :class="tamanosValidServicio(servicioSeleccionadoTamanos) ? 'text-emerald-600' : 'text-rose-600'">
              {{ sumaTamanosServicio(servicioSeleccionadoTamanos) }}
            </p>
          </div>
          <div class="text-right">
            <p class="text-sm text-gray-600 dark:text-slate-400">Objetivo:</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-slate-100">
              {{ serviciosConTamanos[servicioSeleccionadoTamanos]?.cantidad_total || 0 }}
            </p>
          </div>
        </div>
        
        <div class="mt-6 flex justify-end gap-3">
          <button @click="servicioSeleccionadoTamanos = null" 
                  class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:text-slate-100">
            Cancelar
          </button>
          <button @click="definirTamanosServicio(servicioSeleccionadoTamanos)" 
                  :disabled="!tamanosValidServicio(servicioSeleccionadoTamanos)"
                  class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
            Confirmar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>


