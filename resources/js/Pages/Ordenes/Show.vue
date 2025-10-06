<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  orden:       { type: Object, required: true },
  urls:        { type: Object, required: true },   // { asignar_tl, avances_store, evidencias_store, evidencias_destroy, calidad_validar, calidad_rechazar, cliente_autorizar, facturar, pdf }
  can:         { type: Object, default: () => ({}) }, // { reportarAvance:bool, asignar_tl:bool }
  teamLeaders: { type: Array,  default: () => [] },
  cotizacion:  { type: Object, default: () => ({}) },
})

// colecciones “a prueba de null”
const items   = computed(() => props.orden?.items   ?? [])
const avances = computed(() => props.orden?.avances ?? [])

// ----- Calidad / Cliente -----
const obs = ref('No cumple especificación')
const validarCalidad   = () => router.post(props.urls.calidad_validar)
const rechazarCalidad  = () => router.post(props.urls.calidad_rechazar, { observaciones: obs.value })
const autorizarCliente = () => router.post(props.urls.cliente_autorizar)

// ----- Asignar TL -----
const tlForm = useForm({ team_leader_id: props.orden?.team_leader_id ?? null })
function asignarTL () { tlForm.patch(props.urls.asignar_tl, { preserveScroll: true }) }

// ----- Registrar avances -----
const avForm = useForm({
  items: items.value.map(i => ({ id_item: i.id, cantidad: 0 })), // captura incremental
  comentario: ''
})
const restante = (it) => Math.max(0, (it?.cantidad_planeada ?? 0) - (it?.cantidad_real ?? 0))
function registrarAvance () {
  avForm.items = avForm.items
    .map((x, idx) => {
      const it = items.value[idx]
      const max = restante(it)
      return { ...x, cantidad: Math.max(0, Math.min(Number(x.cantidad || 0), max)) }
    })
    .filter(x => x.cantidad > 0)

  if (!avForm.items.length) return
  avForm.post(props.urls.avances_store, { preserveScroll: true })
}

// ----- Evidencias (archivos por avance / ítem) -----
const evForm = useForm({
  id_item: null,
  evidencias: []
})
function onPickEvidencias(e){ evForm.evidencias = Array.from(e.target.files || []) }
function subirEvidencias(){
  if (!evForm.evidencias.length) return
  const fd = new FormData()
  if (evForm.id_item) fd.append('id_item', evForm.id_item)
  evForm.evidencias.forEach(f => fd.append('evidencias[]', f))
  evForm.post(props.urls.evidencias_store, { forceFormData:true, preserveScroll:true })
}
const vistaEvidencias = computed(() => props.orden?.evidencias ?? [])
function borrarEvidencia(id){
  // urls.evidencias_destroy viene como .../evidencias/0 -> reemplazamos el 0 por el id real
  const url = props.urls.evidencias_destroy.replace(/\/0$/, '/'+id)
  router.delete(url, { preserveScroll:true })
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-2">OT #{{ orden?.id }} — {{ orden?.servicio?.nombre }}</h1>
    <div class="opacity-70">
      Centro: {{ orden?.centro?.nombre }} |
      TL: {{ orden?.team_leader?.name ?? 'No asignado' }}
    </div>
    <div class="mt-2 flex flex-wrap items-center gap-2">
      <a :href="urls.pdf" class="px-3 py-2 rounded bg-gray-700 text-white inline-block" target="_blank">
        Descargar PDF
      </a>
      <span>Estatus:</span>
      <span class="px-2 py-1 bg-gray-100 rounded">{{ orden?.estatus }}</span>
      <span class="ml-2 px-2 py-1 rounded"
            :class="{'bg-yellow-100': orden?.calidad_resultado==='pendiente',
                     'bg-emerald-100': orden?.calidad_resultado==='validado',
                     'bg-red-100': orden?.calidad_resultado==='rechazado'}">
        Calidad: {{ orden?.calidad_resultado }}
      </span>
    </div>

    <!-- Asignar TL (admin / coordinador) -->
    <div v-if="can?.asignar_tl" class="mt-4 flex items-end gap-2">
      <div>
        <label class="block text-sm mb-1">Asignar Team Leader</label>
        <select v-model="tlForm.team_leader_id" class="border p-2 rounded min-w-[16rem]">
          <option :value="null">— Selecciona —</option>
          <option v-for="u in teamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <p v-if="tlForm.errors.team_leader_id" class="text-red-600 text-sm mt-1">{{ tlForm.errors.team_leader_id }}</p>
      </div>
      <button @click="asignarTL" :disabled="tlForm.processing"
              class="px-3 py-2 rounded bg-black text-white disabled:opacity-60">
        {{ tlForm.processing ? 'Asignando…' : 'Asignar' }}
      </button>
    </div>

    <!-- Ítems -->
    <h2 class="font-semibold mt-6 mb-2">Ítems</h2>
    <table class="w-full text-sm border" v-if="items.length">
      <thead>
        <tr class="bg-gray-50 text-left">
          <th class="p-2">Descripción / Tamaño</th>
          <th class="p-2">Planeado</th>
          <th class="p-2">Real</th>
          <th class="p-2">P.U.</th>
          <th class="p-2">Subtotal</th>
          <th class="p-2" v-if="can?.reportarAvance">Capturar</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(it, idx) in items" :key="it.id" class="border-t align-top">
          <td class="p-2">
            <span v-if="it?.tamano">Tamaño: {{ it.tamano }}</span>
            <span v-else>{{ it?.descripcion }}</span>
          </td>
          <td class="p-2">{{ it?.cantidad_planeada }}</td>
          <td class="p-2">{{ it?.cantidad_real }}</td>
          <td class="p-2">{{ Number(it?.precio_unitario ?? 0).toFixed(2) }}</td>
          <td class="p-2">{{ Number(it?.subtotal ?? 0).toFixed(2) }}</td>

          <!-- Captura de avance por ítem -->
          <td class="p-2" v-if="can?.reportarAvance">
            <div class="flex items-center gap-2">
              <input type="number" min="0"
                     :max="restante(it)"
                     v-model.number="avForm.items[idx].cantidad"
                     class="border p-2 rounded w-28" />
              <span class="text-xs opacity-60">máx: {{ restante(it) }}</span>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
    <div v-else class="text-sm opacity-70">Sin ítems.</div>

    <!-- Cotización -->
    <div v-if="cotizacion?.lines?.length" class="mt-4 border rounded">
      <div class="p-2 font-medium">Totales</div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="p-2 text-left">Concepto</th>
            <th class="p-2 text-right">Cantidad</th>
            <th class="p-2 text-right">P. Unitario</th>
            <th class="p-2 text-right">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(l,i) in cotizacion.lines" :key="i" class="border-t">
            <td class="p-2">{{ l.label }}</td>
            <td class="p-2 text-right">{{ l.cantidad }}</td>
            <td class="p-2 text-right">${{ Number(l.pu||0).toFixed(2) }}</td>
            <td class="p-2 text-right">${{ Number(l.subtotal||0).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
      <div class="p-3 grid gap-1 justify-end">
        <div class="text-sm">Subtotal: <strong>${{ Number(cotizacion.subtotal||0).toFixed(2) }}</strong></div>
        <div class="text-sm">IVA ({{ Number((cotizacion.iva_rate||0)*100).toFixed(0) }}%): <strong>${{ Number(cotizacion.iva||0).toFixed(2) }}</strong></div>
        <div class="text-base font-semibold">Total: <strong>${{ Number(cotizacion.total||0).toFixed(2) }}</strong></div>
      </div>
    </div>

    <!-- Guardar avance -->
    <div v-if="can?.reportarAvance" class="mt-2">
      <textarea v-model="avForm.comentario" rows="2" class="border p-2 rounded w-full md:w-1/2"
                placeholder="Comentario (opcional)"></textarea>
      <div class="mt-2">
        <button @click="registrarAvance" :disabled="avForm.processing"
                class="px-3 py-2 rounded bg-emerald-600 text-white disabled:opacity-60">
          {{ avForm.processing ? 'Guardando…' : 'Registrar avance' }}
        </button>
        <p v-if="avForm.errors.items" class="text-red-600 text-sm mt-1">{{ avForm.errors.items }}</p>
      </div>
    </div>

    <!-- Evidencias: subir -->
    <div v-if="can?.reportarAvance" class="mt-6 border rounded p-3">
      <div class="font-medium mb-2">Evidencias</div>
      <div class="flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-sm mb-1">Asociar a ítem (opcional)</label>
          <select v-model="evForm.id_item" class="border p-2 rounded min-w-[16rem]">
            <option :value="null">— Ninguno —</option>
            <option v-for="it in orden.items" :key="it.id" :value="it.id">
              #{{it.id}} — {{ it.tamano ? ('Tamaño: '+it.tamano) : it.descripcion }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Archivos</label>
          <input type="file" multiple accept="image/*,application/pdf,video/mp4" @change="onPickEvidencias" />
        </div>
        <button @click="subirEvidencias" class="px-3 py-2 bg-slate-800 text-white rounded"
                :disabled="evForm.processing">
          {{ evForm.processing ? 'Subiendo…' : 'Subir evidencias' }}
        </button>
      </div>
      <p v-if="evForm.errors.evidencias" class="text-red-600 text-sm mt-2">{{ evForm.errors.evidencias }}</p>
    </div>

    <!-- Evidencias: galería -->
    <h2 class="font-semibold mt-6 mb-2">Evidencias</h2>
    <div v-if="vistaEvidencias.length" class="grid md:grid-cols-4 gap-3">
      <div v-for="ev in vistaEvidencias" :key="ev.id" class="border rounded p-2">
        <div class="text-xs opacity-70 mb-1">
          {{ ev.created_at }} — {{ ev.usuario?.name || '—' }}
          <span v-if="ev.id_item" class="ml-1">(ítem #{{ev.id_item}})</span>
        </div>

        <!-- imagen -->
        <a v-if="ev.mime && ev.mime.startsWith('image/')" :href="ev.url" target="_blank">
          <img :src="ev.url" class="w-full h-36 object-cover rounded" />
        </a>

        <!-- pdf -->
  <a v-else-if="ev.mime==='application/pdf'" :href="ev.url" target="_blank"
           class="block text-indigo-600 text-sm">Ver PDF</a>

        <!-- video -->
        <video v-else-if="ev.mime==='video/mp4'" controls class="w-full rounded">
          <source :src="ev.url" type="video/mp4" />
        </video>

        <!-- otro -->
        <a v-else :href="ev.url" target="_blank" class="block text-indigo-600 text-sm">
          Descargar archivo
        </a>

        <button v-if="can?.reportarAvance" @click="borrarEvidencia(ev.id)"
                class="mt-2 text-xs text-red-700">Eliminar</button>
      </div>
    </div>
    <div v-else class="text-sm opacity-70">Sin evidencias.</div>

    <!-- Acciones de Calidad / Cliente / Facturación -->
    <div class="mt-6 flex flex-wrap gap-2">
      <button v-if="can?.calidad_validar"
              @click="validarCalidad" class="px-3 py-2 rounded bg-green-600 text-white">
        Validar Calidad
      </button>

      <div v-if="can?.calidad_validar"
           class="flex gap-2 items-center">
        <input v-model="obs" class="border p-2 rounded" placeholder="Motivo del rechazo" />
        <button @click="rechazarCalidad" class="px-3 py-2 rounded bg-red-600 text-white">Rechazar</button>
      </div>

      <button v-if="can?.cliente_autorizar"
              @click="autorizarCliente" class="px-3 py-2 rounded bg-black text-white">
        Autorizar como Cliente
      </button>

      <a v-if="can?.facturar" :href="urls.facturar"
         class="px-3 py-2 rounded bg-indigo-600 text-white">
        Ir a Facturación
      </a>
    </div>

    <!-- Historial de avances -->
    <h2 class="font-semibold mt-6 mb-2">Historial de avances</h2>
    <ul class="list-disc pl-6">
      <li v-for="a in avances" :key="a?.id">
        {{ a?.created_at }} — {{ a?.usuario?.name }}: +{{ a?.cantidad }}
        <span v-if="a && a.id_item"> (ítem #{{ a.id_item }})</span>
      </li>
    </ul>
  </div>
</template>
