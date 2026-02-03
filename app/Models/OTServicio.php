<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OTServicio extends Model
{
    protected $table = 'ot_servicios';

    protected $fillable = [
        'ot_id',
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

    /**
     * Relación con la Orden de Trabajo
     */
    public function ot(): BelongsTo
    {
        return $this->belongsTo(Orden::class, 'ot_id');
    }

    /**
     * Relación con el Servicio
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioEmpresa::class, 'servicio_id');
    }

    /**
     * Relación con los Items del Servicio
     */
    public function items(): HasMany
    {
        return $this->hasMany(OTServicioItem::class, 'ot_servicio_id');
    }

    /**
     * Relación con los Avances del Servicio
     */
    public function avances(): HasMany
    {
        return $this->hasMany(OTServicioAvance::class, 'ot_servicio_id');
    }

    /**
     * Calcular totales del servicio basados en items
     */
    public function calcularTotales(): array
    {
        $planeado = $this->items()->sum('planeado');
        $completado = $this->items()->sum('completado');
        $faltante = $planeado - $completado;

        return [
            'planeado' => $planeado,
            'completado' => $completado,
            'faltante' => $faltante,
        ];
    }

    /**
     * Boot para calcular subtotal automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->subtotal = $model->cantidad * $model->precio_unitario;
        });
    }
}
