# 🔍 DEBUG: Investigación de Nombres de Ítems

**Fecha:** 15 de octubre de 2025  
**Estado:** 🔧 En Investigación

---

## 🐛 Problema Reportado

**Síntoma:** Cuando se activa la separación y se colocan nombres específicos a los ítems, esos nombres NO aparecen en la vista de la OT.

**Ejemplo:**
- Usuario activa separación
- Llena ítems:
  - "Lenovo ThinkPad" - 6 pz
  - "Asus VivoBook" - 4 pz
- Crea OT
- En vista de OT: columna DESCRIPCIÓN aparece vacía

---

## 🔧 Cambios de Debug Implementados

### 1. **Logging en Backend**

**Archivo:** `app/Http/Controllers/OrdenController.php`

**Logs agregados:**
```php
// DEBUG: Log para ver qué items están llegando
logger()->info('Creando ítems de OT', [
    'separarItems' => $separarItems,
    'items' => $data['items']
]);

// Dentro del loop:
logger()->info('Guardando ítem', [
    'descripcion' => $descripcion,
    'tamano' => $tamano,
    'cantidad' => (int)$it['cantidad']
]);
```

**Propósito:**
- Ver si los datos llegan correctamente desde el frontend
- Verificar si la descripción se está guardando

### 2. **Corrección en Botón Eliminar**

**Archivo:** `resources/js/Pages/Ordenes/CreateFromSolicitud.vue`

**Cambio:**
```vue
<!-- ANTES -->
<button v-if="!usaTamanos && form.items.length > 1">

<!-- DESPUÉS -->
<button v-if="!usaTamanos && separarItems && form.items.length > 1">
```

**Propósito:**
- Solo mostrar botón eliminar cuando hay separación activa
- Evitar comportamientos inesperados

---

## 📋 Pasos para Debugging

### Paso 1: Limpiar Logs Anteriores
```powershell
# Vaciar archivo de logs
"" > storage/logs/laravel.log
```

### Paso 2: Crear OT con Separación
1. Ir a crear OT desde una solicitud de servicio SIN tamaños
2. Click en "Activar Separación"
3. Llenar ítems con nombres específicos:
   - Ítem 1: "Dell Latitude 5420" - 3 pz
   - Ítem 2: "HP ProBook 450" - 4 pz
   - Ítem 3: "Lenovo ThinkPad T14" - 3 pz
4. Verificar que contador muestra: Suma = 10 ✅
5. Click en "Crear Orden de Trabajo"

### Paso 3: Revisar Logs
```powershell
# Ver últimas líneas del log
Get-Content storage/logs/laravel.log -Tail 50
```

**Buscar en el log:**
```
[timestamp] local.INFO: Creando ítems de OT
{
    "separarItems": true,
    "items": [
        {
            "descripcion": "Dell Latitude 5420",
            "cantidad": 3,
            "tamano": null
        },
        ...
    ]
}

[timestamp] local.INFO: Guardando ítem
{
    "descripcion": "Dell Latitude 5420",
    "tamano": null,
    "cantidad": 3
}
```

### Paso 4: Verificar en Base de Datos
```powershell
# Conectar a MySQL
cd C:\xampp\mysql\bin
.\mysql.exe -u root -p

# En MySQL
USE upper_control;

# Ver últimas OT creadas
SELECT id, folio, descripcion_general FROM ordenes_trabajo 
ORDER BY id DESC LIMIT 5;

# Ver ítems de la última OT (reemplazar ID)
SELECT id, descripcion, tamano, cantidad_planeada 
FROM orden_items 
WHERE id_orden = [ID_DE_LA_OT]
ORDER BY id;
```

### Paso 5: Verificar en Vista de OT
1. Ir a la vista de la OT recién creada
2. Buscar la sección "Ítems de la Orden"
3. Ver columna "DESCRIPCIÓN"

---

## 🔍 Posibles Causas

### Causa 1: Datos No Llegan al Backend ❌
**Indicador:** Logs muestran `items` con descripciones vacías o null

**Solución:** Verificar frontend - probablemente `v-model` no funciona

### Causa 2: Datos No se Guardan en BD ❌
**Indicador:** Logs muestran descripciones correctas, pero BD tiene valores null

**Solución:** Verificar modelo `OrdenItem` - campo descripción en fillable

### Causa 3: Datos No se Cargan en Vista ❌
**Indicador:** BD tiene descripciones, pero no aparecen en Show.vue

**Solución:** Verificar que el eager loading incluye el campo descripción

### Causa 4: Problema de Visualización ❌
**Indicador:** Datos están en Vue props, pero no se muestran

**Solución:** Verificar lógica de renderizado en Show.vue

---

## 📊 Diagnóstico Esperado

### Escenario A: Logs Muestran Descripciones ✅
```
"descripcion": "Dell Latitude 5420"  ✅
"descripcion": "HP ProBook 450"      ✅
"descripcion": "Lenovo ThinkPad T14" ✅
```

**Conclusión:** Frontend envía datos correctamente
**Siguiente paso:** Verificar BD

### Escenario B: Logs Muestran Null/Vacío ❌
```
"descripcion": null  ❌
"descripcion": ""    ❌
```

**Conclusión:** Problema en frontend
**Siguiente paso:** Revisar v-model en CreateFromSolicitud.vue

### Escenario C: BD Tiene Datos pero Vista No ❌
```sql
-- BD muestra:
descripcion: "Dell Latitude 5420" ✅

-- Pero vista muestra:
DESCRIPCIÓN: [vacío] ❌
```

**Conclusión:** Problema en carga o visualización
**Siguiente paso:** Revisar Show.vue y OrdenController::show

---

## 🛠️ Soluciones Potenciales

### Si Problema es en Frontend

**Verificar:**
1. `v-model="it.descripcion"` está presente
2. Campo no está `disabled`
3. `separarItems` está `true` cuando se edita

**Posible Fix:**
```vue
<textarea v-model="it.descripcion" 
          :disabled="!separarItems"  <!-- Agregar esta línea -->
          rows="2">
</textarea>
```

### Si Problema es en Backend

**Verificar modelo:**
```php
// app/Models/OrdenItem.php
protected $fillable = [
    'id_orden',
    'descripcion',  // ¿Está aquí?
    'tamano',
    'cantidad_planeada',
    // ...
];
```

### Si Problema es en Vista

**Verificar Show.vue líneas 235-239:**
```vue
<div class="font-semibold text-gray-800">
  <span v-if="it?.tamano">{{ it.tamano }}</span>
  <span v-else>{{ it?.descripcion }}</span>  <!-- ¿Está mostrando? -->
</div>
```

**Agregar debug temporal:**
```vue
<div class="font-semibold text-gray-800">
  <!-- DEBUG -->
  <pre>{{ JSON.stringify(it, null, 2) }}</pre>
  
  <span v-if="it?.tamano">{{ it.tamano }}</span>
  <span v-else>{{ it?.descripcion }}</span>
</div>
```

---

## ✅ Lista de Verificación

- [ ] Logs agregados a OrdenController
- [ ] Botón eliminar corregido
- [ ] Crear OT de prueba con separación
- [ ] Revisar logs en `storage/logs/laravel.log`
- [ ] Verificar datos en base de datos
- [ ] Verificar visualización en Show.vue
- [ ] Identificar causa raíz
- [ ] Aplicar solución correspondiente
- [ ] Remover logs de debug
- [ ] Documentar solución final

---

## 📝 Instrucciones para el Usuario

**Por favor realiza lo siguiente:**

1. **Crear OT de Prueba:**
   - Servicio SIN tamaños (ej: Computadoras)
   - Activar separación
   - Llenar 2-3 ítems con nombres claros
   - Crear OT

2. **Compartir Logs:**
   ```powershell
   Get-Content storage/logs/laravel.log -Tail 100 > debug_logs.txt
   ```
   - Enviar el archivo `debug_logs.txt`

3. **Verificar Base de Datos:**
   - Anotar el ID de la OT creada
   - Verificar qué hay en la tabla `orden_items`
   - Compartir captura de pantalla

4. **Captura de Pantalla:**
   - Vista de la OT mostrando tabla de ítems
   - Especialmente columna DESCRIPCIÓN

Con esta información podré identificar exactamente dónde está el problema.

---

**Creado por:** GitHub Copilot  
**Fecha:** 15 de octubre de 2025  
**Estado:** Pendiente de pruebas
