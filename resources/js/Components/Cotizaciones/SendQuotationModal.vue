<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

import InputError from '@/Components/InputError.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Enviar cotización' },
  quotation: { type: Object, default: null },
  urls: { type: Object, required: true }, // { send, recipients }
  defaultExpiresAt: { type: String, default: '' },
})

const emit = defineEmits(['update:open'])

const loadingRecipients = ref(false)
const recipients = ref([]) // [{ email, label, is_primary }]
const recipientsError = ref(null)

const form = useForm({
  recipient_email: '',
  expires_at: props.defaultExpiresAt || '',
})

watch(
  () => props.defaultExpiresAt,
  (v) => {
    if (!props.open) return
    if (!form.expires_at) form.expires_at = v || ''
  }
)

const canSubmit = computed(() => {
  return !!props.urls?.send && !form.processing
})

function close() {
  emit('update:open', false)
  recipientsError.value = null
  // mantener valores por si re-abre inmediatamente
}

async function loadRecipients() {
  recipientsError.value = null
  recipients.value = []

  if (!props.urls?.recipients) {
    recipients.value = []
    return
  }

  loadingRecipients.value = true
  try {
    const res = await window.axios.get(props.urls.recipients, { headers: { Accept: 'application/json' } })
    const list = res.data?.data || []
    recipients.value = Array.isArray(list) ? list : []

    if (!form.recipient_email) {
      const primary = recipients.value.find((x) => x?.is_primary)
      form.recipient_email = (primary?.email || recipients.value?.[0]?.email || '')
    }
  } catch (e) {
    recipientsError.value = 'No se pudieron cargar los destinatarios.'
  } finally {
    loadingRecipients.value = false
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return

    form.clearErrors()
    recipientsError.value = null

    if (!form.expires_at) {
      form.expires_at = props.defaultExpiresAt || ''
    }

    loadRecipients()
  }
)

function submit() {
  form.post(
    props.urls.send,
    {
      preserveScroll: true,
      onSuccess: () => {
        close()
      },
    }
  )
}
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="close" />

    <div class="relative w-full max-w-lg rounded-2xl bg-white border border-slate-200 shadow-xl p-5">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="text-lg font-bold text-slate-900">{{ title }}</div>
          <div v-if="quotation" class="text-sm text-slate-600 mt-0.5">
            Folio: <strong>{{ quotation.folio ?? quotation.id }}</strong>
          </div>
        </div>

        <button type="button" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700" @click="close">
          Cerrar
        </button>
      </div>

      <div class="mt-4 space-y-3">
        <div v-if="recipientsError" class="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-xl">
          {{ recipientsError }}
        </div>

        <div v-if="form.errors.estatus" class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-xl">
          {{ form.errors.estatus }}
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Enviar a</label>
          <select
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200 bg-gray-50"
            v-model="form.recipient_email"
            :disabled="loadingRecipients || form.processing"
          >
            <option value="">— Seleccione —</option>
            <option v-for="r in recipients" :key="r.email" :value="r.email">
              {{ r.label || r.email }}
            </option>
          </select>
          <div v-if="loadingRecipients" class="text-xs text-slate-500 mt-1">Cargando destinatarios…</div>
          <InputError v-if="form.errors.recipient_email" :message="form.errors.recipient_email" class="mt-1" />
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Vence (opcional)</label>
          <input
            class="w-full px-3 py-2 rounded-xl border-2 border-gray-200"
            type="date"
            v-model="form.expires_at"
            :disabled="form.processing"
          />
          <InputError v-if="form.errors.expires_at" :message="form.errors.expires_at" class="mt-1" />
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 disabled:opacity-60"
            :disabled="form.processing"
            @click="close"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-60"
            :disabled="!canSubmit"
            @click="submit"
          >
            Enviar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
