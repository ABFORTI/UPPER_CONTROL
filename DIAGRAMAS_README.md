# 📚 Documentación de Diagramas - UPPER_CONTROL

Guía completa para entender, visualizar y utilizar los diagramas del sistema.

---

## 📂 Archivos de Diagramas

Este proyecto incluye **3 archivos principales** con diagramas en formato Mermaid:

### 1️⃣ **DIAGRAMAS.md** - Diagramas Generales
- 📋 **Diagrama de Casos de Uso**: Todos los actores y funcionalidades
- 🔄 **Diagrama de Flujo Principal**: Proceso completo del sistema
- 💰 **Diagrama de Flujo de Facturación**: Proceso detallado de facturación
- 📊 **Diagrama de Estados de OT**: Estados y transiciones
- 🔐 **Diagrama de Roles y Permisos**: Mind map de permisos
- 📧 **Diagrama de Flujo de Notificaciones**: Sistema de notificaciones
- 🗂️ **Diagrama de Base de Datos**: Relaciones principales
- 📱 **Tecnologías Utilizadas**: Stack tecnológico

### 2️⃣ **DIAGRAMAS_TECNICOS.md** - Diagramas Team Leaders
- 🏗️ **Arquitectura del Sistema**: Componentes y capas
- 🔄 **Flujo de Procesamiento de Factura**: Job detallado
- 📊 **Flujo de Datos**: Solicitud → Factura
- 🔐 **Autenticación y Autorización**: Sistema de permisos
- 📧 **Sistema de Notificaciones**: Estructura técnica
- 🗄️ **Modelo de Base de Datos Detallado**: Todas las tablas
- 🔄 **Job Queue System**: Sistema de colas
- 📄 **Proceso de Generación de PDF**: Workflow completo
- 🔍 **Parser CFDI**: Extracción de datos XML
- 🔐 **Middleware Stack**: Cadena de middleware
- 📊 **Dashboard Data Flow**: Flujo de datos dashboard
- 🎨 **Frontend Component Architecture**: Componentes Vue
- 📱 **Loading System**: Sistema de carga

### 3️⃣ **DIAGRAMAS_SECUENCIA.md** - Diagramas de Secuencia
- 📝 **Crear y Aprobar Solicitud**: Flujo completo
- 📋 **Crear OT desde Solicitud**: Generación de órdenes
- ⚙️ **Registrar Avances y Evidencias**: Trabajo del TL (Team Leader)
- ✅ **Validación de Calidad**: Proceso de aprobación
- 👥 **Autorización del Cliente**: Cliente autoriza OT
- 💰 **Proceso Completo de Facturación**: De creación a pago
- 🔔 **Sistema de Notificaciones**: Envío y recepción
- 👤 **Impersonación de Usuarios**: Admin feature
- 📊 **Exportar Datos a Excel**: Generación de reportes
- 🔄 **Recordatorios Automáticos**: Sistema de cron
- 💾 **Backup Automático**: Respaldos programados
- 📱 **Navegación con Inertia.js**: SPA navigation

---

## 🔍 Cómo Visualizar los Diagramas

### Opción 1: GitHub/GitLab (Recomendado)
1. Sube los archivos `.md` a tu repositorio
2. Los diagramas Mermaid se renderizan **automáticamente**
3. Navega por el archivo como documentación

### Opción 2: VS Code
1. Instala extensión: **"Markdown Preview Mermaid Support"**
   ```
   Ctrl+Shift+X → Buscar "Markdown Preview Mermaid"
   ```
2. Abre cualquier archivo `.md`
3. Presiona `Ctrl+Shift+V` para vista previa
4. Los diagramas se renderizan en tiempo real

### Opción 3: Mermaid Live Editor (Online)
1. Visita: https://mermaid.live/
2. Copia el código Mermaid de cualquier diagrama
3. Pégalo en el editor online
4. Exporta a PNG/SVG si necesitas

### Opción 4: Notion/Confluence
1. Copia el bloque de código Mermaid
2. En Notion: `/code` → selecciona "Mermaid"
3. En Confluence: Usa el macro "Mermaid"
4. Pega el código y se renderiza

### Opción 5: Exportar a Imagen
**Con Mermaid CLI:**
```bash
npm install -g @mermaid-js/mermaid-cli
mmdc -i DIAGRAMAS.md -o diagramas.pdf
```

**Con VS Code:**
1. Extensión "Markdown PDF"
2. Click derecho en `.md` → "Markdown PDF: Export (pdf)"

---

## 📖 Guía de Lectura por Rol

### 👨‍💼 Para Gerentes/Stakeholders
**Leer en este orden:**
1. `DIAGRAMAS.md` → Diagrama de Casos de Uso
2. `DIAGRAMAS.md` → Diagrama de Flujo Principal
3. `DIAGRAMAS.md` → Diagrama de Roles y Permisos
4. `DIAGRAMAS_SECUENCIA.md` → Casos de uso principales

**Enfoque:** Entender el negocio, flujos y roles

### 👨‍💻 Para Desarrolladores Nuevos
**Leer en este orden:**
1. `DIAGRAMAS.md` → Tecnologías Utilizadas
2. `DIAGRAMAS_TECNICOS.md` → Arquitectura del Sistema
3. `DIAGRAMAS_TECNICOS.md` → Modelo de BD Detallado
4. `DIAGRAMAS_TECNICOS.md` → Frontend Component Architecture
5. `DIAGRAMAS_SECUENCIA.md` → Todos los casos de uso

**Enfoque:** Entender la arquitectura y flujos Team Leaders

### 🎨 Para Diseñadores UI/UX
**Leer en este orden:**
1. `DIAGRAMAS.md` → Diagrama de Casos de Uso
2. `DIAGRAMAS_TECNICOS.md` → Frontend Component Architecture
3. `DIAGRAMAS_SECUENCIA.md` → Navegación con Inertia.js
4. `DIAGRAMAS.md` → Diagrama de Flujo Principal

**Enfoque:** Entender experiencia de usuario y componentes

### 🗄️ Para DBAs
**Leer en este orden:**
1. `DIAGRAMAS.md` → Diagrama de Base de Datos
2. `DIAGRAMAS_TECNICOS.md` → Modelo de BD Detallado
3. `DIAGRAMAS_TECNICOS.md` → Dashboard Data Flow

**Enfoque:** Estructura de datos y queries

### 🔐 Para Auditores/Seguridad
**Leer en este orden:**
1. `DIAGRAMAS_TECNICOS.md` → Autenticación y Autorización
2. `DIAGRAMAS_TECNICOS.md` → Middleware Stack
3. `DIAGRAMAS.md` → Diagrama de Roles y Permisos
4. `DIAGRAMAS_SECUENCIA.md` → Impersonación de Usuarios

**Enfoque:** Seguridad y permisos

---

## 🎯 Casos de Uso Documentados

### Módulo de Solicitudes
- ✅ Crear solicitud (Cliente)
- ✅ Aprobar/Rechazar solicitud (Coordinador)

### Módulo de Órdenes de Trabajo
- ✅ Crear OT desde solicitud
- ✅ Asignar Team Leader
- ✅ Registrar avances
- ✅ Subir evidencias
- ✅ Generar PDF OT

### Módulo de Calidad
- ✅ Validar OT completada
- ✅ Rechazar OT con motivo

### Módulo de Cliente
- ✅ Autorizar OT validada

### Módulo de Facturación
- ✅ Crear factura desde OT
- ✅ Generar PDF con datos XML
- ✅ Generar código QR SAT
- ✅ Enviar email con PDF adjunto
- ✅ Subir XML factura
- ✅ Marcar estados de factura

### Módulo de Administración
- ✅ Gestionar usuarios
- ✅ Impersonar usuarios
- ✅ Ver actividad del sistema
- ✅ Gestionar backups
- ✅ Gestionar centros de trabajo
- ✅ Gestionar servicios y precios

### Funcionalidades del Sistema
- ✅ Dashboard con métricas
- ✅ Sistema de notificaciones
- ✅ Exportar a Excel
- ✅ Recordatorios automáticos
- ✅ Backups automáticos
- ✅ Navegación SPA con Inertia.js

---

## 🔧 Personalizar Diagramas

### Cambiar Colores
Busca las líneas `style` al final de cada diagrama:
```mermaid
style NombreNodo fill:#color,stroke:#borde,color:#texto
```

### Colores Actuales del Sistema
- 🟢 Verde (`#4caf50`): Estados aprobados/exitosos
- 🔴 Rojo (`#f44336`): Estados rechazados/errores
- 🟡 Amarillo (`#ff9800`): Estados en proceso/advertencias
- 🔵 Azul (`#2196f3`): Información/neutral
- 🟣 Morado (`#7b1fa2`): Facturación
- 🟠 Naranja (`#f57c00`): Coordinación

### Agregar Nuevos Diagramas
1. Usa sintaxis Mermaid: https://mermaid.js.org/
2. Tipos disponibles:
   - `graph TB`: Flowchart top-to-bottom
   - `graph LR`: Flowchart left-to-right
   - `sequenceDiagram`: Diagramas de secuencia
   - `erDiagram`: Diagramas entidad-relación
   - `stateDiagram-v2`: Diagramas de estado
   - `mindmap`: Mapas mentales

---

## 📊 Estadísticas del Proyecto

### Módulos Principales
- **7 módulos funcionales**
- **6 roles de usuario**
- **30+ casos de uso**
- **15+ tablas de BD**
- **40+ rutas web**

### Tecnologías
- **Backend**: Laravel 12.26.4 + PHP 8.2
- **Frontend**: Vue.js 3 + Inertia.js 2.x
- **Base de Datos**: MySQL 8.0
- **Estilos**: Tailwind CSS 3.x
- **PDFs**: Laravel Dompdf
- **QR**: Simple QR Code 4.2
- **Permisos**: Spatie Laravel Permission

### Flujos Principales
1. **Solicitud → OT → Calidad → Cliente → Factura** (Flujo completo)
2. **Notificaciones en tiempo real**
3. **Generación automática de PDFs**
4. **Sistema de backups**
5. **Recordatorios automáticos**

---

## 🚀 Próximos Pasos

### Para Desarrolladores
1. ✅ Lee `DIAGRAMAS_TECNICOS.md` → Arquitectura
2. ✅ Revisa `DIAGRAMAS_SECUENCIA.md` → Flujos clave
3. ✅ Consulta la base de datos según diagramas ER
4. ✅ Implementa nuevas features siguiendo los patrones

### Para Product Owners
1. ✅ Usa `DIAGRAMAS.md` para entender el sistema
2. ✅ Define nuevos casos de uso basados en los diagramas
3. ✅ Prioriza features según los flujos existentes

### Para QA/Testers
1. ✅ Usa diagramas de secuencia como casos de prueba
2. ✅ Verifica cada estado del diagrama de estados
3. ✅ Prueba permisos según diagrama de roles

---

## 📝 Mantenimiento de Diagramas

### Cuándo Actualizar
- ✏️ **Nuevas features**: Agregar casos de uso y flujos
- 🔧 **Cambios en BD**: Actualizar diagrama ER
- 🎨 **Nuevos componentes**: Actualizar arquitectura frontend
- 🔐 **Cambios de permisos**: Actualizar roles y permisos

### Cómo Actualizar
1. Edita el archivo `.md` correspondiente
2. Actualiza el código Mermaid
3. Verifica en preview que se vea correctamente
4. Commit y push al repositorio
5. GitHub/GitLab renderiza automáticamente

---

## 🔗 Enlaces Útiles

### Documentación
- **Mermaid**: https://mermaid.js.org/
- **Laravel**: https://laravel.com/docs
- **Vue.js**: https://vuejs.org/
- **Inertia.js**: https://inertiajs.com/

### Herramientas
- **Mermaid Live**: https://mermaid.live/
- **VS Code Extension**: Markdown Preview Mermaid Support
- **Mermaid CLI**: https://github.com/mermaid-js/mermaid-cli

### Exportar
- **PNG/SVG**: Mermaid Live Editor
- **PDF**: VS Code con extensión Markdown PDF
- **Presentaciones**: Incluir en Markdown slides (Marp, Reveal.js)

---

## 💡 Tips y Trucos

### Tip 1: Zoom en Diagramas Grandes
En Mermaid Live Editor, usa la rueda del mouse para zoom

### Tip 2: Exportar para Documentación
Los SVG son mejores para docs técnicas (escalables)

### Tip 3: Compartir Diagramas
Mermaid Live permite compartir con URL única

### Tip 4: Diagramas en Presentaciones
Usa herramientas como Marp que soportan Mermaid

### Tip 5: Integración Continua
Agrega generación de PDFs en GitHub Actions:
```yaml
- name: Generate diagrams
  run: mmdc -i DIAGRAMAS.md -o docs/diagramas.pdf
```

---

## 📄 Licencia

Estos diagramas son parte de la documentación del proyecto **UPPER_CONTROL** y están sujetos a la misma licencia del proyecto.

---

## 👥 Contribuir

Para agregar o mejorar diagramas:
1. Fork el repositorio
2. Edita los archivos `.md`
3. Verifica que Mermaid sea válido
4. Crea un Pull Request
5. Documenta los cambios

---

## 📞 Soporte

Si tienes dudas sobre los diagramas:
1. Revisa esta guía
2. Consulta la documentación de Mermaid
3. Contacta al equipo de desarrollo

---

**Última actualización**: 14 de octubre de 2025  
**Versión**: 1.0  
**Mantenido por**: Equipo de Desarrollo UPPER_CONTROL
