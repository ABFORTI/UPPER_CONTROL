<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtCorteDetalle extends Model
{
    protected $table = 'ot_corte_detalles';

    protected $fillable = [
        'ot_corte_id',
        'ot_servicio_id',
        'orden_item_id',
        'concepto_descripcion',
        'cantidad_cortada',
        'precio_unitario_snapshot',
        'importe_snapshot',
    ];

    protected $casts = [
        'cantidad_cortada'         => 'decimal:2',
        'precio_unitario_snapshot' => 'decimal:2',
        'importe_snapshot'         => 'decimal:2',
    ];

    /* ── Relaciones ── */

    public function corte(): BelongsTo
    {
        return $this->belongsTo(OtCorte::class, 'ot_corte_id');
    }

    public function otServicio(): BelongsTo
    {
        return $this->belongsTo(OTServicio::class, 'ot_servicio_id');
    }

    public function ordenItem(): BelongsTo
    {
        return $this->belongsTo(OrdenItem::class, 'orden_item_id');
    }

    /**
     * Descripción legible del concepto (sea servicio o item).
     */
    public function getNombreConceptoAttribute(): string
    {
        if ($this->concepto_descripcion) {
            return $this->concepto_descripcion;
        }
        if ($this->otServicio) {
            return $this->otServicio->servicio->nombre ?? "Servicio #{$this->ot_servicio_id}";
        }
        if ($this->ordenItem) {
            return $this->ordenItem->descripcion ?? "Item #{$this->orden_item_id}";
        }
        return 'Concepto desconocido';
    }
}
