# Batch Invoices Feature - Review Guide

## Overview
This document summarizes the batch invoice feature implementation that restricts invoice creation to single centro and shows real factura status with color-coded badges.

## Key Requirements ✅

1. **Centro Constraint**: Batch invoices can only contain OTs from the same Centro de Trabajo
2. **Pivot Table**: Uses `factura_orden` table to associate multiple OTs with a single factura
3. **Real Status Display**: Shows actual factura status (pagado, por_pagar, facturado, sin_factura) instead of generic "facturado"
4. **Centro Name Display**: Shows real centro name in Facturas/Index instead of "Varios"
5. **Badge Colors**: Differentiated colors for each status type
6. **Migrations**: Three new migrations for pivot table, facturada status, and unique constraints

## Implementation Details

### Database Schema

#### 1. Pivot Table: `factura_orden`
```sql
CREATE TABLE factura_orden (
    id BIGINT PRIMARY KEY,
    id_factura BIGINT FOREIGN KEY REFERENCES facturas(id) ON DELETE CASCADE,
    id_orden BIGINT FOREIGN KEY REFERENCES ordenes_trabajo(id) ON DELETE CASCADE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(id_factura, id_orden),  -- Prevent duplicates
    UNIQUE(id_orden)                -- One OT can only be in one invoice
);
```

#### 2. Order Status Extension
```sql
-- MySQL only (SQLite skips this in tests)
ALTER TABLE ordenes_trabajo 
MODIFY estatus ENUM('generada','asignada','en_proceso','completada','autorizada_cliente','facturada');
```

### Backend Logic

#### Centro Validation (FacturaController)

Both `createBatch()` and `storeBatch()` methods validate:

```php
// Extract unique centros from selected OTs
$centrosUnicos = $ordenes->pluck('id_centrotrabajo')->filter()->unique()->values();

// Reject if more than one centro
if ($centrosUnicos->count() > 1) {
    abort(422, 'No se puede generar una sola factura con OTs de diferentes Centros de Trabajo. Selecciona OTs del mismo centro.');
}
```

#### OT Reuse Prevention (FacturaController::storeBatch)

```php
// Check both direct and pivot relationships
$usadasDirecto = Factura::query()->whereIn('id_orden', $idsSel)->pluck('id_orden');
$usadasPivot = DB::table('factura_orden')->whereIn('id_orden', $idsSel)->pluck('id_orden');
$ocupadas = array_values(array_unique(array_merge($usadasDirecto, $usadasPivot)));

if (!empty($ocupadas)) {
    $txt = '#'.implode(', #', $ocupadas);
    abort(422, 'Algunas OTs seleccionadas ya están asociadas a una factura: '.$txt);
}
```

#### Real Status Calculation (OrdenController::index)

```php
// Priority: pivot relation -> direct relation -> OT status
$factStatus = 'sin_factura';

if ($o->relationLoaded('facturas') && $o->facturas && $o->facturas->count() > 0) {
    // First priority: status from pivot relationship (new system)
    $factStatus = $o->facturas->first()->estatus ?? 'facturado';
} elseif ($o->relationLoaded('factura') && $o->factura) {
    // Second priority: direct relationship (legacy)
    $factStatus = $o->factura->estatus ?? 'facturado';
} elseif ($o->estatus === 'facturada') {
    // Third priority: OT marked as facturada but no relation loaded
    $factStatus = 'facturado';
}
```

#### Centro Name Display (FacturaController::index)

```php
// Show real centro name from anchor order or first pivot order
$centroName = $f->orden?->centro?->nombre;
if (!$centroName && $f->relationLoaded('ordenes') && $f->ordenes && $f->ordenes->count()>0) {
    $centroName = $f->ordenes->first()?->centro?->nombre;
}
return [
    'centro' => $centroName ?: '—',  // Never shows "Varios"
    // ... other fields
];
```

### Frontend Components

#### Badge Color System

**Ordenes/Index.vue** and **Facturas/Index.vue**:
```javascript
function badgeClass(v){
  const e = String(v||'').toLowerCase()
  if (e === 'pagado') return 'bg-green-100 text-green-700'       // Green
  if (e === 'por_pagar') return 'bg-amber-100 text-amber-700'     // Amber
  if (e === 'facturado') return 'bg-cyan-100 text-cyan-700'       // Cyan
  if (e === 'sin_factura') return 'bg-slate-100 text-slate-700'   // Slate
  return 'bg-gray-100 text-gray-700'
}
```

#### Multi-Select Interface (Ordenes/Index.vue)

```vue
<input
  type="checkbox"
  :disabled="!isSelectable(o)"
  :checked="selected.has(o.id)"
  @change="toggleSelection(o.id, $event.target.checked)"
/>

<script>
// Only allow selection of OTs that are autorizada_cliente and don't have invoice
function isSelectable(o){
  const sinFactura = !o.facturacion || o.facturacion === 'sin_factura'
  return o.estatus === 'autorizada_cliente' && sinFactura
}
</script>
```

#### Batch Creation Flow (Facturas/CreateBatch.vue)

1. User selects multiple OTs from Ordenes/Index
2. Clicks "Generar factura" button
3. System validates same centro constraint
4. Shows CreateBatch view with:
   - List of all selected OTs (centro, servicio, total)
   - XML upload field (optional)
   - Date and folio fields (auto-filled from XML)
   - Total sum of all OTs
5. On submit, creates factura and links all OTs via pivot table

## Testing Recommendations

### Manual Testing Flow

1. **Test Centro Constraint (should FAIL)**:
   - Select OT #1 from Centro A
   - Select OT #2 from Centro B
   - Click "Generar factura"
   - Expected: 422 error "No se puede generar una sola factura con OTs de diferentes Centros de Trabajo"

2. **Test Centro Constraint (should SUCCEED)**:
   - Select OT #1 from Centro A
   - Select OT #2 from Centro A
   - Click "Generar factura"
   - Expected: CreateBatch view shows with both OTs listed

3. **Test XML Upload**:
   - In CreateBatch view, upload valid CFDI XML
   - Expected: Folio and UUID fields auto-fill from XML data
   - Expected: Total from XML overrides calculated total

4. **Test Pivot Table**:
   - Create batch invoice with 3 OTs
   - Check database: `SELECT * FROM factura_orden WHERE id_factura = ?`
   - Expected: 3 rows linking the factura to all 3 OTs

5. **Test Status Display**:
   - Create batch invoice (status = 'facturado')
   - Go to Ordenes/Index
   - Expected: All OTs show cyan badge "facturado"
   - Mark factura as "por_pagar"
   - Refresh Ordenes/Index
   - Expected: All OTs now show amber badge "por_pagar"

6. **Test Centro Name Display**:
   - Create batch invoice with multiple OTs from "Centro Norte"
   - Go to Facturas/Index
   - Expected: Centro column shows "Centro Norte", not "Varios"

7. **Test OT Reuse Prevention**:
   - Create batch invoice with OT #5
   - Try to create another batch with OT #5 included
   - Expected: 422 error "Algunas OTs seleccionadas ya están asociadas a una factura: #5"

### Database Verification Queries

```sql
-- Check factura_orden pivot entries
SELECT f.id as factura_id, f.folio_externo, o.id as orden_id, o.estatus
FROM facturas f
JOIN factura_orden fo ON f.id = fo.id_factura
JOIN ordenes_trabajo o ON fo.id_orden = o.id
WHERE f.id = ?;

-- Verify unique constraint works
SELECT id_orden, COUNT(*) 
FROM factura_orden 
GROUP BY id_orden 
HAVING COUNT(*) > 1;
-- Should return 0 rows

-- Check OT status after invoicing
SELECT id, estatus FROM ordenes_trabajo WHERE id IN (?, ?, ?);
-- Should all show 'facturada'
```

## Migration Deployment

### Production Deployment Steps

1. **Backup database** before running migrations
2. Run migrations:
   ```bash
   php artisan migrate
   ```
3. Verify migrations completed:
   ```bash
   php artisan migrate:status
   ```
4. Check for any existing data conflicts:
   ```sql
   -- Check if any OT is linked to multiple invoices (shouldn't happen)
   SELECT o.id, COUNT(DISTINCT f.id) as invoice_count
   FROM ordenes_trabajo o
   LEFT JOIN facturas f ON o.id = f.id_orden
   GROUP BY o.id
   HAVING invoice_count > 1;
   ```

### Rollback Plan

If issues arise, rollback migrations in reverse order:
```bash
php artisan migrate:rollback --step=3
```

This will:
1. Remove unique constraint from factura_orden
2. Remove 'facturada' from orden status enum  
3. Drop factura_orden pivot table

**Important**: Rollback will delete pivot table data. Export it first if needed:
```bash
mysqldump -u user -p database_name factura_orden > factura_orden_backup.sql
```

## Code Review Checklist

- [ ] Verify centro validation logic in both `createBatch` and `storeBatch`
- [ ] Check that pivot table has proper foreign keys and cascading deletes
- [ ] Confirm unique constraints prevent OT duplication
- [ ] Validate status badge colors are visually distinct
- [ ] Test multi-select UI in Ordenes/Index
- [ ] Verify XML parsing extracts UUID and folio correctly
- [ ] Check that Facturas/Index shows real centro names
- [ ] Confirm OT status changes to 'facturada' after batch creation
- [ ] Verify migrations are idempotent (can be run multiple times safely)
- [ ] Check that existing tests still pass
- [ ] Validate role permissions (only facturacion and admin can batch)

## Known Limitations

1. **MySQL Required for Production**: The status enum migration uses MySQL-specific syntax. SQLite is supported for tests but skips this migration.

2. **Legacy Single-OT Invoices**: Old invoices created before this feature will still work via the direct `id_orden` relationship on `facturas` table. The system handles both pivot and direct relationships.

3. **No Batch Editing**: Once a batch invoice is created, you cannot add/remove OTs from it. You must delete and recreate.

4. **Centro Filter Performance**: On large datasets, the centro filter may be slow. Consider adding an index on `ordenes_trabajo.id_centrotrabajo` if needed.

## Support and Troubleshooting

### Common Issues

**Issue**: "No se puede generar una sola factura con OTs de diferentes Centros"
- **Cause**: User selected OTs from multiple centros
- **Solution**: Clear selection and select only OTs from same centro

**Issue**: "Algunas OTs seleccionadas ya están asociadas a una factura"
- **Cause**: One or more OTs already have an invoice
- **Solution**: Uncheck those OTs or create separate invoice

**Issue**: Centro column shows "—" instead of name
- **Cause**: Data integrity issue - orden doesn't have centro assigned
- **Solution**: Check `ordenes_trabajo.id_centrotrabajo` is not NULL

**Issue**: Badge color is wrong
- **Cause**: Browser cache showing old badge classes
- **Solution**: Hard refresh (Ctrl+Shift+R) or rebuild assets with `npm run build`

### Debug Queries

```sql
-- Find OTs without facturas
SELECT o.id, o.estatus 
FROM ordenes_trabajo o
LEFT JOIN factura_orden fo ON o.id = fo.id_orden
WHERE o.estatus = 'autorizada_cliente' 
  AND fo.id IS NULL;

-- Find facturas with multiple centros (shouldn't exist after validation)
SELECT f.id, GROUP_CONCAT(DISTINCT o.id_centrotrabajo) as centros
FROM facturas f
JOIN factura_orden fo ON f.id = fo.id_factura
JOIN ordenes_trabajo o ON fo.id_orden = o.id
GROUP BY f.id
HAVING COUNT(DISTINCT o.id_centrotrabajo) > 1;
```

## Performance Considerations

- Pivot table has indexes on both foreign keys for fast joins
- Unique constraints are indexed automatically for quick duplicate checks
- Consider adding composite index on `(estatus, id_centrotrabajo)` if filtering is slow:
  ```sql
  CREATE INDEX idx_ordenes_estatus_centro 
  ON ordenes_trabajo(estatus, id_centrotrabajo);
  ```

## Future Enhancements

Potential improvements not included in this release:
- Bulk edit batch invoices (add/remove OTs)
- Export batch invoice details to Excel
- Email notification to all clients when batch is created
- Batch invoice templates for recurring services
- Multi-centro invoices with admin override

---

**Document Version**: 1.0  
**Last Updated**: 2025-10-21  
**Author**: Copilot AI  
**Status**: Production Ready
