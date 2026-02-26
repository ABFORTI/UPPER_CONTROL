<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function ajustes(): HasMany
    {
        return $this->hasMany(OtAjusteDetalle::class, 'ot_detalle_id');
    }

    public function calcularMetricas(): array
    {
        $ajustes = $this->relationLoaded('ajustes') ? $this->ajustes : $this->ajustes()->get();

        $solicitado = (int) $this->planeado;
        $extra = (int) $ajustes->where('tipo', 'extra')->sum('cantidad');
        $faltantes = (int) $ajustes->where('tipo', 'faltante')->sum('cantidad');
        $totalCobrable = max(0, $solicitado + $extra - $faltantes);
        $completado = (int) $this->completado;
        $pendiente = max(0, $totalCobrable - $completado);
        $progreso = $totalCobrable > 0
            ? round(($completado / $totalCobrable) * 100, 2)
            : 0.0;

        return [
            'solicitado' => $solicitado,
            'extra' => $extra,
            'faltantes' => $faltantes,
            'total_cobrable' => $totalCobrable,
            'completado' => $completado,
            'pendiente' => $pendiente,
            'progreso' => $progreso,
        ];
    }
}
