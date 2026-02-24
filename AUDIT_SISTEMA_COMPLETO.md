# AUDITORÍA COMPLETA DEL SISTEMA UPPER CONTROL

**Fecha:** 2026-02-23  
**Stack:** Laravel 11 + Inertia.js + Vue 3 + Ziggy + Spatie Permissions  
**Entorno:** XAMPP (C:\xampp\htdocs\upper-control)

---

## RESUMEN EJECUTIVO (1 página)

Upper Control es un sistema de gestión de órdenes de trabajo (OT) que cubre el ciclo completo: Cotización → Solicitud → Orden de Trabajo → Producción (avances) → Calidad → Autorización cliente → Facturación → Cobro. Soporta OTs tradicionales (1 servicio) y multi-servicio, con un módulo reciente de "Corte de OT" (split) para cobros parciales/semanales.

### Hallazgos Críticos

| Prioridad | Hallazgo | Impacto |
|-----------|----------|---------|
| **P0** | **MAIL_MAILER=log** en .env.example — en producción podría estar igual | Ningún correo llega realmente |
| **P0** | **Ninguna notificación implementa `ShouldQueue`** | Todas las notificaciones se envían sincrónicamente, bloqueando la request |
| **P0** | **Corte de OT no dispara ninguna notificación** | Facturación/coordinador no se enteran del corte generado |
| **P0** | **OT Hija creada sin notificación** | El TL y coordinador no saben que existe una nueva OT |
| **P1** | **Solicitud rechazada no notifica al cliente** | El cliente no sabe que su solicitud fue rechazada |
| **P1** | **Calidad rechazar() envía 2 notificaciones duplicadas** al mismo cliente | Spam/confusión |
| **P1** | **Queue worker** no garantizado en producción | Jobs (PDFs) podrían estar en tabla `jobs` sin procesarse |
| **P1** | **RecordatorioValidacionOt** corre `everyMinute()` sin throttle efectivo | Riesgo de spam |
| **P2** | **`ClienteAutorizoNotification`** definida pero jamás se usa | Código muerto |
| **P2** | **`SolicitudCreadaNotification`** definida pero jamás se usa | Código muerto |
| **P2** | 2 clases de notificación duplicadas: `Notifier` (service) vs `Notify` (support) | Inconsistencia arquitectónica |

### Tareas Técnicas Accionables (priorizadas)

1. **[P0]** Configurar MAIL_MAILER=smtp en producción con credenciales reales
2. **[P0]** Crear notificaciones para Corte de OT: `OtCorteGenerado`, `OtHijaCreada`
3. **[P0]** Agregar `implements ShouldQueue` a NotfS pesadas (FacturaGenerada, CalidadResultado, OtAsignada, etc.)
4. **[P1]** Notificar al cliente cuando solicitud es rechazada
5. **[P1]** Eliminar duplicación en `CalidadController::rechazar()` (envia CalidadResultado + Notifier::toUser)
6. **[P1]** Unificar `App\Services\Notifier` y `App\Support\Notify` en una sola clase
7. **[P1]** Verificar que `php artisan queue:work` esté corriendo en producción
8. **[P2]** Eliminar notificaciones muertas: `ClienteAutorizoNotification`, `SolicitudCreadaNotification`
9. **[P2]** Agregar notificación cuando factura batch es creada (al coordinador)
10. **[P2]** Crear tests Feature para flujos de notificación end-to-end

---

## A) WORKFLOW MAP

### 1. COTIZACIONES

```
ESTADOS: draft → sent → approved / rejected / expired / cancelled
```

| Transición | Trigger | Controller/Método | Roles | Notificaciones |
|------------|---------|-------------------|-------|----------------|
| → `draft` | Crear cotización | `CotizacionController::store()` | admin, coordinador | Ninguna |
| `draft` → `sent` | Enviar a cliente | `CotizacionController::send()` | admin, coordinador | ✅ `QuotationSentNotification` (mail+DB al cliente) + `QuotationSentDatabaseNotification` (DB campanita) |
| `sent` → `approved` | Cliente aprueba | `CotizacionController::approve()` → `QuotationApprovalService` | admin, Cliente_Supervisor, Cliente_Gerente | ✅ `QuotationApprovedCoordinatorNotification` (DB + mail condicional) al coordinador creador |
| `sent` → `rejected` | Cliente rechaza | `CotizacionController::reject()` | admin, Cliente_Supervisor, Cliente_Gerente | ✅ `Notifier::toUser` al creador (SystemEvent) |
| `sent` → `expired` | Expirada al intentar aprobar/rechazar | automático en approve/reject | — | Ninguna |
| `draft`/`sent` → `cancelled` | Coordinador cancela | `CotizacionController::cancel()` | admin, coordinador | ❌ **FALTA**: No se notifica al cliente |

**Evento clave:** `QuotationApproved` → Listener `CreateSolicitudesFromQuotationApproval` → crea Solicitudes automáticamente

**Rutas clave:**
- `POST /cotizaciones` → store
- `POST /cotizaciones/{id}/send` → send
- `POST /cotizaciones/{id}/approve` → approve
- `POST /cotizaciones/{id}/reject` → reject
- `GET /cotizaciones/{id}/pdf` → pdf

---

### 2. SOLICITUDES

```
ESTADOS: pendiente → aprobada / rechazada
```

| Transición | Trigger | Controller/Método | Roles | Notificaciones |
|------------|---------|-------------------|-------|----------------|
| → `pendiente` | Cliente crea solicitud (simple o multi-servicio) | `SolicitudController::store()` | admin, Cliente_Supervisor, Cliente_Gerente | ✅ `Notifier::toRoleInCentro('coordinador', ...)` (SystemEvent DB+mail) |
| `pendiente` → `aprobada` | Coordinador aprueba | `SolicitudController::aprobar()` | admin, coordinador | ✅ `Notifier::toUser(cliente)` (SystemEvent) |
| `pendiente` → `rechazada` | Coordinador rechaza | `SolicitudController::rechazar()` | admin, coordinador | ❌ **FALTA**: No se notifica al cliente |

**Feature flags:** `subir_excel` habilita carga de Excel para parseo de solicitudes  
**Bloqueo negocio:** `verificarBloqueoOTsVencidas()` bloquea nuevas solicitudes si hay OTs con autorización vencida  

**Rutas clave:**
- `POST /solicitudes` → store
- `POST /solicitudes/{id}/aprobar` → aprobar
- `POST /solicitudes/{id}/rechazar` → rechazar

---

### 3. ÓRDENES DE TRABAJO (OT)

```
ESTADOS (estatus): generada → asignada → completada → autorizada_cliente → facturada → entregada
ESTADOS (calidad_resultado): pendiente → validado / rechazado
ESTADOS (ot_status): active → partial / closed / canceled
```

#### 3a. Creación

| Evento | Trigger | Controller/Método | Roles | Notificaciones |
|--------|---------|-------------------|-------|----------------|
| Crear OT desde solicitud | Coord genera OT | `OrdenController::storeFromSolicitud()` | admin, coordinador | ✅ `OtAsignada` al TL (si se asigna) + `Notifier::toRoleInCentro('calidad')` |
| Crear OT multi-servicio directa | Auto desde solicitud multi | `OrdenController::createFromSolicitud()` | admin, coordinador | ✅ `Notifier::toRoleInCentro('calidad')` |
| Crear OT multi-servicio manual | Formulario | `OTMultiServicioController::store()` | auth | ✅ `Notifier::toRoleInCentro('coordinador')` |

#### 3b. Asignación

| Evento | Trigger | Controller/Método | Roles | Notificaciones |
|--------|---------|-------------------|-------|----------------|
| Asignar TL | PATCH | `OrdenController::asignarTL()` | admin, coordinador | ✅ `OtAsignada` al TL (DB+mail) |

#### 3c. Producción (Avances)

| Evento | Trigger | Controller/Método | Roles | Notificaciones |
|--------|---------|-------------------|-------|----------------|
| Registrar avance | POST | `OrdenController::registrarAvance()` | admin, coordinador, TL asignado | ❌ Sin notificación directa |
| Auto-completar OT | Avances ≥ planeados | Dentro de `registrarAvance()` | automático | ✅ `OtListaParaCalidad` a usuarios calidad del centro |
| Registrar faltantes | POST | `OrdenController::registrarFaltantes()` | admin, coordinador, TL asignado | ❌ Sin notificación |
| Registrar faltantes multi-servicio | POST | `OTMultiServicioController::registrarFaltantesServicio()` | — | ❌ Sin notificación |

**Bloqueos post-corte:** Si `ot_status` = partial/closed/canceled → no se permiten avances (OrdenPolicy + controllers)

#### 3d. Calidad

| Evento | Trigger | Controller/Método | Roles | Notificaciones |
|--------|---------|-------------------|-------|----------------|
| Validar calidad | POST | `CalidadController::validar()` | admin, calidad | ✅ `OtValidadaParaCliente` al cliente (DB+mail) + Job `GenerateOrdenPdf` |
| Rechazar calidad | POST | `CalidadController::rechazar()` | admin, calidad | ⚠️ **DUPLICADO**: `CalidadResultadoNotification(RECHAZADO)` (mail) + `Notifier::toUser(Calidad rechazada)` (DB+mail) al mismo cliente |

**Al rechazar:** estatus vuelve a `en_proceso`, `calidad_resultado` = `rechazado`, `fecha_completada` = null

#### 3e. Autorización Cliente

| Evento | Trigger | Controller/Método | Roles | Notificaciones |
|--------|---------|-------------------|-------|----------------|
| Cliente autoriza | POST | `ClienteController::autorizar()` | admin, dueño solicitud, Cliente_Gerente | ✅ `OtAutorizadaParaFacturacion` a rol facturacion del centro (DB+mail) |

**Recordatorios automáticos:** `EnviarRecordatoriosValidacion` (scheduled command) → `RecordatorioValidacionOt` al cliente cada N minutos si OT validada sin autorizar

#### 3f. Corte de OT (Split) — NUEVO

```
ESTADOS CORTE: ready_to_bill → void (billed solo vía proceso factura)
```

| Evento | Trigger | Controller/Método | Roles | Notificaciones |
|--------|---------|-------------------|-------|----------------|
| Preview corte | POST | `OtCorteController::preview()` | auth | Ninguna |
| Crear corte | POST | `OtCorteController::store()` → `OtSplitService::crearCorte()` | auth | ❌ **FALTA**: ninguna notificación |
| Crear OT hija | automático en crearCorte() | `OtSplitService::crearOtHija*()` | automático | ❌ **FALTA**: TL/coordinador no se enteran |
| Anular corte | PATCH | `OtCorteController::updateEstatus()` | auth | ❌ **FALTA**: ninguna notificación |

**Efectos del corte:**
- `ot_status` cambia a `partial` (si hay hija) o `closed` (si no)
- `estatus` cambia a `completada` (para entrar a calidad)
- `calidad_resultado` = `pendiente`
- OT hija hereda TL, servicio, centro, solicitud, con status `active`

---

### 4. FACTURAS

```
ESTADOS: facturado → por_pagar → pagado
```

| Transición | Trigger | Controller/Método | Roles | Notificaciones |
|------------|---------|-------------------|-------|----------------|
| Crear factura individual | POST | `FacturaController::storeFromOrden()` | admin, facturacion | ✅ `GenerateFacturaPdf` Job (+ notifica cliente con PDF adjunto) |
| Crear factura batch | POST | `FacturaController::storeBatch()` | admin, facturacion | ✅ `Notifier::toUser` a clientes del centro + `GenerateFacturaPdf` Job |
| Marcar facturado | POST | `FacturaController::marcarFacturado()` | admin, facturacion | ✅ `Notifier::toUser` a clientes + regenera PDF |
| Marcar cobro (por_pagar) | POST | `FacturaController::marcarCobro()` | admin, facturacion | ✅ `Notifier::toUser` a clientes + regenera PDF |
| Marcar pagado | POST | `FacturaController::marcarPagado()` | admin, facturacion | ✅ `Notifier::toUser` a clientes + regenera PDF |
| Subir XML (CFDI) | POST | `FacturaController::uploadXml()` | admin, facturacion | Ninguna |

**Rutas clave:**
- `POST /ordenes/{id}/facturar` → storeFromOrden
- `POST /facturas/batch` → storeBatch
- `POST /facturas/{id}/facturado` → marcarFacturado
- `POST /facturas/{id}/cobro` → marcarCobro
- `POST /facturas/{id}/pagado` → marcarPagado

---

### 5. BACKUPS (Sistema)

| Evento | Trigger | Listener | Notificaciones |
|--------|---------|----------|----------------|
| Backup exitoso | Spatie `BackupWasSuccessful` | `NotifyBackupOk` | ✅ `SystemEventNotification` a todos los admin |
| Backup fallido | Spatie `BackupHasFailed` / `CleanupHasFailed` | `NotifyBackupFailed` | ✅ `SystemEventNotification` a todos los admin |

---

### 6. CATÁLOGOS (Sin notificaciones)

- **Servicios/Precios:** CRUD (`PrecioController`) — middleware `check.servicios`
- **Centros de Costos:** CRUD (`CentroCostoController`) — middleware `check.areas`
- **Marcas:** CRUD (`MarcaController`) — middleware `check.areas`
- **Áreas:** CRUD (`AreaController`) — middleware `check.areas`
- **Feature Flags:** Admin toggle por centro (`CentroFeatureController`)
- **Usuarios:** Admin CRUD + toggle activo + reset pass + impersonación

---

## B) EMAIL MATRIX (Inventario de Emails Existentes)

### Tabla de Notificaciones

| # | Nombre / Propósito | Clase Notification | Canales | Trigger (Dónde se dispara) | Condiciones | Destinatarios | Plantilla | ¿Usa Cola? | Riesgos |
|---|---|---|---|---|---|---|---|---|---|
| 1 | **Solicitud creada** (a coordinador) | `SystemEventNotification` (via Notifier) | DB + mail | `SolicitudController::store()` | Siempre al crear | Coordinadores del centro | MailMessage genérico | ❌ Sync | `solicitud->centro` null crash |
| 2 | **Solicitud aprobada** (a cliente) | `SystemEventNotification` (via Notifier) | DB + mail | `SolicitudController::aprobar()` | Siempre al aprobar | Cliente (id_cliente) | MailMessage genérico | ❌ Sync | — |
| 3 | **Cotización enviada** (a cliente mail) | `QuotationSentNotification` | mail (o DB+mail) | `CotizacionController::send()` | Siempre al enviar | Cliente o email específico | `emails.quotations.sent` (Markdown) | ❌ Sync | Token plano en URL |
| 4 | **Cotización enviada** (campanita DB) | `QuotationSentDatabaseNotification` | DB | `CotizacionController::send()` | Solo con recipient específico | Cliente (campanita) | — (solo DB) | ❌ Sync | — |
| 5 | **Cotización aprobada** (a coordinador) | `QuotationApprovedCoordinatorNotification` | DB + mail (condicional) | `QuotationApprovalService::notifyCoordinator()` | `business.notify_coordinator_email_on_quotation_approved` | Coordinador creador | MailMessage genérico | ❌ Sync | Mail deshabilitado por defecto |
| 6 | **Cotización rechazada** (a creador) | `SystemEventNotification` (via Notifier) | DB + mail | `CotizacionController::reject()` | Siempre | Creador de la cotización | MailMessage genérico | ❌ Sync | — |
| 7 | **OT asignada** (a Team Leader) | `OtAsignada` | DB + mail | `OrdenController::storeFromSolicitud()` / `asignarTL()` | Solo si se asigna TL | Team Leader asignado | MailMessage genérico | ❌ Sync | `orden->servicio` null |
| 8 | **OT generada** (a calidad del centro) | `SystemEventNotification` (via Notifier) | DB + mail | `OrdenController::storeFromSolicitud()` | Siempre al crear OT | Calidad del centro | MailMessage genérico | ❌ Sync | Prematuro: calidad no puede actuar aún |
| 9 | **OT lista para calidad** | `OtListaParaCalidad` | DB + mail | `OrdenController::registrarAvance()` | Auto al completar (avances ≥ planeados) | Calidad del centro | MailMessage genérico | ❌ Sync | — |
| 10 | **OT validada por calidad** (a cliente) | `OtValidadaParaCliente` | DB + mail | `CalidadController::validar()` | Siempre al validar | Cliente/dueño solicitud | MailMessage genérico | ❌ Sync | `solicitud->cliente` null |
| 11 | **Calidad rechazada** (a cliente — #1) | `CalidadResultadoNotification` | mail | `CalidadController::rechazar()` | Siempre | Cliente | MailMessage genérico | ❌ Sync | ⚠️ DUPLICADO con #12 |
| 12 | **Calidad rechazada** (a cliente — #2) | `SystemEventNotification` (via Notifier) | DB + mail | `CalidadController::rechazar()` | Siempre | Cliente | MailMessage genérico | ❌ Sync | ⚠️ DUPLICADO con #11 |
| 13 | **OT autorizada para facturación** | `OtAutorizadaParaFacturacion` | DB + mail | `ClienteController::autorizar()` | Siempre | Facturación del centro | MailMessage genérico | ❌ Sync | — |
| 14 | **Factura generada** (individual) | `FacturaGeneradaNotification` | DB + mail | `GenerateFacturaPdf` Job | $notifyClient = true | Cliente (vía solicitud) | MailMessage + PDF adjunto | ✅ Queued (Job) | PDF puede no existir aún |
| 15 | **Factura generada** (batch, a clientes) | `SystemEventNotification` (via Notifier) | DB + mail | `FacturaController::storeBatch()` | Siempre | Clientes del centro | MailMessage genérico | ❌ Sync | Loop síncrono = lento |
| 16 | **Factura marcada facturada** | `SystemEventNotification` (via Notifier) | DB + mail | `FacturaController::marcarFacturado()` | Siempre | Clientes del centro | MailMessage genérico | ❌ Sync | Loop síncrono |
| 17 | **Factura por pagar (cobro)** | `SystemEventNotification` (via Notifier) | DB + mail | `FacturaController::marcarCobro()` | Siempre | Clientes del centro | MailMessage genérico | ❌ Sync | Loop síncrono |
| 18 | **Factura pagada** | `SystemEventNotification` (via Notifier) | DB + mail | `FacturaController::marcarPagado()` | Siempre | Clientes del centro | MailMessage genérico | ❌ Sync | Loop síncrono |
| 19 | **Recordatorio autorización OT** | `RecordatorioValidacionOt` | DB + mail | `EnviarRecordatoriosValidacion` (scheduled) | OT validada > N min sin autorizar | Cliente dueño | MailMessage genérico | ❌ Sync (scheduled) | Frecuencia everyMinute |
| 20 | **Backup exitoso** | `SystemEventNotification` | DB + mail | `NotifyBackupOk` listener | Backup Spatie OK | Admins | MailMessage genérico | ❌ Sync | — |
| 21 | **Backup fallido** | `SystemEventNotification` | DB + mail | `NotifyBackupFailed` listener | Backup Spatie FAIL | Admins | MailMessage genérico | ❌ Sync | — |

### Notificaciones DEFINIDAS pero NUNCA UTILIZADAS (Código Muerto)

| Clase | Motivo |
|-------|--------|
| `ClienteAutorizoNotification` | Importada en `ClienteController` pero jamás llamada (se usa `OtAutorizadaParaFacturacion` + `Notify::send` en su lugar) |
| `SolicitudCreadaNotification` | Definida en `app/Notifications/` pero no se importa ni usa en ningún controller — se usa `Notifier::toRoleInCentro` con `SystemEventNotification` en su lugar |

---

## C) GAP LIST (Emails/Notificaciones Faltantes)

### P0 — Críticos (impactan flujo de negocio principal)

#### GAP-1: Corte de OT generado — Sin notificación

**Cuándo:** Al crear un corte en `OtSplitService::crearCorte()`  
**Quién debería ser notificado:**
- Coordinador del centro (que gestiona OTs)
- Facturación del centro (corte listo para cobro)
- Cliente (resumen del corte semanal, opcional)

**Propuesta técnica:**
```
Evento:      App\Events\OtCorteCreated
Notification: App\Notifications\OtCorteGeneradoNotification
Canales:     database + mail
Template:    MailMessage con datos del corte (folio, monto, periodo, detalles)
Disparar en: OtSplitService::crearCorte() — después de DB::transaction
Destinatarios: Notifier::toRoleInCentro('coordinador', $ot->id_centrotrabajo) 
               + Notifier::toRoleInCentro('facturacion', $ot->id_centrotrabajo)
```

#### GAP-2: OT Hija creada — Sin notificación

**Cuándo:** Al crear la OT hija con remanente en `crearOtHijaMultiServicio()` / `crearOtHijaTradicional()`  
**Quién debería ser notificado:**
- Team Leader asignado (tiene nueva OT bajo su responsabilidad)
- Coordinador del centro

**Propuesta técnica:**
```
Notification: App\Notifications\OtHijaCreadaNotification
Canales:     database + mail
Disparar en: OtSplitService::crearCorte() — si $otHija != null
Destinatarios: TL de la OT hija (si tiene) + Notifier::toRoleInCentro('coordinador')
```

#### GAP-3: Solicitud rechazada — Sin notificación al cliente

**Cuándo:** `SolicitudController::rechazar()` cambia estatus a `rechazada` pero no notifica  
**Quién debería ser notificado:** Cliente que creó la solicitud

**Propuesta técnica:**
```
Disparar en: SolicitudController::rechazar() — después de guardar
Usar:        Notifier::toUser($solicitud->id_cliente, 'Solicitud rechazada', 
             "Tu solicitud {$solicitud->folio} ha sido rechazada.", ...)
```

### P1 — Importantes (mejoran confiabilidad)

#### GAP-4: Cotización cancelada — Sin notificación al cliente

**Cuándo:** `CotizacionController::cancel()` — si la cotización estaba en `sent`, el cliente no se entera  
**Propuesta:** Notificar al cliente (solo si estaba en estado `sent`)

#### GAP-5: Corte anulado (void) — Sin notificación

**Cuándo:** `OtCorteController::updateEstatus()` → void  
**Propuesta:** Notificar a coordinador y facturación del centro

#### GAP-6: Avances registrados — Sin notificación al coordinador/cliente

**Cuándo:** Se registra un avance significativo. Actualmente silencioso.  
**Propuesta:** Considerar notificación diaria resumida (consolidada) en lugar de por avance

#### GAP-7: Faltantes registrados — Sin notificación

**Cuándo:** `OrdenController::registrarFaltantes()` o `OTMultiServicioController::registrarFaltantesServicio()`  
**Propuesta:** Notificar al coordinador y al cliente cuando se registran faltantes (reduce cantidad planeada)

### P2 — Deseables (mejoran UX)

#### GAP-8: Factura batch — Sin notificación para coordinador

**Cuándo:** `FacturaController::storeBatch()` — solo notifica a clientes, no a coordinador  

#### GAP-9: Importación Excel exitosa/fallida — Sin notificación

**Cuándo:** `SolicitudExcelController::parseExcel()` — solo retorna resultado al frontend, sin email  
**Propuesta:** Opcional — notificar al usuario que subió si fue background

#### GAP-10: Evidencias cargadas/rechazadas — Sin notificación

**Cuándo:** `EvidenciaController::store()` / `destroy()` — acciones silenciosas  

---

## D) VERIFICACIÓN DE CONFIGURACIÓN Y ENTREGABILIDAD

### D.1 Configuración de Mail

| Parámetro | .env.example | Riesgo |
|-----------|-------------|--------|
| `MAIL_MAILER` | `log` | ⚠️ **CRÍTICO**: Si producción tiene `log`, ningún correo llega. DEBE ser `smtp` |
| `MAIL_HOST` | `127.0.0.1` | OK solo si hay SMTP local |
| `MAIL_PORT` | `2525` | No standard. SMTP normal usa 587 (TLS) o 465 (SSL) |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | ⚠️ **INVÁLIDO** para producción |
| `MAIL_FROM_NAME` | `${APP_NAME}` | OK |
| `MAIL_USERNAME/PASSWORD` | `null` | ⚠️ Sin autenticación |

### D.2 Configuración de Queue

| Parámetro | Valor | Riesgo |
|-----------|-------|--------|
| `QUEUE_CONNECTION` | `database` | ✅ OK, pero requiere worker activo |
| `retry_after` | `90` segundos | OK |
| `failed_jobs driver` | `database-uuids` | ✅ OK |
| Worker en prod | ❓ **No verificable** | Necesita `php artisan queue:work` permanente o supervisor/systemd |

### D.3 Jobs con Cola

| Job | Queue | ¿ShouldQueue? | Riesgo |
|-----|-------|----------------|--------|
| `GenerateOrdenPdf` | `pdfs` | ✅ Sí | Custom queue `pdfs` — worker debe escuchar esta queue |
| `GenerateFacturaPdf` | `default` | ✅ Sí | Envía notificación al cliente con PDF adjunto |

### D.4 Notificaciones — Resumen de Canales

| Característica | Estado | Riesgo |
|---------------|--------|--------|
| `ShouldQueue` en notificaciones | ❌ **Ninguna** lo implementa | TODAS bloquean la HTTP request. Si SMTP es lento → timeouts |
| `SystemEventNotification` explícitamente NO usa ShouldQueue | Comentario en código: `// Sin ShouldQueue para ejecución sincrónica` | Intencional pero riesgoso en prod |
| Canales duplicados | `Notifier` (Services) vs `Notify` (Support) | Confuso; ambos hacen lo mismo |
| Mail views custom | Solo 1: `emails.quotations.sent` | Las demás 12 notificaciones usan MailMessage genérico de Laravel |
| i18n / traducciones | ❌ No implementado | Textos hardcoded en español |

### D.5 Riesgos Detectados

1. **emails enviados en sync dentro de loops** — `FacturaController::storeBatch()`, `marcarFacturado()`, `marcarCobro()`, `marcarPagado()` iteran sobre clientes del centro enviando `Notifier::toUser()` sincrónicamente. Si hay 20 clientes → 20 llamadas SMTP secuenciales bloqueando la request.

2. **listeners sin ShouldQueue** — `NotifyBackupFailed`, `NotifyBackupOk`, `CreateSolicitudesFromQuotationApproval` todos son síncronos. El listener de backup notifica a TODOS los admins en sync.

3. **notificación duplicada en calidad rechazada** — `CalidadController::rechazar()` envía:
   - `CalidadResultadoNotification` (mail directo con observaciones detalladas)
   - `Notifier::toUser` SystemEvent (DB+mail con mensaje genérico)
   - Resultado: el cliente recibe **2 emails** + **1 notificación DB**

4. **RecordatorioValidacionOt con `everyMinute()`** — El scheduled command corre cada minuto. Aunque tiene lógica de intervalo interno, si falla el check → posible spam.

5. **`$factura->orden->solicitud->cliente`** — Cadena de relaciones largas sin null-safe. Si algún eslabón es null → crash en GenerateFacturaPdf job (fail silencioso, queda en failed_jobs).

6. **PDF adjunto en FacturaGeneradaNotification** — Si `Storage::path()` devuelve ruta inválida o archivo corrupto → mail falla pero error se traga.

---

## E) QUICK WINS (5-10 fixes rápidos)

### QW-1: Notificar al cliente cuando solicitud es rechazada
```php
// SolicitudController::rechazar() — agregar después de $solicitud->save()
Notifier::toUser(
    $solicitud->id_cliente,
    'Solicitud rechazada',
    "Tu solicitud {$solicitud->folio} fue rechazada." . ($req->motivo ? " Motivo: {$req->motivo}" : ''),
    route('solicitudes.show', $solicitud->id)
);
```

### QW-2: Eliminar notificación duplicada en calidad rechazada
```php
// CalidadController::rechazar() — ELIMINAR el bloque Notifier::toUser() 
// y mantener SOLO CalidadResultadoNotification que es más descriptivo.
// Cambiar CalidadResultadoNotification para que también use canal 'database'.
```

### QW-3: Agregar notificación mínima al crear corte
```php
// OtSplitService::crearCorte() — después del return de DB::transaction
Notifier::toRoleInCentro(
    'facturacion', $ot->id_centrotrabajo,
    'Corte de OT generado',
    "Corte {$corte->folio_corte} por \${$corte->monto_total} listo para facturar.",
    route('ordenes.show', $ot->id)
);
if ($otHija) {
    Notifier::toRoleInCentro(
        'coordinador', $ot->id_centrotrabajo,
        'OT hija creada por corte',
        "Se creó la OT #{$otHija->id} con remanente del corte de OT #{$ot->id}.",
        route('ordenes.show', $otHija->id)
    );
}
```

### QW-4: Eliminar clases muertas
- Borrar `ClienteAutorizoNotification.php` (nunca usada)
- Borrar `SolicitudCreadaNotification.php` (nunca usada)
- O bien reutilizarlas reemplazando los usos de SystemEventNotification genérico

### QW-5: Unificar Notifier y Notify
```php
// Opción recomendada: Mantener App\Services\Notifier y eliminar App\Support\Notify
// ClienteController: reemplazar Notify::usersByRoleAndCenter + Notify::send por Notifier equivalente
```

### QW-6: Agregar `implements ShouldQueue` a notificaciones pesadas
Priorizar estas (tienen PDF adjunto o se envían a múltiples usuarios):
- `FacturaGeneradaNotification` → ya va por Job, OK
- `CalidadResultadoNotification` → agregar `ShouldQueue`
- `OtAsignada` → agregar `ShouldQueue`
- `OtListaParaCalidad` → agregar `ShouldQueue`
- `OtValidadaParaCliente` → agregar `ShouldQueue`
- `OtAutorizadaParaFacturacion` → agregar `ShouldQueue`
- `RecordatorioValidacionOt` → agregar `ShouldQueue`

### QW-7: Agregar CalidadResultadoNotification con canal 'database'
```php
// Actualmente solo usa 'mail'. Agregar 'database' para campanita.
public function via($notifiable){ return ['database', 'mail']; }
// Agregar método toDatabase()
```

### QW-8: Proteger cadenas de relaciones con null-safe
```php
// En GenerateFacturaPdf::handle() — antes de notificar
$cliente = $factura->orden?->solicitud?->cliente;
if (!$cliente) {
    Log::warning("No se encontró cliente para factura #{$factura->id}");
    return;
}
```

### QW-9: Reducir frecuencia de recordatorios
```php
// Console\Kernel — cambiar everyMinute a algo más razonable
$schedule->command('recordatorios:validacion-ot')->everyThirtyMinutes();
```

### QW-10: Configurar .env.example con valores más seguros
```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.ejemplo.com
MAIL_PORT=587
MAIL_USERNAME=tu@correo.com
MAIL_FROM_ADDRESS="noreply@uppercontrol.com"
```

---

## F) CHECKLIST DE PRUEBAS

### F.1 Pruebas End-to-End Manuales

| # | Flujo | Pasos | Verificar |
|---|-------|-------|-----------|
| 1 | **Cotización completa** | Crear cotización → Enviar a cliente → Cliente aprueba → Verificar solicitudes creadas | Email al cliente + campanita coordinador + solicitudes generadas |
| 2 | **Cotización rechazada** | Crear → Enviar → Cliente rechaza | Email notificación al creador |
| 3 | **Solicitud → OT** | Crear solicitud como cliente → Coordinador aprueba → Genera OT con TL | Email a coordinador (solicitud) + Email a cliente (aprobada) + Email a TL (OT asignada) |
| 4 | **Solicitud rechazada** | Crear → Coordinador rechaza | ❌ Actualmente: sin notificación (GAP-3) |
| 5 | **Avances hasta completar** | Registrar avances hasta que cantreal ≥ planeado | Auto-completar + Email a calidad |
| 6 | **Calidad valida** | Completar OT → Calidad valida | Email al cliente (validada para autorizar) |
| 7 | **Calidad rechaza** | Completar → Calidad rechaza | ⚠️ Verificar que NO lleguen 2 emails |
| 8 | **Cliente autoriza** | Validar calidad → Cliente autoriza | Email a facturación (listo para facturar) |
| 9 | **Factura individual** | Autorizar → Facturar → Verificar PDF y email | PDF generado + email con adjunto al cliente |
| 10 | **Factura batch** | Seleccionar varias OTs → Facturar juntas | Notificación a clientes del centro |
| 11 | **Corte de OT** | Registrar avances → Generar corte → Verificar OT hija | ❌ Actualmente: sin notificaciones (GAP-1, GAP-2) |
| 12 | **Corte + bloqueo avances** | Generar corte → Intentar registrar avance en OT padre | Debe bloquearse si ot_status = partial/closed |
| 13 | **OT multi-servicio** | Crear solicitud multi → Generar OT → Avances → Calidad → Autorizar | Mismo flujo que OT normal |
| 14 | **Recordatorios** | OT completada + validada sin autorizar > N minutos | Email automático de recordatorio |
| 15 | **Backup** | Ejecutar backup manual → Verificar notificación | Notificación a admins |

### F.2 Tests Automatizados Sugeridos (Feature Tests)

```php
// tests/Feature/NotificationFlowTest.php

/** @test */
public function solicitud_creada_notifica_coordinador()

/** @test */
public function solicitud_aprobada_notifica_cliente()

/** @test */
public function solicitud_rechazada_debe_notificar_cliente() // Actualmente FALLA (GAP)

/** @test */
public function cotizacion_enviada_notifica_cliente_por_email()

/** @test */
public function cotizacion_aprobada_crea_solicitudes_y_notifica_coordinador()

/** @test */
public function ot_asignada_notifica_team_leader()

/** @test */
public function ot_completada_notifica_calidad()

/** @test */
public function calidad_validada_notifica_cliente()

/** @test */
public function calidad_rechazada_no_envia_duplicados()

/** @test */
public function cliente_autoriza_notifica_facturacion()

/** @test */
public function factura_generada_envia_pdf_al_cliente()

/** @test */
public function corte_ot_debe_notificar_facturacion() // Requiere implementar GAP-1

/** @test */
public function ot_hija_debe_notificar_tl() // Requiere implementar GAP-2

/** @test */
public function avances_bloqueados_post_corte()
```

---

## G) MAPA DE ROLES Y PERMISOS

| Rol | Puede hacer | Centro-scope |
|-----|-------------|--------------|
| `admin` | Todo | Global |
| `coordinador` | Crear/aprobar solicitudes, crear cotizaciones, crear OTs, asignar TL | Su centro |
| `team_leader` | Registrar avances en OTs asignadas a él | OTs asignadas |
| `calidad` | Validar/rechazar calidad de OTs | Su(s) centro(s) |
| `facturacion` | Generar facturas, marcar pagos | Su(s) centro(s) |
| `Cliente_Supervisor` | Crear solicitudes, autorizar OTs, ver facturas propias | Sus solicitudes |
| `Cliente_Gerente` | Todo de Supervisor + autorizar OTs de su centro | Su centro |
| `gerente_upper` | Solo lectura en calidad, facturas, OTs | Sus centros |

---

## H) FEATURE FLAGS ACTIVOS

| Feature Key | Protege | Middleware |
|------------|---------|------------|
| `ver_cotizacion` | Todo el módulo de cotizaciones | `feature:ver_cotizacion` |
| `subir_excel` | Parse/download de Excel de solicitudes | `feature:subir_excel` |

---

## I) ARQUITECTURA DE NOTIFICACIONES — PROPUESTA UNIFICADA

### Estado Actual (problemas)

```
App\Services\Notifier      → usa SystemEventNotification (genérico)
App\Support\Notify          → wrapper para ->notify() a colección de users
Notificaciones específicas  → OtAsignada, FacturaGenerada, etc. (usadas directamente)
```

**Problema:** 3 patrones distintos para el mismo propósito.

### Propuesta Final

```
1. MANTENER App\Services\Notifier → renombrar a NotificationService
2. ELIMINAR App\Support\Notify → migrar usos a NotificationService
3. Para flujos estándar (título + mensaje + url) → usar SystemEventNotification via Notifier
4. Para flujos ricos (con datos, PDF, markdown) → usar clase Notification específica
5. TODAS las Notification que envíen mail → implements ShouldQueue
6. Crear Eventos para puntos clave (OtCorteCreated, OtCompleted, etc.) → Listeners disparan notificaciones
```

---

*Fin del documento de auditoría*
