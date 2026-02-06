# Guía Visual de Cambios - Refinamiento UI/UX

## 🎨 Comparativa Antes vs. Después

| Elemento | ❌ ANTES (Problema) | ✅ DESPUÉS (Solución) | Impacto |
|----------|---------------------|----------------------|---------|
| **Header Servicio** | Gradiente teal→cyan→blue + badge con backdrop-blur | Color sólido `teal-700` + badge gris discreto | Mayor sobriedad, no compite visualmente |
| **Subtotal Servicio** | `text-xl` blanco sobre fondo con backdrop-blur | `text-base` gris sobre badge `bg-gray-100` | Información visible pero no dominante |
| **KPIs** | Fondos saturados `bg-gradient-to-br` (azul, verde, naranja, morado) | Fondos blancos con `border-t-4` de color semántico | Números destacan por tipografía, no por color |
| **Headers Tabla Items** | `bg-gradient-to-r from-gray-700 to-gray-800` | `bg-gray-700` sólido | Más limpio y profesional |
| **Badges de Items** | `bg-blue-600 text-white` (saturado) | `bg-blue-100 text-blue-700` (sutil) | Mayor legibilidad, menos ruido |
| **Formulario Avance** | `bg-gradient-to-r` con `border-2` y `shadow-inner` | `bg-gray-50` con `border` simple | Diseño limpio y funcional |
| **Botón CTA** | `bg-gradient-to-r from-emerald-600 to-teal-600` con transform | `bg-emerald-600 hover:bg-emerald-700` sólido | Profesional sin distracciones |
| **Segmentos Header** | Gradiente morado fuerte ✅ (MANTIENE) | Gradiente morado fuerte ✅ (MANTIENE) | ⭐ Único elemento con gradiente destacado |
| **Segmentos Badges** | `bg-blue-600 text-white` (saturado) | `bg-blue-100 text-blue-700` (sutil) | Consistencia con el resto |
| **Total Footer** | `bg-gradient-to-r from-emerald-600 to-teal-600` | `bg-emerald-600` sólido | Destacado pero no excesivo |

---

## 📏 Especificaciones de Tailwind

### Colores Primarios

```css
/* Header del Servicio */
.servicio-header {
  @apply bg-teal-700 dark:bg-teal-800 border-b-2 border-teal-800;
}

/* Subtotal Badge */
.subtotal-badge {
  @apply bg-gray-100 dark:bg-slate-700 rounded px-3 py-1.5;
}

/* KPI Cards */
.kpi-planeado {
  @apply bg-white dark:bg-slate-800 border-t-4 border-blue-500 shadow-sm;
}
.kpi-completado {
  @apply bg-white dark:bg-slate-800 border-t-4 border-emerald-500 shadow-sm;
}
.kpi-faltante {
  @apply bg-white dark:bg-slate-800 border-t-4 border-amber-500 shadow-sm;
}
.kpi-total {
  @apply bg-white dark:bg-slate-800 border-t-4 border-slate-400 shadow-sm;
}

/* Badge Tarifa NORMAL */
.badge-normal {
  @apply bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300;
}

/* Badge Tarifa EXTRA */
.badge-extra {
  @apply bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300;
}

/* Badge Tarifa FIN_DE_SEMANA */
.badge-fin-semana {
  @apply bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300;
}

/* Botón CTA Principal */
.btn-guardar {
  @apply bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow-md hover:shadow-lg;
}

/* Segmentos Header (ÚNICO GRADIENTE FUERTE) */
.segmentos-header {
  @apply bg-gradient-to-r from-purple-600 via-fuchsia-600 to-pink-600;
}

/* Total Footer */
.total-footer {
  @apply bg-emerald-600 dark:bg-emerald-700 border-t-2 border-emerald-700;
}
```

---

## 🎯 Decisiones Clave de Diseño

### 1. **Principio de Un Solo Foco**
```
❌ ANTES: 8+ gradientes compitiendo
✅ DESPUÉS: 1 gradiente destacado (Segmentos)
```

### 2. **Tipografía Hace el Trabajo Pesado**
```
❌ ANTES: Color fuerte + tipografía normal
✅ DESPUÉS: Color sutil + tipografía fuerte (font-extrabold)
```

### 3. **Border-Top en Lugar de Fondo Completo**
```css
/* Técnica para KPIs */
.kpi {
  background: white;           /* Fondo neutro */
  border-top: 4px solid blue;  /* Acento de color */
}
```

### 4. **Colores Sutiles con Suficiente Contraste**
```
NORMAL:  bg-blue-100 + text-blue-700   (ratio 4.5:1) ✅
EXTRA:   bg-orange-100 + text-orange-700 (ratio 4.5:1) ✅
FIN SEM: bg-red-100 + text-red-700     (ratio 4.5:1) ✅
```

---

## 📐 Espaciado y Tamaño

| Elemento | Tamaño | Padding | Margin |
|----------|--------|---------|--------|
| Header Servicio | `h-auto` | `px-5 py-3` | - |
| Subtotal Badge | `text-base` | `px-3 py-1.5` | - |
| KPI Cards | `text-2xl` | `px-3 py-2` | `gap-3 mb-3` |
| KPI Labels | `text-[9px]` | - | `mb-0.5` |
| Tabla Headers | `text-[10px]` | `px-3 py-2` | - |
| Badges | `text-xs` | `px-2.5 py-0.5` | - |
| Botón CTA | `text-base` | `px-4 py-2` | - |
| Segmentos Header | `text-base` | `px-4 py-3` | - |
| Segmentos Badges | `text-[10px]` | `px-2.5 py-1` | - |
| Total Footer | `text-lg` | `px-3 py-2.5` | - |

---

## 🌓 Dark Mode

Todos los elementos tienen soporte para dark mode:

```vue
<!-- Ejemplo: Badge con dark mode -->
<span class="bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
  Normal
</span>
```

**Patrón:**
- Light: `bg-{color}-100 text-{color}-700`
- Dark: `dark:bg-{color}-900/30 dark:text-{color}-300`

---

## ✅ Checklist de Implementación

- [x] Header del servicio con color sólido
- [x] Subtotal en badge gris discreto
- [x] KPIs con border-top de color
- [x] Headers de tabla sobrios (gris oscuro sólido)
- [x] Badges sutiles en lugar de saturados
- [x] Formulario con diseño limpio
- [x] Botón CTA verde sólido sin efectos
- [x] Segmentos mantiene gradiente morado (único destacado)
- [x] Badges de segmentos sutiles
- [x] Total footer verde sólido
- [x] Dark mode funcionando
- [x] Responsive design intacto
- [x] Build compilado sin errores

---

## 🔍 Testing Recomendado

1. **Cargar orden con multi-servicio**
   - Verificar que header de servicio sea teal sólido
   - Confirmar que KPIs tengan border-top de color
   - Validar que badges sean sutiles

2. **Revisar Segmentos de Producción**
   - Header debe tener gradiente morado (único destacado)
   - Badges NORMAL/EXTRA/FIN_SEM deben ser sutiles
   - Total debe tener fondo verde sólido

3. **Probar Dark Mode**
   - Alternar tema y verificar contraste
   - Badges deben verse bien en ambos modos

4. **Responsive**
   - Verificar en móvil que grid se adapte
   - Formulario debe ser usable en pantallas pequeñas

---

## 💡 Tips de Mantenimiento

### Para Agregar Nuevos Badges
```vue
<!-- Usar colores sutiles -->
<span class="bg-{color}-100 text-{color}-700 dark:bg-{color}-900/30 dark:text-{color}-300">
  Texto
</span>
```

### Para Agregar Nuevos KPIs
```vue
<!-- Usar border-top en lugar de fondo -->
<div class="bg-white border-t-4 border-{color}-500 shadow-sm">
  <p class="text-[9px] uppercase text-gray-500">Label</p>
  <p class="text-2xl font-extrabold text-gray-800">Valor</p>
</div>
```

### Para Agregar Nuevas Secciones
- **Regla de oro:** Solo UN gradiente fuerte por vista
- Preferir colores sólidos y sobrios
- Usar tipografía para dar peso visual
- Border-top o border-left para acentos de color

---

## 📚 Referencias de Color

### Paleta Semántica
- **Primario:** `teal-700` (headers)
- **Éxito:** `emerald-600` (completado, botones)
- **Advertencia:** `amber-500` (faltante)
- **Info:** `blue-500` (planeado)
- **Destacado:** `purple-600→fuchsia-600→pink-600` (gradiente de segmentos)
- **Neutral:** `gray-50/100/500/700/800` (fondos y textos)

### Fondos
- **Cards principales:** `bg-white dark:bg-slate-800/90`
- **Cards secundarias:** `bg-gray-50 dark:bg-slate-800/50`
- **Headers de tabla:** `bg-gray-700 dark:bg-slate-900`
- **Headers destacados:** `bg-gradient-to-r from-purple-600 via-fuchsia-600 to-pink-600`

---

**Autor:** GitHub Copilot  
**Fecha:** 4 de febrero de 2026  
**Framework:** Laravel 10 + Inertia.js + Vue 3 + Tailwind CSS
