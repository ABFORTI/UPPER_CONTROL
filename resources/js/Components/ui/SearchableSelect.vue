<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Escribe para buscar...' },
  noResultsText: { type: String, default: 'No se encontraron opciones' },
  showEmptyOption: { type: Boolean, default: false },
  emptyOptionLabel: { type: String, default: '— Sin seleccionar —' },
  emptyValue: { type: [String, Number, null], default: null },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
  inputClass: { type: String, default: '' },
  menuClass: { type: String, default: '' },
  dropUp: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const rootRef = ref(null)
const search = ref('')
const isOpen = ref(false)
const activeIndex = ref(-1)

function normalizeText(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
}

function isEqualValue(a, b) {
  if (a === b) return true
  if (a === null || a === undefined || b === null || b === undefined) return false
  return String(a) === String(b)
}

const selectedOption = computed(() => {
  return (props.options || []).find((opt) => isEqualValue(opt?.value, props.modelValue)) || null
})

const filteredOptions = computed(() => {
  const term = normalizeText(search.value)
  const all = props.options || []
  if (!term) return all

  return all.filter((opt) => normalizeText(opt?.label).includes(term))
})

const visibleOptions = computed(() => {
  const list = [...filteredOptions.value]
  if (props.showEmptyOption) {
    list.unshift({
      __empty: true,
      value: props.emptyValue,
      label: props.emptyOptionLabel,
      disabled: false,
    })
  }
  return list
})

const hasNoResults = computed(() => {
  return isOpen.value && filteredOptions.value.length === 0
})

function isOptionDisabled(option) {
  return !!option?.disabled
}

function firstEnabledIndex() {
  return visibleOptions.value.findIndex((opt) => !isOptionDisabled(opt))
}

function openList() {
  if (props.disabled) return
  isOpen.value = true
  nextTick(() => {
    if (activeIndex.value >= 0) return
    activeIndex.value = firstEnabledIndex()
  })
}

function closeList() {
  isOpen.value = false
  activeIndex.value = -1
}

function moveActiveIndex(direction) {
  const options = visibleOptions.value
  const total = options.length
  if (!total) {
    activeIndex.value = -1
    return
  }

  let idx = activeIndex.value
  for (let i = 0; i < total; i++) {
    idx = (idx + direction + total) % total
    if (!isOptionDisabled(options[idx])) {
      activeIndex.value = idx
      return
    }
  }

  activeIndex.value = -1
}

function clearSelectionIfMismatch() {
  if (!selectedOption.value) return
  if (search.value === selectedOption.value.label) return
  emit('update:modelValue', props.emptyValue)
}

function selectOption(option) {
  if (!option || isOptionDisabled(option)) return
  emit('update:modelValue', option.value)
  search.value = String(option.label || '')
  closeList()
}

function handleInput() {
  clearSelectionIfMismatch()
  openList()
}

function handleKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    if (!isOpen.value) openList()
    moveActiveIndex(1)
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    if (!isOpen.value) openList()
    moveActiveIndex(-1)
    return
  }

  if (event.key === 'Enter') {
    if (!isOpen.value) return
    event.preventDefault()
    const option = visibleOptions.value[activeIndex.value]
    if (option) selectOption(option)
    return
  }

  if (event.key === 'Escape') {
    event.preventDefault()
    closeList()
  }
}

function handleOutsideClick(event) {
  const root = rootRef.value
  if (!root) return
  if (root.contains(event.target)) return
  closeList()
}

watch(
  () => props.modelValue,
  () => {
    if (selectedOption.value) {
      search.value = selectedOption.value.label
    } else if (!isOpen.value) {
      search.value = ''
    }
  },
  { immediate: true }
)

watch(
  () => props.options,
  () => {
    if (selectedOption.value) return
    if (!isOpen.value) search.value = ''
  },
  { deep: true }
)

watch(visibleOptions, () => {
  if (activeIndex.value >= visibleOptions.value.length) {
    activeIndex.value = firstEnabledIndex()
  }
})

onMounted(() => {
  document.addEventListener('mousedown', handleOutsideClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleOutsideClick)
})
</script>

<template>
  <div ref="rootRef" class="relative">
    <input
      v-model="search"
      type="text"
      :required="required"
      :disabled="disabled"
      autocomplete="off"
      :placeholder="placeholder"
      class="w-full pl-11 pr-10 py-3 rounded-xl border-2 transition-all outline-none bg-gray-50 hover:bg-white"
      :class="[
        error ? 'border-red-500 focus:border-red-500 focus:ring-4 focus:ring-red-100' : 'border-gray-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-100',
        disabled ? 'opacity-60 cursor-not-allowed' : '',
        inputClass,
      ]"
      @focus="openList"
      @input="handleInput"
      @keydown="handleKeydown"
    />

    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.6-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
    </div>

    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
      <svg class="w-5 h-5 transition-transform duration-200" :class="{ 'rotate-180': isOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </div>

    <div
      v-if="isOpen"
      class="absolute z-40 w-full rounded-xl border-2 border-gray-200 bg-white shadow-xl overflow-hidden"
      :class="[props.dropUp ? 'bottom-full mb-2' : 'top-full mt-2', menuClass]"
    >
      <ul class="max-h-60 overflow-auto py-1">
        <li
          v-for="(option, idx) in visibleOptions"
          :key="`${option.__empty ? 'empty' : 'opt'}-${option.value}-${idx}`"
          class="px-4 py-2.5 text-sm transition-colors"
          :class="[
            isOptionDisabled(option) ? 'cursor-not-allowed text-gray-400' : 'cursor-pointer text-gray-800',
            idx === activeIndex && !isOptionDisabled(option) ? 'bg-blue-100 text-blue-900' : ''
          ]"
          @mouseenter="activeIndex = idx"
          @mousedown.prevent="selectOption(option)"
        >
          {{ option.label }}
        </li>
        <li v-if="hasNoResults" class="px-4 py-3 text-sm text-gray-500">
          {{ noResultsText }}
        </li>
      </ul>
    </div>
  </div>
</template>
