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
})


const form = useForm({
id_centrotrabajo: null,
id_servicio: '',
descripcion: '',
cantidad: 1,
tamanos: { chico:0, mediano:0, grande:0, jumbo:0 },
notas: '',
})
// Inicializar centro si aplica
if (props.canChooseCentro) {
  form.id_centrotrabajo = props.selectedCentroId || (props.centros[0]?.id ?? null)
}


// Servicios disponibles según el centro
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

const servicio = computed(() => filteredServicios.value.find(s => s.id === Number(form.id_servicio)) || null)
const usaTamanos = computed(() => !!servicio.value?.usa_tamanos)
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
  if (usaTamanos.value) {
    return (Number(form.tamanos.chico||0)   * puTam.value.chico)
         + (Number(form.tamanos.mediano||0) * puTam.value.mediano)
         + (Number(form.tamanos.grande||0)  * puTam.value.grande)
         + (Number(form.tamanos.jumbo||0)   * puTam.value.jumbo)
  }
  return Number(form.cantidad||0) * precioUnitario.value
})
const ivaMonto = computed(() => subtotal.value * (props.iva || 0))
const total = computed(() => subtotal.value + ivaMonto.value)


watch(usaTamanos, v => { if (v) form.cantidad = 0; else form.tamanos = {chico:0,mediano:0,grande:0,jumbo:0} })


function guardar(){
const payload = {
id_centrotrabajo: form.id_centrotrabajo,
id_servicio: form.id_servicio,
descripcion: form.descripcion,
notas: form.notas,
}
if (usaTamanos.value) payload.tamanos = {
  chico:  +form.tamanos.chico  || 0,
  mediano:+form.tamanos.mediano|| 0,
  grande: +form.tamanos.grande || 0,
  jumbo:  +form.tamanos.jumbo  || 0,
}
else payload.cantidad = +form.cantidad||0


form.transform(() => payload).post(props.urls.store, { preserveScroll:true })
}
</script>

<template>
  <div class="p-4 md:p-6">
    <div class="card">
      <div class="px-4 py-3 rounded-t-xl" style="background:#1f2a44">
        <h2 class="text-white font-display font-semibold">AGREGAR SOLICITUD DE SERVICIO</h2>
      </div>
      <div class="card-section">
        <div class="grid md:grid-cols-3 gap-4">
          <!-- Izquierda -->
          <div class="md:col-span-2 grid gap-4">
            <div v-if="canChooseCentro" class="grid md:grid-cols-3 gap-3">
              <div class="md:col-span-1">
                <label class="block text-sm mb-1">Centro de trabajo</label>
                <select v-model="form.id_centrotrabajo" class="border p-2 rounded w-full">
                  <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.prefijo }} — {{ c.nombre }}</option>
                </select>
                <p v-if="form.errors.id_centrotrabajo" class="text-red-600 text-sm">{{ form.errors.id_centrotrabajo }}</p>
              </div>
            </div>
            <div class="grid md:grid-cols-3 gap-3">
              <div class="md:col-span-1">
                <label class="block text-sm mb-1">Servicio</label>
                <select v-model="form.id_servicio" class="border p-2 rounded w-full">
                  <option value="">— Selecciona —</option>
                  <option v-for="s in filteredServicios" :key="s.id" :value="s.id">{{ s.nombre }} <span v-if="s.usa_tamanos">(tamaños)</span></option>
                </select>
                <p v-if="form.errors.id_servicio" class="text-red-600 text-sm">{{ form.errors.id_servicio }}</p>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm mb-1">Descripción del producto</label>
                <input v-model="form.descripcion" class="border p-2 rounded w-full" placeholder="Ej. Monitores / Teléfonos" />
                <p v-if="form.errors.descripcion" class="text-red-600 text-sm">{{ form.errors.descripcion }}</p>
              </div>
            </div>
            <!-- Cantidad por pieza -->
            <div v-if="!usaTamanos" class="grid md:grid-cols-4 gap-3 items-end">
              <div class="md:col-span-1">
                <label class="block text-sm mb-1">Cantidad</label>
                <input type="number" min="1" v-model.number="form.cantidad" class="border p-2 rounded w-full" />
                <p v-if="form.errors.cantidad" class="text-red-600 text-sm">{{ form.errors.cantidad }}</p>
              </div>
              <div class="md:col-span-3 grid gap-2 text-sm">
                <div class="p-3 bg-gray-50 rounded border">Precio unitario: <strong>${{ precioUnitario.toFixed(2) }}</strong></div>
                <div class="p-3 bg-gray-50 rounded border">Subtotal: <strong>${{ subtotal.toFixed(2) }}</strong></div>
                <div class="p-3 bg-gray-50 rounded border">IVA ({{ (iva*100).toFixed(0) }}%): <strong>${{ ivaMonto.toFixed(2) }}</strong></div>
                <div class="p-3 bg-gray-100 rounded border font-medium">Total: <strong>${{ total.toFixed(2) }}</strong></div>
              </div>
            </div>
            <!-- Cantidades por tamaño -->
            <div v-else>
              <label class="block text-sm mb-2">Cantidades por tamaño</label>
              <div class="grid md:grid-cols-5 gap-3">
                <div>
                  <span class="text-xs opacity-70">Chico</span>
                  <input type="number" min="0" v-model.number="form.tamanos.chico" class="border p-2 rounded w-full" />
                  <div v-if="preciosServicio" class="text-xs text-gray-500 mt-1">$ {{ puTam.chico.toFixed(2) }}</div>
                </div>
                <div>
                  <span class="text-xs opacity-70">Mediano</span>
                  <input type="number" min="0" v-model.number="form.tamanos.mediano" class="border p-2 rounded w-full" />
                  <div v-if="preciosServicio" class="text-xs text-gray-500 mt-1">$ {{ puTam.mediano.toFixed(2) }}</div>
                </div>
                <div>
                  <span class="text-xs opacity-70">Grande</span>
                  <input type="number" min="0" v-model.number="form.tamanos.grande" class="border p-2 rounded w-full" />
                  <div v-if="preciosServicio" class="text-xs text-gray-500 mt-1">$ {{ puTam.grande.toFixed(2) }}</div>
                </div>
                <div>
                  <span class="text-xs opacity-70">Jumbo</span>
                  <input type="number" min="0" v-model.number="form.tamanos.jumbo" class="border p-2 rounded w-full" />
                  <div v-if="preciosServicio" class="text-xs text-gray-500 mt-1">$ {{ puTam.jumbo.toFixed(2) }}</div>
                </div>
                <div class="flex items-end">
                  <div class="p-3 bg-gray-50 rounded border text-sm w-full">Total piezas: <strong>{{ totalTamanos }}</strong></div>
                </div>
              </div>
              <p v-if="form.errors.tamanos" class="text-red-600 text-sm mt-1">{{ form.errors.tamanos }}</p>

              <!-- Totales por importes -->
              <div class="grid md:grid-cols-3 gap-3 mt-3">
                <div class="p-3 bg-gray-50 rounded border text-sm">Subtotal: <strong>${{ subtotal.toFixed(2) }}</strong></div>
                <div class="p-3 bg-gray-50 rounded border text-sm">IVA ({{ (iva*100).toFixed(0) }}%): <strong>${{ ivaMonto.toFixed(2) }}</strong></div>
                <div class="p-3 bg-gray-100 rounded border text-sm font-medium">Total: <strong>${{ total.toFixed(2) }}</strong></div>
              </div>
            </div>
            <!-- Notas -->
            <div>
              <label class="block text-sm mb-1">Notas</label>
              <textarea v-model="form.notas" rows="3" class="border p-2 rounded w-full" placeholder="Comentarios adicionales"></textarea>
            </div>
          </div>
          <!-- Sidebar Resumen -->
          <aside class="grid gap-4">
            <div class="border rounded p-4 bg-white">
              <h3 class="font-semibold mb-2">Resumen</h3>
              <ul class="text-sm space-y-1">
                <li><span class="opacity-60">Servicio:</span> <strong>{{ servicio?.nombre || '—' }}</strong></li>
                <li><span class="opacity-60">Tipo:</span> <strong>{{ usaTamanos ? 'Con tamaños' : 'Pieza' }}</strong></li>
                <li v-if="!usaTamanos"><span class="opacity-60">Cantidad:</span> <strong>{{ form.cantidad || 0 }}</strong></li>
                <li v-else>
                  <div class="opacity-60">Tamaños</div>
                  <div class="grid grid-cols-4 gap-2">
                    <div>Ch: <strong>{{ form.tamanos.chico || 0 }}</strong></div>
                    <div>Md: <strong>{{ form.tamanos.mediano || 0 }}</strong></div>
                    <div>Gr: <strong>{{ form.tamanos.grande || 0 }}</strong></div>
                    <div>Jb: <strong>{{ form.tamanos.jumbo || 0 }}</strong></div>
                  </div>
                  <div class="mt-1">Total: <strong>{{ totalTamanos }}</strong></div>
                </li>
                <li class="mt-2 pt-2 border-t">
                  <div>Subtotal: <strong>${{ subtotal.toFixed(2) }}</strong></div>
                  <div>IVA ({{ (iva*100).toFixed(0) }}%): <strong>${{ ivaMonto.toFixed(2) }}</strong></div>
                  <div>Total: <strong>${{ total.toFixed(2) }}</strong></div>
                </li>
              </ul>
            </div>
            <button @click="guardar" :disabled="form.processing" class="btn btn-primary">
              {{ form.processing ? 'Guardando…' : 'AGREGAR' }}
            </button>
          </aside>
        </div>
      </div>
    </div>
  </div>
</template>