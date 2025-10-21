# Solución: Datos del XML no aparecían en el PDF de Factura

## 🐛 Problema
Los PDFs de factura se generaban pero **NO incluían los datos del XML** (CFDI), mostrando solo guiones (—) en lugar de:
- Datos del Emisor (RFC, Nombre, Régimen)
- Datos del Receptor (RFC, Nombre, Uso CFDI)
- Serie y Folio del CFDI
- UUID del Timbre Fiscal
- Conceptos del CFDI
- Forma y método de pago
- Código QR de verificación SAT

## ✅ Solución Implementada

### 1. **Actualización del Job `GenerateFacturaPdf.php`**

**Cambios realizados:**
- Se agregó el método `parseCfdi()` para extraer datos del XML
- Se agregó el método `generateQrCode()` para generar el código QR SAT
- Ahora el Job pasa la variable `$xml` a la vista Blade

**Antes:**
```php
$pdf = PDF::loadView('pdf.factura', ['factura'=>$factura])->setPaper('letter');
```

**Después:**
```php
// Parsear XML si existe
$xml = $this->parseCfdi($factura);

$pdf = PDF::loadView('pdf.factura', [
    'factura' => $factura,
    'xml' => $xml  // ← Ahora se pasa el XML parseado
])->setPaper('letter');
```

### 2. **Método `parseCfdi()` implementado**

Extrae del XML todos los datos necesarios:
- ✅ Versión, Serie, Folio, Fecha
- ✅ Subtotal, Descuento, Total
- ✅ Forma de pago, Método de pago, Moneda
- ✅ Tipo de comprobante, Lugar de expedición
- ✅ **Emisor** (RFC, Nombre, Régimen Fiscal)
- ✅ **Receptor** (RFC, Nombre, Uso CFDI, Domicilio Fiscal, Régimen)
- ✅ **Conceptos** (Clave, Cantidad, Descripción, Valor Unitario, Importe)
- ✅ **Impuestos** (Traslados y Retenciones)
- ✅ **Timbre Fiscal Digital** (UUID, Fecha de Timbrado, Certificados SAT, Sellos)

### 3. **Soporte para CFDI 4.0 con Namespaces**

El parser maneja correctamente los namespaces del XML:
```php
$ns = $xml->getDocNamespaces(true);
$cfdi = isset($ns['cfdi']) ? $xml->children($ns['cfdi']) : $xml;
```

### 4. **Generación de Código QR SAT**

Si el XML tiene UUID, se genera automáticamente el código QR:
```php
https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?
  &id=[UUID]
  &re=[RFC_EMISOR]
  &rr=[RFC_RECEPTOR]
  &tt=[TOTAL_FORMATEADO]
  &fe=[ULTIMOS_8_DIGITOS_SELLO]
```

### 5. **Comandos Artisan Agregados**

#### `php artisan factura:regenerar-pdf {id}`
Regenera el PDF de una factura con los datos actualizados del XML.

**Ejemplo:**
```bash
php artisan factura:regenerar-pdf 1
```

**Salida:**
```
📄 Factura #1
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ XML disponible: facturas/xml/...

📋 Datos del XML que se incluirán en el PDF:
   • Serie: —
   • Folio: 1801
   • Total: $1,900.00
   • Emisor: IGNACIO DIAZ IZQUIERDO
   • RFC Emisor: DIII880908PA6
   • Receptor: SILCA OIL & GAS SERVICES
   • RFC Receptor: SOA220613G89
   • UUID: D52437F1-9F66-43CF-9E61-0E508B228ABD
   • Conceptos: 1 concepto(s)

✅ PDF generado exitosamente!
```

#### `php artisan factura:verificar-pdf {id}`
Verifica que una factura tenga XML y PDF correctos.

## 📋 Archivos Modificados

1. **`app/Jobs/GenerateFacturaPdf.php`**
   - Agregado método `parseCfdi()` (140 líneas)
   - Agregado método `generateQrCode()` (45 líneas)
   - Actualizado `handle()` para pasar `$xml` a la vista
   - Import agregado: `use Illuminate\Support\Facades\Log;`

2. **`app/Console/Commands/RegenerarFacturaPdf.php`** (NUEVO)
   - Comando para regenerar PDFs de facturas

3. **`app/Console/Commands/VerificarFacturaPdf.php`** (NUEVO)
   - Comando para verificar XML y PDF de facturas

## 🧪 Cómo Probar

### Regenerar todas las facturas existentes:
```bash
# Para una factura específica
php artisan factura:regenerar-pdf 1

# O regenerar todas con XML
php artisan tinker
Factura::whereNotNull('xml_path')->each(function($f) {
    GenerateFacturaPdf::dispatch($f->id);
});
```

### Verificar que el PDF tenga los datos:
```bash
php artisan factura:verificar-pdf 1
```

## 📊 Antes vs Después

### ANTES (Sin datos del XML):
```
Emisor: —
RFC: —
Receptor: —
Serie: — Folio: —
UUID: —
```

### DESPUÉS (Con datos del XML):
```
Emisor: IGNACIO DIAZ IZQUIERDO
RFC: DIII880908PA6
Receptor: SILCA OIL & GAS SERVICES
RFC Receptor: SOA220613G89
Serie: — Folio: 1801
UUID: D52437F1-9F66-43CF-9E61-0E508B228ABD
[Código QR de verificación SAT]
```

## ✅ Resultado Final

✅ **Todos los datos del XML ahora aparecen correctamente en el PDF**
✅ **Se genera código QR automáticamente (SVG)**
✅ **Compatible con CFDI 3.3 y 4.0**
✅ **Manejo de errores con logs**
✅ **Comandos para regenerar y verificar PDFs**

### 📦 Librería QR Instalada

La librería `simplesoftwareio/simple-qrcode` está ahora instalada y configurada:
- ✅ Genera códigos QR en formato SVG (no requiere imagick)
- ✅ Fallback automático si PNG no está disponible
- ✅ Compatible con la extensión GD de PHP (ya disponible en tu sistema)

**Comando ejecutado:**
```bash
composer require simplesoftwareio/simple-qrcode
```

### 🔧 Configuración del QR

El código QR generado contiene la URL de verificación SAT:
```
https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?
  id=[UUID]
  &re=[RFC_EMISOR]
  &rr=[RFC_RECEPTOR]
  &tt=[TOTAL_FORMATEADO]
  &fe=[ULTIMOS_8_SELLO]
```

**Formato utilizado:**
- SVG (siempre disponible)
- PNG (si imagick está instalado, sino usa SVG)
- Tamaño: 256x256 px
- Corrección de errores: H (alta)
- Margen: 1

## 🚀 Producción

Los nuevos PDFs se generarán automáticamente con todos los datos cuando:
1. Se cargue un XML en una factura
2. Se regenere manualmente con el comando
3. Se dispare el Job `GenerateFacturaPdf`

**No se requieren cambios en la base de datos ni migraciones.**
