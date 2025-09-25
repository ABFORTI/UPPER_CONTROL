<script setup>
import { ref, computed, reactive, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  centros:  { type: Array,  required: true }, // [{id,nombre}]
  centroId: { type: Number, required: true },
  rows:     { type: Array,  required: true }, // filas con precios por servicio
  urls:     { type: Object, required: true }, // { index, guardar }
})

const selCentro = ref(props.centroId)
function changeCentro(){
  const q = new URLSearchParams({ centro: String(selCentro.value) })
  // No preservar estado para forzar refresco de props/rows
  router.get(`${props.urls.index}?${q.toString()}`, {}, { preserveState: false, replace: true })
}

const flashOk = computed(()=> usePage().props?.flash?.ok ?? null)

// Formularios cacheados por servicio
const forms = reactive({})
// Si cambian el centro o las rows, resetea cache de formularios
watch(() => props.centroId, () => { for (const k in forms) delete forms[k] })
watch(() => props.rows,     () => { for (const k in forms) delete forms[k] }, { deep: true })
function getForm(r){
  const key = r.id_servicio
  if (!forms[key]){
    forms[key] = useForm({
      unitario: r.precio_base ?? null,
      chico:   r.tamanos?.chico   ?? null,
      mediano: r.tamanos?.mediano ?? null,
      grande:  r.tamanos?.grande  ?? null,
      jumbo:   r.tamanos?.jumbo   ?? null,
    })
  }
  return forms[key]
}

function guardarFila(r){
  const f = getForm(r)
  const payload = {
    id_centro: selCentro.value,
    items: [
      r.usa_tamanos
        ? {
            id_servicio: r.id_servicio,
            usa_tamanos: true,
            tamanos: {
              chico:   Number(f.chico   ?? 0),
              mediano: Number(f.mediano ?? 0),
              grande:  Number(f.grande  ?? 0),
              jumbo:   Number(f.jumbo   ?? 0),
            },
          }
        : {
            id_servicio: r.id_servicio,
            usa_tamanos: false,
            precio_base: Number(f.unitario ?? 0),
          },
    ],
  }
  f.transform(() => payload).post(props.urls.guardar, { preserveScroll: true })
}
</script>

<template>
  <div class="p-4 md:p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <h1 class="text-2xl md:text-3xl font-semibold font-display tracking-wide">PRECIOS</h1>
      <div class="flex items-center gap-2">
        <span class="text-sm opacity-70">Centro</span>
        <select v-model.number="selCentro" @change="changeCentro" class="border rounded p-2 min-w-[12rem] bg-white">
          <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="flashOk" class="mb-3 rounded-md border border-emerald-200 bg-emerald-50 text-emerald-800 px-3 py-2">
      {{ flashOk }}
    </div>

    <div class="card">
      <div class="px-4 py-3 rounded-t-xl text-white" style="background:#1f2a44">
        <h2 class="font-display font-semibold">CONFIGURACIÓN DE PRECIOS</h2>
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
                <!-- unitario -->
                <td class="table-cell text-right">
                  <template v-if="!r.usa_tamanos">
                    <input type="number" step="0.01" min="0" v-model.number="getForm(r).unitario" class="border p-2 rounded w-28 text-right" />
                  </template>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <!-- tamaños -->
                <template v-if="r.usa_tamanos">
                  <td class="table-cell text-right">
                    <input type="number" step="0.01" min="0" v-model.number="getForm(r).chico" class="border p-2 rounded w-24 text-right" />
                  </td>
                  <td class="table-cell text-right">
                    <input type="number" step="0.01" min="0" v-model.number="getForm(r).mediano" class="border p-2 rounded w-24 text-right" />
                  </td>
                  <td class="table-cell text-right">
                    <input type="number" step="0.01" min="0" v-model.number="getForm(r).grande" class="border p-2 rounded w-24 text-right" />
                  </td>
                  <td class="table-cell text-right">
                    <input type="number" step="0.01" min="0" v-model.number="getForm(r).jumbo" class="border p-2 rounded w-24 text-right" />
                  </td>
                </template>
                <template v-else>
                  <td class="table-cell text-gray-400 text-center">—</td>
                  <td class="table-cell text-gray-400 text-center">—</td>
                  <td class="table-cell text-gray-400 text-center">—</td>
                  <td class="table-cell text-gray-400 text-center">—</td>
                </template>
                <td class="table-cell">
                  <button @click="guardarFila(r)" :disabled="getForm(r).processing" class="btn btn-primary">
                    {{ getForm(r).processing ? 'Guardando…' : 'Guardar' }}
                  </button>
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
  </div>
</template>

<!-- Notas: estado por fila via useForm; si no usa tamaños, solo Unitario; si usa, muestra tamaños. -->
