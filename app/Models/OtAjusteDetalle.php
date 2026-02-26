<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtAjusteDetalle extends Model
{
    protected $table = 'ot_ajustes_detalle';

    protected $fillable = [
        'ot_id',
        'ot_detalle_id',
        'orden_item_id',
        'tipo',
        'cantidad',
        'motivo',
        'user_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function ot(): BelongsTo
    {
        return $this->belongsTo(Orden::class, 'ot_id');
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(OTServicioItem::class, 'ot_detalle_id');
    }

    public function ordenItem(): BelongsTo
    {
        return $this->belongsTo(OrdenItem::class, 'orden_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
