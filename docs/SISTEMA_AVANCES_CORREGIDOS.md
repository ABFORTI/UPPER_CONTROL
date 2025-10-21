# Sistema de Avances Corregidos - Documentación

## 📋 Descripción General

El sistema marca automáticamente como "CORREGIDO" todos los avances que se registran en una Orden de Trabajo (OT) **después** de que Calidad la haya rechazado al menos una vez.

## 🔄 Flujo del Sistema

### 1. Estado Normal (Sin Rechazos)
```
OT Nueva → Asignada → En Proceso → Avances Normales → Completada → Calidad Valida ✅
```
- Los avances se muestran con fondo cyan/azul
- No aparece badge "CORREGIDO"

### 2. Cuando hay Rechazo de Calidad
```
OT Completada → Calidad Rechaza ❌ → 
    └─> Se crea registro en tabla `aprobacions`:
        - tipo: 'calidad'
        - resultado: 'rechazado'
        - motivo y acciones guardadas
    └─> OT vuelve a estado 'en_proceso'
    └─> Se reinicia calidad_resultado a 'pendiente'
```

### 3. Después del Rechazo (Correcciones)
```
Técnico registra nuevos avances →
    └─> Sistema detecta historial de rechazos
    └─> Marca avances como es_corregido = 1 ✅
    └─> Frontend muestra badge "CORREGIDO" amarillo
```

## 🛠️ Implementación Técnica

### Backend: Detección Automática

**Archivos modificados:**
1. `app/Http/Controllers/AvanceController.php` (líneas 38-42)
2. `app/Http/Controllers/OrdenController.php` (líneas 338-344)

**Lógica implementada:**
```php
// Antes de crear cada avance, verifica si existe algún rechazo previo
$isCorregido = \App\Models\Aprobacion::where('aprobable_type', \App\Models\Orden::class)
    ->where('aprobable_id', $orden->id)
    ->where('tipo', 'calidad')
    ->where('resultado', 'rechazado')
    ->exists();

// El avance se crea con el flag correspondiente
Avance::create([
    // ... otros campos ...
    'es_corregido' => $isCorregido,
]);
```

**¿Por qué funciona?**
- La tabla `aprobacions` guarda un registro PERMANENTE cada vez que Calidad valida o rechaza una OT
- Aunque la OT cambie de estado después, el historial de rechazos permanece
- Cada vez que se crea un avance, el sistema consulta ese historial
- Si encuentra AL MENOS UN rechazo previo, marca el nuevo avance como corregido

### Frontend: Visualización

**Archivo:** `resources/js/Pages/Ordenes/Show.vue`

**Características visuales:**
- ✅ Badge amarillo prominente "CORREGIDO" con ícono de advertencia
- ✅ Fondo amarillo claro y borde amarillo grueso (diferente del cyan normal)
- ✅ Muestra número de item + nombre (descripción o tamaño)
- ✅ Toda la información del avance (cantidad, comentario, usuario, fecha)

**Estructura de datos:**
```javascript
avance = {
  id: 23,
  cantidad: 5,
  id_item: 75,
  comentario: "Texto opcional",
  es_corregido: 1,        // Valor de BD
  isCorregido: true,       // Accessor serializado
  item: {                  // Relación cargada
    id: 75,
    descripcion: "Lenoco",
    tamano: null,
    // ...
  },
  usuario: {
    name: "Admin"
  },
  created_at: "2025-10-17 19:28:15"
}
```

### Base de Datos

**Tabla `avances`:**
- Campo: `es_corregido` BOOLEAN DEFAULT 0
- Migración: `database/migrations/2025_10_17_120000_add_es_corregido_to_avances.php`

**Tabla `aprobacions`:**
- Almacena el historial de validaciones/rechazos
- Campos clave: `tipo`, `resultado`, `aprobable_type`, `aprobable_id`
- NO se borra, es histórico permanente

## ✅ Ventajas del Sistema

1. **Automático**: No requiere intervención manual
2. **Histórico**: Usa el registro permanente de aprobaciones
3. **Consistente**: Funciona para TODAS las OTs (nuevas y existentes)
4. **Visual**: Badge amarillo muy claro y distintivo
5. **Informativo**: Muestra nombre completo del item + toda la info del avance

## 🧪 Pruebas

Para probar que funciona correctamente:

1. **Crear una OT nueva**
2. **Completarla** (registrar avances hasta 100%)
3. **Rechazarla desde Calidad** (con motivo y acciones correctivas)
4. **Registrar nuevos avances** (correcciones)
5. **Verificar** que aparecen con badge "CORREGIDO" amarillo

## 📝 Script de Verificación

Ejecutar para probar la lógica:
```bash
php scripts/test_avance_logic.php
```

Este script verifica que la consulta de aprobaciones funciona correctamente.

## 🔍 Solución de Problemas

### El badge no aparece en avances antiguos
**Solución:** Los avances creados ANTES del rechazo no se marcan como corregidos (es el comportamiento esperado). Solo los avances DESPUÉS del rechazo se marcan.

### El badge no aparece en avances nuevos
**Causas posibles:**
1. La caché del navegador (hacer Ctrl+F5)
2. Los assets no se reconstruyeron (`npm run build`)
3. No existe un rechazo de Calidad en el historial de la OT

**Debug:**
```bash
# Verificar si existe rechazo para una OT
php scripts/test_avance_logic.php

# Ver avances de una OT específica
php scripts/check_avance.php [ID_AVANCE]
```

## 📊 Estructura del Badge en UI

```
┌─────────────────────────────────────────┐
│ ⚠️ CORREGIDO                   [Badge]  │
│                                         │
│ +5  Ítem #75  Lenoco          [Info]   │
│ "comentario del técnico"      [Texto]  │
│ 👤 Admin                      [Usuario]│
│ 📅 2025-10-17 19:28:15        [Fecha]  │
└─────────────────────────────────────────┘
  🎨 Fondo amarillo claro (#FEF3C7)
  🖼️ Borde amarillo grueso (#FCD34D)
```

## 🎯 Casos de Uso

### Caso 1: OT Rechazada y Corregida
- ✅ Técnico sube avances → Calidad rechaza → Técnico corrige → Avances nuevos marcados "CORREGIDO"

### Caso 2: OT con Múltiples Rechazos
- ✅ Si se rechaza 2, 3 o más veces, TODOS los avances después del primer rechazo se marcan como corregidos

### Caso 3: OT Nunca Rechazada
- ✅ Avances normales sin badge, fondo cyan

### Caso 4: OT Rechazada → Validada → Rechazada de Nuevo
- ✅ Todos los avances desde el primer rechazo en adelante se marcan como corregidos

---

**Última actualización:** 17 de octubre de 2025  
**Versión del sistema:** Laravel 12.x + Vue 3 + Inertia  
**Autor:** GitHub Copilot
