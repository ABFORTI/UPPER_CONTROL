<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

import QuotationForm from '@/Components/Cotizaciones/QuotationForm.vue'

const props = defineProps({
  cotizacion: Object,
  clientes: Array,
  servicios: Array,
  precios: Object,
  iva: Number,
  areas: Array,
  centrosCostos: Array,
  marcas: Array,
  can: Object,
  urls: Object,
})

const page = usePage()
const flashOk = computed(() => page.props.flash?.ok || page.props.flash?.success || null)
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-upper-50 to-upper-100 px-4 pt-2 pb-6 md:px-8">
    <div class="max-w-6xl mx-auto space-y-4">
      <div v-if="flashOk" class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl">{{ flashOk }}</div>

      <div v-if="Object.keys($page.props.errors||{}).length" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
        <div class="font-semibold mb-1">Revisa los campos marcados.</div>
        <div class="text-sm">Hay errores de validación en el formulario.</div>
      </div>

      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="px-6 py-4" style="background: linear-gradient(90deg, #1E1C8F 0%, #19176F 100%);">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h1 class="text-white font-semibold text-xl">Editar Cotización</h1>
              <p class="text-white/90 text-sm">Folio: <strong>{{ cotizacion.folio ?? cotizacion.id }}</strong> — Estatus: <strong class="uppercase">{{ cotizacion.estatus }}</strong></p>
            </div>
            <div class="flex gap-2">
              <a :href="urls.show" class="px-4 py-2 rounded-lg bg-white/10 text-white font-semibold">Ver</a>
              <a :href="urls.index" class="px-4 py-2 rounded-lg bg-white/10 text-white font-semibold">Volver</a>
            </div>
          </div>
        </div>

        <div class="p-6">
          <QuotationForm
            :cotizacion="cotizacion"
            :clientes="clientes"
            :servicios="servicios"
            :precios="precios"
            :iva="iva"
            :areas="areas"
            :centrosCostos="centrosCostos"
            :marcas="marcas"
            :urls="urls"
            :can="can"
          />
        </div>
      </div>
    </div>
  </div>
</template>
