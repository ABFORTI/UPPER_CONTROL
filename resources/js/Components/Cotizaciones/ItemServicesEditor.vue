<script setup>
import InputError from '@/Components/InputError.vue'
import MoneyInput from '@/Components/Cotizaciones/MoneyInput.vue'
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Array, required: true },
  servicios: { type: Array, default: () => [] },
  precios: { type: Object, default: () => ({}) },
  ivaRate: { type: Number, default: 0.16 },
  baseErrorPath: { type: String, default: '' },
  errors: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const rows = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

function err(path) {
  return props.errors?.[path] || null
}

function addService() {
  const next = [...rows.value]
  next.push({ id_servicio: '', cantidad: null, qty: null, tamano: '', precio_unitario: null, notes: '' })
  rows.value = next
}

function removeService(j) {
  const next = [...rows.value]
  if (next.length <= 1) return
  next.splice(j, 1)
  rows.value = next
}

function updateRow(j, patch) {
  const next = [...rows.value]
  next[j] = { ...(next[j] || {}), ...patch }
  rows.value = next
}

function serviceName(id) {
  const sid = Number(id)
  if (!sid) return ''
  const s = (props.servicios || []).find((x) => Number(x.id) === sid)
  return s?.nombre || ''
}

function precioSugerido(servicioId, tamano) {
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

function qtyForCalc(row, fallbackCantidad) {
  const q = row.qty !== null && row.qty !== '' ? Number(row.qty) : null
  if (q != null && !Number.isNaN(q) && q > 0) return q
  const c = row.cantidad !== null && row.cantidad !== '' ? Number(row.cantidad) : null
  if (c != null && !Number.isNaN(c) && c > 0) return c
  return Number(fallbackCantidad || 1) || 1
}

function money(n) {
  const v = Number(n || 0)
  return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between">
      <div class="font-semibold text-slate-800">Servicios</div>
      <button
        type="button"
        class="px-3 py-1.5 rounded-lg bg-blue-600 text-white font-semibold disabled:opacity-60"
        :disabled="disabled"
        @click="addService"
      >+ Servicio</button>
    </div>

    <div
      v-for="(svc, j) in rows"
      :key="j"
      class="grid md:grid-cols-12 gap-3 items-end border border-slate-100 rounded-xl p-3"
    >
      <div class="md:col-span-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Servicio *</label>
        <select
          :value="svc.id_servicio"
          @change="updateRow(j, { id_servicio: $event.target.value })"
          class="w-full px-3 py-2 rounded-xl border-2 border-gray-200 bg-gray-50"
          :disabled="disabled"
        >
          <option value="">— Seleccione —</option>
          <option v-for="s in servicios" :key="s.id" :value="s.id">{{ s.nombre }}</option>
        </select>
        <InputError :message="err(`${baseErrorPath}.servicios.${j}.id_servicio`)" class="mt-1" />
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Qty</label>
        <input
          class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
          :disabled="disabled"
          type="number"
          step="0.001"
          placeholder="Ej: 1"
          :value="svc.qty"
          @input="updateRow(j, { qty: $event.target.value })"
        />
        <InputError :message="err(`${baseErrorPath}.servicios.${j}.qty`)" class="mt-1" />
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Cantidad (int)</label>
        <input
          class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
          :disabled="disabled"
          type="number"
          min="1"
          placeholder="Opcional"
          :value="svc.cantidad"
          @input="updateRow(j, { cantidad: $event.target.value === '' ? null : Number($event.target.value) })"
        />
        <InputError :message="err(`${baseErrorPath}.servicios.${j}.cantidad`)" class="mt-1" />
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Tamaño</label>
        <input
          class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
          :disabled="disabled"
          type="text"
          placeholder="chico/mediano…"
          :value="svc.tamano"
          @input="updateRow(j, { tamano: $event.target.value })"
        />
        <InputError :message="err(`${baseErrorPath}.servicios.${j}.tamano`)" class="mt-1" />
      </div>

      <div class="md:col-span-2">
        <MoneyInput
          :model-value="svc.precio_unitario"
          @update:modelValue="(v) => updateRow(j, { precio_unitario: v })"
          label="P.U."
          :placeholder="String(precioSugerido(svc.id_servicio, svc.tamano) || '')"
          :disabled="disabled"
          :error="err(`${baseErrorPath}.servicios.${j}.precio_unitario`)"
        />
      </div>

      <div class="md:col-span-12">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Notas</label>
        <textarea
          :value="svc.notes"
          @input="updateRow(j, { notes: $event.target.value })"
          rows="2"
          class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
          :disabled="disabled"
        />
        <InputError :message="err(`${baseErrorPath}.servicios.${j}.notes`)" class="mt-1" />
      </div>

      <div class="md:col-span-12 flex items-center justify-between gap-3">
        <div class="text-sm text-slate-600">
          <span class="mr-3">{{ serviceName(svc.id_servicio) }}</span>
          <span class="mr-3">Qty: <strong>{{ qtyForCalc(svc, 1) }}</strong></span>
          <span class="mr-3">PU: <strong>{{ money(svc.precio_unitario ?? precioSugerido(svc.id_servicio, svc.tamano)) }}</strong></span>
          <span class="mr-3">Subtotal: <strong>{{ money((Number(svc.precio_unitario ?? precioSugerido(svc.id_servicio, svc.tamano))||0) * qtyForCalc(svc, 1)) }}</strong></span>
        </div>
        <button
          type="button"
          class="px-3 py-1.5 rounded-lg bg-slate-200 text-slate-800 font-semibold disabled:opacity-60"
          :disabled="disabled || rows.length <= 1"
          @click="removeService(j)"
        >Quitar</button>
      </div>
    </div>
  </div>
</template>
