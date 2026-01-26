<script setup>
import { computed } from 'vue'
import InputError from '@/Components/InputError.vue'
import MoneyInput from '@/Components/Cotizaciones/MoneyInput.vue'
import ItemServicesEditor from '@/Components/Cotizaciones/ItemServicesEditor.vue'

const props = defineProps({
  modelValue: { type: Array, required: true },
  servicios: { type: Array, required: true },
  precios: { type: Object, default: () => ({}) },
  centrosCostos: { type: Array, required: true },
  marcas: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  baseErrorPath: { type: String, default: 'items' },
})

const emit = defineEmits(['update:modelValue'])

const items = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

function err(path) {
  return props.errors?.[path] ?? null
}

function addItem() {
  items.value = [
    ...items.value,
    {
      descripcion: '',
      cantidad: 1,
      notas: '',
      unit: 'pz',
      centro_costo_id: null,
      brand_id: null,
      servicios: [{ id_servicio: '', cantidad: null, qty: null, tamano: '', precio_unitario: null, notes: '' }],
    },
  ]
}

function removeItem(i) {
  if (items.value.length <= 1) return
  const next = [...items.value]
  next.splice(i, 1)
  items.value = next
}

function updateItem(i, patch) {
  const next = [...items.value]
  next[i] = { ...next[i], ...patch }
  items.value = next
}

function itemSubtotal(item) {
  const servicios = Array.isArray(item.servicios) ? item.servicios : []
  return servicios.reduce((sum, s) => {
    const qty = s.qty !== null && s.qty !== '' ? Number(s.qty) : Number(s.cantidad ?? 0)
    const precio = Number(s.precio_unitario ?? 0)
    return sum + qty * precio
  }, 0)
}

function total() {
  return items.value.reduce((sum, item) => sum + itemSubtotal(item), 0)
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-bold">Partidas</h2>
      <button
        type="button"
        class="px-3 py-2 rounded-xl border border-gray-200 hover:bg-gray-50"
        @click="addItem"
        :disabled="disabled"
      >
        + Agregar partida
      </button>
    </div>

    <div v-if="!items.length" class="p-4 rounded-xl border border-gray-200 text-gray-600">
      Sin partidas. Agrega una para comenzar.
    </div>

    <div v-for="(item, i) in items" :key="i" class="rounded-2xl border border-gray-200 p-4 bg-white">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="font-semibold">Partida #{{ i + 1 }}</div>
          <div class="text-sm text-gray-500">Subtotal estimado: {{ itemSubtotal(item).toFixed(2) }}</div>
        </div>

        <button
          type="button"
          class="text-red-600 hover:underline"
          @click="removeItem(i)"
          :disabled="disabled || items.length <= 1"
        >
          Quitar
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mt-4">
        <div class="md:col-span-6">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Descripción</label>
          <input
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
            type="text"
            :value="item.descripcion"
            :disabled="disabled"
            @input="updateItem(i, { descripcion: $event.target.value })"
          />
          <InputError :message="err(`${baseErrorPath}.${i}.descripcion`)" class="mt-1" />
        </div>

        <div class="md:col-span-6">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Notas</label>
          <input
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
            type="text"
            :value="item.notas"
            :disabled="disabled"
            @input="updateItem(i, { notas: $event.target.value })"
          />
          <InputError :message="err(`${baseErrorPath}.${i}.notas`)" class="mt-1" />
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Cantidad</label>
          <input
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
            type="number"
            min="1"
            step="1"
            :value="item.cantidad"
            :disabled="disabled"
            @input="updateItem(i, { cantidad: Number($event.target.value || 1) })"
          />
          <InputError :message="err(`${baseErrorPath}.${i}.cantidad`)" class="mt-1" />
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Unidad</label>
          <input
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
            type="text"
            :value="item.unit"
            :disabled="disabled"
            @input="updateItem(i, { unit: $event.target.value })"
          />
          <InputError :message="err(`${baseErrorPath}.${i}.unit`)" class="mt-1" />
        </div>

        <div class="md:col-span-4">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Centro de costos</label>
          <select
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200 bg-white"
            :value="item.centro_costo_id"
            :disabled="disabled"
            @change="updateItem(i, { centro_costo_id: $event.target.value ? Number($event.target.value) : null })"
          >
            <option :value="null">(Opcional)</option>
            <option v-for="cc in centrosCostos" :key="cc.id" :value="cc.id">
              {{ cc.nombre ?? cc.label ?? cc.descripcion ?? cc.id }}
            </option>
          </select>
          <InputError :message="err(`${baseErrorPath}.${i}.centro_costo_id`)" class="mt-1" />
        </div>

        <div class="md:col-span-4">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Marca</label>
          <select
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200 bg-white"
            :value="item.brand_id"
            :disabled="disabled"
            @change="updateItem(i, { brand_id: $event.target.value ? Number($event.target.value) : null })"
          >
            <option :value="null">(Opcional)</option>
            <option v-for="m in marcas" :key="m.id" :value="m.id">
              {{ m.nombre ?? m.label ?? m.descripcion ?? m.id }}
            </option>
          </select>
          <InputError :message="err(`${baseErrorPath}.${i}.brand_id`)" class="mt-1" />
        </div>
      </div>

      <div class="mt-4">
        <ItemServicesEditor
          :model-value="item.servicios"
          @update:modelValue="(v) => updateItem(i, { servicios: v })"
          :servicios="servicios"
          :precios="precios"
          :disabled="disabled"
          :errors="errors"
          :baseErrorPath="`${baseErrorPath}.${i}`"
        />
      </div>

      <div class="mt-4 flex justify-end">
        <MoneyInput
          :model-value="itemSubtotal(item)"
          label="Subtotal (estimado)"
          :disabled="true"
        />
      </div>
    </div>

    <div v-if="items.length" class="flex justify-end">
      <div class="w-full md:w-1/3">
        <MoneyInput :model-value="total()" label="Total estimado" :disabled="true" />
      </div>
    </div>
  </div>
</template>
