<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OTServicioAvance extends Model
{
    protected $table = 'ot_servicio_avances';

    protected $fillable = [
        'ot_servicio_id',
        'tarifa',
        'precio_unitario_aplicado',
        'cantidad_registrada',
        'comentario',
        'created_by',
        'request_id',  // Para idempotencia
    ];

    protected $casts = [
        'precio_unitario_aplicado' => 'decimal:2',
        'cantidad_registrada' => 'integer',
    ];

    /**
     * Relación con el Servicio de la OT
     */
    public function otServicio(): BelongsTo
    {
        return $this->belongsTo(OTServicio::class, 'ot_servicio_id');
    }

    /**
     * Relación con el Usuario que creó el avance
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
