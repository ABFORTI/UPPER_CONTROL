<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import InputError from '@/Components/InputError.vue'
import MoneyInput from '@/Components/Cotizaciones/MoneyInput.vue'
import ClientSelect from '@/Components/Cotizaciones/ClientSelect.vue'
import QuotationItemsEditor from '@/Components/Cotizaciones/QuotationItemsEditor.vue'
import SendQuotationModal from '@/Components/Cotizaciones/SendQuotationModal.vue'

const props = defineProps({
  cotizacion: { type: Object, required: true },
  clientes: { type: Array, required: true },
  servicios: { type: Array, required: true },
  precios: { type: Object, default: () => ({}) },
  iva: { type: Number, default: 0.16 },
  areas: { type: Array, default: () => [] },
  centrosCostos: { type: Array, default: () => [] },
  marcas: { type: Array, default: () => [] },
  urls: { type: Object, required: true },
  can: { type: Object, default: () => ({ edit: true, send: true, duplicate: false }) },
})

const local = ref(JSON.parse(JSON.stringify(props.cotizacion)))
const snapshot = ref(JSON.stringify(props.cotizacion))

watch(
  () => props.cotizacion,
  (v) => {
    local.value = JSON.parse(JSON.stringify(v))
    snapshot.value = JSON.stringify(v)
  },
)

const isSaving = ref(false)
const showSendModal = ref(false)

const hasUnsavedChanges = computed(() => {
  try {
    return JSON.stringify(local.value) !== snapshot.value
  } catch (e) {
    return true
  }
})

const subtotal = computed(() => {
  const items = Array.isArray(local.value.items) ? local.value.items : []
  return items.reduce((sum, item) => {
    const servicios = Array.isArray(item.servicios) ? item.servicios : []
    const itemTotal = servicios.reduce((s, svc) => {
      const qty = Number(svc.qty ?? (svc.cantidad ?? 0))
      const precio = Number(svc.precio_unitario ?? 0)
      return s + qty * precio
    }, 0)
    return sum + itemTotal
  }, 0)
})

const ivaMonto = computed(() => subtotal.value * (Number(props.iva ?? 0) || 0))
const total = computed(() => subtotal.value + ivaMonto.value)

function submitUpdate() {
  isSaving.value = true
  router.patch(
    props.urls.update,
    {
      id_cliente: local.value.id_cliente,
      id_centrocosto: local.value.id_centrocosto,
      id_marca: local.value.id_marca,
      id_area: local.value.id_area,
      expires_at: local.value.expires_at,
      notas: local.value.notas,
      items: local.value.items,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        isSaving.value = false
      },
    },
  )
}

function submitSend() {
  if (hasUnsavedChanges.value) return
  showSendModal.value = true
}

function submitDuplicate() {
  if (!props.urls.duplicate) return
  if (!confirm('¿Duplicar esta cotización?')) return
  router.post(props.urls.duplicate, {}, { preserveScroll: true })
}
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-2xl border border-gray-200 bg-white p-4">
      <div class="flex items-start justify-between gap-4">
        <div>
          <div class="text-sm text-gray-500">Folio</div>
          <div class="text-xl font-bold">{{ local.folio ?? local.id }}</div>
        </div>
        <div class="text-right">
          <div class="text-sm text-gray-500">Estatus</div>
          <div class="font-semibold">{{ local.estatus }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mt-4">
        <div class="md:col-span-6">
          <ClientSelect
            v-model="local.id_cliente"
            :clientes="clientes"
            label="Cliente"
            :disabled="!can.edit || isSaving"
          />
          <InputError :message="$page.props.errors?.id_cliente" class="mt-1" />
        </div>

        <div class="md:col-span-3">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Vence</label>
          <input
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
            type="date"
            v-model="local.expires_at"
            :disabled="!can.edit || isSaving"
          />
          <InputError :message="$page.props.errors?.expires_at" class="mt-1" />
        </div>

        <div class="md:col-span-3">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Centro de costos</label>
          <select
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200 bg-white"
            v-model="local.id_centrocosto"
            :disabled="!can.edit || isSaving"
          >
            <option :value="null">— Seleccione —</option>
            <option v-for="cc in centrosCostos" :key="cc.id" :value="cc.id">
              {{ cc.nombre ?? cc.label ?? cc.descripcion ?? cc.id }}
            </option>
          </select>
          <InputError :message="$page.props.errors?.id_centrocosto" class="mt-1" />
        </div>

        <div class="md:col-span-3">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Marca</label>
          <select
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200 bg-white"
            v-model="local.id_marca"
            :disabled="!can.edit || isSaving"
          >
            <option :value="null">(Opcional)</option>
            <option v-for="m in marcas" :key="m.id" :value="m.id">
              {{ m.nombre ?? m.label ?? m.descripcion ?? m.id }}
            </option>
          </select>
          <InputError :message="$page.props.errors?.id_marca" class="mt-1" />
        </div>

        <div class="md:col-span-3">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Área</label>
          <select
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200 bg-white"
            v-model="local.id_area"
            :disabled="!can.edit || isSaving"
          >
            <option :value="null">(Opcional)</option>
            <option v-for="a in areas" :key="a.id" :value="a.id">
              {{ a.nombre ?? a.label ?? a.descripcion ?? a.id }}
            </option>
          </select>
          <InputError :message="$page.props.errors?.id_area" class="mt-1" />
        </div>

        <div class="md:col-span-12">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Notas</label>
          <textarea
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
            rows="3"
            v-model="local.notas"
            :disabled="!can.edit || isSaving"
          />
          <InputError :message="$page.props.errors?.notas" class="mt-1" />
        </div>
      </div>

      <div class="mt-4 flex items-center justify-between gap-4">
        <div class="w-full md:w-1/3">
          <MoneyInput :model-value="total" label="Total (estimado)" :disabled="true" />
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-4 py-2 rounded-xl border border-gray-200 hover:bg-gray-50"
            :disabled="!can.edit || isSaving"
            @click="submitUpdate"
          >
            Guardar borrador
          </button>

          <button
            v-if="can.duplicate && urls.duplicate"
            type="button"
            class="px-4 py-2 rounded-xl border border-gray-200 hover:bg-gray-50"
            :disabled="isSaving"
            @click="submitDuplicate"
          >
            Duplicar
          </button>

          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700"
            :disabled="!can.send || isSaving || hasUnsavedChanges"
            @click="submitSend"
          >
            Enviar al cliente
          </button>
        </div>
      </div>

      <div v-if="hasUnsavedChanges" class="mt-2 text-xs text-amber-700">
        Guarda el borrador antes de enviar.
      </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4">
      <QuotationItemsEditor
        v-model="local.items"
        :servicios="servicios"
        :precios="precios"
        :centrosCostos="centrosCostos"
        :marcas="marcas"
        :disabled="!can.edit || isSaving"
        :errors="$page.props.errors"
        baseErrorPath="items"
      />
    </div>

    <SendQuotationModal
      v-model:open="showSendModal"
      :quotation="{ id: local.id, folio: local.folio }"
      :default-expires-at="local.expires_at || ''"
      :urls="{ send: urls.send, recipients: urls.recipients }"
      title="Enviar cotización al cliente"
    />
  </div>
</template>
