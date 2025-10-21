<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $fillable = [
        'id_centrotrabajo',
        'nombre',
        'descripcion',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relación con centro de trabajo
    public function centro()
    {
        return $this->belongsTo(CentroTrabajo::class, 'id_centrotrabajo');
    }

    // Scope para áreas activas
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
