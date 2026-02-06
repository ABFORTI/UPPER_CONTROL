<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OTServicioItem extends Model
{
    protected $table = 'ot_servicio_items';

    protected $fillable = [
        'ot_servicio_id',
        'descripcion_item',
        'tamano',
        'planeado',
        'completado',
        'faltante',
        'precio_unitario',
        'subtotal',
    ];

    protected $casts = [
        'planeado' => 'integer',
        'completado' => 'integer',
        'faltante' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Relación con el Servicio de la OT
     */
    public function otServicio(): BelongsTo
    {
        return $this->belongsTo(OTServicio::class, 'ot_servicio_id');
    }
}
