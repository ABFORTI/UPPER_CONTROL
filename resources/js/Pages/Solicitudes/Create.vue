<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  servicios: Array, // [{id,nombre,usa_tamanos}]
  urls: Object      // { store: 'http://localhost/upper-control/public/solicitudes' }
})

// Estado con useForm (maneja POST, errores y "processing")
const form = useForm({
  id_servicio: props.servicios?.[0]?.id ?? null,
  descripcion: '',
  cantidad: 1, // para SIN tamaños
  notas: '',
  tamanos: { chico: 0, mediano: 0, grande: 0 }, // para CON tamaños
  archivos: [] // opcional
})

const servicioSel  = computed(() => props.servicios.find(s => s.id === Number(form.id_servicio)))
const usaTamanos   = computed(() => !!servicioSel.value?.usa_tamanos)
const totalTamanos = computed(() =>
  Number(form.tamanos.chico||0) + Number(form.tamanos.mediano||0) + Number(form.tamanos.grande||0)
)

watch(usaTamanos, (v) => {
  if (v) form.cantidad = totalTamanos.value || 0
  else   form.tamanos = { chico:0, mediano:0, grande:0 }
})
watch(() => ({...form.tamanos}), () => { if (usaTamanos.value) form.cantidad = totalTamanos.value })

function onFiles(e){ form.archivos = Array.from(e.target.files || []) }

// Usamos un nombre distinto para evitar colisión con el método nativo submit()
function handleSubmit() {
  // Si hay archivos -> multipart
  if (form.archivos.length) {
    const fd = new FormData()
    fd.append('id_servicio', Number(form.id_servicio))
    fd.append('descripcion', form.descripcion)
    fd.append('cantidad',    usaTamanos.value ? totalTamanos.value : Number(form.cantidad||0))
    fd.append('notas',       form.notas || '')
    if (usaTamanos.value) {
      fd.append('tamanos', JSON.stringify({
        chico: Number(form.tamanos.chico||0),
        mediano: Number(form.tamanos.mediano||0),
        grande: Number(form.tamanos.grande||0),
      }))
    }
    form.archivos.forEach(f => fd.append('archivos[]', f))
    form.post(props.urls.store, { forceFormData: true })
  } else {
    // Sin archivos -> transform enviando justo lo necesario
    form.transform(d => ({
      id_servicio: Number(d.id_servicio),
      descripcion: d.descripcion,
      cantidad:    usaTamanos.value ? totalTamanos.value : Number(d.cantidad||0),
      notas:       d.notas,
      tamanos:     usaTamanos.value ? {
        chico: Number(d.tamanos.chico||0),
        mediano: Number(d.tamanos.mediano||0),
        grande: Number(d.tamanos.grande||0),
      } : null,
    })).post(props.urls.store, { preserveScroll: true })
  }
}
</script>

<template>
  <div class="p-6 max-w-2xl">
    <h1 class="text-2xl font-bold mb-4">Nueva solicitud</h1>

    <form @submit.prevent="handleSubmit" class="space-y-4">
      <div>
        <label class="block mb-1">Servicio</label>
        <select v-model="form.id_servicio" class="border p-2 rounded w-full">
          <option v-for="s in servicios" :key="s.id" :value="s.id">
            {{ s.nombre }}{{ s.usa_tamanos ? ' (con tamaños)' : '' }}
          </option>
        </select>
        <p v-if="form.errors.id_servicio" class="text-red-600 text-sm">{{ form.errors.id_servicio }}</p>
      </div>

      <div>
        <label class="block mb-1">Descripción del producto</label>
        <input v-model="form.descripcion" class="border p-2 rounded w-full" placeholder="Ej. Teléfonos" />
        <p v-if="form.errors.descripcion" class="text-red-600 text-sm">{{ form.errors.descripcion }}</p>
      </div>

      <div v-if="!usaTamanos">
        <label class="block mb-1">Cantidad</label>
        <input type="number" min="1" v-model.number="form.cantidad" class="border p-2 rounded w-full" />
        <p v-if="form.errors.cantidad" class="text-red-600 text-sm">{{ form.errors.cantidad }}</p>
      </div>

      <div v-else class="border rounded p-3">
        <div class="font-medium mb-2">Cantidades por tamaño</div>
        <div class="grid gap-2 md:grid-cols-3">
          <div>
            <label class="block text-sm mb-1">Chico</label>
            <input type="number" min="0" v-model.number="form.tamanos.chico" class="border p-2 rounded w-full"/>
          </div>
          <div>
            <label class="block text-sm mb-1">Mediano</label>
            <input type="number" min="0" v-model.number="form.tamanos.mediano" class="border p-2 rounded w-full"/>
          </div>
          <div>
            <label class="block text-sm mb-1">Grande</label>
            <input type="number" min="0" v-model.number="form.tamanos.grande" class="border p-2 rounded w-full"/>
          </div>
        </div>
        <div class="mt-2 text-sm">Total: <b>{{ totalTamanos }}</b></div>
        <p v-if="form.errors.tamanos" class="text-red-600 text-sm">{{ form.errors.tamanos }}</p>
      </div>

      <div>
        <label class="block mb-1">Notas</label>
        <textarea v-model="form.notas" rows="3" class="border p-2 rounded w-full"></textarea>
      </div>

      <div>
        <label class="block mb-1">Adjuntos</label>
        <input type="file" multiple @change="onFiles" />
      </div>

      <button type="submit" :disabled="form.processing"
              class="px-4 py-2 bg-black text-white rounded disabled:opacity-60">
        {{ form.processing ? 'Guardando…' : 'Guardar' }}
      </button>

      <div v-if="$page.props?.flash?.ok" class="p-2 bg-emerald-50 border border-emerald-200 rounded">
        {{$page.props.flash.ok}}
      </div>
    </form>
  </div>
</template>
