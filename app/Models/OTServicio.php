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
    /**
     * Calcula los totales del servicio basándose en los items
     * REGLA: Todos los cálculos desde los items cargados en memoria
     */
    public function calcularTotales(): array
    {
        // Usar la colección cargada si existe, sino hacer query
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        
        $planeado = (int)$items->sum('planeado');
        $completado = (int)$items->sum('completado');
        $faltantesRegistrados = (int)$items->sum('faltante');
        
        $pendiente = max(0, $planeado - ($completado + $faltantesRegistrados));
        $progreso = $planeado > 0 
            ? round((($completado + $faltantesRegistrados) / $planeado) * 100) 
            : 0;

        return [
            'planeado' => $planeado,
            'completado' => $completado,
            'faltantes_registrados' => $faltantesRegistrados,
            'pendiente' => $pendiente,
            'progreso' => $progreso,
            'total' => $planeado, // Total siempre igual a planeado
        ];
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
            
            if (!$tieneAvances) {
                // Solo para servicios nuevos sin avances, calcular subtotal inicial
                $model->subtotal = $model->cantidad * $model->precio_unitario;
            }
            // Si tiene avances, NO tocar el subtotal - viene del controller
        });
    }
}
