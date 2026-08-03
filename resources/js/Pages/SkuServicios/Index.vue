<script setup>
import { ref } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'

const page = usePage()
const props = defineProps({
  data: Object, // paginator
  filters: Object, // { centro, sku }
  centros: Array,
  servicios: Array,
  urls: Object, // { index, store }
})

const centroFiltro = ref(props.filters?.centro || '')
const skuFiltro = ref(props.filters?.sku || '')

function aplicarFiltros() {
  const params = {}
  if (centroFiltro.value) params.centro = centroFiltro.value
  if (skuFiltro.value) params.sku = skuFiltro.value
  router.get(props.urls.index, params, { preserveState: true, replace: true })
}

const defaultCentro = props.centros?.length === 1 ? props.centros[0].id : (page.props.auth?.user?.centro_trabajo_id || null)

const form = useForm({
  id_centrotrabajo: defaultCentro,
  sku: '',
  id_servicio: '',
})

function submit() {
  form.post(props.urls.store, {
    preserveScroll: true,
    onSuccess: () => {
      form.sku = ''
      form.id_servicio = ''
    },
  })
}

function eliminar(item) {
  if (!confirm(`¿Eliminar el SKU "${item.sku}" del catálogo?`)) return
  router.delete(route('sku-servicios.destroy', item.id), { preserveScroll: true })
}

function toPage(link) {
  if (link.url) router.get(link.url, {}, { preserveState: true })
}
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-6">
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm p-4 sm:p-6">
      <h1 class="font-display text-xl sm:text-2xl font-semibold tracking-wide uppercase text-slate-900 dark:text-slate-100">
        Catálogo SKU &rarr; Servicio
      </h1>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Registra qué servicio corresponde a cada SKU. Cuando un cliente suba un Excel de productos con un SKU aquí registrado,
        el sistema le asignará automáticamente el servicio correspondiente.
      </p>

      <!-- Formulario de alta -->
      <form @submit.prevent="submit" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-4">
        <select v-model.number="form.id_centrotrabajo" class="h-10 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100">
          <option value="" disabled>Centro de trabajo</option>
          <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
        <input v-model.trim="form.sku" type="text" placeholder="SKU"
               class="h-10 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100" />
        <select v-model.number="form.id_servicio" class="h-10 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100">
          <option value="" disabled>Servicio</option>
          <option v-for="s in servicios" :key="s.id" :value="s.id">{{ s.nombre }}</option>
        </select>
        <button type="submit" :disabled="form.processing"
                class="h-10 inline-flex items-center justify-center rounded-md px-4 text-sm font-semibold text-white bg-[#1A73E8] hover:bg-[#1557b0] transition-colors disabled:opacity-60">
          Guardar
        </button>
      </form>
      <p v-if="form.errors.id_centrotrabajo || form.errors.sku || form.errors.id_servicio" class="mt-1 text-sm text-red-600">
        {{ form.errors.id_centrotrabajo || form.errors.sku || form.errors.id_servicio }}
      </p>

      <!-- Filtros -->
      <div class="mt-6 flex flex-wrap items-center gap-2">
        <select v-model="centroFiltro" @change="aplicarFiltros" class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100">
          <option value="">Todos los centros</option>
          <option v-for="c in centros" :key="c.id" :value="c.id">{{ c.nombre }}</option>
        </select>
        <input v-model="skuFiltro" @change="aplicarFiltros" type="text" placeholder="Buscar SKU"
               class="h-9 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-sm text-slate-800 dark:text-slate-100" />
      </div>

      <!-- Tabla -->
      <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
        <table class="w-full text-sm">
          <thead class="bg-slate-800 text-white uppercase text-xs">
            <tr>
              <th class="px-3 py-2 text-left">SKU</th>
              <th class="px-3 py-2 text-left">Centro</th>
              <th class="px-3 py-2 text-left">Servicio</th>
              <th class="px-3 py-2 text-center w-24">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in data.data" :key="item.id" class="border-t border-slate-100 dark:border-slate-800 even:bg-slate-50 dark:even:bg-slate-800/40">
              <td class="px-3 py-2 font-semibold">{{ item.sku }}</td>
              <td class="px-3 py-2">{{ item.centro?.nombre }}</td>
              <td class="px-3 py-2">{{ item.servicio?.nombre }}</td>
              <td class="px-3 py-2 text-center">
                <button @click="eliminar(item)" class="text-red-600 hover:underline text-xs font-semibold">Eliminar</button>
              </td>
            </tr>
            <tr v-if="!data.data?.length">
              <td colspan="4" class="px-3 py-6 text-center text-slate-500">Sin SKU registrados todavía.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="data.links?.length" class="mt-3 flex flex-wrap gap-1">
        <button v-for="(link, i) in data.links" :key="i" v-html="link.label" :disabled="!link.url"
                @click="toPage(link)"
                :class="['px-3 py-1 rounded-md text-xs font-semibold border', link.active ? 'text-white border-[#1A73E8]' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700', !link.url ? 'opacity-40 cursor-not-allowed' : '']"
                :style="link.active ? 'background-color:#1A73E8' : ''">
        </button>
      </div>
    </div>
  </div>
</template>
