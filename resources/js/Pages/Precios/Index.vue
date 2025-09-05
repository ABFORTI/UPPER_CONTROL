<script setup>
import { reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  centros: Array,
  centroId: Number,
  rows: Array,
  urls: Object
})

const centroSel = reactive({ id: props.centroId })
const items = reactive(props.rows.map(r => ({
  id_servicio: r.id_servicio,
  servicio: r.servicio,
  usa_tamanos: r.usa_tamanos ? true : false,
  precio_base: r.precio_base ?? 0,
  tamanos: { chico: r.tamanos?.chico ?? 0, mediano: r.tamanos?.mediano ?? 0, grande: r.tamanos?.grande ?? 0 },
})))

function cambiarCentro() {
  router.get(props.urls.index, { centro: centroSel.id }, { preserveState:false, replace:true })
}

function guardar() {
  const payload = {
    id_centro: centroSel.id,
    items: items.map(i => ({
      id_servicio: i.id_servicio,
      usa_tamanos: !!i.usa_tamanos,
      precio_base: i.usa_tamanos ? null : Number(i.precio_base ?? 0),
      tamanos: i.usa_tamanos ? {
        chico: Number(i.tamanos.chico ?? 0),
        mediano: Number(i.tamanos.mediano ?? 0),
        grande: Number(i.tamanos.grande ?? 0),
      } : null
    }))
  }
  router.post(props.urls.guardar, payload)
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Catálogo de Precios por Centro</h1>

    <!-- Selector de centro -->
    <div class="mb-4 max-w-sm">
      <label class="block text-sm mb-1">Centro de trabajo</label>
      <select v-model="centroSel.id" @change="cambiarCentro" class="border p-2 rounded w-full">
        <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
      </select>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm border">
        <thead>
        <tr class="bg-gray-50 text-left">
          <th class="p-2">Servicio</th>
          <th class="p-2">Usa tamaños</th>
          <th class="p-2">Precio base</th>
          <th class="p-2">Chico</th>
          <th class="p-2">Mediano</th>
          <th class="p-2">Grande</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="i in items" :key="i.id_servicio" class="border-t">
          <td class="p-2 font-medium">{{ i.servicio }}</td>
          <td class="p-2">
            <span class="text-xs px-2 py-1 rounded bg-gray-100" v-if="i.usa_tamanos">Sí</span>
            <span class="text-xs px-2 py-1 rounded bg-gray-100" v-else>No</span>
          </td>

          <td class="p-2">
            <input type="number" step="0.01" class="border p-2 rounded w-36"
                   v-model.number="i.precio_base" :disabled="i.usa_tamanos"/>
          </td>

          <td class="p-2">
            <input type="number" step="0.01" class="border p-2 rounded w-28"
                   v-model.number="i.tamanos.chico" :disabled="!i.usa_tamanos"/>
          </td>
          <td class="p-2">
            <input type="number" step="0.01" class="border p-2 rounded w-28"
                   v-model.number="i.tamanos.mediano" :disabled="!i.usa_tamanos"/>
          </td>
          <td class="p-2">
            <input type="number" step="0.01" class="border p-2 rounded w-28"
                   v-model.number="i.tamanos.grande" :disabled="!i.usa_tamanos"/>
          </td>
        </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      <button @click="guardar" class="px-4 py-2 bg-black text-white rounded">Guardar cambios</button>
    </div>

    <div v-if="$page.props?.flash?.ok" class="mt-3 p-2 bg-emerald-50 border border-emerald-200 rounded">
      {{$page.props.flash.ok}}
    </div>
  </div>
</template>
