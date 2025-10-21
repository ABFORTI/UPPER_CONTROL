<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  factura: { type: Object, required: true },
  ordenes: { type: Array, required: false, default: () => [] },
  cfdi:    { type: Object, required: false, default: null },
  urls: { type: Object, required: true }
})

const page = usePage()

// Verificar si el usuario puede operar (facturacion/admin)
const puedeOperar = computed(() => {
  const user = page.props.auth?.user
  if (!user) return false
  
  // Los roles vienen como array de strings: ['admin'] o ['facturacion']
  // No como array de objetos con .name
  const roles = user.roles || []
  return roles.includes('facturacion') || roles.includes('admin')
})

const folio = ref(props.factura?.folio_externo ?? '')

const marcarFacturado = () =>
  router.post(props.urls.facturado, { folio_externo: folio.value })

const marcarCobro  = () => router.post(props.urls.cobro)
const marcarPagado = () => router.post(props.urls.pagado)

// Eliminado: reemplazo de XML (solo usado para pruebas)

const statusBadgeClass = computed(() => {
  const s = props.factura?.estatus
  if (s === 'pagado' || s === 'facturado') return 'badge badge-green'
  if (s === 'pendiente') return 'badge badge-gray'
  if (s === 'por_pagar') return 'badge badge-gray'
  return 'badge badge-gray'
})

const tipoEtiqueta = computed(() => {
  const t = props.cfdi?.tipo
  if (!t) return '—'
  const map = { I: 'Ingreso', E: 'Egreso', T: 'Traslado', P: 'Pago', N: 'Nómina' }
  const k = String(t).toUpperCase()
  return `${k}-${map[k] ?? k}`
})
</script>

<template>
  <div class="p-6 max-w-screen-2xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
      <div>
        <h1 class="section-title text-2xl">Factura #{{ factura?.id }}</h1>
        <div class="text-sm opacity-70 mt-1">
          <template v-if="(ordenes && ordenes.length)">
            OTs: <span v-for="(o,idx) in ordenes" :key="o.id">#{{ o.id }}<span v-if="idx < ordenes.length-1">, </span></span>
          </template>
          <template v-else>
            OT #{{ factura?.orden?.id }} — {{ factura?.orden?.servicio?.nombre }}
          </template>
        </div>
        
        <!-- Descripción General del Producto/Servicio -->
        <div v-if="factura?.orden?.descripcion_general && (!ordenes || ordenes.length===0)" class="mt-3">
          <div class="inline-flex items-center gap-2 bg-gradient-to-r from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl px-4 py-2">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            </svg>
            <div>
              <div class="text-xs text-purple-600 font-semibold uppercase tracking-wide">Producto/Servicio</div>
              <div class="text-sm font-bold text-purple-900">{{ factura.orden.descripcion_general }}</div>
            </div>
          </div>
        </div>
        
        <div class="mt-2 flex flex-wrap gap-2 items-center text-sm">
          <span :class="statusBadgeClass">{{ factura?.estatus }}</span>
          <span class="badge badge-gray">CFDI folio: {{ factura?.folio ?? '—' }}</span>
          <span class="badge badge-gray">UUID: {{ factura?.folio_externo ?? '—' }}</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <a :href="urls.pdf" target="_blank" class="btn btn-primary">Ver PDF</a>
      </div>
    </div>

    <!-- Acciones de estatus (solo para facturacion/admin) -->
    <div v-if="puedeOperar" class="mt-2 flex flex-wrap gap-2">
      <!-- pendiente -> facturado -->
      <div v-if="factura?.estatus==='pendiente'" class="flex gap-2 items-center">
        <input v-model="folio" class="border p-2 rounded" placeholder="Folio timbrado" />
        <button @click="marcarFacturado" class="btn btn-primary">
          Marcar facturado
        </button>
      </div>

      <!-- facturado -> por_pagar -->
      <button v-if="factura?.estatus==='facturado'" @click="marcarCobro"
              class="btn" style="background:#f59e0b; color:#fff;">
        Registrar cobro (por pagar)
      </button>

      <!-- por_pagar -> pagado -->
      <button v-if="factura?.estatus==='por_pagar'" @click="marcarPagado"
              class="btn" style="background: var(--c-success); color:#fff;">
        Marcar pagado
      </button>
    </div>
    
    <!-- Mensaje para clientes -->
    <div v-else class="mt-4 bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
      <div class="flex items-center">
        <svg class="w-5 h-5 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <p class="text-sm text-blue-700">
          <strong>Vista de solo lectura.</strong> Puedes ver la factura y descargar el PDF, pero no realizar cambios de estado.
        </p>
      </div>
    </div>

    <!-- Mensajes -->
    <div v-if="$page.props?.flash?.ok" class="mt-4 alert-success">{{$page.props.flash.ok}}</div>
    <div v-if="$page.props?.errors && Object.keys($page.props.errors).length" class="mt-2 alert-danger">
      <div v-for="(v,k) in $page.props.errors" :key="k">{{ v }}</div>
    </div>

    <!-- Eliminado: Re-subir XML usado solo en pruebas -->

    <!-- Vista "de sistema" del CFDI (si hay XML) -->
    <div v-if="cfdi" class="mt-8">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Columna izquierda (contenido principal) -->
        <div class="md:col-span-2 space-y-4">
          <!-- Lista de OTs asociadas -->
          <div v-if="ordenes && ordenes.length" class="border border-gray-200 rounded p-4 bg-white">
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-3">Órdenes facturadas</div>
            <div class="overflow-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left border-b border-gray-200">OT</th>
                    <th class="px-3 py-2 text-left border-b border-gray-200">Centro</th>
                    <th class="px-3 py-2 text-left border-b border-gray-200">Servicio</th>
                    <th class="px-3 py-2 text-left border-b border-gray-200">Producto/Descripción</th>
                    <th class="px-3 py-2 text-right border-b border-gray-200">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="o in ordenes" :key="o.id">
                    <td class="px-3 py-2 border-b border-gray-100"><a :href="o.url" class="text-blue-600 hover:underline">#{{ o.id }}</a></td>
                    <td class="px-3 py-2 border-b border-gray-100">{{ o.centro || '—' }}</td>
                    <td class="px-3 py-2 border-b border-gray-100">{{ o.servicio || '—' }}</td>
                    <td class="px-3 py-2 border-b border-gray-100">{{ o.descripcion_general || '—' }}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-right">${{ Number(o.total||0).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <!-- Subheader: Serie-Folio | Tipo | Fecha -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div class="bg-white border border-gray-200 rounded p-3">
              <div class="text-gray-500 text-xs">Serie/Folio</div>
              <div class="font-semibold">{{ cfdi.serie || '—' }} {{ cfdi.folio || '—' }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded p-3">
              <div class="text-gray-500 text-xs">Tipo</div>
              <div class="font-semibold">{{ tipoEtiqueta }}</div>
            </div>
            <div class="bg-white border border-gray-200 rounded p-3">
              <div class="text-gray-500 text-xs">Fecha</div>
              <div class="font-semibold">{{ cfdi.fecha || '—' }}</div>
            </div>
          </div>

          <!-- Emisor / Receptor -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded p-4 bg-white">
              <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Emisor</div>
              <div class="text-sm"><span class="text-gray-500">RFC:</span> {{ cfdi.emisor?.rfc || '—' }}</div>
              <div class="text-sm"><span class="text-gray-500">Nombre:</span> {{ cfdi.emisor?.nombre || '—' }}</div>
              <div class="text-sm"><span class="text-gray-500">Régimen:</span> {{ cfdi.emisor?.regimen || '—' }}</div>
              <div class="text-sm"><span class="text-gray-500">Lugar de expedición:</span> {{ cfdi.lugar_expedicion || '—' }}</div>
            </div>
            <div class="border border-gray-200 rounded p-4 bg-white">
              <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Receptor</div>
              <div class="text-sm"><span class="text-gray-500">RFC:</span> {{ cfdi.receptor?.rfc || '—' }}</div>
              <div class="text-sm"><span class="text-gray-500">Nombre:</span> {{ cfdi.receptor?.nombre || '—' }}</div>
              <div class="text-sm"><span class="text-gray-500">Uso CFDI:</span> {{ cfdi.receptor?.uso || '—' }}</div>
            </div>
          </div>

          <!-- Barra de pago -->
          <div class="grid grid-cols-1 md:grid-cols-3 border border-gray-200 rounded overflow-hidden text-sm bg-white">
            <div class="p-3 border-b md:border-b-0 md:border-r border-gray-200"><span class="text-gray-500">Moneda:</span> {{ cfdi.moneda || '—' }}</div>
            <div class="p-3 border-b md:border-b-0 md:border-r border-gray-200"><span class="text-gray-500">Forma pago:</span> {{ cfdi.forma_pago || '—' }}</div>
            <div class="p-3"><span class="text-gray-500">Método pago:</span> {{ cfdi.metodo_pago || '—' }}</div>
          </div>

          <!-- Conceptos + Totales en la misma fila (en pantallas grandes) -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Conceptos -->
            <div class="lg:col-span-2">
              <div class="overflow-auto border border-gray-200 rounded bg-white">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left border-b border-gray-200">Cant.</th>
                      <th class="px-3 py-2 text-left border-b border-gray-200">Clave</th>
                      <th class="px-3 py-2 text-left border-b border-gray-200">Descripción</th>
                      <th class="px-3 py-2 text-right border-b border-gray-200">V. Unitario</th>
                      <th class="px-3 py-2 text-right border-b border-gray-200">Importe</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(c,idx) in (cfdi.conceptos||[])" :key="idx">
                      <td class="px-3 py-2 border-b border-gray-100 text-right">{{ c.cantidad }}</td>
                      <td class="px-3 py-2 border-b border-gray-100">{{ c.clave }}</td>
                      <td class="px-3 py-2 border-b border-gray-100">{{ c.descripcion }}</td>
                      <td class="px-3 py-2 border-b border-gray-100 text-right">{{ Number(c.valor_unitario||0).toFixed(2) }}</td>
                      <td class="px-3 py-2 border-b border-gray-100 text-right">{{ Number(c.importe||0).toFixed(2) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Totales a la derecha -->
            <div>
              <div class="border border-gray-200 rounded bg-white">
                <div class="flex justify-between px-3 py-2 text-sm border-b"><span>Subtotal</span><span>${{ Number(cfdi.subtotal||0).toFixed(2) }}</span></div>
                <div v-if="cfdi.descuento" class="flex justify-between px-3 py-2 text-sm border-b"><span>Descuento</span><span>-${{ Number(cfdi.descuento||0).toFixed(2) }}</span></div>
                <div class="flex justify-between px-3 py-2 text-sm border-b"><span>IVA</span><span>${{ Number(cfdi?.impuestos?.total_trasladados||0).toFixed(2) }}</span></div>
                <div v-if="cfdi?.impuestos?.total_retenciones" class="flex justify-between px-3 py-2 text-sm border-b"><span>Retenciones</span><span>-${{ Number(cfdi?.impuestos?.total_retenciones||0).toFixed(2) }}</span></div>
                <div class="flex justify-between px-3 py-2 text-sm font-semibold"><span>Total</span><span>${{ Number(cfdi.total||0).toFixed(2) }}</span></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Barra lateral derecha: QR pegado a los bordes + Timbre -->
        <aside class="space-y-4">
          <div class="border border-gray-200 rounded bg-white overflow-hidden">
            <div class="p-4">
              <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Timbre y certificación</div>
              <div class="space-y-1 text-sm">
                <div><span class="text-gray-500">UUID:</span> {{ cfdi.uuid || factura.folio_externo || '—' }}</div>
                <div><span class="text-gray-500">Fecha timbrado:</span> {{ cfdi.fecha_timbrado || '—' }}</div>
                <div><span class="text-gray-500">No. Cert. CFDI:</span> {{ cfdi.no_certificado || '—' }}</div>
                <div><span class="text-gray-500">No. Cert. SAT:</span> {{ cfdi.no_cert_sat || '—' }}</div>
              </div>
              <!-- QR reducido y debajo de los datos -->
              <div v-if="cfdi.sat_qr_png || cfdi.sat_qr_svg_datauri" class="mt-3 w-full flex justify-center">
                <img :src="cfdi.sat_qr_png || cfdi.sat_qr_svg_datauri" alt="QR SAT" class="w-32 h-32 object-contain" />
              </div>
              <div class="mt-3 w-full flex justify-center">
                <a v-if="cfdi.sat_qr_target" :href="cfdi.sat_qr_target" target="_blank" class="text-xs text-blue-600 inline-block">Verificar en SAT</a>
                <a v-else-if="cfdi.sat_qr_url" :href="cfdi.sat_qr_url" target="_blank" class="text-xs text-blue-600 inline-block">Verificar en SAT</a>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </div>
</template>
