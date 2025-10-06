<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  centros:  { type: Array,  required: true },
  centroId: { type: Number, required: true },
  rows:     { type: Array,  required: true },
  urls:     { type: Object, required: true },
})

const selCentro = ref(props.centroId)
function changeCentro(){
  const q = new URLSearchParams({ centro: String(selCentro.value) })
  router.get(`${props.urls.index}?${q.toString()}`, {}, { preserveState: false, replace: true })
}

const flashOk = computed(()=> usePage().props?.flash?.ok ?? null)

// Ya no hay formulario aquí; se mueve a Servicios/Create

// Guardar una fila (precios de un servicio) para el centro seleccionado
const saving = ref({})
function saveRow(r){
  const form = useForm({})
  const payload = {
    id_centro: selCentro.value,
    items: [ r.usa_tamanos
      ? { id_servicio: r.id_servicio, usa_tamanos: true, tamanos: {
          chico: Number(r._chico ?? r.tamanos?.chico ?? 0),
          mediano: Number(r._mediano ?? r.tamanos?.mediano ?? 0),
          grande: Number(r._grande ?? r.tamanos?.grande ?? 0),
          jumbo: Number(r._jumbo ?? r.tamanos?.jumbo ?? 0),
        } }
      : { id_servicio: r.id_servicio, usa_tamanos: false, precio_base: Number(r._unitario ?? r.precio_base ?? 0) }
    ]
  }
  saving.value[r.id_servicio] = true
  form.transform(() => payload).post(props.urls.guardar, {
    preserveScroll: true,
    onFinish: () => { saving.value[r.id_servicio] = false },
  })
}

// Formulario para clonar servicios desde otro centro al seleccionado
const cloneForm = useForm({ centro_origen: null })
const otherCentros = computed(() => (props.centros || []).filter(c => Number(c.id) !== Number(selCentro.value)))
watch(otherCentros, (list) => { if (!cloneForm.centro_origen && list?.length) cloneForm.centro_origen = list[0].id }, { immediate: true })
function doClone(){
  if (!cloneForm.centro_origen) return
  cloneForm.transform(() => ({
    centro_origen: Number(cloneForm.centro_origen),
    centro_destino: Number(selCentro.value),
  })).post(props.urls.clonar, { preserveScroll: true })
}

// Eliminar un servicio del centro actual
function removeRow(r){
  if (!confirm(`¿Eliminar "${r.servicio}" de este centro?`)) return
  const form = useForm({})
  form.transform(() => ({ id_centro: Number(selCentro.value), id_servicio: Number(r.id_servicio) }))
      .post(props.urls.eliminar, { preserveScroll: true })
}
</script>

<template>
  <div class="p-4 md:p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <h1 class="text-2xl md:text-3xl font-semibold font-display tracking-wide">SERVICIOS</h1>
      <div class="flex items-center gap-2">
        <span class="text-sm opacity-70">Centro</span>
        <select v-model.number="selCentro" @change="changeCentro" class="border rounded p-2 min-w-[12rem] bg-white">
          <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
      </div>
    </div>

    <div v-if="flashOk" class="mb-3 rounded-md border border-emerald-200 bg-emerald-50 text-emerald-800 px-3 py-2">
      {{ flashOk }}
    </div>

    <!-- Botón Agregar que lleva al formulario -->
    <div class="mb-4 flex justify-end">
      <a :href="urls.create" class="btn btn-primary">Agregar servicio</a>
    </div>

    <!-- (Clonación se muestra abajo de la tabla) -->

    <!-- Estado vacío con acciones -->
    <div v-if="!rows?.length" class="card mb-4">
      <div class="px-4 py-3 rounded-t-xl text-white" style="background:#1f2a44">
        <h2 class="font-display font-semibold">No hay servicios en este centro</h2>
      </div>
      <div class="card-section flex flex-col gap-3">
        <p class="text-gray-700">Empieza agregando un servicio o clona desde otro centro.</p>
        <div class="flex flex-wrap gap-2 items-center">
          <a :href="urls.create" class="btn btn-primary">Agregar servicio</a>
          <div class="flex items-center gap-2">
            <select v-model.number="cloneForm.centro_origen" class="border p-2 rounded">
              <option v-for="c in otherCentros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
            </select>
            <button class="btn btn-secondary" @click="doClone" :disabled="cloneForm.processing || !cloneForm.centro_origen">
              {{ cloneForm.processing ? 'Clonando…' : 'Clonar desde centro' }}
            </button>
          </div>
          <div v-if="cloneForm.errors.centro_origen" class="text-red-600 text-sm">{{ cloneForm.errors.centro_origen }}</div>
        </div>
      </div>
    </div>

  <!-- Lista/edición de precios (reutilizada) -->
  <div v-if="rows?.length" class="card">
      <div class="px-4 py-3 rounded-t-xl text-white" style="background:#1f2a44">
        <h2 class="font-display font-semibold">Precios por servicio</h2>
      </div>
      <div class="card-section">
        <div class="overflow-auto rounded-lg border">
          <table class="min-w-full text-sm">
            <thead class="table-head">
              <tr>
                <th class="table-cell">Servicio</th>
                <th class="table-cell">Tipo</th>
                <th class="table-cell text-right">Unitario</th>
                <th class="table-cell text-right">Chico</th>
                <th class="table-cell text-right">Mediano</th>
                <th class="table-cell text-right">Grande</th>
                <th class="table-cell text-right">Jumbo</th>
                <th class="table-cell">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rows" :key="r.id_servicio" class="odd:bg-white even:bg-gray-50">
                <td class="table-cell font-medium">{{ r.servicio }}</td>
                <td class="table-cell">
                  <span class="badge" :class="r.usa_tamanos ? 'badge-gray' : 'badge-green'">
                    {{ r.usa_tamanos ? 'Por tamaños' : 'Unitario' }}
                  </span>
                </td>
                <td class="table-cell text-right">
                  <template v-if="!r.usa_tamanos">
                    <input type="number" step="0.01" min="0" v-model.number="r._unitario" class="border p-2 rounded w-28 text-right" />
                  </template>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <template v-if="r.usa_tamanos">
                  <td class="table-cell text-right"><input type="number" step="0.01" min="0" v-model.number="r._chico" class="border p-2 rounded w-24 text-right" /></td>
                  <td class="table-cell text-right"><input type="number" step="0.01" min="0" v-model.number="r._mediano" class="border p-2 rounded w-24 text-right" /></td>
                  <td class="table-cell text-right"><input type="number" step="0.01" min="0" v-model.number="r._grande" class="border p-2 rounded w-24 text-right" /></td>
                  <td class="table-cell text-right"><input type="number" step="0.01" min="0" v-model.number="r._jumbo" class="border p-2 rounded w-24 text-right" /></td>
                </template>
                <template v-else>
                  <td class="table-cell text-gray-400 text-center">—</td>
                  <td class="table-cell text-gray-400 text-center">—</td>
                  <td class="table-cell text-gray-400 text-center">—</td>
                  <td class="table-cell text-gray-400 text-center">—</td>
                </template>
                <td class="table-cell flex gap-2">
                  <button class="btn btn-primary" @click="saveRow(r)" :disabled="saving[r.id_servicio]">
                    {{ saving[r.id_servicio] ? 'Guardando…' : 'Guardar' }}
                  </button>
                  <button class="btn btn-danger bg-red-600 hover:bg-red-700 text-white border-red-700" @click="removeRow(r)">Eliminar</button>
                </td>
              </tr>
              <tr v-if="!rows?.length">
                <td colspan="8" class="table-cell text-center text-gray-500">Sin servicios</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Barra de clonación debajo de la tabla -->
    <div v-if="rows?.length && otherCentros.length" class="mt-4 flex items-center gap-2">
      <span class="text-sm opacity-70">Clonar desde</span>
      <select v-model.number="cloneForm.centro_origen" class="border p-2 rounded">
        <option v-for="c in otherCentros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
      </select>
      <button class="btn btn-secondary" @click="doClone" :disabled="cloneForm.processing || !cloneForm.centro_origen">
        {{ cloneForm.processing ? 'Clonando…' : 'Clonar (solo faltantes)' }}
      </button>
      <div v-if="cloneForm.errors.centro_origen" class="text-red-600 text-sm">{{ cloneForm.errors.centro_origen }}</div>
    </div>
  </div>
</template>

<!-- script adicional eliminado; todo vive en <script setup> -->
