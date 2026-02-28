<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SolicitudServicio extends Model
{
    use LogsActivity;

    protected $table = 'solicitud_servicios';
    
    protected $fillable = [
        'solicitud_id',
        'servicio_id',
        'sku',
        'origen',
        'pedimento',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('solicitud_servicios')
            ->logOnly(['sku', 'origen', 'pedimento'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
