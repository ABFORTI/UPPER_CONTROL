<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionItemServicio extends Model
{
    protected $table = 'cotizacion_item_servicios';

    protected $fillable = [
        'cotizacion_item_id',
        'id_servicio',
        'tamano',
        'tamanos_json',
        'cantidad',
        'qty',
        'precio_unitario',
        'subtotal',
        'iva',
        'total',
        'notes',
    ];

    protected $casts = [
        'tamanos_json' => 'array',
        'cantidad' => 'integer',
        'qty' => 'decimal:3',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(CotizacionItem::class, 'cotizacion_item_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioEmpresa::class, 'id_servicio');
    }
}
