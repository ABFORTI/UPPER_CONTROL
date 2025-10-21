# 📚 Documentación - UPPER_CONTROL

Sistema de gestión de órdenes de trabajo y facturación para control de calidad.

---

## 🚀 Inicio Rápido

### 🆕 Nuevo en el Proyecto
Empieza aquí: **[INDICE_DIAGRAMAS.md](./INDICE_DIAGRAMAS.md)** 

Este índice visual te guiará a todos los diagramas según tu rol y necesidad.

---

## 📊 Diagramas del Sistema

### 🗺️ [INDICE_DIAGRAMAS.md](./INDICE_DIAGRAMAS.md) ⭐ EMPIEZA AQUÍ
**Navegación rápida a todos los diagramas**
- Índice por tema (Usuarios, Solicitudes, OTs, Calidad, Facturación, etc.)
- Búsqueda por tipo de diagrama
- Búsqueda por rol de usuario
- Matriz de cobertura
- Enlaces directos

### 📚 [DIAGRAMAS_README.md](./DIAGRAMAS_README.md)
**Guía completa de uso de los diagramas**
- Cómo visualizar los diagramas (GitHub, VS Code, Online)
- Guía de lectura por rol
- Personalizar y mantener diagramas
- Tips y trucos

### 📋 [DIAGRAMAS.md](./DIAGRAMAS.md)
**Diagramas Generales del Sistema**
- ✅ Diagrama de Casos de Uso
- ✅ Diagrama de Flujo Principal
- ✅ Diagrama de Flujo de Facturación
- ✅ Diagrama de Estados de OT
- ✅ Diagrama de Roles y Permisos
- ✅ Diagrama de Notificaciones
- ✅ Diagrama de Base de Datos
- ✅ Tecnologías Utilizadas

### 🔧 [DIAGRAMAS_TECNICOS.md](./DIAGRAMAS_TECNICOS.md)
**Diagramas Team Leaders y Arquitectura**
- ✅ Arquitectura del Sistema
- ✅ Flujo de Procesamiento de Factura (Job)
- ✅ Flujo de Datos Completo
- ✅ Sistema de Autenticación y Autorización
- ✅ Sistema de Notificaciones
- ✅ Modelo de Base de Datos Detallado
- ✅ Job Queue System
- ✅ Proceso de Generación de PDF
- ✅ Parser CFDI (XML)
- ✅ Middleware Stack
- ✅ Dashboard Data Flow
- ✅ Frontend Component Architecture
- ✅ Loading System

### 🔄 [DIAGRAMAS_SECUENCIA.md](./DIAGRAMAS_SECUENCIA.md)
**Diagramas de Secuencia - Casos de Uso**
- ✅ Caso 1: Crear y Aprobar Solicitud
- ✅ Caso 2: Crear OT desde Solicitud
- ✅ Caso 3: Registrar Avances y Evidencias
- ✅ Caso 4: Validación de Calidad
- ✅ Caso 5: Autorización del Cliente
- ✅ Caso 6: Proceso Completo de Facturación ⭐
- ✅ Caso 7: Sistema de Notificaciones
- ✅ Caso 8: Impersonación de Usuarios
- ✅ Caso 9: Exportar Datos a Excel
- ✅ Caso 10: Recordatorios Automáticos
- ✅ Caso 11: Backup Automático
- ✅ Caso 12: Navegación con Inertia.js

---

## 📖 Guías Técnicas

### 💰 Sistema de Facturación
- **[SOLUCION_PDF_FACTURA.md](./SOLUCION_PDF_FACTURA.md)** - Generación de PDFs con datos XML
- **[ACTIVACION_QR_FACTURAS.md](./ACTIVACION_QR_FACTURAS.md)** - Códigos QR SAT
- **[INICIO_RAPIDO_CORREOS.md](./INICIO_RAPIDO_CORREOS.md)** - Email con PDF adjunto

### 🔔 Sistema de Recordatorios
- **[RESUMEN_RECORDATORIOS.md](./RESUMEN_RECORDATORIOS.md)** - Visión general del sistema
- **[RECORDATORIOS_VALIDACION.md](./RECORDATORIOS_VALIDACION.md)** - Recordatorios de validación
- **[INICIO_RAPIDO_RECORDATORIOS.md](./INICIO_RAPIDO_RECORDATORIOS.md)** - Guía rápida

---

## 🎯 Acceso Rápido por Rol

### 👨‍💼 Product Owner / Manager
```
1. INDICE_DIAGRAMAS.md (visión general)
2. DIAGRAMAS.md → Casos de Uso
3. DIAGRAMAS.md → Flujo Principal
4. DIAGRAMAS.md → Roles y Permisos
```

### 👨‍💻 Desarrollador Backend
```
1. DIAGRAMAS_TECNICOS.md → Arquitectura
2. DIAGRAMAS_TECNICOS.md → BD Detallado
3. DIAGRAMAS_SECUENCIA.md → Todos los casos
4. Guías técnicas específicas
```

### 🎨 Desarrollador Frontend
```
1. DIAGRAMAS_TECNICOS.md → Frontend Components
2. DIAGRAMAS_SECUENCIA.md → Navegación Inertia
3. DIAGRAMAS_TECNICOS.md → Loading System
```

### 🗄️ DBA / Data Engineer
```
1. DIAGRAMAS.md → Diagrama de BD
2. DIAGRAMAS_TECNICOS.md → Modelo Detallado
3. DIAGRAMAS_TECNICOS.md → Dashboard Data Flow
```

### 🧪 QA / Tester
```
1. DIAGRAMAS.md → Estados de OT
2. DIAGRAMAS.md → Flujo Principal
3. DIAGRAMAS_SECUENCIA.md → Como test cases
```

### 🔐 Auditor / Seguridad
```
1. DIAGRAMAS_TECNICOS.md → Autenticación
2. DIAGRAMAS.md → Roles y Permisos
3. DIAGRAMAS_SECUENCIA.md → Impersonación
```

---

## 📊 Estadísticas del Proyecto

### Documentación
- **35+ diagramas** en formato Mermaid
- **~3,000+ líneas** de documentación
- **12 casos de uso** detallados
- **Cobertura**: >95% del sistema

### Sistema
- **7 módulos funcionales**
- **6 roles de usuario**
- **15+ tablas** de base de datos
- **40+ rutas** web

### Stack Tecnológico
- **Backend**: Laravel 12.26.4 + PHP 8.2
- **Frontend**: Vue.js 3 + Inertia.js 2.x
- **Base de Datos**: MySQL 8.0
- **Estilos**: Tailwind CSS 3.x
- **PDFs**: Laravel Dompdf
- **QR**: Simple QR Code 4.2

---

## 🔍 Visualización de Diagramas

### ✅ En GitHub (Automático)
Los diagramas Mermaid se renderizan automáticamente al abrir los archivos `.md` en GitHub.

**URL del repositorio**: https://github.com/ABFORTI/UPPER_CONTROL

### 💻 En VS Code
1. Instala: **"Markdown Preview Mermaid Support"**
2. Abre cualquier archivo `.md`
3. Presiona `Ctrl+Shift+V`

### 🌐 Online
1. Visita: https://mermaid.live/
2. Copia el código Mermaid
3. Visualiza y exporta

---

## 📁 Estructura de Documentación

```
📁 UPPER_CONTROL/
│
├── 📄 DOCUMENTACION.md (este archivo)
│
├── 📊 DIAGRAMAS/
│   ├── 🗺️ INDICE_DIAGRAMAS.md (EMPIEZA AQUÍ)
│   ├── 📚 DIAGRAMAS_README.md (Guía de uso)
│   ├── 📋 DIAGRAMAS.md (Generales)
│   ├── 🔧 DIAGRAMAS_TECNICOS.md (Team Leaders)
│   └── 🔄 DIAGRAMAS_SECUENCIA.md (Casos de uso)
│
├── 💰 FACTURACIÓN/
│   ├── SOLUCION_PDF_FACTURA.md
│   ├── ACTIVACION_QR_FACTURAS.md
│   └── INICIO_RAPIDO_CORREOS.md
│
└── 🔔 RECORDATORIOS/
    ├── RESUMEN_RECORDATORIOS.md
    ├── RECORDATORIOS_VALIDACION.md
    └── INICIO_RAPIDO_RECORDATORIOS.md
```

---

## 🌟 Características Principales

### Gestión de Órdenes de Trabajo
- ✅ Creación desde solicitudes
- ✅ Asignación de Team Leaders
- ✅ Registro de avances (0-100%)
- ✅ Evidencias fotográficas
- ✅ Generación de PDF

### Sistema de Calidad
- ✅ Validación de OTs completadas
- ✅ Aprobación/Rechazo con motivos
- ✅ Notificaciones automáticas

### Facturación Avanzada
- ✅ Generación de PDF con datos XML CFDI
- ✅ Código QR SAT de verificación
- ✅ Email automático con PDF adjunto
- ✅ Control de estados (pendiente → facturado → cobrado → pagado)
- ✅ Exportación a Excel

### Sistema de Notificaciones
- ✅ Email + Base de datos
- ✅ Notificaciones en tiempo real
- ✅ Campana de notificaciones
- ✅ Recordatorios automáticos

### Administración
- ✅ Gestión de usuarios y roles
- ✅ Impersonación de usuarios
- ✅ Activity log completo
- ✅ Backups automáticos
- ✅ Panel de métricas

---

## 🚀 Comandos Artisan Útiles

### Facturación
```bash
# Regenerar PDF de factura
php artisan factura:regenerar-pdf {id}

# Verificar archivos de factura
php artisan factura:verificar-pdf {id}

# Probar email con PDF
php artisan factura:probar-correo {id}
```

### Recordatorios
```bash
# Enviar recordatorios de validación
php artisan recordatorios:validacion-ot

# Limpiar recordatorios antiguos
php artisan recordatorios:limpiar

# Simular OT pendiente (testing)
php artisan recordatorios:simular-ot-pendiente
```

### Sistema
```bash
# Ver tareas programadas
php artisan schedule:list

# Ejecutar tareas manualmente
php artisan schedule:run

# Probar configuración
php artisan test:schedule
```

---

## 🔧 Configuración

### Variables de Entorno Clave
```env
# Aplicación
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/UPPER_CONTROL/public

# Base de Datos
DB_CONNECTION=mysql
DB_DATABASE=upper_control

# Email
MAIL_MAILER=log  # log para desarrollo, smtp para producción

# Colas
QUEUE_CONNECTION=sync  # database para producción
```

### Horarios Programados
```php
// En app/Console/Kernel.php
$schedule->command('recordatorios:validacion-ot')->hourly();
$schedule->command('recordatorios:limpiar')->daily();
$schedule->command('backup:run')->daily();
```

---

## 📝 Contribuir

Para agregar o mejorar documentación:

1. **Fork** el repositorio
2. Crea una rama: `git checkout -b docs/mejora-diagramas`
3. Edita los archivos `.md`
4. Verifica que Mermaid sea válido
5. Commit: `git commit -m "docs: descripción"`
6. Push: `git push origin docs/mejora-diagramas`
7. Crea un **Pull Request**

---

## 📞 Soporte

- **Documentación**: Lee las guías en este repositorio
- **Diagramas**: Consulta INDICE_DIAGRAMAS.md
- **Issues**: GitHub Issues
- **Equipo**: Contacta al equipo de desarrollo

---

## 📜 Licencia

Este proyecto está bajo la licencia definida en el repositorio.

---

## ✨ Última Actualización

**Fecha**: 14 de octubre de 2025  
**Versión**: 1.0  
**Estado**: ✅ Documentación completa  
**Mantenido por**: Equipo de Desarrollo UPPER_CONTROL

---

**¡Bienvenido a UPPER_CONTROL! 🚀**
