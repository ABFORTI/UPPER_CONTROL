<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  clientes: Array,
  servicios: Array,
  precios: Object,
  centros: { type: Array, default: () => [] },
  canChooseCentro: { type: Boolean, default: false },
  selectedCentroId: { type: Number, default: null },
  iva: { type: Number, default: 0.16 },
  urls: Object,
  areas: { type: Array, default: () => [] },
  centrosCostos: { type: Array, default: () => [] },
  marcas: { type: Array, default: () => [] },
})

const form = useForm({
  id_centrotrabajo: props.canChooseCentro ? (props.selectedCentroId || (props.centros?.[0]?.id ?? null)) : null,
  id_cliente: null,
  id_centrocosto: null,
  id_marca: null,
  id_area: null,
  notas: '',
  items: [
    { descripcion: '', cantidad: 1, notas: '', servicios: [ { id_servicio: '', cantidad: null, tamano: '' } ] }
  ]
})

const ready = ref(false)
onMounted(() => { ready.value = true })

watch(() => form.id_centrotrabajo, (newVal, oldVal) => {
  if (!props.canChooseCentro) return
  if (!ready.value) return
  if (!newVal || newVal === oldVal) return
  router.get(route('cotizaciones.create'), { centro: newVal }, { preserveScroll: true, replace: true })
})

// Servicios disponibles según precios configurados (solo los que tienen entry en map)
const filteredServicios = computed(() => {
  const ids = Object.keys(props.precios || {}).map(n => Number(n))
  return (props.servicios || []).filter(s => ids.includes(Number(s.id)))
})

function addItem(){
  form.items.push({ descripcion: '', cantidad: 1, notas: '', servicios: [ { id_servicio: '', cantidad: null, tamano: '' } ] })
}
function removeItem(i){
  if (form.items.length <= 1) return
  form.items.splice(i, 1)
}

function addServicio(item){
  item.servicios.push({ id_servicio: '', cantidad: null, tamano: '' })
}
function removeServicio(item, j){
  if (item.servicios.length <= 1) return
  item.servicios.splice(j, 1)
}

function precioUnitario(servicioId, tamano){
  const sid = Number(servicioId)
  if (!sid) return 0
  const data = props.precios?.[sid]
  if (!data) return 0
  const base = Number(data.precio_base || 0) || 0
  const t = data.tamanos || {}
  const key = String(tamano || '').toLowerCase().trim()
  if (key && t[key] !== undefined && t[key] !== null) return Number(t[key]) || base
  return base
}

const totals = computed(() => {
  let sub = 0
  for (const item of form.items) {
    for (const svc of (item.servicios || [])) {
      const cantidad = Number(svc.cantidad || item.cantidad || 0) || 0
      const pu = precioUnitario(svc.id_servicio, svc.tamano)
      sub += pu * cantidad
    }
  }
  const iva = sub * (props.iva || 0)
  return { subtotal: sub, iva, total: sub + iva }
})

function submit(){
  // Limpiar campos vacíos
  const payload = {
    id_centrotrabajo: form.id_centrotrabajo,
    id_cliente: form.id_cliente,
    id_centrocosto: form.id_centrocosto,
    id_marca: form.id_marca,
    id_area: form.id_area,
    notas: form.notas,
    items: form.items.map(it => ({
      descripcion: it.descripcion,
      cantidad: Number(it.cantidad || 1),
      notas: it.notas || null,
      servicios: (it.servicios || []).map(s => ({
        id_servicio: s.id_servicio,
        cantidad: s.cantidad === null || s.cantidad === '' ? null : Number(s.cantidad),
        tamano: s.tamano || null,
      }))
    }))
  }

  form.transform(() => payload).post(props.urls.store, { preserveScroll: true })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-upper-50 to-upper-100 px-4 pt-2 pb-6 md:px-8">
    <div class="max-w-6xl mx-auto space-y-4">
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="px-6 py-4" style="background: linear-gradient(90deg, #1E1C8F 0%, #19176F 100%);">
          <h1 class="text-white font-semibold text-xl">Nueva Cotización</h1>
          <p class="text-white/90 text-sm">Crea una cotización y envíala al cliente</p>
        </div>

        <div class="p-6 space-y-6">
          <div v-if="Object.keys(form.errors||{}).length" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
            <div class="font-semibold mb-1">Revisa los campos marcados.</div>
            <div class="text-sm">Hay errores de validación en el formulario.</div>
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div v-if="canChooseCentro">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Centro de Trabajo</label>
              <select v-model="form.id_centrotrabajo" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50">
                <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.prefijo }} — {{ c.nombre }}</option>
              </select>
              <p v-if="form.errors.id_centrotrabajo" class="text-red-600 text-sm mt-1">{{ form.errors.id_centrotrabajo }}</p>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Cliente</label>
              <select v-model="form.id_cliente" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50">
                <option :value="null">— Seleccione —</option>
                <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.name }} ({{ c.email }})</option>
              </select>
              <p v-if="form.errors.id_cliente" class="text-red-600 text-sm mt-1">{{ form.errors.id_cliente }}</p>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Centro de Costos *</label>
              <select v-model="form.id_centrocosto" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50">
                <option :value="null">— Seleccione —</option>
                <option v-for="cc in centrosCostos" :key="cc.id" :value="cc.id">{{ cc.nombre }}</option>
              </select>
              <p v-if="form.errors.id_centrocosto" class="text-red-600 text-sm mt-1">{{ form.errors.id_centrocosto }}</p>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Marca (opcional)</label>
              <select v-model="form.id_marca" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50">
                <option :value="null">— Ninguna —</option>
                <option v-for="m in marcas" :key="m.id" :value="m.id">{{ m.nombre }}</option>
              </select>
              <p v-if="form.errors.id_marca" class="text-red-600 text-sm mt-1">{{ form.errors.id_marca }}</p>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Área (opcional)</label>
              <select v-model="form.id_area" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50">
                <option :value="null">— Ninguna —</option>
                <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre }}</option>
              </select>
              <p v-if="form.errors.id_area" class="text-red-600 text-sm mt-1">{{ form.errors.id_area }}</p>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Notas (opcional)</label>
              <textarea v-model="form.notas" rows="3" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50" />
              <p v-if="form.errors.notas" class="text-red-600 text-sm mt-1">{{ form.errors.notas }}</p>
            </div>
          </div>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-bold text-gray-800">Ítems</h2>
              <button type="button" @click="addItem" class="px-4 py-2 rounded-lg bg-slate-800 text-white font-semibold">+ Ítem</button>
            </div>

            <div v-for="(item, i) in form.items" :key="i" class="border border-slate-200 rounded-2xl p-4 bg-white shadow-sm">
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1 grid md:grid-cols-3 gap-3">
                  <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Descripción *</label>
                    <input v-model="item.descripcion" class="w-full px-4 py-2 rounded-xl border-2 border-gray-200" placeholder="Producto/Ítem" />
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Cantidad *</label>
                    <input v-model="item.cantidad" type="number" min="1" class="w-full px-4 py-2 rounded-xl border-2 border-gray-200" />
                  </div>
                  <div class="md:col-span-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Notas (opcional)</label>
                    <input v-model="item.notas" class="w-full px-4 py-2 rounded-xl border-2 border-gray-200" placeholder="Notas del ítem" />
                  </div>
                </div>

                <button type="button" @click="removeItem(i)" class="px-3 py-2 rounded-lg bg-red-600 text-white font-semibold">Eliminar</button>
              </div>

              <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                  <h3 class="font-semibold">Servicios del ítem</h3>
                  <button type="button" @click="addServicio(item)" class="px-3 py-1.5 rounded-lg bg-blue-600 text-white font-semibold">+ Servicio</button>
                </div>

                <div v-for="(svc, j) in item.servicios" :key="j" class="grid md:grid-cols-4 gap-3 items-end border border-slate-100 rounded-xl p-3 mb-2">
                  <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Servicio *</label>
                    <select v-model="svc.id_servicio" class="w-full px-4 py-2 rounded-xl border-2 border-gray-200 bg-gray-50">
                      <option value="">— Seleccione —</option>
                      <option v-for="s in filteredServicios" :key="s.id" :value="s.id">{{ s.nombre }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Cantidad (opcional)</label>
                    <input v-model="svc.cantidad" type="number" min="1" class="w-full px-4 py-2 rounded-xl border-2 border-gray-200" placeholder="= ítem" />
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tamaño (opcional)</label>
                    <select v-model="svc.tamano" class="w-full px-4 py-2 rounded-xl border-2 border-gray-200 bg-gray-50">
                      <option value="">— N/A —</option>
                      <option value="chico">chico</option>
                      <option value="mediano">mediano</option>
                      <option value="grande">grande</option>
                      <option value="jumbo">jumbo</option>
                    </select>
                  </div>

                  <div class="md:col-span-4 flex items-center justify-between">
                    <div class="text-sm text-slate-600">
                      PU: <strong>{{ precioUnitario(svc.id_servicio, svc.tamano).toFixed(2) }}</strong>
                    </div>
                    <button type="button" @click="removeServicio(item, j)" class="px-3 py-1.5 rounded-lg bg-slate-200 text-slate-800 font-semibold">Quitar</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
              <div class="text-sm text-slate-600">Subtotal: <strong>{{ totals.subtotal.toFixed(2) }}</strong></div>
              <div class="text-sm text-slate-600">IVA: <strong>{{ totals.iva.toFixed(2) }}</strong></div>
              <div class="text-lg text-slate-900">Total: <strong>{{ totals.total.toFixed(2) }}</strong></div>
            </div>
            <button type="button" @click="submit" :disabled="form.processing" class="px-6 py-3 rounded-xl bg-[#1E1C8F] text-white font-bold disabled:opacity-60">Guardar Cotización</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
