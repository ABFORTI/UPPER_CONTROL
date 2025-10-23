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
  const base = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border-2'
  const s = props.factura?.estatus
  if (s === 'pagado' || s === 'facturado') return base + ' bg-emerald-600 text-white border-emerald-700'
  if (s === 'por_pagar') return base + ' bg-orange-500 text-white border-orange-600'
  if (s === 'pendiente') return base + ' bg-gray-200 text-gray-800 border-gray-300'
  return base + ' bg-gray-200 text-gray-800 border-gray-300'
})

// Etiqueta legible de estatus para el badge (no mostrar slugs)
const estatusLabel = computed(() => {
  const map = {
    pendiente: 'Pendiente',
    por_pagar: 'Por pagar',
    facturado: 'Facturado',
    pagado: 'Pagado',
  }
  const s = props.factura?.estatus || 'pendiente'
  return map[s] || String(s).replace(/_/g, ' ')
})

// Badge específico para el header (contraste sobre fondo azul)
const headerBadgeClass = computed(() => {
  const base = 'inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold'
  const s = props.factura?.estatus
  if (s === 'pagado' || s === 'facturado') return base + ' bg-emerald-500 text-white'
  if (s === 'por_pagar') return base + ' bg-orange-400 text-white'
  if (s === 'pendiente') return base + ' bg-white text-[#1E1C8F]'
  return base + ' bg-white text-[#1E1C8F]'
})

const tipoEtiqueta = computed(() => {
  const t = props.cfdi?.tipo
  if (!t) return '—'
  const map = { I: 'Ingreso', E: 'Egreso', T: 'Traslado', P: 'Pago', N: 'Nómina' }
  const k = String(t).toUpperCase()
  return `${k}-${map[k] ?? k}`
})

// Nombre de producto/servicio a mostrar en la barra superior
const nombreProducto = computed(() => {
  return props.factura?.orden?.descripcion_general
    || (props.cfdi?.conceptos && props.cfdi.conceptos[0]?.descripcion)
    || null
})

// IVA total: intenta leer de distintas rutas comunes; si no existe, lo deriva
const ivaTotal = computed(() => {
  const c = props.cfdi || {}

  const toNum = (v) => {
    if (v == null) return 0
    const n = Number(String(v).replace(/[,\s]/g, ''))
    return isFinite(n) ? n : 0
  }

  // 1) Campos comunes en el objeto impuestos
  const imp = c.impuestos || {}
  const direct = toNum(imp.total_trasladados || imp.total_traslados || imp.traslados_total || imp.iva)
  if (direct > 0) return direct

  // 2) Sumar traslados si vienen en arreglo
  const trasArr = Array.isArray(imp.traslados) ? imp.traslados : []
  const sumTras = trasArr.reduce((acc, t) => acc + toNum(t?.importe || t?.importe_trasladado), 0)
  if (sumTras > 0) return sumTras

  // 3) Sumar traslados a nivel de conceptos
  const conceptos = Array.isArray(c.conceptos) ? c.conceptos : []
  const sumConceptos = conceptos.reduce((acc, con) => {
    const it = con?.impuestos?.traslados
    if (!it) return acc
    const arr = Array.isArray(it) ? it : [it]
    return acc + arr.reduce((a, t) => a + toNum(t?.importe || t?.importe_trasladado), 0)
  }, 0)
  if (sumConceptos > 0) return sumConceptos

  // 4) Derivar con fórmula: total = subtotal - descuento + IVA - retenciones
  const subtotal = toNum(c.subtotal)
  const descuento = toNum(c.descuento)
  const total = toNum(c.total)
  const ret = toNum(imp.total_retenciones || imp.retenciones_total || imp.retenciones)
  const ivaDerivado = total - (subtotal - descuento) + ret
  // Evitar negativos por redondeo
  return Math.max(0, Number(ivaDerivado.toFixed(2)))
})
</script>

<template>
  <div class="pt-2 px-6 max-w-screen-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-xl border-2 border-indigo-100 p-6 space-y-6">
    <!-- Header (barra azul superior como en la imagen) -->
    <div class="-m-6 -mb-2 px-6 py-5 rounded-t-2xl bg-gradient-to-r from-indigo-600 to-[#1E1C8F] text-white flex items-center justify-between">
      <div>
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-white">Factura #{{ factura?.id }}</h1>
          <span :class="headerBadgeClass">{{ estatusLabel }}</span>
        </div>
        <div class="text-sm text-indigo-100 mt-1">
          <template v-if="(ordenes && ordenes.length)">
            OTs: <span v-for="(o,idx) in ordenes" :key="o.id">#{{ o.id }}<span v-if="idx < ordenes.length-1">, </span></span>
          </template>
          <template v-else>
            OT #{{ factura?.orden?.id }} — {{ factura?.orden?.servicio?.nombre }}
          </template>
        </div>
      </div>
      <a :href="urls.pdf" target="_blank" class="px-4 py-2 rounded-full bg-white text-[#1E1C8F] font-semibold shadow-sm hover:shadow">Ver PDF</a>
    </div>

    <!-- Barra informativa debajo del header (sin caja de fondo) -->
      <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border-2 bg-orange-100 text-orange-800 border-orange-200">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
          </svg>
          Producto/Servicio
        </span>
  <span v-if="nombreProducto" class="ml-3 text-sm font-semibold text-gray-800">{{ nombreProducto}}</span>
        
      </div>
      <div class="flex items-center gap-6 text-sm text-gray-700 flex-wrap">
        <div><span class="text-gray-500">CFDI folio:</span> <span class="font-semibold">{{ factura?.folio ?? '—' }}</span></div>
        <div><span class="text-gray-500">UUID:</span> <span class="font-semibold">{{ factura?.folio_externo ?? '—' }}</span></div>
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
        class="px-4 py-2 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 text-white font-semibold shadow hover:shadow-md">
        Registrar cobro (por pagar)
      </button>

      <!-- por_pagar -> pagado -->
      <button v-if="factura?.estatus==='por_pagar'" @click="marcarPagado"
        class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-semibold shadow hover:shadow-md">
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
  <div v-if="cfdi" class="mt-2">
      <!-- Fila superior: Datos Generales | Método de pago -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mb-4">
        <!-- Col 1: Datos Generales -->
        <div class="bg-white border-2 border-indigo-100 rounded-2xl p-3">
          <div class="text-xs uppercase tracking-wide text-indigo-700 mb-2">Datos Generales</div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <div class="text-gray-500 text-xs">Serie/Folio</div>
              <div class="font-semibold text-lg text-slate-800">{{ cfdi.serie || '—' }} {{ cfdi.folio || '—' }}</div>
            </div>
            <div>
              <div class="text-gray-500 text-xs">Tipo</div>
              <div class="font-semibold text-lg text-slate-800">{{ tipoEtiqueta }}</div>
            </div>
            <div>
              <div class="text-gray-500 text-xs">Fecha</div>
              <div class="font-semibold text-lg text-slate-800">{{ cfdi.fecha || '—' }}</div>
            </div>
          </div>
        </div>
        <!-- Col 2: Método de pago -->
        <div class="bg-white border-2 border-indigo-100 rounded-2xl p-3">
          <div class="text-xs uppercase tracking-wide text-indigo-700 mb-2">Método de pago</div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <div class="text-gray-500 text-xs">Método</div>
              <div class="font-semibold text-lg text-slate-800">{{ cfdi.metodo_pago || '—' }}</div>
            </div>
            <div>
              <div class="text-gray-500 text-xs">Moneda</div>
              <div class="font-semibold text-lg text-slate-800">{{ cfdi.moneda || 'MXN' }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4">
        <!-- Columna izquierda (contenido principal) -->
        <div class="space-y-4">
          <!-- Lista de OTs asociadas -->
          <div v-if="ordenes && ordenes.length" class="border-2 border-indigo-100 rounded-2xl p-4 bg-white">
            <div class="text-xs uppercase tracking-wide text-indigo-700 mb-3">Órdenes facturadas</div>
            <div class="overflow-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-indigo-50">
                  <tr>
                    <th class="px-3 py-2 text-left border-b border-indigo-100">OT</th>
                    <th class="px-3 py-2 text-left border-b border-indigo-100">Centro</th>
                    <th class="px-3 py-2 text-left border-b border-indigo-100">Servicio</th>
                    <th class="px-3 py-2 text-left border-b border-indigo-100">Producto/Descripción</th>
                    <th class="px-3 py-2 text-right border-b border-indigo-100">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="o in ordenes" :key="o.id">
                    <td class="px-3 py-2 border-b border-indigo-100"><a :href="o.url" class="text-indigo-700 hover:underline">#{{ o.id }}</a></td>
                    <td class="px-3 py-2 border-b border-indigo-100">{{ o.centro || '—' }}</td>
                    <td class="px-3 py-2 border-b border-indigo-100">{{ o.servicio || '—' }}</td>
                    <td class="px-3 py-2 border-b border-indigo-100">{{ o.descripcion_general || '—' }}</td>
                    <td class="px-3 py-2 border-b border-indigo-100 text-right">${{ Number(o.total||0).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          

          <!-- Emisor / Receptor -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Emisor con acento lateral azul -->
            <div class="relative bg-white rounded-2xl border border-indigo-200 shadow-sm p-4 overflow-hidden">
              <div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-indigo-500 to-blue-500"></div>
              <div class="text-sm font-semibold text-[#1E1C8F] mb-2">Emisor</div>
              <div class="text-sm"><span class="font-semibold text-slate-700">RFC:</span> <span class="text-slate-800">{{ cfdi.emisor?.rfc || '—' }}</span></div>
              <div class="text-sm"><span class="font-semibold text-slate-700">Nombre:</span> <span class="text-slate-800">{{ cfdi.emisor?.nombre || '—' }}</span></div>
              <div class="text-sm"><span class="font-semibold text-slate-700">Régimen:</span> <span class="text-slate-800">{{ cfdi.emisor?.regimen || '—' }}</span></div>
              <div class="text-sm"><span class="font-semibold text-slate-700">Lugar de expedición:</span> <span class="text-slate-800">{{ cfdi.lugar_expedicion || '—' }}</span></div>
            </div>
            <!-- Receptor con acento lateral azul -->
            <div class="relative bg-white rounded-2xl border border-indigo-200 shadow-sm p-4 overflow-hidden">
              <div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-indigo-500 to-blue-500"></div>
              <div class="text-sm font-semibold text-[#1E1C8F] mb-2">Receptor</div>
              <div class="text-sm"><span class="font-semibold text-slate-700">RFC:</span> <span class="text-slate-800">{{ cfdi.receptor?.rfc || '—' }}</span></div>
              <div class="text-sm"><span class="font-semibold text-slate-700">Nombre:</span> <span class="text-slate-800">{{ cfdi.receptor?.nombre || '—' }}</span></div>
              <div class="text-sm"><span class="font-semibold text-slate-700">Uso CFDI:</span> <span class="text-slate-800">{{ cfdi.receptor?.uso || '—' }}</span></div>
            </div>
          </div>

          <!-- Eliminado: barra de pago separada. Método de pago ya está en el panel central. -->

          <!-- Conceptos en su propia fila (ancho completo) -->
          <div class="grid grid-cols-1 gap-4">
            <!-- Conceptos -->
            <div>
              <div class="overflow-hidden border-2 border-indigo-100 rounded-2xl bg-white">
                <table class="min-w-full text-sm">
                  <thead class="bg-gradient-to-r from-indigo-600 to-[#1E1C8F] text-white">
                    <tr>
                      <th class="p-3 text-left font-bold">Cant.</th>
                      <th class="p-3 text-left font-bold">Clave</th>
                      <th class="p-3 text-left font-bold">Descripción</th>
                      <th class="p-3 text-right font-bold">V. Unitario</th>
                      <th class="p-3 text-right font-bold">Importe</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(c,idx) in (cfdi.conceptos||[])" :key="idx">
                      <td class="px-3 py-2 border-b border-indigo-100 text-right">{{ c.cantidad }}</td>
                      <td class="px-3 py-2 border-b border-indigo-100">{{ c.clave }}</td>
                      <td class="px-3 py-2 border-b border-indigo-100">{{ c.descripcion }}</td>
                      <td class="px-3 py-2 border-b border-indigo-100 text-right">{{ Number(c.valor_unitario||0).toFixed(2) }}</td>
                      <td class="px-3 py-2 border-b border-indigo-100 text-right">{{ Number(c.importe||0).toFixed(2) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Fila inferior: Verificación SAT (QR) | Resumen de totales -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Verificación SAT con QR a un lado de los datos -->
            <div class="bg-white border-2 border-indigo-100 rounded-2xl p-4">
              <div class="text-xs uppercase tracking-wide text-indigo-700 mb-2">Verificación SAT</div>
              <div class="flex flex-col sm:flex-row items-start gap-4">
                <div class="flex-1 space-y-1 text-sm">
                  <div><span class="text-gray-500">UUID:</span> {{ cfdi.uuid || factura.folio_externo || '—' }}</div>
                  <div><span class="text-gray-500">Fecha timbrado:</span> {{ cfdi.fecha_timbrado || '—' }}</div>
                  <div><span class="text-gray-500">No. Cert. CFDI:</span> {{ cfdi.no_certificado || '—' }}</div>
                  <div><span class="text-gray-500">No. Cert. SAT:</span> {{ cfdi.no_cert_sat || '—' }}</div>
                </div>
                <div class="sm:w-28 sm:self-start">
                  <img v-if="cfdi.sat_qr_png || cfdi.sat_qr_svg_datauri" :src="cfdi.sat_qr_png || cfdi.sat_qr_svg_datauri" alt="QR SAT" class="w-24 h-24 object-contain" />
                  <div class="mt-2">
                    <a v-if="cfdi.sat_qr_target" :href="cfdi.sat_qr_target" target="_blank" class="text-xs text-emerald-700 inline-block">Verificar autenticidad</a>
                    <a v-else-if="cfdi.sat_qr_url" :href="cfdi.sat_qr_url" target="_blank" class="text-xs text-emerald-700 inline-block">Verificar autenticidad</a>
                  </div>
                </div>
              </div>
            </div>

            <!-- Resumen de totales -->
            <div>
              <div class="border-2 border-emerald-100 rounded-2xl bg-white overflow-hidden">
                <div class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white font-bold text-sm">Resumen de totales</div>
                <div class="flex justify-between px-4 py-2 text-sm border-b border-emerald-50"><span>Subtotal</span><span>${{ Number(cfdi.subtotal||0).toFixed(2) }}</span></div>
                <div v-if="cfdi.descuento" class="flex justify-between px-4 py-2 text-sm border-b border-emerald-50"><span>Descuento</span><span>-${{ Number(cfdi.descuento||0).toFixed(2) }}</span></div>
                <div class="flex justify-between px-4 py-2 text-sm border-b border-emerald-50"><span>IVA</span><span>${{ Number(ivaTotal||0).toFixed(2) }}</span></div>
                <div v-if="cfdi?.impuestos?.total_retenciones" class="flex justify-between px-4 py-2 text-sm border-b border-emerald-50"><span>Retenciones</span><span>-${{ Number(cfdi?.impuestos?.total_retenciones||0).toFixed(2) }}</span></div>
                <div class="flex justify-between px-4 py-3 text-base font-semibold"><span>Total</span><span class="text-emerald-700">${{ Number(cfdi.total||0).toFixed(2) }}</span></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Eliminada barra lateral de QR: información movida al panel derecho de la fila superior -->
      </div>
    </div>
    </div>
  </div>
</template>
