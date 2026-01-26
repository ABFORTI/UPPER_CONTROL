<script setup>
import InputError from '@/Components/InputError.vue'
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: [Number, String], default: '' },
  label: { type: String, default: null },
  error: { type: String, default: null },
  placeholder: { type: String, default: null },
  disabled: { type: Boolean, default: false },
  step: { type: [Number, String], default: '0.01' },
})

const emit = defineEmits(['update:modelValue'])

const value = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})
</script>

<template>
  <div>
    <label v-if="label" class="block text-sm font-semibold text-gray-700 mb-1">{{ label }}</label>
    <input
      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
      :value="value"
      @input="value = $event.target.value"
      type="number"
      :step="step"
      :placeholder="placeholder"
      :disabled="disabled"
    />
    <InputError v-if="error" :message="error" class="mt-1" />
  </div>
</template>
