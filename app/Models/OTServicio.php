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
        'origen',
        'added_by_user_id',
        'nota',
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
     * Relación con el Usuario que agregó el servicio adicional
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /**
     * Calcular totales del servicio basados en items
     */
    /**
     * Calcula los totales del servicio basándose en los items
     * REGLA: Todos los cálculos desde los items cargados en memoria
     */
    public function calcularTotales(): array
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->with('ajustes')->get();

        $metrics = $items->map(function (OTServicioItem $item) {
            return $item->calcularMetricas();
        });

        $solicitado = (int) $metrics->sum('solicitado');
        $extra = (int) $metrics->sum('extra');
        $faltantes = (int) $metrics->sum('faltantes');
        $totalCobrable = (int) $metrics->sum('total_cobrable');
        $completado = (int) $metrics->sum('completado');
        $pendiente = max(0, $totalCobrable - $completado);
        $progreso = $totalCobrable > 0
            ? round(($completado / $totalCobrable) * 100, 2)
            : 0.0;

        return [
            'solicitado' => $solicitado,
            'planeado' => $solicitado,
            'extra' => $extra,
            'total_cobrable' => $totalCobrable,
            'completado' => $completado,
            'faltantes' => $faltantes,
            'faltantes_registrados' => $faltantes,
            'pendiente' => $pendiente,
            'progreso' => $progreso,
            'total' => $totalCobrable,
        ];
    }

    public function recalcularSubtotalDesdeCobrable(): float
    {
        $this->loadMissing('items.ajustes');

        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $met = $item->calcularMetricas();
            $precioUnitario = (float) ($item->precio_unitario ?? 0);
            if ($precioUnitario <= 0) {
                $precioUnitario = (float) ($this->precio_unitario ?? 0);
            }

            $lineSub = round(((float) $met['total_cobrable']) * $precioUnitario, 2);
            $item->subtotal = $lineSub;
            $item->save();
            $subtotal += $lineSub;
        }

        $this->subtotal = round($subtotal, 2);
        $this->save();

        return (float) $this->subtotal;
    }

    /**
     * Boot para calcular subtotal automáticamente
     * NOTA: Solo calcula subtotal si no ha sido establecido manualmente
     * Para servicios con avances, el subtotal se calcula desde el controller
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // NO calcular subtotal automático si ya tiene avances registrados
            // El subtotal debe venir de la suma de los avances
            $tieneAvances = \App\Models\OTServicioAvance::where('ot_servicio_id', $model->id)->exists();
            
            if (!$tieneAvances && !$model->isDirty('subtotal')) {
                // Solo para servicios nuevos sin avances, calcular subtotal inicial
                $model->subtotal = $model->cantidad * $model->precio_unitario;
            }
            // Si tiene avances, NO tocar el subtotal - viene del controller
        });
    }
}
