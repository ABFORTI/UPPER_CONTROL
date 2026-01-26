<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotizacionItem extends Model
{
    protected $table = 'cotizacion_items';

    protected $fillable = [
        'cotizacion_id',
        'descripcion',
        'cantidad',
        'notas',
        'product_name',
        'quantity',
        'unit',
        'centro_costo_id',
        'brand_id',
        'metadata',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'quantity' => 'decimal:3',
        'metadata' => 'array',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(CotizacionItemServicio::class, 'cotizacion_item_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'brand_id');
    }
}
