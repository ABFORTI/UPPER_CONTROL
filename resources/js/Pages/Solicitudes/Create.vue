<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'


const props = defineProps({
  servicios: { type: Array, required: true },
  precios:   { type: Object, required: false, default: () => ({}) },
  preciosPorCentro: { type: Object, required: false, default: () => ({}) },
  centros:   { type: Array, required: false, default: () => ([])} ,
  canChooseCentro: { type: Boolean, required: false, default: false },
  selectedCentroId: { type: Number, required: false, default: null },
  iva:       { type: Number, required: false, default: 0.16 },
  urls:      { type: Object, required: true }, // { store }
  areas:     { type: Array, required: false, default: () => ([]) },
  areasPorCentro: { type: Object, required: false, default: () => ({}) },
  centrosCostos: { type: Array, required: false, default: () => ([]) },
  centrosCostosPorCentro: { type: Object, required: false, default: () => ({}) },
  marcas: { type: Array, required: false, default: () => ([]) },
  marcasPorCentro: { type: Object, required: false, default: () => ({}) },
})


const form = useForm({
id_centrotrabajo: null,
id_servicio: '',
descripcion: '',
id_area: null,
cantidad: 1,
// Modo diferido: ya no capturamos por tamaño en la solicitud
tamanos: { chico:0, mediano:0, grande:0, jumbo:0 },
notas: '',
archivos: [],
id_centrocosto: null,
id_marca: null,
})
// Inicializar centro: si puede elegir usa el selectedCentroId o primer centro disponible;
// si no puede elegir (cliente), usa igualmente selectedCentroId (su centro asignado)
if (props.canChooseCentro) {
  form.id_centrotrabajo = props.selectedCentroId || (props.centros[0]?.id ?? null)
} else {
  form.id_centrotrabajo = props.selectedCentroId
}


// Servicios disponibles según el centro (solo los que tienen precio definido)
const filteredServicios = computed(() => {
  if (props.canChooseCentro) {
    const cid = Number(form.id_centrotrabajo)
    const map = props.preciosPorCentro?.[cid] || {}
    const ids = Object.keys(map).map(n => Number(n))
    return props.servicios.filter(s => ids.includes(Number(s.id)))
  }
  const ids = Object.keys(props.precios || {}).map(n => Number(n))
  return props.servicios.filter(s => ids.includes(Number(s.id)))
})

// Áreas disponibles según el centro
const filteredAreas = computed(() => {
  if (props.canChooseCentro) {
    const cid = Number(form.id_centrotrabajo)
    return props.areasPorCentro?.[cid] || []
  }
  return props.areas || []
})

// Centros de Costos y Marcas por centro
const filteredCentrosCostos = computed(() => {
  if (props.canChooseCentro) {
    const cid = Number(form.id_centrotrabajo)
    return props.centrosCostosPorCentro?.[cid] || []
  }
  return props.centrosCostos || []
})
const filteredMarcas = computed(() => {
  if (props.canChooseCentro) {
    const cid = Number(form.id_centrotrabajo)
    return props.marcasPorCentro?.[cid] || []
  }
  return props.marcas || []
})

const servicio = computed(() => filteredServicios.value.find(s => s.id === Number(form.id_servicio)) || null)
// MODO PER-CENTRO: detectar si para el centro elegido existen precios por tamaño
function serviceUsesSizesInCentro(serviceId){
  if (!serviceId) return false
  
  let data = null
  if (props.canChooseCentro) {
    const cid = Number(form.id_centrotrabajo)
    if (!cid) return false
    data = props.preciosPorCentro?.[cid]?.[serviceId]
  } else {
    // Cliente: usar directamente el mapa de precios (ya filtrado por su centro)
    data = props.precios?.[serviceId]
  }
  
  if (!data) return false
  const t = data.tamanos || {}
  return ['chico','mediano','grande','jumbo'].some(k => t[k] !== undefined && t[k] !== null)
}
const usaTamanos = computed(() => serviceUsesSizesInCentro(Number(form.id_servicio)))
const totalTamanos = computed(() => (
  Number(form.tamanos.chico||0)
  + Number(form.tamanos.mediano||0)
  + Number(form.tamanos.grande||0)
  + Number(form.tamanos.jumbo||0)
))

// Precios del servicio seleccionado según el centro elegido (si aplica)
const preciosServicio = computed(() => {
  if (props.canChooseCentro) {
    const cid = Number(form.id_centrotrabajo)
    const sid = Number(form.id_servicio)
    return props.preciosPorCentro?.[cid]?.[sid] || null
  }
  return props.precios?.[Number(form.id_servicio)] || null
})

// Precio unitario para servicios sin tamaños
const precioUnitario = computed(() => {
  if (!preciosServicio.value || usaTamanos.value) return 0
  const p = Number(preciosServicio.value.precio_base || 0)
  return isFinite(p) ? p : 0
})

// Precios unitarios por tamaño
const puTam = computed(() => {
  const base = Number(preciosServicio.value?.precio_base || 0) || 0
  const t = preciosServicio.value?.tamanos || {}
  return {
    chico:  Number(t.chico  ?? base) || 0,
    mediano:Number(t.mediano?? base) || 0,
    grande: Number(t.grande ?? base) || 0,
    jumbo:  Number(t.jumbo  ?? base) || 0,
  }
})

// Subtotal, IVA y total
const subtotal = computed(() => {
  if (!servicio.value) return 0
  // Para servicios por tamaños (flujo diferido), no calcular precios en la solicitud
  if (usaTamanos.value) return 0
  return Number(form.cantidad||0) * precioUnitario.value
})
const ivaMonto = computed(() => subtotal.value * (props.iva || 0))
const total = computed(() => subtotal.value + ivaMonto.value)


// En el nuevo flujo diferido: si usa tamaños, seguimos capturando 'cantidad'
watch(usaTamanos, v => { if (!v) form.tamanos = {chico:0,mediano:0,grande:0,jumbo:0} })


function guardar(){
const payload = {
id_centrotrabajo: form.id_centrotrabajo,
id_servicio: form.id_servicio,
descripcion: form.descripcion,
notas: form.notas,
archivos: form.archivos,
id_centrocosto: form.id_centrocosto,
id_marca: form.id_marca,
id_area: form.id_area,
}
if (usaTamanos.value) payload.tamanos = {
  // Ya no se envían tamaños; sólo total de piezas
}
else payload.cantidad = +form.cantidad||0

// Para usa_tamanos, enviar cantidad total
if (usaTamanos.value) payload.cantidad = +form.cantidad||0


form.transform(() => payload).post(props.urls.store, { 
  preserveScroll:true,
  forceFormData: true, // Para enviar archivos
})
}

function handleFiles(e) {
  form.archivos = Array.from(e.target.files || [])
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-upper-50 to-upper-100 px-4 pt-2 pb-3 md:px-8 md:pt-3 md:pb-4">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-3 md:mb-4">
        <div class="flex items-center gap-3 mb-0">
          <div class="p-2 rounded-xl shadow-lg" style="background: linear-gradient(135deg, #1E1C8F 0%, #14134F 100%);">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <div>
            <h1 class="text-xl md:text-2xl font-bold text-#333333">Nueva Solicitud de Servicio</h1>
            <p class="text-#333333 text-sm mt-0">Complete el formulario para crear su solicitud</p>
          </div>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-6">
        <!-- Formulario Principal -->
        <div class="lg:col-span-2 space-y-6">
          
          <!-- Sección: Información del Servicio -->
          <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="px-6 py-4" style="background: linear-gradient(90deg, #1E1C8F 0%, #19176F 100%);">
              <h2 class="text-white font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Información del Servicio
              </h2>
            </div>
            <div class="p-6 space-y-5">
              <!-- Centro de Trabajo -->
              <div v-if="canChooseCentro" class="form-group">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Centro de Trabajo
                  </span>
                </label>
                <select 
                  v-model="form.id_centrotrabajo" 
                  class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all outline-none bg-gray-50 hover:bg-white"
                >
                  <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.prefijo }} — {{ c.nombre }}</option>
                </select>
                <p v-if="form.errors.id_centrotrabajo" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ form.errors.id_centrotrabajo }}
                </p>
              </div>

              <!-- Centro de Costos (Obligatorio) y Marca (Opcional) -->
              <div class="grid md:grid-cols-2 gap-5">
                <div class="form-group">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <span class="flex items-center gap-2">
                      <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"/>
                      </svg>
                      Centro de Costos
                      <span class="text-red-500">*</span>
                    </span>
                  </label>
                  <select 
                    v-model="form.id_centrocosto" 
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all outline-none bg-gray-50 hover:bg-white"
                  >
                    <option :value="null">— Seleccione —</option>
                    <option v-for="cc in filteredCentrosCostos" :key="cc.id" :value="cc.id">{{ cc.nombre }}</option>
                  </select>
                  <p v-if="form.errors.id_centrocosto" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ form.errors.id_centrocosto }}
                  </p>
                </div>

                <div class="form-group">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <span class="flex items-center gap-2">
                      <svg class="w-4 h-4 text-fuchsia-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                      </svg>
                      Marca
                      <span class="text-xs text-gray-500 font-normal">(Opcional)</span>
                    </span>
                  </label>
                  <select 
                    v-model="form.id_marca" 
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-fuchsia-500 focus:ring-4 focus:ring-fuchsia-100 transition-all outline-none bg-gray-50 hover:bg-white"
                  >
                    <option :value="null">— Seleccione —</option>
                    <option v-for="m in filteredMarcas" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                  </select>
                  <p v-if="form.errors.id_marca" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ form.errors.id_marca }}
                  </p>
                </div>
              </div>

              <!-- Servicio y Descripción -->
              <div class="grid md:grid-cols-2 gap-5">
                <div class="form-group">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <span class="flex items-center gap-2">
                      <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                      </svg>
                      Tipo de Servicio
                      <span class="text-red-500">*</span>
                    </span>
                  </label>
                  <select 
                    v-model="form.id_servicio" 
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all outline-none bg-gray-50 hover:bg-white"
                  >
                    <option value="">— Seleccione un servicio —</option>
                    <option v-for="s in filteredServicios" :key="s.id" :value="s.id">
                      {{ s.nombre }} {{ serviceUsesSizesInCentro(s.id) ? '(Por tamaños)' : '(Unitario)' }}
                    </option>
                  </select>
                  <p v-if="form.errors.id_servicio" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ form.errors.id_servicio }}
                  </p>
                </div>

                <div class="form-group">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <span class="flex items-center gap-2">
                      <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                      </svg>
                      Descripción del Producto
                    </span>
                  </label>
                  <input 
                    v-model="form.descripcion" 
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all outline-none bg-gray-50 hover:bg-white" 
                    placeholder="Nombre del producto"
                  />
                  <p v-if="form.errors.descripcion" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ form.errors.descripcion }}
                  </p>
                </div>
              </div>

              <!-- Área (Opcional): el cliente puede pre-seleccionarla -->
              <div class="form-group">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    Área
                    <span class="text-xs text-gray-500 font-normal">(Opcional)</span>
                  </span>
                </label>
                <select
                  v-model="form.id_area"
                  class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all outline-none bg-gray-50 hover:bg-white"
                >
                  <option :value="null">— Sin seleccionar —</option>
                  <option v-for="a in filteredAreas" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                </select>
                <p v-if="form.errors.id_area" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ form.errors.id_area }}
                </p>
              </div>
            </div>
          </div>

          <!-- Sección: Cantidades y Precios -->
          <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="px-6 py-4" style="background: linear-gradient(90deg, #7ED321 0%, #5CB415 100%);">
              <h2 class="text-white font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Cantidades y Precios
              </h2>
            </div>
            <div class="p-6">
              <!-- Cantidad Simple -->
              <div v-if="!usaTamanos">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div class="rounded-xl border-2 border-gray-200 bg-gray-50 p-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                      <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                        </svg>
                        Cantidad
                        <span class="text-red-500">*</span>
                      </span>
                    </label>
                    <input 
                      type="number" 
                      min="1" 
                      v-model.number="form.cantidad" 
                      class="w-full px-4 py-3 text-lg font-semibold rounded-xl border-2 border-gray-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 transition-all outline-none bg-white hover:bg-white" 
                    />
                    <p v-if="form.errors.cantidad" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                      </svg>
                      {{ form.errors.cantidad }}
                    </p>
                  </div>

                  <div class="bg-gradient-to-br from-upper-50 to-upper-100 rounded-xl p-4 border-2 border-upper-200">
                    <div class="text-xs font-semibold text-blue-700 uppercase mb-1">Precio Unitario</div>
                    <div class="text-2xl font-bold text-blue-900">${{ precioUnitario.toFixed(2) }}</div>
                  </div>

                  <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-xl p-4 border-2 border-emerald-100">
                    <div class="text-xs font-semibold text-emerald-700 uppercase mb-1">Subtotal</div>
                    <div class="text-2xl font-bold text-emerald-900">${{ subtotal.toFixed(2) }}</div>
                  </div>
                </div>
              </div>

              <!-- Modo por tamaños (flujo diferido): capturar solo total -->
              <div v-else>
                <div class="mb-4 bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                  <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-blue-800">
                      <p class="font-semibold mb-1">Servicio por tamaños</p>
                      <p>Por ahora solo captura el <strong>total de piezas</strong>. El desglose por tamaños y el precio final se calcularán al <strong>terminar la OT</strong>.</p>
                    </div>
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div class="rounded-xl border-2 border-gray-200 bg-gray-50 p-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Total de piezas <span class="text-red-500">*</span></label>
                    <input type="number" min="1" v-model.number="form.cantidad"
                           class="w-full px-4 py-3 text-lg font-semibold rounded-xl border-2 border-gray-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 transition-all outline-none bg-white hover:bg-white" />
                    <p v-if="form.errors.cantidad" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                      </svg>
                      {{ form.errors.cantidad }}
                    </p>
                  </div>

                  <div class="bg-gradient-to-br from-upper-50 to-upper-100 rounded-xl p-4 border-2 border-upper-200">
                    <div class="text-xs font-semibold text-blue-700 uppercase mb-1">Subtotal</div>
                    <div class="text-2xl font-bold text-blue-900">$0.00</div>
                  </div>

                  <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-xl p-4 border-2 border-emerald-100">
                    <div class="text-xs font-semibold text-emerald-700 uppercase mb-1">Total (con IVA)</div>
                    <div class="text-2xl font-bold text-emerald-900">$0.00</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sección: Información Adicional -->
          <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
    <div class="px-6 py-4" style="background: linear-gradient(90deg, #FF7A00 0%, #E86A00 100%);">
              <h2 class="text-white font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                Información Adicional
              </h2>
            </div>
            <div class="p-6 space-y-5">
              <!-- Archivos Adjuntos -->
              <div class="form-group">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    Archivos Adjuntos
                    <span class="text-xs text-gray-500 font-normal">(Opcional)</span>
                  </span>
                </label>
                <div class="relative">
                  <input 
                    type="file" 
                    @change="handleFiles"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.xls"
                    class="w-full px-4 py-3 rounded-xl border-2 border-dashed border-gray-300 hover:border-[#1E1C8F] focus:border-[#1E1C8F] focus:ring-4 focus:ring-[#1E1C8F] transition-all outline-none bg-gray-50 hover:bg-white text-sm
                    file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-orange-600 file:to-orange-500 file:text-white hover:file:from-[#FF8A2A] hover:file:to-[#FF6A00] file:cursor-pointer file:transition-all"
                  />
                </div>
                <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Máximo 10 MB por archivo. Formatos: PDF, imágenes, Word, Excel
                </p>
                <p v-if="form.errors.archivos" class="text-red-600 text-sm mt-2 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                  </svg>
                  {{ form.errors.archivos }}
                </p>
                
                <!-- Lista de Archivos -->
                  <div v-if="form.archivos.length" class="mt-4 space-y-2">
                  <div v-for="(file, i) in form.archivos" :key="i" class="flex items-center gap-3 p-3 bg-gradient-to-r from-upper-50 to-upper-100 border-2 border-upper-200 rounded-xl">
                    <div class="p-2 bg-white rounded-lg">
                      <svg class="w-5 h-5 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-gray-900 truncate">{{ file.name }}</p>
                      <p class="text-xs text-gray-500">{{ (file.size / 1024).toFixed(0) }} KB</p>
                    </div>
                    <div class="flex items-center gap-2">
                      <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Notas -->
              <div class="form-group">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-[#1E1C8F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Notas y Comentarios
                    <span class="text-xs text-gray-500 font-normal">(Opcional)</span>
                  </span>
                </label>
                <textarea 
                  v-model="form.notas" 
                  rows="4" 
                  class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-[#1E1C8F] focus:ring-4 focus:ring-upper-50 transition-all outline-none bg-gray-50 hover:bg-white resize-none" 
                  placeholder="Agregue cualquier información adicional que considere relevante..."
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar: Resumen -->
        <aside class="lg:col-span-1">
          <div class="sticky top-8 space-y-4">
            <!-- Resumen de la Solicitud -->
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
              <div class="px-5 py-4" style="background: linear-gradient(90deg, #0F0F30 0%, #14134F 100%);">
                <h3 class="text-white font-bold flex items-center gap-2">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  Resumen
                </h3>
              </div>
              <div class="p-5 space-y-4">
                <!-- Servicio -->
                <div class="pb-3 border-b border-gray-200">
                  <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Servicio</div>
                  <div class="text-sm font-bold text-gray-900">{{ servicio?.nombre || 'No seleccionado' }}</div>
                </div>

                <!-- Tipo -->
                <div class="pb-3 border-b border-gray-200">
                  <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Tipo</div>
                  <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold"
                    :class="usaTamanos ? 'bg-upper-50 text-[#1E1C8F]' : 'bg-blue-100 text-blue-700'"
                  >
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    {{ usaTamanos ? 'Por tamaños' : 'Por pieza' }}
                  </div>
                </div>

                <!-- Cantidades -->
                <div v-if="!usaTamanos" class="pb-3 border-b border-gray-200">
                  <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Cantidad</div>
                  <div class="text-2xl font-bold text-gray-900">{{ form.cantidad || 0 }}</div>
                </div>
                <div v-else class="pb-3 border-b border-gray-200">
                  <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Total de piezas</div>
                  <div class="text-2xl font-bold text-gray-900">{{ form.cantidad || 0 }}</div>
                  <div class="mt-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-[11px] text-blue-800">
                    El precio se calculará al finalizar la OT con la separación por tamaños.
                  </div>
                </div>

                <!-- Totales -->
                <div class="space-y-2 pt-2">
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="font-semibold text-gray-900">${{ (usaTamanos ? 0 : subtotal).toFixed(2) }}</span>
                  </div>
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">IVA ({{ (iva*100).toFixed(0) }}%)</span>
                    <span class="font-semibold text-gray-900">${{ (usaTamanos ? 0 : ivaMonto).toFixed(2) }}</span>
                  </div>
                  <div class="pt-3 border-t-2 border-gray-300">
                    <div class="flex items-center justify-between">
                      <span class="text-base font-bold text-gray-700">Total</span>
                      <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                        ${{ (usaTamanos ? 0 : total).toFixed(2) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Botón de Guardar -->
            <button 
              @click="guardar" 
              :disabled="form.processing"
              class="w-full py-4 px-6 rounded-xl font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:ring-4 focus:ring-blue-300 shadow-lg shadow-blue-500/50 hover:shadow-xl hover:shadow-blue-600/50 transition-all duration-200 transform hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center gap-2"
            >
              <svg v-if="!form.processing" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ form.processing ? 'Creando Solicitud...' : 'Crear Solicitud' }}</span>
            </button>

            <!-- Ayuda -->
            <div class="bg-upper-50 border-2 border-upper-200 rounded-xl p-4">
              <div class="flex items-start gap-3">
                <div class="p-2 bg-blue-100 rounded-lg">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
                <div class="flex-1">
                  <h4 class="text-sm font-semibold text-blue-900 mb-1">¿Necesitas ayuda?</h4>
                  <p class="text-xs text-blue-700">Complete todos los campos requeridos (*) para crear su solicitud. El equipo revisará y procesará su solicitud a la brevedad.</p>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </div>
</template>