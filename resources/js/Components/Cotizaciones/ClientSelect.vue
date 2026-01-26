<script setup>
import InputError from '@/Components/InputError.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [Number, String, null], default: null },
  // Compat: en este proyecto normalmente se llama `clientes`.
  // Aceptamos ambos para evitar romper usos existentes.
  clientes: { type: Array, default: null },
  clients: { type: Array, default: null },
  label: { type: String, default: 'Cliente' },
  placeholder: { type: String, default: 'Buscar…' },
  error: { type: String, default: null },
  disabled: { type: Boolean, default: false },
  showEmail: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])

const q = ref('')

watch(
  () => props.modelValue,
  () => {
    // No-op: mantenemos el query para facilitar cambios
  }
)

const filtered = computed(() => {
  const term = String(q.value || '').trim().toLowerCase()
  const all = props.clientes || props.clients || []
  if (!term) return all
  return all.filter((c) => {
    const name = String(c?.name || '').toLowerCase()
    const email = String(c?.email || '').toLowerCase()
    const id = String(c?.id || '')
    return name.includes(term) || email.includes(term) || id.includes(term)
  })
})

const selectedLabel = computed(() => {
  const id = props.modelValue == null ? null : Number(props.modelValue)
  if (!id) return ''
  const c = (props.clientes || props.clients || []).find((x) => Number(x.id) === id)
  if (!c) return ''
  return props.showEmail && c.email ? `${c.name} (${c.email})` : c.name
})
</script>

<template>
  <div>
    <label class="block text-sm font-semibold text-gray-700 mb-1">{{ label }}</label>

    <div class="grid gap-2">
      <TextInput v-model="q" :placeholder="placeholder" :disabled="disabled" />

      <select
        class="w-full px-3 py-2 rounded-xl border-2 border-gray-200 bg-gray-50"
        :disabled="disabled"
        :value="modelValue"
        @change="emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)"
      >
        <option :value="null">— Seleccione —</option>
        <option v-for="c in filtered" :key="c.id" :value="c.id">
          {{ showEmail && c.email ? `${c.name} (${c.email})` : c.name }}
        </option>
      </select>

      <div v-if="selectedLabel" class="text-xs text-slate-600">
        Seleccionado: <strong>{{ selectedLabel }}</strong>
      </div>

      <InputError v-if="error" :message="error" />
    </div>
  </div>
</template>
