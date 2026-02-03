<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudServicio extends Model
{
    protected $table = 'solicitud_servicios';
    
    protected $fillable = [
        'solicitud_id',
        'servicio_id',
        'tipo_cobro',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relaciones
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioEmpresa::class, 'servicio_id');
    }

    // Boot para calcular subtotal automáticamente
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($solicitudServicio) {
            $solicitudServicio->subtotal = $solicitudServicio->cantidad * $solicitudServicio->precio_unitario;
        });
    }
}
