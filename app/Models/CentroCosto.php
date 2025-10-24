<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentroCosto extends Model
{
    protected $table = 'centros_costos';

    protected $fillable = [
        'id_centrotrabajo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function centro()
    {
        return $this->belongsTo(CentroTrabajo::class, 'id_centrotrabajo');
    }

    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }
}
