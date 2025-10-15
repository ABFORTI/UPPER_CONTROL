# Diagramas Técnicos - UPPER_CONTROL

Diagramas detallados de arquitectura, flujos técnicos y procesos internos.

---

## 🏗️ Arquitectura del Sistema

```mermaid
graph TB
    subgraph Cliente["💻 Cliente (Navegador)"]
        UI[Vue.js 3 + Inertia.js]
        CSS[Tailwind CSS]
    end
    
    subgraph Servidor["🖥️ Servidor XAMPP"]
        subgraph Laravel["Laravel 12"]
            Routes[Routes]
            Controllers[Controllers]
            Models[Eloquent Models]
            Jobs[Jobs/Queue]
            Notifications[Notifications]
            Middleware[Middleware]
            Policies[Policies]
        end
        
        subgraph Servicios["📦 Servicios"]
            PDF[Dompdf]
            QR[Simple QR Code]
            Mail[Laravel Mail]
            Permission[Spatie Permission]
            ActivityLog[Spatie Activity Log]
            Backup[Spatie Backup]
            Excel[Maatwebsite Excel]
        end
        
        subgraph Storage["💾 Almacenamiento"]
            FileSystem[Local Storage]
            PDFs[PDFs]
            XMLs[XMLs]
            Images[Imágenes]
        end
    end
    
    subgraph Database["🗄️ Base de Datos"]
        MySQL[(MySQL)]
        Tables[Tablas]
        Migrations[Migrations]
        Seeders[Seeders]
    end
    
    UI <-->|HTTP/AJAX| Routes
    Routes --> Middleware
    Middleware --> Controllers
    Controllers --> Models
    Controllers --> Jobs
    Controllers --> Notifications
    Models <--> MySQL
    Jobs --> PDF
    Jobs --> QR
    Jobs --> Mail
    Notifications --> Mail
    Jobs --> FileSystem
    Models --> Tables
    
    style Cliente fill:#42b883,stroke:#35495e,color:#fff
    style Servidor fill:#ff2d20,stroke:#c62828,color:#fff
    style Database fill:#00758f,stroke:#004d61,color:#fff
    style Laravel fill:#fff3e0,stroke:#ff6f00
    style Servicios fill:#e8eaf6,stroke:#3f51b5
    style Storage fill:#e0f2f1,stroke:#00796b
```

---

## 🔄 Flujo de Procesamiento de Factura (Job)

```mermaid
sequenceDiagram
    participant Controller as FacturaController
    participant Queue as Queue System
    participant Job as GenerateFacturaPdf Job
    participant Storage as File Storage
    participant DB as Database
    participant PDF as Dompdf Service
    participant QR as QR Generator
    participant Mail as Mail Service
    participant Client as Cliente

    Controller->>DB: Crear registro Factura
    DB-->>Controller: Factura creada (ID)
    Controller->>Queue: Dispatch Job(id, notifyClient=true)
    
    Queue->>Job: Ejecutar handle()
    Job->>DB: Cargar Factura con relaciones
    DB-->>Job: Datos completos
    
    Job->>Job: parseCfdi() - Extraer datos XML
    Note over Job: Procesa CFDI 3.3/4.0<br/>Extrae: Emisor, Receptor,<br/>Conceptos, Impuestos, Timbre
    
    Job->>Job: generateQrCode() - Crear QR SAT
    Job->>QR: Generate SVG/PNG
    QR-->>Job: QR Code (SVG fallback)
    
    Job->>PDF: loadView('pdf.factura', data)
    PDF->>PDF: Renderizar HTML con datos
    PDF-->>Job: PDF Binary
    
    Job->>Storage: Guardar PDF
    Storage-->>Job: Path guardado
    
    Job->>DB: Actualizar pdf_path
    
    alt notifyClient == true
        Job->>Mail: FacturaGeneradaNotification
        Mail->>Storage: Leer PDF
        Storage-->>Mail: PDF Binary
        Mail->>Client: Email con PDF adjunto
        Mail->>DB: Guardar notificación
    end
    
    Job-->>Queue: Job completado
    Queue-->>Controller: Success
    Controller-->>Client: Redirect con mensaje
```

---

## 📊 Flujo de Datos - Solicitud a Factura

```mermaid
graph LR
    subgraph Entrada["📥 Entrada"]
        S1[Cliente crea Solicitud]
        S1 --> S2[Datos:<br/>- Descripción<br/>- Área<br/>- Prioridad]
    end
    
    subgraph Aprobacion["✅ Aprobación"]
        A1[Coordinador revisa]
        A1 --> A2{¿Aprueba?}
        A2 -->|Sí| A3[Estado: aprobada]
        A2 -->|No| A4[Estado: rechazada]
    end
    
    subgraph OTCreacion["📋 Creación OT"]
        O1[Seleccionar Servicio]
        O1 --> O2[Seleccionar Centro]
        O2 --> O3[Definir Items]
        O3 --> O4[Asignar Técnico]
        O4 --> O5[Crear OT]
    end
    
    subgraph Ejecucion["⚙️ Ejecución"]
        E1[Técnico registra avances]
        E1 --> E2[Sube evidencias]
        E2 --> E3[Progreso alcanza 100%]
        E3 --> E4[Estado: completada]
    end
    
    subgraph Validacion["✅ Validación"]
        V1[Calidad revisa]
        V1 --> V2{¿Aprueba?}
        V2 -->|Sí| V3[Estado: validada_calidad]
        V2 -->|No| V4[Regresar a ejecución]
        V3 --> V5[Cliente revisa]
        V5 --> V6{¿Autoriza?}
        V6 -->|Sí| V7[Estado: validada_cliente]
        V6 -->|No| V8[Estado: bloqueada]
    end
    
    subgraph Facturacion["💰 Facturación"]
        F1[Crear Factura]
        F1 --> F2[Generar PDF + QR]
        F2 --> F3[Enviar a cliente]
        F3 --> F4[Subir XML]
        F4 --> F5[Marcar estados]
        F5 --> F6[Estado: pagado]
    end
    
    S2 --> A1
    A3 --> O1
    O5 --> E1
    E4 --> V1
    V7 --> F1
    V4 --> E1
    
    style Entrada fill:#e3f2fd,stroke:#1976d2
    style Aprobacion fill:#fff3e0,stroke:#f57c00
    style OTCreacion fill:#f3e5f5,stroke:#7b1fa2
    style Ejecucion fill:#e8f5e9,stroke:#388e3c
    style Validacion fill:#fce4ec,stroke:#c2185b
    style Facturacion fill:#fff9c4,stroke:#f9a825
```

---

## 🔐 Sistema de Autenticación y Autorización

```mermaid
flowchart TD
    Start([Usuario accede]) --> Login[Login Form]
    Login --> Auth{Autenticación<br/>Laravel Breeze}
    
    Auth -->|Falla| LoginError[Error: Credenciales inválidas]
    LoginError --> Login
    
    Auth -->|Éxito| CheckActive{¿Usuario activo?}
    CheckActive -->|No| Inactive[Usuario desactivado]
    Inactive --> Logout([Logout])
    
    CheckActive -->|Sí| Session[Crear sesión]
    Session --> LoadRole[Cargar rol y permisos]
    
    LoadRole --> RoleCheck{Tipo de usuario}
    
    RoleCheck -->|admin| AdminAccess[✅ Acceso total<br/>+ Impersonación]
    RoleCheck -->|coordinador| CoordAccess[✅ Gestión de<br/>Solicitudes y OTs]
    RoleCheck -->|tecnico_lider| TLAccess[✅ OTs asignadas<br/>Avances y evidencias]
    RoleCheck -->|calidad| CalAccess[✅ Validación<br/>de calidad]
    RoleCheck -->|facturacion| FactAccess[✅ Gestión de<br/>facturas]
    RoleCheck -->|cliente| ClienteAccess[✅ Solicitudes<br/>Autorización OTs]
    
    AdminAccess --> Dashboard[Dashboard]
    CoordAccess --> Dashboard
    TLAccess --> Dashboard
    CalAccess --> Dashboard
    FactAccess --> Dashboard
    ClienteAccess --> Dashboard
    
    Dashboard --> Route[Usuario accede a ruta]
    Route --> Middleware{Middleware<br/>role:xxx}
    
    Middleware -->|Autorizado| Policy{Policy check}
    Middleware -->|No autorizado| Error403[403 Forbidden]
    
    Policy -->|Permitido| Controller[Controller action]
    Policy -->|Denegado| Error403
    
    Controller --> Response[Respuesta Inertia]
    Response --> UI[Render Vue.js]
    
    Error403 --> ErrorPage[Página de error]
    
    style Start fill:#4caf50,stroke:#2e7d32,color:#fff
    style Dashboard fill:#2196f3,stroke:#1565c0,color:#fff
    style Error403 fill:#f44336,stroke:#c62828,color:#fff
    style AdminAccess fill:#ff9800,stroke:#e65100,color:#fff
```

---

## 📧 Sistema de Notificaciones

```mermaid
graph TB
    subgraph Triggers["🎯 Eventos que Disparan Notificaciones"]
        T1[OT Asignada a TL]
        T2[OT Lista para Calidad]
        T3[OT Validada para Cliente]
        T4[Cliente Autorizó OT]
        T5[Factura Generada]
        T6[Backup Exitoso/Fallido]
        T7[Recordatorio Validación]
    end
    
    subgraph NotificationSystem["🔔 Sistema de Notificaciones"]
        NS1[Crear Notification]
        NS1 --> NS2{Channels}
        NS2 -->|Database| NS3[Guardar en BD]
        NS2 -->|Mail| NS4[Enviar Email]
    end
    
    subgraph Storage["💾 Almacenamiento"]
        DB[(notifications table)]
        Email[📧 Mail Queue]
    end
    
    subgraph Frontend["💻 Frontend"]
        Bell[🔔 Campana Header]
        Bell --> Count[Badge contador]
        Count --> List[Lista notificaciones]
        List --> Mark[Marcar como leída]
        List --> Action[Click → Navegar]
    end
    
    T1 --> NS1
    T2 --> NS1
    T3 --> NS1
    T4 --> NS1
    T5 --> NS1
    T6 --> NS1
    T7 --> NS1
    
    NS3 --> DB
    NS4 --> Email
    
    DB --> Bell
    
    style Triggers fill:#e8eaf6,stroke:#3f51b5
    style NotificationSystem fill:#fff3e0,stroke:#f57c00
    style Storage fill:#e0f2f1,stroke:#00796b
    style Frontend fill:#fce4ec,stroke:#c2185b
```

---

## 🗄️ Modelo de Base de Datos Detallado

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        string role
        boolean activo
        string remember_token
        timestamps
    }
    
    SOLICITUDES {
        bigint id PK
        bigint id_cliente FK
        bigint id_area FK
        text descripcion
        string prioridad
        enum estado
        text motivo_rechazo
        bigint aprobado_por FK
        timestamp aprobado_at
        timestamps
    }
    
    ORDENES {
        bigint id PK
        bigint id_solicitud FK
        bigint id_tl FK
        bigint id_servicio FK
        bigint id_centro FK
        string numero_ot
        enum estado
        decimal progreso
        text observaciones
        timestamp fecha_inicio
        timestamp fecha_fin
        timestamps
    }
    
    ORDEN_ITEMS {
        bigint id PK
        bigint id_orden FK
        string concepto
        integer cantidad
        decimal precio_unitario
        decimal subtotal
        timestamps
    }
    
    AVANCES {
        bigint id PK
        bigint id_orden FK
        bigint id_usuario FK
        decimal porcentaje
        text descripcion
        timestamp fecha
        timestamps
    }
    
    EVIDENCIAS {
        bigint id PK
        bigint id_orden FK
        bigint id_usuario FK
        string tipo
        string ruta
        text descripcion
        timestamps
    }
    
    FACTURAS {
        bigint id PK
        bigint id_orden FK
        string folio
        decimal subtotal
        decimal iva
        decimal total
        enum estado
        string pdf_path
        string xml_path
        timestamp fecha_facturacion
        timestamp fecha_cobro
        timestamp fecha_pago
        timestamps
    }
    
    ARCHIVOS {
        bigint id PK
        string nombre_original
        string nombre_guardado
        string ruta
        string mime_type
        bigint tamanio
        morphs archivable
        timestamps
    }
    
    AREAS {
        bigint id PK
        string nombre
        text descripcion
        boolean activa
        timestamps
    }
    
    CENTRO_TRABAJO {
        bigint id PK
        string nombre
        string codigo
        string direccion
        boolean activo
        timestamps
    }
    
    SERVICIO_EMPRESA {
        bigint id PK
        string nombre
        text descripcion
        boolean activo
        timestamps
    }
    
    SERVICIO_CENTRO {
        bigint id PK
        bigint id_servicio FK
        bigint id_centro FK
        timestamps
    }
    
    SERVICIO_TAMANO {
        bigint id PK
        bigint id_servicio_centro FK
        string tamano
        decimal precio
        timestamps
    }
    
    NOTIFICATIONS {
        uuid id PK
        string type
        morphs notifiable
        text data
        timestamp read_at
        timestamps
    }
    
    ACTIVITY_LOG {
        bigint id PK
        string log_name
        text description
        morphs subject
        morphs causer
        text properties
        timestamps
    }
    
    USERS ||--o{ SOLICITUDES : "crea (cliente)"
    USERS ||--o{ SOLICITUDES : "aprueba (coordinador)"
    USERS ||--o{ ORDENES : "asignado (TL)"
    USERS ||--o{ AVANCES : "registra"
    USERS ||--o{ EVIDENCIAS : "sube"
    USERS ||--o{ NOTIFICATIONS : "recibe"
    USERS ||--o{ ACTIVITY_LOG : "causa"
    
    AREAS ||--o{ SOLICITUDES : "pertenece"
    
    SOLICITUDES ||--o| ORDENES : "genera"
    
    ORDENES ||--o{ ORDEN_ITEMS : "contiene"
    ORDENES ||--o{ AVANCES : "tiene"
    ORDENES ||--o{ EVIDENCIAS : "tiene"
    ORDENES ||--o| FACTURAS : "genera"
    ORDENES }o--|| CENTRO_TRABAJO : "ubicacion"
    ORDENES }o--|| SERVICIO_EMPRESA : "tipo_servicio"
    
    FACTURAS ||--o{ ARCHIVOS : "xml (polymorphic)"
    
    SERVICIO_EMPRESA ||--o{ SERVICIO_CENTRO : "disponible_en"
    CENTRO_TRABAJO ||--o{ SERVICIO_CENTRO : "ofrece"
    SERVICIO_CENTRO ||--o{ SERVICIO_TAMANO : "precios"
```

---

## 🔄 Job Queue System

```mermaid
graph LR
    subgraph Dispatch["📤 Dispatch"]
        D1[GenerateFacturaPdf::dispatch]
        D2[GenerateOrdenPdf::dispatch]
        D3[NotifyBackupOk]
        D4[NotifyBackupFailed]
    end
    
    subgraph Queue["⚙️ Queue Driver: sync"]
        Q1[Ejecuta inmediatamente]
        Q1 --> Q2[Sin cola real]
        Q2 --> Q3[Proceso síncrono]
    end
    
    subgraph Jobs["🎯 Jobs"]
        J1[GenerateFacturaPdf]
        J2[GenerateOrdenPdf]
    end
    
    subgraph Process["🔨 Procesamiento"]
        P1[handle method]
        P1 --> P2[Lógica del job]
        P2 --> P3[Interactuar con servicios]
        P3 --> P4[Guardar resultados]
    end
    
    subgraph Services["📦 Servicios Usados"]
        S1[Dompdf]
        S2[QR Generator]
        S3[Storage]
        S4[Mail]
    end
    
    D1 --> Q1
    D2 --> Q1
    D3 --> Q1
    D4 --> Q1
    
    Q3 --> J1
    Q3 --> J2
    
    J1 --> P1
    J2 --> P1
    
    P3 --> S1
    P3 --> S2
    P3 --> S3
    P3 --> S4
    
    style Queue fill:#ffeb3b,stroke:#f57f17,color:#000
    style Jobs fill:#4caf50,stroke:#2e7d32,color:#fff
    style Services fill:#2196f3,stroke:#1565c0,color:#fff
```

---

## 📄 Proceso de Generación de PDF

```mermaid
flowchart TD
    Start([Inicio Generación PDF]) --> Type{Tipo de PDF}
    
    Type -->|Factura| LoadFactura[Cargar Factura<br/>con relaciones]
    Type -->|Orden| LoadOrden[Cargar Orden<br/>con relaciones]
    
    LoadFactura --> ParseXML{¿Existe XML?}
    ParseXML -->|Sí| ExtractCFDI[Extraer datos CFDI<br/>parseCfdi method]
    ParseXML -->|No| DefaultData[Usar datos de BD]
    
    ExtractCFDI --> ProcessData[Procesar datos:<br/>- Emisor<br/>- Receptor<br/>- Conceptos<br/>- Impuestos<br/>- Timbre<br/>- UUID]
    DefaultData --> PrepareView
    ProcessData --> GenQR[Generar QR SAT]
    
    GenQR --> TryPNG{¿Imagick<br/>disponible?}
    TryPNG -->|Sí| QRPng[QR formato PNG]
    TryPNG -->|No| QRSvg[QR formato SVG]
    
    QRPng --> PrepareView[Preparar datos para vista]
    QRSvg --> PrepareView
    
    LoadOrden --> PrepareView
    
    PrepareView --> SelectView{Seleccionar vista}
    SelectView -->|Factura| ViewFactura[pdf.factura]
    SelectView -->|Orden| ViewOrden[pdf.orden]
    
    ViewFactura --> LoadView[Dompdf::loadView]
    ViewOrden --> LoadView
    
    LoadView --> SetPaper[setPaper('letter')]
    SetPaper --> RenderPDF[Renderizar PDF]
    
    RenderPDF --> SaveStorage[Guardar en Storage]
    SaveStorage --> UpdateDB[Actualizar path en BD]
    
    UpdateDB --> CheckNotify{¿Enviar<br/>notificación?}
    CheckNotify -->|Sí| SendMail[Enviar Email<br/>con PDF adjunto]
    CheckNotify -->|No| End
    
    SendMail --> End([PDF Generado])
    
    style Start fill:#4caf50,stroke:#2e7d32,color:#fff
    style End fill:#4caf50,stroke:#2e7d32,color:#fff
    style GenQR fill:#ff9800,stroke:#e65100,color:#fff
    style RenderPDF fill:#2196f3,stroke:#1565c0,color:#fff
    style SendMail fill:#9c27b0,stroke:#6a1b9a,color:#fff
```

---

## 🔍 Parser CFDI (XML Factura)

```mermaid
graph TD
    Start([XML File]) --> Load[Cargar XML]
    Load --> Check{¿XML válido?}
    Check -->|No| Error[Log error y retornar null]
    Check -->|Sí| DetectVersion{Detectar versión CFDI}
    
    DetectVersion -->|3.3| V33[Namespace CFDI 3.3]
    DetectVersion -->|4.0| V40[Namespace CFDI 4.0]
    
    V33 --> ParseComprobante[Extraer Comprobante:<br/>- Folio<br/>- Fecha<br/>- FormaPago<br/>- MetodoPago<br/>- TipoCambio<br/>- Moneda]
    V40 --> ParseComprobante
    
    ParseComprobante --> ParseEmisor[Extraer Emisor:<br/>- RFC<br/>- Nombre<br/>- RegimenFiscal]
    ParseEmisor --> ParseReceptor[Extraer Receptor:<br/>- RFC<br/>- Nombre<br/>- UsoCFDI<br/>- DomicilioFiscal<br/>- RegimenFiscal]
    
    ParseReceptor --> ParseConceptos[Extraer Conceptos:<br/>Loop sobre items]
    ParseConceptos --> ForEachConcepto{Para cada concepto}
    
    ForEachConcepto --> ConceptoData[Extraer:<br/>- Cantidad<br/>- Unidad<br/>- Descripción<br/>- ValorUnitario<br/>- Importe<br/>- ObjetoImp]
    ConceptoData --> ForEachConcepto
    
    ForEachConcepto -->|Fin| ParseImpuestos[Extraer Impuestos:<br/>- TotalImpTraslados<br/>- TotalImpRetenidos]
    
    ParseImpuestos --> ParseTimbre[Extraer Timbre Fiscal:<br/>- UUID<br/>- FechaTimbrado<br/>- SelloCFD<br/>- SelloSAT<br/>- NoCertSAT<br/>- RfcProvCertif]
    
    ParseTimbre --> FormatData[Formatear datos:<br/>- Totales<br/>- Fechas<br/>- Montos]
    
    FormatData --> Return[Retornar array<br/>con todos los datos]
    Return --> End([Datos procesados])
    
    Error --> End
    
    style Start fill:#2196f3,stroke:#1565c0,color:#fff
    style End fill:#4caf50,stroke:#2e7d32,color:#fff
    style Error fill:#f44336,stroke:#c62828,color:#fff
    style ParseTimbre fill:#ff9800,stroke:#e65100,color:#fff
```

---

## 🔐 Middleware Stack

```mermaid
graph LR
    Request[HTTP Request] --> M1[StartSession]
    M1 --> M2[ShareErrorsFromSession]
    M2 --> M3[VerifyCsrfToken]
    M3 --> M4[SubstituteBindings]
    M4 --> M5[HandleInertiaRequests]
    
    M5 --> Auth{Ruta protegida?}
    Auth -->|No| Controller
    Auth -->|Sí| M6[Authenticate]
    
    M6 --> Role{Requiere rol?}
    Role -->|No| Controller
    Role -->|Sí| M7[EnsureUserHasRole]
    
    M7 --> Permission{Requiere permiso?}
    Permission -->|No| Controller
    Permission -->|Sí| M8[Permission Middleware]
    
    M8 --> Controller[Controller]
    Controller --> Policy[Policy Check]
    Policy --> Action[Controller Action]
    Action --> Response[Response]
    
    Response --> M9[HandleInertiaRequests]
    M9 --> M10[AddQueuedCookiesToResponse]
    M10 --> M11[EncryptCookies]
    M11 --> Output[HTTP Response]
    
    style Request fill:#4caf50,stroke:#2e7d32,color:#fff
    style Output fill:#4caf50,stroke:#2e7d32,color:#fff
    style M6 fill:#ff9800,stroke:#e65100,color:#fff
    style M7 fill:#f44336,stroke:#c62828,color:#fff
    style M8 fill:#f44336,stroke:#c62828,color:#fff
```

---

## 📊 Dashboard Data Flow

```mermaid
sequenceDiagram
    participant User as Usuario
    participant Browser as Navegador
    participant Inertia as Inertia.js
    participant Controller as DashboardController
    participant DB as Database
    participant View as Vue Component

    User->>Browser: Accede a /dashboard
    Browser->>Inertia: GET /dashboard
    Inertia->>Controller: index()
    
    Controller->>DB: Query OTs por estado
    DB-->>Controller: Conteo OTs
    
    Controller->>DB: Query Facturas por estado
    DB-->>Controller: Conteo Facturas
    
    Controller->>DB: Query actividad reciente
    DB-->>Controller: Actividad
    
    Controller->>DB: Query notificaciones pendientes
    DB-->>Controller: Notificaciones
    
    Controller->>Controller: Formatear datos por rol
    Controller->>Inertia: Inertia response con props
    Inertia->>View: Renderizar Dashboard.vue
    View->>Browser: HTML/CSS/JS
    Browser->>User: Mostrar dashboard
    
    User->>View: Click en "Exportar OTs"
    View->>Controller: GET /dashboard/export/ots
    Controller->>DB: Query todas las OTs
    DB-->>Controller: Dataset completo
    Controller->>Controller: Generar Excel
    Controller-->>Browser: Descargar archivo
    Browser-->>User: archivo.xlsx
```

---

## 🎨 Frontend Component Architecture

```mermaid
graph TB
    subgraph Layouts
        L1[AuthenticatedLayout]
        L2[GuestLayout]
    end
    
    subgraph Pages
        P1[Dashboard]
        P2[Solicitudes/Index]
        P3[Ordenes/Show]
        P4[Facturas/Index]
        P5[Admin/Users]
    end
    
    subgraph Components
        C1[PrimaryButton]
        C2[TextInput]
        C3[Modal]
        C4[DataTable]
        C5[FilePreview]
        C6[NotificationBell]
        C7[StatusBadge]
    end
    
    subgraph Composables
        CO1[useAuth]
        CO2[useNotifications]
        CO3[useFilters]
    end
    
    L1 --> P1
    L1 --> P2
    L1 --> P3
    L1 --> P4
    L1 --> P5
    L2 --> Login[Login/Register]
    
    P1 --> C4
    P1 --> C7
    P2 --> C1
    P2 --> C4
    P3 --> C5
    P3 --> C1
    P4 --> C4
    P4 --> C2
    P5 --> C3
    
    P1 --> CO1
    P2 --> CO2
    P4 --> CO3
    
    style Layouts fill:#42b883,stroke:#35495e,color:#fff
    style Pages fill:#64b5f6,stroke:#1976d2,color:#fff
    style Components fill:#81c784,stroke:#388e3c,color:#fff
    style Composables fill:#ffb74d,stroke:#f57c00,color:#fff
```

---

## 📱 Loading System (Banda Transportadora)

```mermaid
flowchart TD
    Start([Navegación/Acción]) --> Trigger[Evento Inertia]
    Trigger --> Check{Tipo de acción}
    
    Check -->|Primera carga| AppSplash[App Splash Screen<br/>Logo + Barra progreso]
    Check -->|Navegación| ProcessLoader[Process Loader<br/>Banda transportadora]
    
    ProcessLoader --> Show[Mostrar loader]
    Show --> UpdateText[Actualizar texto según ruta:<br/>- factura: "Procesando factura..."<br/>- orden: "Procesando orden..."<br/>- etc.]
    
    UpdateText --> Animate[Animación CSS:<br/>- Cajas moviéndose<br/>- Banda girando<br/>- Delay entre cajas]
    
    Animate --> Wait[Esperar respuesta]
    Wait --> Response{Respuesta recibida}
    
    Response -->|Success| Hide[Ocultar loader]
    Response -->|Error| ShowError[Mostrar error]
    ShowError --> Hide
    
    AppSplash --> ProgressBar[Barra de progreso<br/>animada 14% → 100%]
    ProgressBar --> AppLoaded[App cargada]
    AppLoaded --> HideApp[Ocultar splash]
    
    Hide --> Complete([Completado])
    HideApp --> Complete
    
    style Start fill:#4caf50,stroke:#2e7d32,color:#fff
    style Complete fill:#4caf50,stroke:#2e7d32,color:#fff
    style Animate fill:#ff9800,stroke:#e65100,color:#fff
    style ShowError fill:#f44336,stroke:#c62828,color:#fff
```

---

## 🔧 Configuración del Entorno

```mermaid
graph TB
    subgraph ENV["🔧 .env Configuration"]
        E1[APP_ENV=local]
        E2[APP_DEBUG=true]
        E3[APP_URL=http://localhost/UPPER_CONTROL/public]
        E4[DB_CONNECTION=mysql]
        E5[DB_DATABASE=upper_control]
        E6[MAIL_MAILER=log]
        E7[QUEUE_CONNECTION=sync]
        E8[SESSION_DRIVER=file]
    end
    
    subgraph Services["📦 Servicios Configurados"]
        S1[Laravel Dompdf]
        S2[Simple QR Code]
        S3[Spatie Permission]
        S4[Spatie Activity Log]
        S5[Spatie Backup]
        S6[Maatwebsite Excel]
    end
    
    subgraph Providers["🔌 Service Providers"]
        SP1[AppServiceProvider]
        SP2[AuthServiceProvider]
        SP3[EventServiceProvider]
        SP4[RouteServiceProvider]
        SP5[PermissionServiceProvider]
    end
    
    ENV --> Services
    Services --> Providers
    
    Providers --> Boot[Application Boot]
    Boot --> Routes[Load Routes]
    Boot --> Middleware[Load Middleware]
    Boot --> Policies[Load Policies]
    
    style ENV fill:#fff3e0,stroke:#f57c00
    style Services fill:#e8eaf6,stroke:#3f51b5
    style Providers fill:#e0f2f1,stroke:#00796b
```

---

## 📝 Resumen de Patrones de Diseño Utilizados

### 🏗️ Arquitecturales
- **MVC**: Model-View-Controller (Laravel)
- **Repository Pattern**: Eloquent ORM
- **Service Container**: Laravel IoC Container
- **Facade Pattern**: Laravel Facades

### 🔄 De Comportamiento
- **Observer Pattern**: Laravel Events & Listeners
- **Strategy Pattern**: Queue Drivers, Mail Drivers
- **Command Pattern**: Artisan Commands
- **Chain of Responsibility**: Middleware Stack

### 🏭 Creacionales
- **Factory Pattern**: Model Factories
- **Builder Pattern**: Query Builder, PDF Builder
- **Singleton Pattern**: Config, Cache

### 📊 De Datos
- **Active Record**: Eloquent Models
- **Data Mapper**: Database Migrations
- **Unit of Work**: Database Transactions

---

**Generado**: 14 de octubre de 2025  
**Framework**: Laravel 12.26.4  
**Vue**: 3.x + Inertia.js 2.x
