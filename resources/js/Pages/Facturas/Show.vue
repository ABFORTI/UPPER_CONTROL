<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  factura: { type: Object, required: true },
  urls: { type: Object, required: true }
})

const folio = ref(props.factura?.folio_externo ?? '')

const marcarFacturado = () =>
  router.post(props.urls.facturado, { folio_externo: folio.value })

const marcarCobro  = () => router.post(props.urls.cobro)
const marcarPagado = () => router.post(props.urls.pagado)
</script>

<template>
  <div class="p-6 max-w-2xl">
    <h1 class="text-2xl font-bold mb-2">Factura #{{ factura?.id }}</h1>
    <p class="opacity-70 mb-4">
      OT #{{ factura?.orden?.id }} — {{ factura?.orden?.servicio?.nombre }}
    </p>

    <div class="mb-2">Total: {{ factura?.total }}</div>
    <div class="mb-2">Folio: {{ factura?.folio_externo ?? '—' }}</div>
    <div class="mb-2">
      Estatus: <span class="px-2 py-1 bg-gray-100 rounded">{{ factura?.estatus }}</span>
    </div>

    <!-- ACCIONES (sin filtrar por roles en el front) -->
    <div class="mt-4 flex flex-wrap gap-2">
      <!-- pendiente -> facturado -->
      <div v-if="factura?.estatus==='pendiente'" class="flex gap-2 items-center">
        <input v-model="folio" class="border p-2 rounded" placeholder="Folio timbrado" />
        <button @click="marcarFacturado" class="px-3 py-2 rounded bg-indigo-600 text-white">
          Marcar facturado
        </button>
      </div>

      <!-- facturado -> por_pagar -->
      <button v-if="factura?.estatus==='facturado'" @click="marcarCobro"
              class="px-3 py-2 rounded bg-amber-600 text-white">
        Registrar cobro (por pagar)
      </button>

      <!-- por_pagar -> pagado -->
      <button v-if="factura?.estatus==='por_pagar'" @click="marcarPagado"
              class="px-3 py-2 rounded bg-green-600 text-white">
        Marcar pagado
      </button>
    </div>

    <!-- Descargar PDF -->
    <div class="mt-4">
      <a :href="urls.pdf" class="px-3 py-2 rounded bg-gray-700 text-white inline-block" target="_blank">
        Descargar PDF
      </a>
    </div>
    <!-- Mensaje flash -->
    <div v-if="$page.props?.flash?.ok" class="mt-4 p-2 bg-emerald-50 border border-emerald-200 rounded">
      {{$page.props.flash.ok}}
    </div>
  </div>
</template>
