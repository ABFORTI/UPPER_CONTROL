<script setup>
import { usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import SendQuotationModal from '@/Components/Cotizaciones/SendQuotationModal.vue'

const props = defineProps({
  data: Object,
  filters: Object,
  clientesFilter: { type: Array, default: () => [] },
  urls: Object,
})

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles ?? [])
const canCreate = computed(() => roles.value.includes('admin') || roles.value.includes('coordinador'))

const sel = ref(props.filters?.estatus || '')
const clientId = ref(props.filters?.client_id || null)
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')
const q = ref(props.filters?.q || '')
const estatuses = computed(() => ['draft','sent','approved','rejected','expired','cancelled'])

const sendModalOpen = ref(false)
const sendTarget = ref(null)

function applyFilter(){
  const params = {}
  if (sel.value) params.estatus = sel.value
  if (clientId.value) params.client_id = clientId.value
  if (dateFrom.value) params.date_from = dateFrom.value
  if (dateTo.value) params.date_to = dateTo.value
  if (q.value) params.q = q.value
  router.get(props.urls.index, params, { preserveState: true, replace: true })
}

function resetFilters(){
  sel.value = ''
  clientId.value = null
  dateFrom.value = ''
  dateTo.value = ''
  q.value = ''
  applyFilter()
}

function send(c){
  sendTarget.value = { id: c.id, folio: c.folio || c.id }
  sendModalOpen.value = true
}

function duplicate(c){
  if (!confirm('¿Duplicar esta cotización?')) return
  router.post(route('cotizaciones.duplicate', c.id), {}, { preserveScroll: true })
}

function toPage(link){ if(link.url){ router.get(link.url, {}, {preserveState:true}) } }
</script>

<template>
  <div class="max-w-none px-1 py-3 sm:px-2 lg:px-3">
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden">
      <div class="px-2 pt-3 pb-2 sm:px-3 lg:px-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <h1 class="font-display text-2xl sm:text-3xl font-semibold tracking-wide uppercase text-slate-900 dark:text-slate-100">Cotizaciones</h1>
        <a v-if="canCreate" :href="props.urls.create" class="btn btn-primary w-full sm:w-auto text-center self-start sm:self-end">AGREGAR +</a>
      </div>

      <div class="px-2 py-2 sm:px-3 lg:px-4">
        <div class="flex flex-wrap gap-2">
          <button @click="sel=''; applyFilter()" :class="['px-4 py-2 rounded-full text-sm font-semibold border transition-colors', sel==='' ? 'text-white border-[#1A73E8]' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700']" :style="sel==='' ? 'background-color: #1A73E8' : ''">Todos</button>
          <button v-for="e in estatuses" :key="e" @click="sel=e; applyFilter()" :class="['px-4 py-2 rounded-full text-sm font-semibold border uppercase transition-colors', sel===e ? 'text-white border-[#1A73E8]' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700']" :style="sel===e ? 'background-color: #1A73E8' : ''">{{ e }}</button>
        </div>

        <div class="mt-3 grid grid-cols-1 md:grid-cols-12 gap-2">
          <div class="md:col-span-4">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Cliente</label>
            <select v-model="clientId" class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900">
              <option :value="null">— Todos —</option>
              <option v-for="c in clientesFilter" :key="c.id" :value="c.id">{{ c.name }} ({{ c.email }})</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Desde</label>
            <input v-model="dateFrom" type="date" class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Hasta</label>
            <input v-model="dateTo" type="date" class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" />
          </div>
          <div class="md:col-span-4">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Buscar (folio/cliente)</label>
            <input v-model="q" type="text" placeholder="Ej: UC-000123" class="w-full px-3 py-2 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900" @keyup.enter="applyFilter" />
          </div>

          <div class="md:col-span-12 flex flex-wrap gap-2 mt-1">
            <button @click="applyFilter" class="px-4 py-2 rounded-xl bg-[#1A73E8] text-white font-semibold">Aplicar</button>
            <button @click="resetFilters" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 font-semibold">Limpiar</button>
          </div>
        </div>
      </div>

      <div class="px-2 pb-3 sm:px-3 lg:px-4 hidden md:block">
        <div class="rounded-lg shadow-sm border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950/40">
          <div class="overflow-x-auto">
            <table class="w-full text-xs md:text-sm table-auto">
              <thead class="bg-slate-800 text-white uppercase text-xs">
                <tr>
                  <th class="px-2 py-2 text-center">Folio</th>
                  <th class="px-2 py-2 text-center">Cliente</th>
                  <th class="px-2 py-2 text-center">Centro</th>
                  <th class="px-2 py-2 text-center">Centro de costos</th>
                  <th class="px-2 py-2 text-center">Marca</th>
                  <th class="px-2 py-2 text-center">Total</th>
                  <th class="px-2 py-2 text-center">Estatus</th>
                  <th class="px-2 py-2 text-center">Fecha</th>
                  <th class="px-2 py-2 text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in data.data" :key="c.id" class="border-t border-slate-100 dark:border-slate-800 even:bg-slate-50 dark:even:bg-slate-800/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/70 text-slate-800 dark:text-slate-100 transition-colors">
                  <td class="px-2 py-2 text-center">{{ c.folio || c.id }}</td>
                  <td class="px-2 py-2 text-center">{{ c.cliente?.name || '-' }}</td>
                  <td class="px-2 py-2 text-center">{{ c.centro?.nombre || '-' }}</td>
                  <td class="px-2 py-2 text-center">{{ c.centroCosto?.nombre || '-' }}</td>
                  <td class="px-2 py-2 text-center">{{ c.marca?.nombre || '-' }}</td>
                  <td class="px-2 py-2 text-center">{{ Number(c.total||0).toFixed(2) }}</td>
                  <td class="px-2 py-2 text-center">
                    <span class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide"
                      :class="{
                        'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-200': c.estatus==='draft',
                        'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200': c.estatus==='sent',
                        'bg-green-100 text-green-700 dark:bg-emerald-500/20 dark:text-emerald-300': c.estatus==='approved',
                        'bg-red-100 text-red-700 dark:bg-rose-500/20 dark:text-rose-200': c.estatus==='rejected',
                        'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-200': c.estatus==='expired',
                        'bg-gray-200 text-gray-700 dark:bg-gray-500/20 dark:text-gray-200': c.estatus==='cancelled'
                      }">{{ c.estatus }}</span>
                  </td>
                  <td class="px-2 py-2 text-center">{{ c.fecha || '' }}</td>
                  <td class="px-2 py-2 text-center">
                    <div class="flex items-center justify-center gap-2 flex-wrap">
                      <a :href="route('cotizaciones.show', c.id)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400 text-white text-sm font-medium rounded-lg transition-colors">Ver</a>
                      <a v-if="c.can?.edit" :href="route('cotizaciones.edit', c.id)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-lg transition-colors">Editar</a>
                      <button v-if="c.can?.send" type="button" @click="send(c)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-400 hover:bg-amber-500 text-slate-900 text-sm font-bold rounded-lg transition-colors">Enviar</button>
                      <button v-if="c.can?.duplicate" type="button" @click="duplicate(c)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-900 text-sm font-semibold rounded-lg transition-colors">Duplicar</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="md:hidden px-2 pb-3 sm:px-3 lg:px-4">
        <div class="space-y-3">
          <div v-for="c in data.data" :key="c.id" class="border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm bg-white dark:bg-slate-900/80 text-slate-800 dark:text-slate-100">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Folio</div>
                <div class="text-lg font-semibold">{{ c.folio || c.id }}</div>
                <div class="text-sm text-slate-600 dark:text-slate-300">{{ c.cliente?.name || '-' }}</div>
              </div>
              <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide"
                :class="{
                  'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-200': c.estatus==='draft',
                  'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200': c.estatus==='sent',
                  'bg-green-100 text-green-700 dark:bg-emerald-500/20 dark:text-emerald-300': c.estatus==='approved',
                  'bg-red-100 text-red-700 dark:bg-rose-500/20 dark:text-rose-200': c.estatus==='rejected',
                  'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-200': c.estatus==='expired',
                  'bg-gray-200 text-gray-700 dark:bg-gray-500/20 dark:text-gray-200': c.estatus==='cancelled'
                }">{{ c.estatus }}</span>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
              <div class="text-slate-500 dark:text-slate-400">Centro</div>
              <div class="text-right">{{ c.centro?.nombre || '-' }}</div>
              <div class="text-slate-500 dark:text-slate-400">Total</div>
              <div class="text-right font-semibold">{{ Number(c.total||0).toFixed(2) }}</div>
            </div>

            <div class="mt-3">
              <div class="grid grid-cols-2 gap-2">
                <a :href="route('cotizaciones.show', c.id)" class="w-full inline-flex justify-center px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold">Ver</a>
                <a v-if="c.can?.edit" :href="route('cotizaciones.edit', c.id)" class="w-full inline-flex justify-center px-4 py-2 bg-slate-800 text-white rounded-lg font-semibold">Editar</a>
                <button v-if="c.can?.send" type="button" @click="send(c)" class="w-full inline-flex justify-center px-4 py-2 bg-amber-400 text-slate-900 rounded-lg font-bold">Enviar</button>
                <button v-if="c.can?.duplicate" type="button" @click="duplicate(c)" class="w-full inline-flex justify-center px-4 py-2 bg-slate-200 text-slate-900 rounded-lg font-semibold">Duplicar</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="px-2 pb-4 sm:px-3 lg:px-4" v-if="data.links">
        <div class="flex flex-wrap gap-2 justify-center">
          <button v-for="link in data.links" :key="link.label" :disabled="!link.url" @click="toPage(link)" v-html="link.label" class="px-3 py-1 rounded border" :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200'" />
        </div>
      </div>
    </div>
  </div>

  <SendQuotationModal
    v-model:open="sendModalOpen"
    :quotation="sendTarget"
    :default-expires-at="''"
    :urls="sendTarget ? { send: route('cotizaciones.send', sendTarget.id), recipients: route('cotizaciones.recipients', sendTarget.id) } : { send: '', recipients: '' }"
    title="Enviar cotización al cliente"
  />
</template>
