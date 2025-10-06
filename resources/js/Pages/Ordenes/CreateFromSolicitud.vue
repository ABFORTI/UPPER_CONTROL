<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  solicitud: Object,
  folio: String,
  teamLeaders: Array,
  urls: Object,
  cotizacion: Object,
  prefill: { type: Array, default: () => [] },
})

const form = useForm({
  team_leader_id: null,
  items: (props.prefill && props.prefill.length)
    ? props.prefill.map(i => ({ descripcion: i.descripcion || '', cantidad: i.cantidad || 1, tamano: i.tamano ?? null }))
    : [{ descripcion: props.solicitud?.descripcion || '', cantidad: props.solicitud?.cantidad || 1, tamano: null }]
})

function addItem(){
  form.items.push({ descripcion: '', cantidad: 1, tamano: null })
}
function removeItem(i){
  form.items.splice(i,1)
}

function submit(){
  form.post(props.urls.store, { preserveScroll: true })
}
</script>

<template>
  <div class="p-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-1">Generar OT — {{ folio }}</h1>
    <p class="opacity-70 mb-4">
      Servicio: {{ solicitud?.id_servicio }} | Centro: {{ solicitud?.id_centrotrabajo }}
    </p>

    <!-- Cotización previa -->
    <div v-if="cotizacion?.lines?.length" class="mb-4 border rounded">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left p-2 border">Concepto</th>
            <th class="text-right p-2 border">Cantidad</th>
            <th class="text-right p-2 border">P. Unitario</th>
            <th class="text-right p-2 border">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(l,i) in cotizacion.lines" :key="i">
            <td class="p-2 border">{{ l.label }}</td>
            <td class="p-2 border text-right">{{ l.cantidad }}</td>
            <td class="p-2 border text-right">${{ (l.pu||0).toFixed(2) }}</td>
            <td class="p-2 border text-right">${{ (l.subtotal||0).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
      <div class="p-3 grid gap-1 justify-end">
        <div class="text-sm">Subtotal: <strong>${{ (cotizacion.subtotal||0).toFixed(2) }}</strong></div>
        <div class="text-sm">IVA ({{ ((cotizacion.iva_rate||0)*100).toFixed(0) }}%): <strong>${{ (cotizacion.iva||0).toFixed(2) }}</strong></div>
        <div class="text-base font-semibold">Total: <strong>${{ (cotizacion.total||0).toFixed(2) }}</strong></div>
      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <!-- Team Leader -->
      <div>
        <label class="block text-sm mb-1">Team Leader (opcional)</label>
        <select v-model="form.team_leader_id" class="border p-2 rounded w-full">
          <option :value="null">— Sin asignar —</option>
          <option v-for="u in teamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <p v-if="form.errors.team_leader_id" class="text-red-600 text-sm">{{ form.errors.team_leader_id }}</p>
      </div>

      <!-- Ítems -->
      <div class="border rounded p-3">
        <div class="font-medium mb-2">Ítems</div>

        <div v-for="(it, i) in form.items" :key="i" class="grid gap-2 md:grid-cols-7 items-center mb-2">
          <div class="md:col-span-3">
            <input v-model="it.descripcion" class="border p-2 rounded w-full" placeholder="Descripción" />
          </div>
          <div class="md:col-span-1">
            <input type="number" min="1" v-model.number="it.cantidad" class="border p-2 rounded w-full" />
          </div>
          <div class="md:col-span-2">
            <input v-model="it.tamano" class="border p-2 rounded w-full" placeholder="Tamaño (opcional)" />
          </div>
          <div class="md:col-span-1">
            <button type="button" class="px-2 py-2 border rounded w-full" @click="removeItem(i)" v-if="form.items.length>1">Quitar</button>
          </div>
        </div>

        <button type="button" class="mt-2 px-3 py-2 bg-gray-100 rounded" @click="addItem">Agregar ítem</button>

        <div v-if="form.errors['items']" class="text-red-600 text-sm mt-2">{{ form.errors['items'] }}</div>
        <div v-if="form.errors['items.0.descripcion']" class="text-red-600 text-sm">{{ form.errors['items.0.descripcion'] }}</div>
        <div v-if="form.errors['items.0.cantidad']" class="text-red-600 text-sm">{{ form.errors['items.0.cantidad'] }}</div>
      </div>

      <button type="submit" :disabled="form.processing"
              class="px-4 py-2 bg-black text-white rounded disabled:opacity-60">
        {{ form.processing ? 'Creando…' : 'Crear OT' }}
      </button>

      <div v-if="$page.props?.flash?.ok" class="p-2 bg-emerald-50 border border-emerald-200 rounded mt-2">
        {{$page.props.flash.ok}}
      </div>
    </form>
  </div>
</template>
